<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Parapharmacie;
use App\Entity\Produit;
use App\Form\CommandeType;
use App\Integration\FastApiSemanticSearchClient;
use App\Payment\BtcPayPaymentService;
use App\Repository\CommandeRepository;
use App\Repository\ParapharmacieRepository;
use App\Repository\ProduitRepository;
use App\Service\ProduitSemanticModelService;
use Doctrine\ORM\EntityManagerInterface;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProduitController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(string:MOLLIE_API_KEY)%')]
        private readonly string $mollieApiKey,
        #[Autowire('%env(default::MOLLIE_WEBHOOK_URL)%')]
        private readonly ?string $mollieWebhookUrl = null
    ) {
    }

    /**
     * Afficher les details d'un produit
     */
    #[Route('/produit/{id}', name: 'app_produit_details')]
    public function details(
        Produit $produit, 
        ParapharmacieRepository $parapharmacieRepository,
        ProduitRepository $produitRepository
    ): Response
    {
        $pharmaciesAvecProduit = $parapharmacieRepository->findAllWithProductAndPrice($produit);
        if (empty($pharmaciesAvecProduit)) {
            $pharmaciesAvecProduit = $produit->getParapharmacies()->toArray();
        }

        // Offres multi-parapharmacies: meme nom de produit, IDs potentiellement differents
        $pharmacieOffers = [];
        $seenParapharmacies = [];
        $sameNameProducts = $produitRepository->findByNormalizedName($produit->getNom());

        foreach ($sameNameProducts as $sameProduct) {
            foreach ($sameProduct->getParapharmacies() as $pharmacie) {
                $pharmacieId = $pharmacie->getId();
                if (!$pharmacieId || isset($seenParapharmacies[$pharmacieId])) {
                    continue;
                }

                $pharmacieOffers[] = [
                    'pharmacie' => $pharmacie,
                    'produitId' => $sameProduct->getId(),
                    'prix' => (float) $sameProduct->getPrix(),
                ];
                $seenParapharmacies[$pharmacieId] = true;
            }
        }
        
        // afficherrer toutes les pharmacies pour afficher aussi celles qui n'ont pas le produit
        $toutesParapharmacies = $parapharmacieRepository->findAll();
        
        return $this->render('produit/search_results.html.twig', [
            'produit' => $produit,
            'pharmaciesAvecProduit' => $pharmaciesAvecProduit,
            'pharmacieOffers' => $pharmacieOffers,
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
        SessionInterface $session
    ): Response
    {
        // afficherrer les parapharmacies qui ont ce produit
        $parapharmaciesCollection = $produit->getParapharmacies();
        
        // afficherrifier si le produit est disponible dans au moins une parapharmacie
        if ($parapharmaciesCollection->isEmpty()) {
            $this->addFlash('error', 'Ce produit n\'est disponible dans aucune parapharmacie.');
            return $this->redirectToRoute('app_produit_details', ['id' => $produit->getId()]);
        }
        
        // Convertir la Collection en tableau
        $parapharmacies = $parapharmaciesCollection->toArray();
        
        // RÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©cupÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©rer la quantitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© et pharmacie depuis les paramÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¨tres GET (si prÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©sents)
        $quantite = $request->query->getInt('quantite', 1);
        $pharmacieId = $request->query->getInt('pharmacie');
        
        // CrÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©er une nouvelle commande
        $commande = new Commande();
        $commande->setProduit($produit);
        $commande->setQuantite($quantite);
        $commande->setPrixUnitaire($produit->getPrix());
        $commande->calculerPrixTotal();
        
        // Si une pharmacie est spÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©cifiÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©e, la prÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©-sÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©lectionner
        if ($pharmacieId) {
            $pharmacie = $entityManager->getRepository(Parapharmacie::class)->find($pharmacieId);
            if ($pharmacie && in_array($pharmacie, $parapharmacies, true)) {
                $commande->setParapharmacie($pharmacie);
            }
        }
        
        // CrÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©er le formulaire
        $form = $this->createForm(CommandeType::class, $commande, [
            'produit' => $produit,
            'parapharmacies' => $parapharmacies
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Action alternative: ajouter au panier pour commander plusieurs produits d'une meme parapharmacie
            if ($request->request->get('action') === 'add_to_cart') {
                $parapharmacie = $commande->getParapharmacie();
                if (!$parapharmacie) {
                    $this->addFlash('error', 'Veuillez selectionner une parapharmacie avant d\'ajouter au panier.');
                    return $this->redirectToRoute('app_commander', ['id' => $produit->getId()]);
                }

                $panier = $session->get('panier', []);
                $quantite = max(1, (int) $commande->getQuantite());
                $pharmacieId = (int) $parapharmacie->getId();

                $found = false;
                foreach ($panier as &$item) {
                    if ((int) $item['produit_id'] === (int) $produit->getId() && (int) $item['pharmacie_id'] === $pharmacieId) {
                        $item['quantite'] += $quantite;
                        $found = true;
                        break;
                    }
                }
                unset($item);

                if (!$found) {
                    $panier[] = [
                        'produit_id' => $produit->getId(),
                        'produit_nom' => $produit->getNom(),
                        'produit_image' => $produit->getImage(),
                        'pharmacie_id' => $pharmacieId,
                        'pharmacie_nom' => $parapharmacie->getNom(),
                        'quantite' => $quantite,
                        'prix' => (float) $produit->getPrix(),
                    ];
                }

                $session->set('panier', $panier);
                $this->addFlash('success', 'Produit ajoute au panier. Vous pouvez ajouter d\'autres produits de la meme parapharmacie puis valider une seule commande.');

                return $this->redirectToRoute('app_panier');
            }

            $quantiteDemandee = max(1, (int) $commande->getQuantite());
            $stockActuel = $produit->getStock();
            if ($stockActuel !== null) {
                if ($stockActuel < $quantiteDemandee) {
                    $this->addFlash('error', sprintf('Stock insuffisant pour "%s". Disponible: %d.', (string) $produit->getNom(), $stockActuel));
                    return $this->redirectToRoute('app_commander', ['id' => $produit->getId()]);
                }

                $produit->setStock($stockActuel - $quantiteDemandee);
            }

            $commande->setQuantite($quantiteDemandee);
            $commande->calculerPrixTotal();
            $commande->setDateModification(new \DateTime());
            $commande->setStatut('en_attente_paiement');

            $entityManager->persist($commande);
            $entityManager->flush();

            if (empty($this->mollieApiKey)) {
                $this->addFlash('error', 'Cle API Mollie manquante. Configurez MOLLIE_API_KEY dans votre .env.');
                return $this->redirectToRoute('app_commander_confirmation', ['id' => $commande->getId()]);
            }

            $mollie = new MollieApiClient();
            $mollie->setApiKey($this->mollieApiKey);

            try {
                $payment = $mollie->payments->create([
                    'amount' => [
                        'currency' => 'EUR',
                        'value' => number_format((float) $commande->getPrixTotal(), 2, '.', ''),
                    ],
                    'description' => sprintf('Commande %s - %s', $commande->getNumero(), $produit->getNom()),
                    'redirectUrl' => $this->generateUrl('app_commander_confirmation', [
                        'id' => $commande->getId(),
                    ], UrlGeneratorInterface::ABSOLUTE_URL),
                    'webhookUrl' => $this->mollieWebhookUrl ?: $this->generateUrl('app_mollie_webhook', [], UrlGeneratorInterface::ABSOLUTE_URL),
                    'metadata' => [
                        'commande_id' => $commande->getId(),
                        'commande_numero' => $commande->getNumero(),
                        'produit_id' => $produit->getId(),
                    ],
                    'locale' => 'fr_FR',
                ]);
            } catch (ApiException $e) {
                $this->addFlash('error', 'Erreur Mollie: ' . $e->getMessage());
                return $this->redirectToRoute('app_commander_confirmation', ['id' => $commande->getId()]);
            }

            return $this->redirect($payment->getCheckoutUrl(), 303);
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
        Commande $commande,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response
    {
        $paymentId = $request->query->get('payment_id');

        if ($paymentId && !empty($this->mollieApiKey) && preg_match('/^tr_/', (string) $paymentId) === 1) {
            $mollie = new MollieApiClient();
            $mollie->setApiKey($this->mollieApiKey);

            try {
                $payment = $mollie->payments->get($paymentId);
                if ($payment->isPaid()) {
                    $commande->setStatut('confirmee');
                    $this->addFlash('success', 'Paiement confirme. Merci pour votre commande.');
                } elseif ($payment->isCanceled() || $payment->isExpired() || $payment->isFailed()) {
                    $commande->setStatut('annulee');
                    $this->addFlash('error', 'Le paiement a echoue ou a ete annule.');
                } else {
                    $commande->setStatut('en_attente_paiement');
                    $this->addFlash('info', 'Paiement en cours de validation.');
                }

                $commande->setDateModification(new \DateTime());
                $entityManager->flush();
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Impossible de verifier le paiement Mollie.');
            }
        }

        return $this->render('commande/confirmation.html.twig', [
            'commande' => $commande,
            'produit' => $commande->getProduit()
        ]);
    }

    #[Route('/mollie/webhook', name: 'app_mollie_webhook', methods: ['POST'])]
    public function mollieWebhook(
        Request $request,
        EntityManagerInterface $entityManager,
        CommandeRepository $commandeRepository
    ): Response {
        $paymentId = $request->request->get('id');
        if (!$paymentId || empty($this->mollieApiKey)) {
            return new Response('', Response::HTTP_OK);
        }

        $mollie = new MollieApiClient();
        $mollie->setApiKey($this->mollieApiKey);

        try {
            $payment = $mollie->payments->get($paymentId);
            $commandeId = (int) ($payment->metadata->commande_id ?? 0);
            if (!$commandeId) {
                return new Response('', Response::HTTP_OK);
            }

            $commande = $commandeRepository->find($commandeId);
            if (!$commande) {
                return new Response('', Response::HTTP_OK);
            }

            if ($payment->isPaid()) {
                $commande->setStatut('confirmee');
            } elseif ($payment->isCanceled() || $payment->isExpired() || $payment->isFailed()) {
                $commande->setStatut('annulee');
            } else {
                $commande->setStatut('en_attente_paiement');
            }

            $commande->setDateModification(new \DateTime());
            $entityManager->flush();
        } catch (\Throwable $e) {
            return new Response('', Response::HTTP_OK);
        }

        return new Response('', Response::HTTP_OK);
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
        // RÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©cupÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©rer l'email depuis la session ou le formulaire
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
        
        // Sauvegarder l'email en session pour une utilisation ultÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©rieure
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
        // VÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©rifier si la commande peut ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âªtre annulÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©e (seulement si en attente)
        if (!in_array($commande->getStatut(), ['en_attente', 'en_attente_paiement'], true)) {
            $this->addFlash('error', 'Cette commande ne peut plus ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âªtre annulÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©e.');
            return $this->redirectToRoute('app_mes_commandes');
        }
        
        // VÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©rifier le token CSRF pour la sÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©curitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©
        $submittedToken = (string) (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('annuler-commande', $submittedToken)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_mes_commandes');
        }
        
        // Changer le statut de la commande
        $commande->setStatut('annulee');
        $commande->setDateModification(new \DateTime());
        
        // Enregistrer les modifications
        $entityManager->flush();
        
        // Message de succÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¨s
        $this->addFlash('success', 'Commande #' . $commande->getNumero() . ' annulÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©e avec succÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¨s.');
        
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
        ParapharmacieRepository $parapharmacieRepository,
        ProduitSemanticModelService $semanticModelService,
        FastApiSemanticSearchClient $semanticSearchClient
    ): Response
    {
        $searchTerm = trim((string) $request->query->get('q', ''));
        $results = [];
        $semanticKeywords = [];
        $matchedIntents = [];

        if ($searchTerm !== '') {
            $semanticKeywords = $this->buildSemanticKeywords($searchTerm);
            $matchedIntents = $this->detectSemanticIntents($searchTerm);

            $externalIds = $semanticSearchClient->searchProductIds($searchTerm, 5, $semanticKeywords);
            if (!empty($externalIds)) {
                $results = $produitRepository->findActiveByIdsOrdered($externalIds);
            }

            if (empty($results)) {
                $results = $semanticModelService->search($searchTerm, $semanticKeywords, 5, 0.20);
            }

            if (empty($results) && !empty($semanticKeywords)) {
                $results = $produitRepository->semanticSearch($semanticKeywords, 5);
            }
        }

        // Afficher un seul produit par nom (evite les doublons multi-parapharmacies)
        $uniqueByName = [];
        $dedupedResults = [];
        foreach ($results as $result) {
            if (!$result instanceof Produit) {
                continue;
            }
            $nameKey = mb_strtolower(trim((string) $result->getNom()), 'UTF-8');
            if ($nameKey === '' || isset($uniqueByName[$nameKey])) {
                continue;
            }
            $uniqueByName[$nameKey] = true;
            $dedupedResults[] = $result;
        }
        $results = array_slice($dedupedResults, 0, 5);

        return $this->render('produit/recherche.html.twig', [
            'searchTerm' => $searchTerm,
            'results' => $results,
            'semanticKeywords' => $semanticKeywords,
            'matchedIntents' => $matchedIntents,
            'parapharmacies' => $parapharmacieRepository->findAll(),
        ]);
    }
    /**
     * API pour vÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©rifier la disponibilitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© d'un produit
     */
    #[Route('/api/produit/{id}/disponibilite', name: 'api_produit_disponibilite')]
    public function disponibiliteProduit(
        Produit $produit,
        ParapharmacieRepository $parapharmacieRepository
    ): Response
    {
        // Trouver les parapharmacies qui ont ce produit
        $parapharmacies = $parapharmacieRepository->findAllWithProductAndPrice($produit);
        
        // PrÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©parer la rÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©ponse JSON
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
  
        return $this->json($response);
    }
    /**
     * @return array<string, string[]>
     */
    private function getSemanticDictionary(): array
    {
        return [
            'mal de gorge' => ['gorge', 'pastilles', 'spray gorge', 'sirop', 'propolis', 'miel', 'toux', 'angine'],
            'toux' => ['sirop', 'pastilles', 'gorge', 'expectoration', 'respiration'],
            'rhume' => ['nez', 'congestion', 'spray nasal', 'vitamine c', 'immunite'],
            'fievre' => ['temperature', 'thermometre', 'douleur', 'paracetamol'],
            'maux de tete' => ['migraine', 'douleur', 'calmant', 'paracetamol'],
            'stress' => ['relaxation', 'sommeil', 'magnesium', 'calme'],
            'fatigue' => ['energie', 'vitamines', 'magnesium', 'fer'],
            'digestion' => ['ballonnements', 'probiotiques', 'estomac', 'intestinal'],
            'allergie' => ['antihistaminique', 'nez', 'yeux', 'demangeaison'],
            'peau seche' => ['hydratant', 'creme', 'baume', 'reparateur'],
        ];
    }

    /**
     * @return string[]
     */
    private function buildSemanticKeywords(string $rawQuery): array
    {
        $query = $this->normalizeSearchText($rawQuery);
        if ($query === '') {
            return [];
        }

        $stopWords = $this->getSemanticStopWords();
        $keywords = [$query];
        $tokens = preg_split('/\s+/', $query) ?: [];
        foreach ($tokens as $token) {
            if ($this->isSemanticKeywordUseful($token, $stopWords)) {
                $keywords[] = $token;
            }
        }

        foreach ($this->getSemanticDictionary() as $intent => $intentKeywords) {
            $normalizedIntent = $this->normalizeSearchText($intent);
            if (str_contains($query, $normalizedIntent)) {
                $keywords = array_merge($keywords, $intentKeywords);
                continue;
            }

            foreach ($tokens as $token) {
                if (mb_strlen($token) >= 4 && str_contains($normalizedIntent, $token)) {
                    $keywords = array_merge($keywords, $intentKeywords);
                    break;
                }
            }
        }

        $normalized = array_map(
            fn (string $item) => $this->normalizeSearchText($item),
            $keywords
        );

        return array_values(array_filter(array_unique($normalized), function (string $item) use ($stopWords): bool {
            if (str_contains($item, ' ')) {
                return true;
            }

            return $this->isSemanticKeywordUseful($item, $stopWords);
        }));
    }

    /**
     * @return string[]
     */
    private function detectSemanticIntents(string $rawQuery): array
    {
        $query = $this->normalizeSearchText($rawQuery);
        if ($query === '') {
            return [];
        }

        $intents = [];
        foreach ($this->getSemanticDictionary() as $intent => $intentKeywords) {
            $normalizedIntent = $this->normalizeSearchText($intent);
            if (str_contains($query, $normalizedIntent)) {
                $intents[] = $intent;
                continue;
            }

            foreach ($intentKeywords as $keyword) {
                if (str_contains($query, $this->normalizeSearchText($keyword))) {
                    $intents[] = $intent;
                    break;
                }
            }
        }

        return array_values(array_unique($intents));
    }

    private function normalizeSearchText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return string[]
     */
    private function getSemanticStopWords(): array
    {
        return [
            'a',
            'au',
            'aux',
            'avec',
            'ce',
            'ces',
            'dans',
            'de',
            'des',
            'du',
            'en',
            'et',
            'je',
            'la',
            'le',
            'les',
            'mon',
            'mes',
            'ma',
            'moi',
            'pour',
            'par',
            'pas',
            'qui',
            'que',
            'sur',
            'un',
            'une',
            'vos',
            'votre',
            'produit',
            'produits',
            'parapharmacie',
            'pharmacie',
            'besoin',
            'symptome',
        ];
    }

    private function isSemanticKeywordUseful(string $word, array $stopWords): bool
    {
        $word = $this->normalizeSearchText($word);

        return $word !== '' && mb_strlen($word) >= 3 && !in_array($word, $stopWords, true);
    }

    /**
     * API pour obtenir les dÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©tails d'une parapharmacie
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
        // RÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©cupÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©rer tous les produits
        $produits = $produitRepository->findAll();
        
        // RÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©cupÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©rer toutes les parapharmacies
        $parapharmacies = $parapharmacieRepository->findAll();
        
        // Afficher la liste des produits
        return $this->render('produit/liste.html.twig', [
            'produits' => $produits,
            'parapharmacies' => $parapharmacies
        ]);
    }
    
    /**
     * Produits par catÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©gorie
     */
    #[Route('/produits/categorie/{categorie}', name: 'app_produits_categorie')]
    public function produitsParCategorie(
        string $categorie,
        ProduitRepository $produitRepository,
        ParapharmacieRepository $parapharmacieRepository
    ): Response
    {
        // Rechercher les produits par catÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©gorie
        $produits = $produitRepository->findByCategorie($categorie);
        
        // RÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©cupÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©rer toutes les parapharmacies
        $parapharmacies = $parapharmacieRepository->findAll();
        
        // Afficher les produits de la catÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©gorie
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
        
        // RÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©cupÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©rer toutes les parapharmacies
        $parapharmacies = $parapharmacieRepository->findAll();
        
        // Afficher les produits en promotion
        return $this->render('produit/promotions.html.twig', [
            'produits' => $produits,
            'parapharmacies' => $parapharmacies
        ]);
    }
    
    /**
     * TÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©lÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©charger la facture d'une commande
     */
    #[Route('/commande/{id}/facture', name: 'app_commande_facture')]
    public function facture(
        Commande $commande
    ): Response
    {
        // CrÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©er un PDF ou HTML de facture
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
        // VÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©rifier si l'utilisateur est admin
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'AccÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¨s rÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©servÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© aux administrateurs.');
            return $this->redirectToRoute('app_home');
        }
        
        // RÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©cupÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©rer les statistiques
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








