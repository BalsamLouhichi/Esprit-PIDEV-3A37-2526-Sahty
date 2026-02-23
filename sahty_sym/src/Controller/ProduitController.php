<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Parapharmacie;
use App\Entity\Produit;
use App\Form\CommandeType;
use App\Payment\BtcPayPaymentService;
use App\Repository\CommandeRepository;
use App\Repository\ParapharmacieRepository;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ProduitController extends AbstractController
{
    /**
     * Afficher les détails d'un produit
     */
    #[Route('/produit/{id}', name: 'app_produit_details')]
    public function details(
        Produit $produit, 
        ParapharmacieRepository $parapharmacieRepository
    ): Response
    {
        // Trouver toutes les pharmacies qui ont ce produit spécifique (par ID)
        $pharmaciesAvecProduit = $parapharmacieRepository->findAllWithProductAndPrice($produit);
        
        // Récupérer toutes les pharmacies pour afficher aussi celles qui n'ont pas le produit
        $toutesParapharmacies = $parapharmacieRepository->findAll();
        
        return $this->render('produit/search_results.html.twig', [
            'produit' => $produit,
            'pharmaciesAvecProduit' => $pharmaciesAvecProduit,
            'toutesParapharmacies' => $toutesParapharmacies,
        ]);
    }
    
    /**
     * Page de commande pour un produit
     */
    #[Route('/commander/{id}', name: 'app_commander')]
    public function commander(
        Produit $produit,
        Request $request,
        EntityManagerInterface $entityManager,
        BtcPayPaymentService $btcPayPaymentService
    ): Response
    {
        // Récupérer les parapharmacies qui ont ce produit
        $parapharmaciesCollection = $produit->getParapharmacies();
        
        // Vérifier si le produit est disponible dans au moins une parapharmacie
        if ($parapharmaciesCollection->isEmpty()) {
            $this->addFlash('error', 'Ce produit n\'est disponible dans aucune parapharmacie.');
            return $this->redirectToRoute('app_produit_details', ['id' => $produit->getId()]);
        }
        
        // Convertir la Collection en tableau
        $parapharmacies = $parapharmaciesCollection->toArray();
        
        // Récupérer la quantité et pharmacie depuis les paramètres GET (si présents)
        $quantite = $request->query->getInt('quantite', 1);
        $pharmacieId = $request->query->getInt('pharmacie');
        
        // Créer une nouvelle commande
        $commande = new Commande();
        $commande->setProduit($produit);
        $commande->setQuantite($quantite);
        $commande->setPrixUnitaire($produit->getPrix());
        $commande->calculerPrixTotal();
        
        // Si une pharmacie est spécifiée, la pré-sélectionner
        if ($pharmacieId) {
            $pharmacie = $entityManager->getRepository(Parapharmacie::class)->find($pharmacieId);
            if ($pharmacie && in_array($pharmacie, $parapharmacies, true)) {
                $commande->setParapharmacie($pharmacie);
            }
        }
        
        // Créer le formulaire
        $form = $this->createForm(CommandeType::class, $commande, [
            'produit' => $produit,
            'parapharmacies' => $parapharmacies
        ]);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Recalculer le prix total au cas où
            $commande->calculerPrixTotal();
            
            // Définir la date de modification
            $commande->setDateModification(new \DateTime());
            $commande->setPaymentProvider(null);
            $commande->setPaymentReference(null);
            $commande->setPaymentUrl(null);
            
            // Persister la commande
            $entityManager->persist($commande);
            $entityManager->flush();

            if ($commande->getModePaiement() === 'online_btcpay') {
                try {
                    $redirectPath = $this->generateUrl('app_commander_confirmation', ['id' => $commande->getId()]);
                    $redirectUrl = $request->getSchemeAndHttpHost() . $redirectPath;
                    $invoice = $btcPayPaymentService->createInvoice($commande, $redirectUrl);

                    $commande->setPaymentProvider('btcpay');
                    $commande->setPaymentReference($invoice['invoiceId']);
                    $commande->setPaymentUrl($invoice['checkoutUrl']);
                    $commande->setPaymentStatus('pending');
                    $entityManager->flush();

                    return $this->redirect($invoice['checkoutUrl']);
                } catch (\Throwable $e) {
                    $commande->setPaymentStatus('failed');
                    $entityManager->flush();
                    $this->addFlash('warning', 'Paiement en ligne indisponible. Vous pouvez reessayer depuis la confirmation.');
                }
            } else {
                $commande->setPaymentStatus('not_required');
                $entityManager->flush();
            }
            
            // Message de succès
            $this->addFlash('success', 
                "Commande #{$commande->getNumero()} confirmée ! " .
                "Vous serez contacté par la parapharmacie pour finaliser."
            );
            
            // Rediriger vers la page de confirmation
            return $this->redirectToRoute('app_commander_confirmation', [
                'id' => $commande->getId()
            ]);
        }
        
        // Afficher le formulaire de commande
        return $this->render('commande/formulaire.html.twig', [
            'produit' => $produit,
            'form' => $form->createView(),
            'parapharmacies' => $parapharmacies
        ]);
    }
    
    /**
     * Page de confirmation de commande
     */
    #[Route('/commander-confirmation/{id}', name: 'app_commander_confirmation')]
    public function confirmation(
        Commande $commande
    ): Response
    {
        return $this->render('commande/confirmation.html.twig', [
            'commande' => $commande,
            'produit' => $commande->getProduit()
        ]);
    }

    #[Route('/commande/{id}/payer', name: 'app_commande_payer', methods: ['POST'])]
    public function payerCommande(
        Commande $commande,
        Request $request,
        EntityManagerInterface $entityManager,
        BtcPayPaymentService $btcPayPaymentService
    ): Response {
        if (!$this->isCsrfTokenValid('payer-commande-' . $commande->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_commander_confirmation', ['id' => $commande->getId()]);
        }

        if ($commande->getModePaiement() !== 'online_btcpay') {
            $this->addFlash('info', 'Cette commande ne necessite pas de paiement en ligne.');
            return $this->redirectToRoute('app_commander_confirmation', ['id' => $commande->getId()]);
        }

        try {
            $redirectPath = $this->generateUrl('app_commander_confirmation', ['id' => $commande->getId()]);
            $redirectUrl = $request->getSchemeAndHttpHost() . $redirectPath;
            $invoice = $btcPayPaymentService->createInvoice($commande, $redirectUrl);

            $commande->setPaymentProvider('btcpay');
            $commande->setPaymentReference($invoice['invoiceId']);
            $commande->setPaymentUrl($invoice['checkoutUrl']);
            $commande->setPaymentStatus('pending');
            $commande->setDateModification(new \DateTime());
            $entityManager->flush();

            return $this->redirect($invoice['checkoutUrl']);
        } catch (\Throwable $e) {
            $commande->setPaymentStatus('failed');
            $commande->setDateModification(new \DateTime());
            $entityManager->flush();
            $this->addFlash('error', 'Echec de creation du paiement: ' . $e->getMessage());
            return $this->redirectToRoute('app_commander_confirmation', ['id' => $commande->getId()]);
        }
    }

    #[Route('/payment/btcpay/webhook', name: 'app_payment_btcpay_webhook', methods: ['POST'])]
    public function btcPayWebhook(
        Request $request,
        EntityManagerInterface $entityManager,
        BtcPayPaymentService $btcPayPaymentService
    ): JsonResponse {
        if (!$btcPayPaymentService->isWebhookValid($request)) {
            return $this->json(['ok' => false, 'message' => 'invalid signature'], 401);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['ok' => false, 'message' => 'invalid payload'], 400);
        }

        $invoiceId = (string) ($payload['invoiceId'] ?? $payload['invoice']['id'] ?? '');
        $eventType = strtoupper((string) ($payload['type'] ?? ''));
        if ($invoiceId === '') {
            return $this->json(['ok' => true, 'message' => 'no invoice id']);
        }

        $commande = $entityManager->getRepository(Commande::class)->findOneBy(['paymentReference' => $invoiceId]);
        if (!$commande) {
            return $this->json(['ok' => true, 'message' => 'commande not found']);
        }

        if (str_contains($eventType, 'SETTLED') || str_contains($eventType, 'CONFIRMED') || str_contains($eventType, 'COMPLETED')) {
            $commande->setPaymentStatus('paid');
            $commande->setStatut('confirmee');
        } elseif (str_contains($eventType, 'EXPIRED') || str_contains($eventType, 'INVALID')) {
            $commande->setPaymentStatus('failed');
        } else {
            $commande->setPaymentStatus('pending');
        }

        $commande->setDateModification(new \DateTime());
        $entityManager->flush();

        return $this->json(['ok' => true]);
    }
    
    /**
     * Page pour voir ses commandes (suivi par email)
     */
    #[Route('/mes-commandes', name: 'app_mes_commandes')]
    public function mesCommandes(
        Request $request,
        CommandeRepository $commandeRepository
    ): Response
    {
        // Récupérer l'email depuis la session ou le formulaire
        $email = $request->getSession()->get('commande_email') ?? $request->query->get('email');
        
        // Si aucun email n'est fourni, afficher le formulaire de saisie
        if (!$email) {
            return $this->render('commande/email_form.html.twig');
        }
        
        // Rechercher les commandes par email
        $commandes = $commandeRepository->createQueryBuilder('c')
            ->where('c.email = :email')
            ->setParameter('email', $email)
            ->orderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
        
        // Sauvegarder l'email en session pour une utilisation ultérieure
        $request->getSession()->set('commande_email', $email);
        
        // Afficher la liste des commandes
        return $this->render('commande/mes_commandes.html.twig', [
            'commandes' => $commandes,
            'email' => $email
        ]);
    }
    
    /**
     * Annuler une commande
     */
    #[Route('/commande/{id}/annuler', name: 'app_commande_annuler')]
    public function annulerCommande(
        Commande $commande,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response
    {
        // Vérifier si la commande peut être annulée (seulement si en attente)
        if ($commande->getStatut() !== 'en_attente') {
            $this->addFlash('error', 'Cette commande ne peut plus être annulée.');
            return $this->redirectToRoute('app_mes_commandes');
        }
        
        // Vérifier le token CSRF pour la sécurité
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('annuler-commande', $submittedToken)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_mes_commandes');
        }
        
        // Changer le statut de la commande
        $commande->setStatut('annulee');
        $commande->setDateModification(new \DateTime());
        
        // Enregistrer les modifications
        $entityManager->flush();
        
        // Message de succès
        $this->addFlash('success', 'Commande #' . $commande->getNumero() . ' annulée avec succès.');
        
        // Rediriger vers la liste des commandes
        return $this->redirectToRoute('app_mes_commandes');
    }
    
    /**
     * Recherche de produits
     */
    #[Route('/recherche-produits', name: 'app_recherche_produits')]
    public function rechercheProduits(
        Request $request,
        ProduitRepository $produitRepository,
        ParapharmacieRepository $parapharmacieRepository
    ): Response
    {
        // Récupérer le terme de recherche
        $searchTerm = $request->query->get('q', '');
        $results = [];
        
        // Si un terme de recherche est fourni
        if (!empty($searchTerm)) {
            $results = $produitRepository->search($searchTerm);
        }
        
        // Afficher la page de recherche
        return $this->render('produit/recherche.html.twig', [
            'searchTerm' => $searchTerm,
            'results' => $results,
            'parapharmacies' => $parapharmacieRepository->findAll()
        ]);
    }
    
    /**
     * API pour vérifier la disponibilité d'un produit
     */
    #[Route('/api/produit/{id}/disponibilite', name: 'api_produit_disponibilite')]
    public function disponibiliteProduit(
        Produit $produit,
        ParapharmacieRepository $parapharmacieRepository
    ): Response
    {
        // Trouver les parapharmacies qui ont ce produit
        $parapharmacies = $parapharmacieRepository->findAllWithProductAndPrice($produit);
        
        // Préparer la réponse JSON
        $response = [
            'produit' => [
                'id' => $produit->getId(),
                'nom' => $produit->getNom(),
                'prix' => $produit->getPrix(),
                'description' => $produit->getDescription()
            ],
            'disponibilite' => [
                'total' => count($parapharmacies),
                'parapharmacies' => array_map(function($p) {
                    return [
                        'id' => $p->getId(),
                        'nom' => $p->getNom(),
                        'adresse' => $p->getAdresse(),
                        'telephone' => $p->getTelephone(),
                        'email' => $p->getEmail()
                    ];
                }, $parapharmacies)
            ],
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
        
        // Retourner la réponse JSON
        return $this->json($response);
    }
    
    /**
     * API pour obtenir les détails d'une parapharmacie
     */
    #[Route('/api/parapharmacie/{id}', name: 'api_parapharmacie_details')]
    public function apiParapharmacieDetails(
        Parapharmacie $parapharmacie
    ): Response
    {
        return $this->json([
            'id' => $parapharmacie->getId(),
            'nom' => $parapharmacie->getNom(),
            'adresse' => $parapharmacie->getAdresse(),
            'telephone' => $parapharmacie->getTelephone(),
            'email' => $parapharmacie->getEmail()
        ]);
    }
    
    /**
     * Page d'accueil des produits
     */
    #[Route('/produits', name: 'app_produits_list')]
    public function listeProduits(
        ProduitRepository $produitRepository,
        ParapharmacieRepository $parapharmacieRepository
    ): Response
    {
        // Récupérer tous les produits
        $produits = $produitRepository->findAll();
        
        // Récupérer toutes les parapharmacies
        $parapharmacies = $parapharmacieRepository->findAll();
        
        // Afficher la liste des produits
        return $this->render('produit/liste.html.twig', [
            'produits' => $produits,
            'parapharmacies' => $parapharmacies
        ]);
    }
    
    /**
     * Produits par catégorie
     */
    #[Route('/produits/categorie/{categorie}', name: 'app_produits_categorie')]
    public function produitsParCategorie(
        string $categorie,
        ProduitRepository $produitRepository,
        ParapharmacieRepository $parapharmacieRepository
    ): Response
    {
        // Rechercher les produits par catégorie
        $produits = $produitRepository->findByCategorie($categorie);
        
        // Récupérer toutes les parapharmacies
        $parapharmacies = $parapharmacieRepository->findAll();
        
        // Afficher les produits de la catégorie
        return $this->render('produit/categorie.html.twig', [
            'produits' => $produits,
            'parapharmacies' => $parapharmacies,
            'categorie' => $categorie
        ]);
    }
    
    /**
     * Produits en promotion
     */
    #[Route('/produits/promotions', name: 'app_produits_promotions')]
    public function produitsPromotions(
        ProduitRepository $produitRepository,
        ParapharmacieRepository $parapharmacieRepository
    ): Response
    {
        // Rechercher les produits en promotion
        $produits = $produitRepository->findPromotions();
        
        // Récupérer toutes les parapharmacies
        $parapharmacies = $parapharmacieRepository->findAll();
        
        // Afficher les produits en promotion
        return $this->render('produit/promotions.html.twig', [
            'produits' => $produits,
            'parapharmacies' => $parapharmacies
        ]);
    }
    
    /**
     * Télécharger la facture d'une commande
     */
    #[Route('/commande/{id}/facture', name: 'app_commande_facture')]
    public function facture(
        Commande $commande
    ): Response
    {
        // Créer un PDF ou HTML de facture
        $html = $this->renderView('commande/facture.html.twig', [
            'commande' => $commande
        ]);
        
        // Retourner le PDF (ou HTML pour le moment)
        return new Response($html);
    }
    
    /**
     * Statistiques des commandes (admin)
     */
    #[Route('/admin/statistiques', name: 'app_admin_statistiques')]
    public function statistiques(
        CommandeRepository $commandeRepository,
        ProduitRepository $produitRepository,
        ParapharmacieRepository $parapharmacieRepository
    ): Response
    {
        // Vérifier si l'utilisateur est admin
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Accès réservé aux administrateurs.');
            return $this->redirectToRoute('app_home');
        }
        
        // Récupérer les statistiques
        $stats = $commandeRepository->getStats();
        $commandesRecent = $commandeRepository->findRecentOrders(10);
        $produitsPopulaires = $produitRepository->findMostPopular(10);
        
        return $this->render('admin/statistiques.html.twig', [
            'stats' => $stats,
            'commandesRecent' => $commandesRecent,
            'produitsPopulaires' => $produitsPopulaires,
            'totalParapharmacies' => count($parapharmacieRepository->findAll())
        ]);
    }
}
