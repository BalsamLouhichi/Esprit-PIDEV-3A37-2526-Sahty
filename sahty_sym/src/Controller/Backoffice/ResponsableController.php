<?php
// src/Controller/Backoffice/ResponsableController.php

namespace App\Controller\Backoffice;

use App\Entity\Produit;
use App\Entity\Commande;
use App\Entity\Parapharmacie;
use App\Entity\ResponsableParapharmacie;
use App\Form\ProduitType;
use App\Service\ProductContentGenerator;
use App\Service\ResponsableDailySummaryService;
use App\Repository\ProduitRepository;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;



#[Route('/responsable')]
class ResponsableController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * MÃ©thode helper pour rÃ©cupÃ©rer la parapharmacie de l'utilisateur connectÃ©
     */
    private function getCurrentParapharmacie(): Parapharmacie
    {
        $user = $this->getUser();
        
        if (!$user instanceof ResponsableParapharmacie) {
            throw new AccessDeniedException('AccÃ¨s rÃ©servÃ© aux responsables de parapharmacie');
        }

        $parapharmacie = $user->getParapharmacie();
        
        if (!$parapharmacie) {
            $this->addFlash('error', 'Vous n\'Ãªtes pas associÃ© Ã  une parapharmacie. Veuillez contacter l\'administrateur.');
            throw new AccessDeniedException('Aucune parapharmacie associÃ©e Ã  ce compte');
        }

        return $parapharmacie;
    }
    
    /**
     * Tableau de bord du responsable
     */
    #[Route('/dashboard', name: 'app_responsable_dashboard')]
    public function dashboard(
        CommandeRepository $commandeRepository,
        ProduitRepository $produitRepository,
        ResponsableDailySummaryService $dailySummaryService
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
        $aiSummary = $dailySummaryService->generate(
            $parapharmacie,
            $commandesRecent,
            $commandesEnAttente,
            (int) $totalProduits,
            is_array($statsVentes) ? $statsVentes : []
        );
        
        return $this->render('backoffice/responsable/dashboard.html.twig', [
            'parapharmacie' => $parapharmacie,
            'commandesEnAttente' => count($commandesEnAttente),
            'commandesRecent' => $commandesRecent,
            'totalProduits' => $totalProduits,
            'statsVentes' => $statsVentes,
            'aiSummary' => $aiSummary,
        ]);
    }
    
    /**
     * Liste des produits de la parapharmacie
     */
    #[Route('/produits', name: 'app_responsable_produits')]
    public function produits(
        Request $request,
        ProduitRepository $produitRepository
    ): Response {
        try {
            $parapharmacie = $this->getCurrentParapharmacie();
        } catch (AccessDeniedException $e) {
            return $this->redirectToRoute('app_home');
        }

        $searchTerm = trim((string) $request->query->get('search', ''));
        if ($searchTerm !== '') {
            $produits = $produitRepository->searchByParapharmacie($parapharmacie->getId(), $searchTerm);
        } else {
            $produits = $produitRepository->findByParapharmacie($parapharmacie->getId());
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => true,
                'count' => count($produits),
                'html' => $this->renderView('backoffice/responsable/_produits_rows.html.twig', [
                    'produits' => $produits,
                ]),
            ]);
        }

        return $this->render('backoffice/responsable/produits.html.twig', [
            'produits' => $produits,
            'parapharmacie' => $parapharmacie,
            'searchTerm' => $searchTerm,
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
            // GÃ©rer l'upload de l'image
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
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image: ' . $e->getMessage());
                }
            }
            
            $produit->addParapharmacie($parapharmacie);
            $produit->setReference('PROD-'.date('Ymd').'-'.uniqid());
            
            $this->entityManager->persist($produit);
            $this->entityManager->flush();
            
            
            
            
            $this->addFlash('success', 'Produit ajoutÃ© avec succÃ¨s !');
            
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
        
        // VÃ©rifier que le produit appartient bien Ã  cette parapharmacie
        if (!$produit->getParapharmacies()->contains($parapharmacie)) {
            $this->addFlash('error', 'Ce produit n\'appartient pas Ã  votre parapharmacie');
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
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image: ' . $e->getMessage());
                }
            }
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Produit modifiÃ© avec succÃ¨s !');
            
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
        
        // VÃ©rifier que le produit appartient bien Ã  cette parapharmacie
        if (!$produit->getParapharmacies()->contains($parapharmacie)) {
            $this->addFlash('error', 'Ce produit n\'appartient pas Ã  votre parapharmacie');
            return $this->redirectToRoute('app_responsable_produits');
        }
        
        if ($this->isCsrfTokenValid('delete'.$produit->getId(), (string) $request->request->get('_token'))) {
            // Supprimer l'image associÃ©e
            if ($produit->getImage()) {
                $imagePath = $this->getParameter('produits_images_directory').'/'.$produit->getImage();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            $this->entityManager->remove($produit);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Produit supprimÃ© avec succÃ¨s');
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
        Request $request,
        MailerInterface $mailer
    ): Response {
        try {
            $parapharmacie = $this->getCurrentParapharmacie();
        } catch (AccessDeniedException $e) {
            return $this->redirectToRoute('app_home');
        }
        
        // VÃ©rifier que la commande appartient bien Ã  cette parapharmacie
        if ($commande->getParapharmacie()->getId() !== $parapharmacie->getId()) {
            $this->addFlash('error', 'Cette commande n\'appartient pas Ã  votre parapharmacie');
            return $this->redirectToRoute('app_responsable_commandes');
        }
        
        $nouveauStatut = $request->request->get('statut');
        $statutsValides = ['en_attente', 'confirmee', 'preparation', 'expediee', 'livree', 'annulee'];
        
        if (in_array($nouveauStatut, $statutsValides)) {
            $ancienStatut = $commande->getStatut();
            $commande->setStatut($nouveauStatut);
            $commande->setDateModification(new \DateTime());
            
            $this->entityManager->flush();
            
            // Notify patient when order is accepted
            if ($ancienStatut !== 'confirmee' && $nouveauStatut === 'confirmee') {
                $this->notifierPatientCommandeAcceptee($commande, $mailer);
            }
            
            $this->addFlash('success', 'Statut de la commande mis Ã  jour');
        }
        
        return $this->redirectToRoute('app_responsable_commandes');
    }
    
    private function notifierPatientCommandeAcceptee(Commande $commande, MailerInterface $mailer): void
    {
        $emailClient = $commande->getEmail();
        if (!$emailClient) {
            return;
        }

        $from = (string) (
            $_ENV['APP_MAILER_FROM']
            ?? getenv('APP_MAILER_FROM')
            ?? $_ENV['MAILER_FROM']
            ?? getenv('MAILER_FROM')
            ?: 'no-reply@sahty.local'
        );

        try {
            $html = $this->renderView('emails/commande_acceptee_patient.html.twig', [
                'commande' => $commande,
                'parapharmacie' => $commande->getParapharmacie(),
            ]);

            $email = (new Email())
                ->from($from)
                ->to($emailClient)
                ->subject('Votre commande #' . $commande->getNumero() . ' a ete acceptee')
                ->html($html);

            $mailer->send($email);
        } catch (\Throwable $e) {
            error_log('Erreur notification commande acceptee: ' . $e->getMessage());
            $this->addFlash('warning', 'La commande est mise a jour, mais l email de notification a echoue.');
        }
    }

    /**
     * DÃ©tails d'une commande
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
        
        // VÃ©rifier que la commande appartient bien Ã  cette parapharmacie
        if ($commande->getParapharmacie()->getId() !== $parapharmacie->getId()) {
            $this->addFlash('error', 'Cette commande n\'appartient pas Ã  votre parapharmacie');
            return $this->redirectToRoute('app_responsable_commandes');
        }
        
        return $this->render('backoffice/responsable/commande_details.html.twig', [
            'commande' => $commande,
            'parapharmacie' => $parapharmacie
        ]);
    }
    
    /**
     * Statistiques dÃ©taillÃ©es
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
     * ParamÃ¨tres de la parapharmacie
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
            ->add('telephone', null, ['label' => 'TÃ©lÃ©phone'])
            ->add('email', null, ['label' => 'Email'])
            ->getForm();
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'ParamÃ¨tres mis Ã  jour avec succÃ¨s');
            
            return $this->redirectToRoute('app_responsable_parametres');
        }
        
        return $this->render('backoffice/responsable/parametres.html.twig', [
            'form' => $form->createView(),
            'parapharmacie' => $parapharmacie
        ]);
    }

    /**
     * Generer une fiche produit avec IA
     */
    #[Route('/produit/generer-fiche-ia', name: 'app_responsable_produit_generer_fiche_ia', methods: ['POST'])]
    public function genererFicheIA(
        Request $request,
        ProductContentGenerator $productContentGenerator
    ): JsonResponse {
        try {
            $this->getCurrentParapharmacie();
        } catch (AccessDeniedException $e) {
            return $this->json([
                'success' => false,
                'message' => 'Acces non autorise',
            ], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json([
                'success' => false,
                'message' => 'Requete invalide',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $generated = $productContentGenerator->generate($payload);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'data' => $generated,
        ]);
    }

    /**
     * API alertes stock faible + suggestion de reapprovisionnement.
     */
    #[Route('/api/stock/alertes', name: 'app_responsable_api_stock_alertes', methods: ['GET'])]
    public function stockAlertesApi(
        Request $request,
        ProduitRepository $produitRepository
    ): JsonResponse {
        try {
            $parapharmacie = $this->getCurrentParapharmacie();
        } catch (AccessDeniedException $e) {
            return $this->json([
                'success' => false,
                'message' => 'Acces non autorise',
            ], Response::HTTP_FORBIDDEN);
        }

        $threshold = max(1, (int) $request->query->get('threshold', 5));
        $targetStock = max(10, $threshold * 3);
        $produits = $produitRepository->findLowStockByParapharmacie((int) $parapharmacie->getId(), $threshold);

        $alerts = array_map(static function (Produit $produit) use ($threshold, $targetStock): array {
            $stock = max(0, (int) ($produit->getStock() ?? 0));
            $suggestedQty = max(1, $targetStock - $stock);

            $priority = 'moyenne';
            if ($stock <= 0) {
                $priority = 'critique';
            } elseif ($stock < max(1, (int) ceil($threshold / 2))) {
                $priority = 'haute';
            }

            return [
                'produit_id' => $produit->getId(),
                'nom' => $produit->getNom(),
                'stock_actuel' => $stock,
                'seuil_alerte' => $threshold,
                'priorite' => $priority,
                'suggestion_reapprovisionnement' => $suggestedQty,
                'message' => sprintf(
                    'Stock faible pour %s (%d). Suggestion: recommander %d unites.',
                    (string) $produit->getNom(),
                    $stock,
                    $suggestedQty
                ),
            ];
        }, $produits);

        return $this->json([
            'success' => true,
            'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'parapharmacie_id' => $parapharmacie->getId(),
            'threshold' => $threshold,
            'count' => count($alerts),
            'alerts' => $alerts,
        ]);
    }
}





