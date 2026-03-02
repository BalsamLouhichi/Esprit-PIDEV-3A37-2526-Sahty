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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
        // Si l'utilisateur est déjà connecté
        if ($this->getUser()) {
            return $this->redirectToRoute('app_profile');
        }

        $form = $this->createForm(SignupType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $roleSelected = $this->formString($form, 'role');
            $confirmPassword = $this->requestString($request, 'confirm_password');
            $password = $this->formString($form, 'password');

            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->render('signup/signup.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            // Création de l'utilisateur selon le rôle
            switch ($roleSelected) {
                case 'admin':
                    $user = new Administrateur();
                    break;

                case 'medecin':
                    $user = new Medecin();
                    $user->setSpecialite($this->requestNullableString($request, 'specialite'));
                    $user->setAnneeExperience((int) $this->requestString($request, 'annee_experience', '0'));
                    $user->setGrade($this->requestNullableString($request, 'grade'));
                    $user->setAdresseCabinet($this->requestNullableString($request, 'adresse_cabinet'));
                    $user->setTelephoneCabinet($this->requestNullableString($request, 'telephone_cabinet'));
                    $user->setNomEtablissement($this->requestNullableString($request, 'nom_etablissement'));
                    $user->setNumeroUrgence($this->requestNullableString($request, 'numero_urgence'));
                    $user->setDisponibilite($this->requestNullableString($request, 'disponibilite'));

                    // Gestion document PDF
                    $documentPdfFile = $request->files->get('document_pdf');
                    if ($documentPdfFile instanceof UploadedFile) {
                        $originalFilename = pathinfo($documentPdfFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = (string) $slugger->slug($originalFilename);
                        $extension = $documentPdfFile->guessExtension() ?: 'pdf';
                        $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $extension;

                        $projectDirValue = $this->getParameter('kernel.project_dir');
                        $projectDir = is_scalar($projectDirValue) ? (string) $projectDirValue : '';
                        $uploadDir = $projectDir . '/public/uploads/documents';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }

                        $documentPdfFile->move($uploadDir, $newFilename);
                        $user->setDocumentPdf('uploads/documents/' . $newFilename);
                    }
                    break;

                case 'responsable_labo':
                    $user = $this->createResponsableLaboratoire($request, $em);
                    $laboratoireId = (int) $this->requestString($request, 'laboratoire_id', '0');
                    if ($laboratoireId > 0) {
                        $laboratoire = $em->getRepository(Laboratoire::class)->find($laboratoireId);
                        if ($laboratoire) {
                            $user->setLaboratoire($laboratoire);
                            if ($laboratoire->hasResponsable()) {
                                $this->addFlash('warning', 'Ce laboratoire a déjà un responsable.');
                            }
                        } else {
                            $this->addFlash('error', 'Le laboratoire sélectionné n\'existe pas.');
                            return $this->render('signup/signup.html.twig', [
                                'form' => $form->createView(),
                            ]);
                        }
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
            $user->setPrenom($this->formString($form, 'prenom'))
                 ->setNom($this->formString($form, 'nom'))
                 ->setEmail($this->formString($form, 'email'))
                 ->setTelephone($this->formString($form, 'telephone'))
                 ->setVille($this->formString($form, 'ville'))
                 ->setRole($roleSelected);

            $dateNaissance = $form->get('dateNaissance')->getData();
            if ($dateNaissance instanceof \DateTimeInterface) {
                $user->setDateNaissance($dateNaissance);
            }

            // Mot de passe
            $hashedPassword = $passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            // Photo de profil
            $photoProfilFile = $form->get('photoProfil')->getData();
            if ($photoProfilFile instanceof UploadedFile) {
                $originalFilename = pathinfo($photoProfilFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = (string) $slugger->slug($originalFilename);
                $extension = $photoProfilFile->guessExtension() ?: 'bin';
                $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $extension;

                $projectDirValue = $this->getParameter('kernel.project_dir');
                $projectDir = is_scalar($projectDirValue) ? (string) $projectDirValue : '';
                $uploadDir = $projectDir . '/public/uploads/photos';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $photoProfilFile->move($uploadDir, $newFilename);
                $user->setPhotoProfil('uploads/photos/' . $newFilename);
            }

            try {
                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'Votre compte a été créé avec succès !');
                return $this->redirectToRoute('app_login');

            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'UNIQ') !== false || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $this->addFlash('error', 'Cet email est déjà utilisé.');
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

    /**
     * Create a ResponsableLaboratoire user (NEW from Balsam)
     */
    private function createResponsableLaboratoire(Request $request, EntityManagerInterface $em): ResponsableLaboratoire
    {
        $user = new ResponsableLaboratoire();
        
        $laboratoireId = (int) $this->requestString($request, 'signup[laboratoire_id]', '0');
        if ($laboratoireId > 0) {
            $laboratoire = $em->getRepository(Laboratoire::class)->find($laboratoireId);
            if ($laboratoire) {
                $user->setLaboratoire($laboratoire);
            }
        }
        
        return $user;
    }

    /**
     * Create a ResponsableParapharmacie user (NEW from Balsam)
     */
    private function createResponsableParapharmacie(Request $request): ResponsableParapharmacie
    {
        $user = new ResponsableParapharmacie();
        $user->setParapharmacieId((int) $this->requestString($request, 'signup[parapharmacie_id]', '0'));
        return $user;
    }

    /**
     * Create a Patient user with patient-specific fields (keep YOUR version)
     */
    private function createPatient(Request $request): Patient
    {
        $user = new Patient();
        
        // Patient specific fields
        $user->setSexe($this->requestNullableString($request, 'signup[sexe]'));
        $user->setGroupeSanguin($this->requestNullableString($request, 'signup[groupe_sanguin]'));
        $user->setContactUrgence($this->requestNullableString($request, 'signup[contact_urgence]'));
        
        return $user;
    }

    private function requestString(Request $request, string $key, string $default = ''): string
    {
        $value = $request->request->get($key, $default);
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return $default;
    }

    private function requestNullableString(Request $request, string $key): ?string
    {
        $value = $this->requestString($request, $key, '');
        return $value !== '' ? $value : null;
    }

    private function formString(FormInterface $form, string $field): string
    {
        $value = $form->get($field)->getData();
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }
}
