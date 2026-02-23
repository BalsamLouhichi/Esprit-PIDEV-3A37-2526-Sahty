<?php

namespace App\Twig;

use App\Entity\ResponsableParapharmacie;
use App\Repository\CommandeRepository;
use App\Repository\ProduitRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ResponsableNotificationsExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly CommandeRepository $commandeRepository,
        private readonly ProduitRepository $produitRepository,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('responsable_notifications', [$this, 'getNotifications']),
        ];
    }

    public function getNotifications(int $maxResults = 8): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof ResponsableParapharmacie || !$user->getParapharmacie()) {
            return [
                'unreadCount' => 0,
                'items' => [],
            ];
        }

        $parapharmacieId = $user->getParapharmacie()->getId();
        if (!$parapharmacieId) {
            return [
                'unreadCount' => 0,
                'items' => [],
            ];
        }

        $recentOrders = $this->commandeRepository->findRecentByParapharmacie($parapharmacieId, $maxResults);
        $pendingOrders = $this->commandeRepository->findByParapharmacieAndStatut($parapharmacieId, 'en_attente');
        $pendingPaymentOrders = $this->commandeRepository->findByParapharmacieAndStatut($parapharmacieId, 'en_attente_paiement');
        $lowStockProducts = $this->produitRepository->findLowStockByParapharmacie((int) $parapharmacieId, 5);

        $items = [];
        foreach ($lowStockProducts as $produit) {
            $stock = max(0, (int) ($produit->getStock() ?? 0));
            $suggestedQty = max(1, 15 - $stock);

            $items[] = [
                'title' => sprintf(
                    'Alerte stock: %s (%d restant) - reappro conseille: +%d',
                    (string) $produit->getNom(),
                    $stock,
                    $suggestedQty
                ),
                'date' => new \DateTimeImmutable(),
                'url' => $this->urlGenerator->generate('app_responsable_produits'),
            ];
        }

        $newOrderIds = [];
        foreach (array_merge($pendingOrders, $pendingPaymentOrders) as $commande) {
            $commandeId = (int) ($commande->getId() ?? 0);
            if ($commandeId <= 0 || isset($newOrderIds[$commandeId])) {
                continue;
            }
            $newOrderIds[$commandeId] = true;

            $items[] = [
                'title' => sprintf('Nouvelle commande %s - %s (%s)', $commande->getNumero(), $commande->getNomClient(), $commande->getStatutLibelle()),
                'date' => $commande->getDateCreation(),
                'url' => $this->urlGenerator->generate('app_responsable_commande_details', ['id' => $commandeId]),
            ];
        }

        foreach ($recentOrders as $commande) {
            $commandeId = (int) ($commande->getId() ?? 0);
            if ($commandeId > 0 && isset($newOrderIds[$commandeId])) {
                continue;
            }

            $items[] = [
                'title' => sprintf('Commande %s - %s (%s)', $commande->getNumero(), $commande->getNomClient(), $commande->getStatutLibelle()),
                'date' => $commande->getDateCreation(),
                'url' => $this->urlGenerator->generate('app_responsable_commande_details', ['id' => $commandeId]),
            ];
        }

        if ($maxResults > 0 && count($items) > $maxResults) {
            $items = array_slice($items, 0, $maxResults);
        }

        return [
            'unreadCount' => count($newOrderIds) + count($lowStockProducts),
            'items' => $items,
        ];
    }
}
