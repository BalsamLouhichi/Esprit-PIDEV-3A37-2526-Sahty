<?php
// src/Controller/PanierController.php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use App\Entity\Parapharmacie;
use App\Repository\ParapharmacieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PanierController extends AbstractController
{
    /**
     * Afficher le panier
     */
    #[Route('/panier', name: 'app_panier')]
    public function index(SessionInterface $session): Response
    {
        $panier = $session->get('panier', []);
        $total = 0;

        foreach ($panier as $item) {
            $total += $item['prix'] * $item['quantite'];
        }

        return $this->render('panier/index.html.twig', [
            'panier' => $panier,
            'total' => $total,
        ]);
    }
  
    /**
     * Ajouter un produit au panier
     */
    #[Route('/panier/ajouter/{id}', name: 'app_panier_ajouter', methods: ['POST'])]
    public function ajouter(
        Request $request,
        Produit $produit,
        SessionInterface $session,
        ParapharmacieRepository $parapharmacieRepository
    ): Response {
        $quantite = max(1, (int) $request->request->get('quantite', 1));
        $pharmacieId = $request->request->get('pharmacie');
        $pharmacieId = is_numeric($pharmacieId) ? (int) $pharmacieId : null;
        $prix = (float) $produit->getPrix();

        $pharmacieNom = 'Non specifiee';
        if (!$pharmacieId) {
            $firstParapharmacie = $produit->getParapharmacies()->first();
            if ($firstParapharmacie) {
                $pharmacieId = (int) $firstParapharmacie->getId();
            }
        }

        if ($pharmacieId) {
            $pharmacie = $parapharmacieRepository->find($pharmacieId);
            $pharmacieNom = $pharmacie ? $pharmacie->getNom() : 'Pharmacie';
        }

        $panier = $session->get('panier', []);

        $found = false;
        foreach ($panier as &$item) {
            if ($item['produit_id'] == $produit->getId() && $item['pharmacie_id'] == $pharmacieId) {
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
                'pharmacie_nom' => $pharmacieNom,
                'quantite' => $quantite,
                'prix' => $prix,
            ];
        }

        $session->set('panier', $panier);
        $cartCount = 0;
        foreach ($panier as $item) {
            $cartCount += (int) ($item['quantite'] ?? 1);
        }

        // Retour JSON pour ajout au panier sans rechargement de page (AJAX)
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Produit ajoute au panier avec succes !',
                'cartCount' => $cartCount,
                'productName' => $produit->getNom(),
            ]);
        }

        $this->addFlash('success', 'Produit ajouté au panier avec succès !');

        $referer = $request->headers->get('referer');
        return $this->redirect($referer ?: $this->generateUrl('app_panier'));
    }

    /**
     * Supprimer un article du panier
     */
    #[Route('/panier/supprimer/{index}', name: 'app_panier_supprimer')]
    public function supprimer(int $index, SessionInterface $session): Response
    {
        $panier = $session->get('panier', []);

        if (isset($panier[$index])) {
            unset($panier[$index]);
            $panier = array_values($panier);
            $session->set('panier', $panier);
            $this->addFlash('success', 'Produit retiré du panier.');
        }

        return $this->redirectToRoute('app_panier');
    }

    /**
     * Modifier la quantité d'un article
     */
    #[Route('/panier/modifier/{index}', name: 'app_panier_modifier', methods: ['POST'])]
    public function modifier(int $index, Request $request, SessionInterface $session): Response
    {
        $panier = $session->get('panier', []);
        $quantite = (int) $request->request->get('quantite', 1);

        if (isset($panier[$index]) && $quantite > 0) {
            $panier[$index]['quantite'] = $quantite;
            $session->set('panier', $panier);
            $this->addFlash('success', 'Quantité mise à jour.');
        }

        return $this->redirectToRoute('app_panier');
    }

    /**
     * Vider le panier
     */
    #[Route('/panier/vider', name: 'app_panier_vider')]
    public function vider(SessionInterface $session): Response
    {
        $session->remove('panier');
        $this->addFlash('success', 'Panier vidé avec succès.');

        return $this->redirectToRoute('app_panier');
    }

    /**
     * Afficher le formulaire de commande (GET)
     */
    #[Route('/panier/commander', name: 'app_commande_formulaire_panier', methods: ['GET'])]
    public function formulaireCommande(SessionInterface $session): Response
    {
        $panier = $session->get('panier', []);
        
        if (empty($panier)) {
            $this->addFlash('error', 'Votre panier est vide.');
            return $this->redirectToRoute('app_panier');
        }
        
        // Calculer le total
        $total = 0;
        foreach ($panier as $item) {
            $total += $item['prix'] * $item['quantite'];
        }
        
        return $this->render('panier/formulaire_commande.html.twig', [
            'panier' => $panier,
            'total' => $total
        ]);
    }

    /**
     * Traiter la commande (POST)
     */
    #[Route('/panier/commander/valider', name: 'app_panier_commander_valider', methods: ['POST'])]
    public function validerCommande(
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        ParapharmacieRepository $parapharmacieRepository
    ): Response {
        $panier = $session->get('panier', []);

        if (empty($panier)) {
            $this->addFlash('error', 'Votre panier est vide.');
            return $this->redirectToRoute('app_panier');
        }

        $nomClient = $request->request->get('nomClient');
        $email = $request->request->get('email');
        $telephone = $request->request->get('telephone');
        $adresseLivraison = $request->request->get('adresseLivraison');
        $notes = $request->request->get('notes');

        if (!$nomClient || !$email || !$telephone || !$adresseLivraison) {
            $this->addFlash('error', 'Veuillez remplir tous les champs obligatoires.');
            return $this->redirectToRoute('app_commande_formulaire_panier');
        }

        $articlesParPharmacie = [];
        $articlesIgnores = 0;
        foreach ($panier as $item) {
            $pharmacieId = isset($item['pharmacie_id']) ? (int) $item['pharmacie_id'] : 0;

            if ($pharmacieId <= 0 && isset($item['produit_id'])) {
                $produit = $entityManager->getRepository(Produit::class)->find((int) $item['produit_id']);
                if ($produit && $produit->getParapharmacies()->count() === 1) {
                    $pharmacie = $produit->getParapharmacies()->first();
                    if ($pharmacie) {
                        $pharmacieId = (int) $pharmacie->getId();
                        $item['pharmacie_nom'] = $pharmacie->getNom();
                    }
                }
            }

            if ($pharmacieId <= 0) {
                $articlesIgnores++;
                continue;
            }

            if (!isset($articlesParPharmacie[$pharmacieId])) {
                $articlesParPharmacie[$pharmacieId] = [
                    'pharmacie_nom' => $item['pharmacie_nom'] ?? '',
                    'articles' => [],
                    'total' => 0,
                ];
            }

            $articlesParPharmacie[$pharmacieId]['articles'][] = $item;
            $articlesParPharmacie[$pharmacieId]['total'] += (float) $item['prix'] * (int) $item['quantite'];
        }

        if ($articlesIgnores > 0) {
            $this->addFlash('warning', sprintf('%d article(s) sans parapharmacie ont ete ignores.', $articlesIgnores));
        }

        if (empty($articlesParPharmacie)) {
            $this->addFlash('error', 'Impossible de creer la commande: aucune parapharmacie valide trouvee.');
            return $this->redirectToRoute('app_panier');
        }

        $commandesCrees = [];
        $notificationsParPharmacie = [];
        $totalGeneral = 0.0;
        $connection = $entityManager->getConnection();

        try {
            $connection->beginTransaction();

            foreach ($articlesParPharmacie as $pharmacieId => $data) {
                $parapharmacie = $entityManager->getRepository(Parapharmacie::class)->find($pharmacieId);
                if (!$parapharmacie) {
                    continue;
                }

                $commande = new Commande();
                $commande->setParapharmacie($parapharmacie);
                $commande->setNomClient($nomClient);
                $commande->setEmail($email);
                $commande->setTelephone($telephone);
                $commande->setAdresseLivraison($adresseLivraison);
                $commande->setNotes($notes);
                $commande->setStatut('en_attente');
                $commande->setDateCreation(new \DateTime());
                $commande->setPrixTotal((string) $data['total']);

                if (!empty($data['articles'])) {
                    $premierArticle = $data['articles'][0];
                    $produit = $entityManager->getRepository(Produit::class)->find((int) $premierArticle['produit_id']);
                    if ($produit) {
                        $commande->setProduit($produit);
                        $commande->setQuantite(max(1, (int) $premierArticle['quantite']));
                        $commande->setPrixUnitaire((string) $premierArticle['prix']);
                    }
                }

                $entityManager->persist($commande);

                foreach ($data['articles'] as $article) {
                    $produit = $entityManager->getRepository(Produit::class)->find((int) ($article['produit_id'] ?? 0));
                    if (!$produit) {
                        continue;
                    }

                    $quantiteArticle = max(1, (int) ($article['quantite'] ?? 1));
                    $stockActuel = $produit->getStock();
                    if ($stockActuel !== null) {
                        if ($stockActuel < $quantiteArticle) {
                            throw new \RuntimeException(sprintf('Stock insuffisant pour "%s". Disponible: %d.', (string) $produit->getNom(), $stockActuel));
                        }
                        $produit->setStock($stockActuel - $quantiteArticle);
                    }

                    $ligneCommande = new LigneCommande();
                    $ligneCommande->setCommande($commande);
                    $ligneCommande->setProduit($produit);
                    $ligneCommande->setQuantite($quantiteArticle);
                    $ligneCommande->setPrixUnitaire((string) ($article['prix'] ?? 0));
                    $ligneCommande->calculerSousTotal();
                    $entityManager->persist($ligneCommande);
                }

                $commandesCrees[] = $commande;
                $totalGeneral += (float) $data['total'];
                $notificationsParPharmacie[] = [
                    'commande' => $commande,
                    'articles' => $data['articles'],
                ];
            }

            $entityManager->flush();
            $connection->commit();
        } catch (\RuntimeException $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_panier');
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->addFlash('error', 'Erreur lors de la validation de la commande. Veuillez reessayer.');
            return $this->redirectToRoute('app_panier');
        }

        if (empty($commandesCrees)) {
            $this->addFlash('error', 'Aucune commande n\'a pu etre creee. Veuillez reessayer.');
            return $this->redirectToRoute('app_panier');
        }

        foreach ($notificationsParPharmacie as $notification) {
            $this->notifierParapharmacie($notification['commande'], $notification['articles'], $mailer);
        }

        $session->remove('panier');
        $this->notifierClient($nomClient, $email, $commandesCrees, $totalGeneral, $mailer);

        $this->addFlash('success', 'Votre commande a ete enregistree avec succes ! Les parapharmacies concernees ont ete notifiees.');
        $this->addFlash('info', 'Commande #' . $commandesCrees[0]->getNumero() . ' creee avec succes !');

        return $this->redirectToRoute('app_panier');
    }

    /**
     * Notifier la parapharmacie par email
     */
    private function notifierParapharmacie(Commande $commande, array $articles, MailerInterface $mailer): void
    {
        $parapharmacie = $commande->getParapharmacie();
        
        // Envoyer un email de notification à la parapharmacie
        if ($parapharmacie->getEmail()) {
            try {
                $html = $this->renderView('emails/nouvelle_commande_pharmacie.html.twig', [
                    'commande' => $commande,
                    'articles' => $articles,
                    'parapharmacie' => $parapharmacie
                ]);
                
                $email = (new Email())
                    ->from('commandes@sahty.com')
                    ->to($parapharmacie->getEmail())
                    ->subject('🔔 Nouvelle commande #' . $commande->getNumero())
                    ->html($html);
                
                $mailer->send($email);
            } catch (\Exception $e) {
                // Log l'erreur mais ne pas bloquer la commande
                error_log('Erreur envoi email pharmacie: ' . $e->getMessage());
            }
        }
    }

    /**
     * Notifier le client par email
     */
    private function notifierClient(string $nomClient, string $emailClient, array $commandes, float $totalGeneral, MailerInterface $mailer): void
    {
        try {
            $html = $this->renderView('emails/confirmation_commande_client.html.twig', [
                'nomClient' => $nomClient,
                'commandes' => $commandes,
                'totalGeneral' => $totalGeneral,
                'date' => new \DateTime()
            ]);
            
            $email = (new Email())
                ->from('commandes@sahty.com')
                ->to($emailClient)
                ->subject('✅ Confirmation de votre commande')
                ->html($html);
            
            $mailer->send($email);
        } catch (\Exception $e) {
            error_log('Erreur envoi email client: ' . $e->getMessage());
        }
    }
}

