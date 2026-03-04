<?php

namespace App\Controller;

use App\Entity\Administrateur;
use App\Entity\Patient;
use App\Entity\Medecin;
use App\Entity\ResponsableLaboratoire;
use App\Entity\ResponsableParapharmacie;
use App\Entity\Laboratoire;
use App\Form\SignupType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class SignupController extends AbstractController
{
    #[Route('/signup', name: 'app_sign')]
    public function signup(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
    ): Response {
        // Si l'utilisateur est deja connecte
        if ($this->getUser()) {
            return $this->redirectToRoute('app_profile');
        }

        $form = $this->createForm(SignupType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $roleSelected = $form->get('role')->getData();
            $confirmPassword = $request->request->get('confirm_password');
            $password = $this->toRequiredString($form->get('password')->getData());

            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->render('signup/signup.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            // Creation de l'utilisateur selon le role
            switch ($roleSelected) {
                case 'admin':
                    $user = new Administrateur();
                    break;

                case 'medecin':
                    $user = new Medecin();
                    $user->setSpecialite($this->toNullableString($request->request->get('specialite')));
                    $user->setAnneeExperience((int) ($request->request->get('annee_experience', 0) ?: 0));
                    $user->setGrade($this->toNullableString($request->request->get('grade')));
                    $user->setAdresseCabinet($this->toNullableString($request->request->get('adresse_cabinet')));
                    $user->setTelephoneCabinet($this->toNullableString($request->request->get('telephone_cabinet')));
                    $user->setNomEtablissement($this->toNullableString($request->request->get('nom_etablissement')));
                    $user->setNumeroUrgence($this->toNullableString($request->request->get('numero_urgence')));
                    $user->setDisponibilite($this->toNullableString($request->request->get('disponibilite')));

                    // Gestion document PDF
                    $documentPdfFile = $request->files->get('document_pdf');
                    if ($documentPdfFile) {
                        $originalFilename = pathinfo($documentPdfFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename.'-'.uniqid().'.'.$documentPdfFile->guessExtension();

                        $uploadDir = $this->getProjectDir().'/public/uploads/documents';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }

                        $documentPdfFile->move($uploadDir, $newFilename);
                        $user->setDocumentPdf('uploads/documents/'.$newFilename);
                    }
                    break;

                case 'responsable_labo':
                    try {
                        $user = $this->createResponsableLaboratoire($request, $em);
                    } catch (\InvalidArgumentException $exception) {
                        $this->addFlash('error', $exception->getMessage());
                        return $this->render('signup/signup.html.twig', [
                            'form' => $form->createView(),
                        ]);
                    }

                    $laboratoire = $user->getLaboratoire();
                    if ($laboratoire !== null && $laboratoire->hasResponsable()) {
                        $this->addFlash('warning', 'Ce laboratoire a deja un responsable.');
                    }
                    break;

                case 'responsable_para':
                    $user = $this->createResponsableParapharmacie($request);
                    break;

                case 'patient':
                default:
                    $user = $this->createPatient($request);
                    break;
            }

            // Champs communs
            $user->setPrenom($form->get('prenom')->getData())
                 ->setNom($form->get('nom')->getData())
                 ->setEmail($form->get('email')->getData())
                 ->setTelephone($form->get('telephone')->getData())
                 ->setVille($form->get('ville')->getData())
                 ->setRole($roleSelected);

            if ($form->get('dateNaissance')->getData()) {
                $user->setDateNaissance($form->get('dateNaissance')->getData());
            }

            // Mot de passe
            $hashedPassword = $passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            // Photo de profil
            $photoProfilFile = $form->get('photoProfil')->getData();
            if ($photoProfilFile) {
                $originalFilename = pathinfo($photoProfilFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$photoProfilFile->guessExtension();

                $uploadDir = $this->getProjectDir().'/public/uploads/photos';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $photoProfilFile->move($uploadDir, $newFilename);
                $user->setPhotoProfil('uploads/photos/'.$newFilename);
            }

            try {
                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'Votre compte a ete cree avec succes !');
                return $this->redirectToRoute('app_login');

            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'UNIQ') !== false || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $this->addFlash('error', 'Cet email est deja utilise.');
                } else {
                    $this->addFlash('error', 'Erreur: '.$e->getMessage());
                }

                return $this->render('signup/signup.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
        }

        return $this->render('signup/signup.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function createResponsableLaboratoire(Request $request, EntityManagerInterface $em): ResponsableLaboratoire
    {
        $user = new ResponsableLaboratoire();

        $laboratoireId = (int) ($request->request->get('laboratoire_id', 0) ?: 0);
        if ($laboratoireId > 0) {
            $laboratoire = $em->getRepository(Laboratoire::class)->find($laboratoireId);
            if ($laboratoire) {
                $user->setLaboratoire($laboratoire);
            } else {
                throw new \InvalidArgumentException('Le laboratoire selectionne n\'existe pas.');
            }
        }

        return $user;
    }

    private function createResponsableParapharmacie(Request $request): ResponsableParapharmacie
    {
        $user = new ResponsableParapharmacie();
        $user->setParapharmacieId((int) ($request->request->get('parapharmacie_id', 0) ?: 0));
        return $user;
    }

    private function createPatient(Request $request): Patient
    {
        $user = new Patient();

        // Patient specific fields
        $user->setSexe($this->toNullableString($request->request->get('sexe')));
        $user->setGroupeSanguin($this->toNullableString($request->request->get('groupe_sanguin')));
        $user->setContactUrgence($this->toNullableString($request->request->get('contact_urgence')));

        return $user;
    }

    private function toNullableString(mixed $value): ?string
    {
        if (!is_scalar($value) && $value !== null) {
            return null;
        }

        $normalized = trim((string) ($value ?? ''));
        return $normalized === '' ? null : $normalized;
    }

    private function toRequiredString(mixed $value): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '';
        }

        return trim((string) ($value ?? ''));
    }

    private function getProjectDir(): string
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        return is_string($projectDir) ? $projectDir : '';
    }
}
