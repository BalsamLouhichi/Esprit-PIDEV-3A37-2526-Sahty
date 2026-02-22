<?php
// src/Controller/Backoffice/ResponsableController.php

namespace App\Controller\Backoffice;

use App\Entity\Produit;
use App\Entity\Commande;
use App\Entity\Parapharmacie;
use App\Entity\ResponsableParapharmacie;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/responsable')]
class ResponsableController extends AbstractController
{
    private $entityManager;
    
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Méthode helper pour récupérer la parapharmacie de l'utilisateur connecté
     */
    private function getCurrentParapharmacie(): Parapharmacie
    {
        $user = $this->getUser();
        
        if (!$user instanceof ResponsableParapharmacie) {
            throw new AccessDeniedException('Accès réservé aux responsables de parapharmacie');
        }

        $parapharmacie = $user->getParapharmacie();
        
        if (!$parapharmacie) {
            $this->addFlash('error', 'Vous n\'êtes pas associé à une parapharmacie. Veuillez contacter l\'administrateur.');
            throw new AccessDeniedException('Aucune parapharmacie associée à ce compte');
        }

        return $parapharmacie;
    }
    
    /**
     * Tableau de bord du responsable
     */
    #[Route('/dashboard', name: 'app_responsable_dashboard')]
    public function dashboard(
        CommandeRepository $commandeRepository,
        ProduitRepository $produitRepository
    ): Response {
        try {
            $parapharmacie = $this->getCurrentParapharmacie();
        } catch (AccessDeniedException $e) {
            return $this->redirectToRoute('app_home');
        }
        
        // Statistiques pour cette parapharmacie
        $commandesEnAttente = $commandeRepository->findByParapharmacieAndStatut(
            $parapharmacie->getId(), 
            'en_attente'
        );
        
        $commandesRecent = $commandeRepository->findRecentByParapharmacie(
            $parapharmacie->getId(), 
            10
        );
        
        $totalProduits = $produitRepository->countByParapharmacie($parapharmacie->getId());
        
        $statsVentes = $commandeRepository->getStatsByParapharmacie($parapharmacie->getId());
        
        return $this->render('backoffice/responsable/dashboard.html.twig', [
            'parapharmacie' => $parapharmacie,
            'commandesEnAttente' => count($commandesEnAttente),
            'commandesRecent' => $commandesRecent,
            'totalProduits' => $totalProduits,
            'statsVentes' => $statsVentes
        ]);
    }
    
    /**
     * Liste des produits de la parapharmacie
     */
    #[Route('/produits', name: 'app_responsable_produits')]
    public function produits(
        ProduitRepository $produitRepository
    ): Response {
        try {
            $parapharmacie = $this->getCurrentParapharmacie();
        } catch (AccessDeniedException $e) {
            return $this->redirectToRoute('app_home');
        }
        
        $produits = $produitRepository->findByParapharmacie($parapharmacie->getId());
        
        return $this->render('backoffice/responsable/produits.html.twig', [
            'produits' => $produits,
            'parapharmacie' => $parapharmacie
        ]);
    }
    
    /**
     * Ajouter un nouveau produit
     */
    #[Route('/produit/ajouter', name: 'app_responsable_produit_ajouter')]
    public function ajouterProduit(
        Request $request,
        SluggerInterface $slugger
    ): Response {
        try {
            $parapharmacie = $this->getCurrentParapharmacie();
        } catch (AccessDeniedException $e) {
            return $this->redirectToRoute('app_home');
        }
        
        $produit = new Produit();
        
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Gérer l'upload de l'image
            $imageFile = $form->get('imageFile')->getData();
            
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
                
                try {
                    $imageFile->move(
                        $this->getParameter('produits_images_directory'),
                        $newFilename
                    );
                    $produit->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image');
                }
            }
            
            $produit->addParapharmacie($parapharmacie);
            $produit->setReference('PROD-'.date('Ymd').'-'.uniqid());
            
            $this->entityManager->persist($produit);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Produit ajouté avec succès !');
            
            return $this->redirectToRoute('app_responsable_produits');
        }
        
        return $this->render('backoffice/responsable/produit_form.html.twig', [
            'form' => $form->createView(),
            'produit' => $produit,
            'parapharmacie' => $parapharmacie,
            'mode' => 'ajouter'
        ]);
    }
    
    /**
     * Modifier un produit
     */
    #[Route('/produit/modifier/{id}', name: 'app_responsable_produit_modifier')]
    public function modifierProduit(
        Produit $produit,
        Request $request,
        SluggerInterface $slugger
    ): Response {
        try {
            $parapharmacie = $this->getCurrentParapharmacie();
        } catch (AccessDeniedException $e) {
            return $this->redirectToRoute('app_home');
        }
        
        // Vérifier que le produit appartient bien à cette parapharmacie
        if (!$produit->getParapharmacies()->contains($parapharmacie)) {
            $this->addFlash('error', 'Ce produit n\'appartient pas à votre parapharmacie');
            return $this->redirectToRoute('app_responsable_produits');
        }
        
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
                
                try {
                    $imageFile->move(
                        $this->getParameter('produits_images_directory'),
                        $newFilename
                    );
                    
                    // Supprimer l'ancienne image
                    if ($produit->getImage()) {
                        $oldImage = $this->getParameter('produits_images_directory').'/'.$produit->getImage();
                        if (file_exists($oldImage)) {
                            unlink($oldImage);
                        }
                    }
                    
                    $produit->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image');
                }
            }
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Produit modifié avec succès !');
            
            return $this->redirectToRoute('app_responsable_produits');
        }
        
        return $this->render('backoffice/responsable/produit_form.html.twig', [
            'form' => $form->createView(),
            'produit' => $produit,
            'parapharmacie' => $parapharmacie,
            'mode' => 'modifier'
        ]);
    }
    
    /**
     * Supprimer un produit
     */
    #[Route('/produit/supprimer/{id}', name: 'app_responsable_produit_supprimer', methods: ['POST'])]
    public function supprimerProduit(
        Produit $produit,
        Request $request
    ): Response {
        try {
            $parapharmacie = $this->getCurrentParapharmacie();
        } catch (AccessDeniedException $e) {
            return $this->redirectToRoute('app_home');
        }
        
        // Vérifier que le produit appartient bien à cette parapharmacie
        if (!$produit->getParapharmacies()->contains($parapharmacie)) {
            $this->addFlash('error', 'Ce produit n\'appartient pas à votre parapharmacie');
            return $this->redirectToRoute('app_responsable_produits');
        }
        
        if ($this->isCsrfTokenValid('delete'.$produit->getId(), $request->request->get('_token'))) {
            // Supprimer l'image associée
            if ($produit->getImage()) {
                $imagePath = $this->getParameter('produits_images_directory').'/'.$produit->getImage();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            $this->entityManager->remove($produit);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Produit supprimé avec succès');
        }
        
        return $this->redirectToRoute('app_responsable_produits');
    }
    
    /**
     * Gestion des commandes
     */
    #[Route('/commandes', name: 'app_responsable_commandes')]
    public function commandes(
        CommandeRepository $commandeRepository,
        Request $request
    ): Response {
        try {
            $parapharmacie = $this->getCurrentParapharmacie();
        } catch (AccessDeniedException $e) {
            return $this->redirectToRoute('app_home');
        }
        
        $statut = $request->query->get('statut', 'tous');
        
        if ($statut !== 'tous') {
            $commandes = $commandeRepository->findByParapharmacieAndStatut(
                $parapharmacie->getId(),
                $statut
            );
        } else {
            $commandes = $commandeRepository->findByParapharmacie($parapharmacie->getId());
        }
        
        return $this->render('backoffice/responsable/commandes.html.twig', [
            'commandes' => $commandes,
            'parapharmacie' => $parapharmacie,
            'statutActuel' => $statut
        ]);
    }
    
    /**
     * Changer le statut d'une commande
     */
    #[Route('/commande/{id}/statut', name: 'app_responsable_commande_statut', methods: ['POST'])]
    public function changerStatutCommande(
        Commande $commande,
        Request $request
    ): Response {
        try {
            $parapharmacie = $this->getCurrentParapharmacie();
        } catch (AccessDeniedException $e) {
            return $this->redirectToRoute('app_home');
        }
        
        // Vérifier que la commande appartient bien à cette parapharmacie
        if ($commande->getParapharmacie()->getId() !== $parapharmacie->getId()) {
            $this->addFlash('error', 'Cette commande n\'appartient pas à votre parapharmacie');
            return $this->redirectToRoute('app_responsable_commandes');
        }
        
        $nouveauStatut = $request->request->get('statut');
        $statutsValides = ['en_attente', 'confirmee', 'preparation', 'expediee', 'livree', 'annulee'];
        
        if (in_array($nouveauStatut, $statutsValides)) {
            $commande->setStatut($nouveauStatut);
            $commande->setDateModification(new \DateTime());
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Statut de la commande mis à jour');
        }
        
        return $this->redirectToRoute('app_responsable_commandes');
    }
    
    /**
     * Détails d'une commande
     */
    #[Route('/commande/{id}', name: 'app_responsable_commande_details')]
    public function commandeDetails(
        Commande $commande
    ): Response {
        try {
            $parapharmacie = $this->getCurrentParapharmacie();
        } catch (AccessDeniedException $e) {
            return $this->redirectToRoute('app_home');
        }
        
        // Vérifier que la commande appartient bien à cette parapharmacie
        if ($commande->getParapharmacie()->getId() !== $parapharmacie->getId()) {
            $this->addFlash('error', 'Cette commande n\'appartient pas à votre parapharmacie');
            return $this->redirectToRoute('app_responsable_commandes');
        }
        
        return $this->render('backoffice/responsable/commande_details.html.twig', [
            'commande' => $commande,
            'parapharmacie' => $parapharmacie
        ]);
    }
    
    /**
     * Statistiques détaillées
     */
    #[Route('/statistiques', name: 'app_responsable_statistiques')]
    public function statistiques(
        CommandeRepository $commandeRepository,
        ProduitRepository $produitRepository
    ): Response {
        try {
            $parapharmacie = $this->getCurrentParapharmacie();
        } catch (AccessDeniedException $e) {
            return $this->redirectToRoute('app_home');
        }
        
        $statsMensuelles = $commandeRepository->getMonthlyStatsByParapharmacie($parapharmacie->getId());
        $topProduits = $produitRepository->findTopSellingByParapharmacie($parapharmacie->getId(), 10);
        $statsStatuts = $commandeRepository->getStatsByStatutAndParapharmacie($parapharmacie->getId());
        
        return $this->render('backoffice/responsable/statistiques.html.twig', [
            'parapharmacie' => $parapharmacie,
            'statsMensuelles' => $statsMensuelles,
            'topProduits' => $topProduits,
            'statsStatuts' => $statsStatuts
        ]);
    }
    
    /**
     * Paramètres de la parapharmacie
     */
    #[Route('/parametres', name: 'app_responsable_parametres')]
    public function parametres(
        Request $request
    ): Response {
        try {
            $parapharmacie = $this->getCurrentParapharmacie();
        } catch (AccessDeniedException $e) {
            return $this->redirectToRoute('app_home');
        }
        
        $form = $this->createFormBuilder($parapharmacie)
            ->add('nom', null, ['label' => 'Nom de la parapharmacie'])
            ->add('adresse', null, ['label' => 'Adresse'])
            ->add('telephone', null, ['label' => 'Téléphone'])
            ->add('email', null, ['label' => 'Email'])
            ->getForm();
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Paramètres mis à jour avec succès');
            
            return $this->redirectToRoute('app_responsable_parametres');
        }
        
        return $this->render('backoffice/responsable/parametres.html.twig', [
            'form' => $form->createView(),
            'parapharmacie' => $parapharmacie
        ]);
    }
}