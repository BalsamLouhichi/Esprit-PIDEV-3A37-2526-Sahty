<?php
// src/Controller/Backoffice/ResponsableParapharmacieConfigController.php

namespace App\Controller\Backoffice;

use App\Entity\Parapharmacie;
use App\Entity\ResponsableParapharmacie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/responsable/parapharmacie')]
#[IsGranted('ROLE_RESPONSABLE_PARA')]
class ResponsableParapharmacieConfigController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Page de configuration initiale de la parapharmacie
     */
    #[Route('/configurer', name: 'app_responsable_para_configurer')]
    public function configurer(Request $request): Response
    {
        /** @var ResponsableParapharmacie $user */
        $user = $this->getUser();

        
        // Si la parapharmacie est déjà configurée, rediriger vers le dashboard
        if ($user->hasParapharmacie()) {
            // Marquer que la première connexion est terminée
            if ($user->isPremiereConnexion()) {
                $user->setPremiereConnexion(false);
                $this->entityManager->flush();
            }
            return $this->redirectToRoute('app_responsable_dashboard');
        }

        // Créer un formulaire manuel pour la configuration
        if ($request->isMethod('POST')) {
            $nom = $request->request->get('nom');
            $adresse = $request->request->get('adresse');
            $telephone = $request->request->get('telephone');
            $email = $request->request->get('email');

            // Validation basique
            $errors = [];
            if (empty($nom)) $errors[] = "Le nom est obligatoire";
            if (empty($adresse)) $errors[] = "L'adresse est obligatoire";
            if (empty($telephone)) $errors[] = "Le téléphone est obligatoire";

            if (empty($errors)) {
                // Créer la nouvelle parapharmacie
                $parapharmacie = new Parapharmacie();
                $parapharmacie->setNom($nom);
                $parapharmacie->setAdresse($adresse);
                $parapharmacie->setTelephone($telephone);
                $parapharmacie->setEmail($email);

                // Associer au responsable
                $user->setParapharmacie($parapharmacie);
                $user->setPremiereConnexion(false);

                // Persister
                $this->entityManager->persist($parapharmacie);
                $this->entityManager->flush();

                $this->addFlash('success', 'Votre parapharmacie a été configurée avec succès !');
                return $this->redirectToRoute('app_responsable_dashboard');
            }

            // En cas d'erreurs
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('backoffice/responsable/configurer_parapharmacie.html.twig');
    }

    /**
     * Page pour rejoindre une parapharmacie existante
     */
    #[Route('/rejoindre', name: 'app_responsable_para_rejoindre')]
    public function rejoindre(Request $request): Response
    {
        /** @var ResponsableParapharmacie $user */
        $user = $this->getUser();

        // CORRECTION : Remplacer isParapharmacieConfigured() par hasParapharmacie()
        if ($user->hasParapharmacie()) {
            return $this->redirectToRoute('app_responsable_dashboard');
        }

        if ($request->isMethod('POST')) {
            $parapharmacieId = $request->request->get('parapharmacie_id');
            $codeAcces = $request->request->get('code_acces');

            // Rechercher la parapharmacie
            $parapharmacie = $this->entityManager
                ->getRepository(Parapharmacie::class)
                ->find($parapharmacieId);

            if ($parapharmacie) {
                // Vérifier le code d'accès (vous pouvez implémenter votre propre logique)
                // Par exemple, vérifier si la parapharmacie a un code secret
                
                $user->setParapharmacie($parapharmacie);
                $user->setPremiereConnexion(false);
                $this->entityManager->flush();

                $this->addFlash('success', 'Vous avez rejoint la parapharmacie avec succès !');
                return $this->redirectToRoute('app_responsable_dashboard');
            } else {
                $this->addFlash('error', 'Parapharmacie non trouvée');
            }
        }

        return $this->render('backoffice/responsable/rejoindre_parapharmacie.html.twig', [
            'parapharmacies' => $this->entityManager->getRepository(Parapharmacie::class)->findAll(),
        ]);
    }

    /**
     * Vérifier le statut de configuration
     */
    #[Route('/statut', name: 'app_responsable_para_statut')]
    public function statut(): Response
    {
        /** @var ResponsableParapharmacie $user */
        $user = $this->getUser();

        return $this->json([
            'configured' => $user->hasParapharmacie(),
            'premiereConnexion' => $user->isPremiereConnexion(),
            'needsConfiguration' => $user->needsConfiguration()
        ]);
    }
}