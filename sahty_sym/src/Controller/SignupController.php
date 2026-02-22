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
        // Si l'utilisateur est déjà connecté
        if ($this->getUser()) {
            return $this->redirectToRoute('app_profile');
        }

        $form = $this->createForm(SignupType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $roleSelected = $form->get('role')->getData();
            $confirmPassword = $request->request->get('confirm_password');
            $password = $form->get('password')->getData();

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
                    $user->setSpecialite($request->request->get('specialite', ''));
                    $user->setAnneeExperience((int)$request->request->get('annee_experience', 0));
                    $user->setGrade($request->request->get('grade', ''));
                    $user->setAdresseCabinet($request->request->get('adresse_cabinet', ''));
                    $user->setTelephoneCabinet($request->request->get('telephone_cabinet', ''));
                    $user->setNomEtablissement($request->request->get('nom_etablissement', ''));
                    $user->setNumeroUrgence($request->request->get('numero_urgence', ''));
                    $user->setDisponibilite($request->request->get('disponibilite', ''));

                    // Gestion document PDF
                    $documentPdfFile = $request->files->get('document_pdf');
                    if ($documentPdfFile) {
                        $originalFilename = pathinfo($documentPdfFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename.'-'.uniqid().'.'.$documentPdfFile->guessExtension();

                        $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/documents';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                        $documentPdfFile->move($uploadDir, $newFilename);
                        $user->setDocumentPdf('uploads/documents/'.$newFilename);
                    }
                    break;

                case 'responsable_labo':
                    $user = new ResponsableLaboratoire();
                    $laboratoireId = (int)$request->request->get('laboratoire_id', 0);
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
                    $user = new ResponsableParapharmacie();
                    $user->setParapharmacieId((int)$request->request->get('parapharmacie_id', 0));
                    break;

                case 'patient':
                default:
                    $user = new Patient();
                    $user->setSexe($request->request->get('sexe', ''));
                    $user->setGroupeSanguin($request->request->get('groupe_sanguin', ''));
                    $user->setContactUrgence($request->request->get('contact_urgence', ''));
                    break;
            }

            // Champs communs
            $user->setPrenom($form->get('prenom')->getData())
                 ->setNom($form->get('nom')->getData())
                 ->setEmail($form->get('email')->getData())
                 ->setTelephone($form->get('telephone')->getData())
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

                $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/photos';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $photoProfilFile->move($uploadDir, $newFilename);
                $user->setPhotoProfil('uploads/photos/'.$newFilename);
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
}
