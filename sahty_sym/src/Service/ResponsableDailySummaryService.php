<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\Parapharmacie;

class ResponsableDailySummaryService
{
    /**
     * @param Commande[] $commandesRecentes
     * @param Commande[] $commandesEnAttente
     * @param array<string, mixed> $statsVentes
     * @return array<string, mixed>
     */
    public function generate(
        Parapharmacie $parapharmacie,
        array $commandesRecentes,
        array $commandesEnAttente,
        int $totalProduits,
        array $statsVentes = []
    ): array {
        $now = new \DateTimeImmutable();
        $todayStart = $now->setTime(0, 0, 0);

        $urgentOrders = 0;
        $todayOrders = 0;
        $todayRevenue = 0.0;

        foreach ($commandesRecentes as $commande) {
            $createdAt = $commande->getDateCreation();
            if ($createdAt instanceof \DateTimeInterface) {
                if ($createdAt < $now->modify('-48 hours') && $commande->getStatut() === 'en_attente') {
                    $urgentOrders++;
                }

                if ($createdAt >= $todayStart) {
                    $todayOrders++;
                    $todayRevenue += (float) ($commande->getPrixTotal() ?? 0);
                }
            }
        }

        $waitingOrders = count($commandesEnAttente);
        $totalOrders = (int) ($statsVentes['total_commandes'] ?? 0);
        $globalRevenue = (float) ($statsVentes['total_chiffre_affaires'] ?? 0);
        $avgBasket = $totalOrders > 0 ? $globalRevenue / $totalOrders : 0.0;

        $priorite = 'Faible';
        if ($urgentOrders > 0 || $waitingOrders >= 8) {
            $priorite = 'Elevee';
        } elseif ($waitingOrders >= 4) {
            $priorite = 'Moyenne';
        }

        $insights = [
            sprintf('%d commande(s) en attente actuellement.', $waitingOrders),
            sprintf('%d commande(s) creee(s) aujourd\'hui.', $todayOrders),
            sprintf('Panier moyen estime: %.2f EUR.', $avgBasket),
            sprintf('Catalogue actif: %d produit(s).', $totalProduits),
        ];

        if ($urgentOrders > 0) {
            $insights[] = sprintf('%d commande(s) en attente depuis plus de 48h.', $urgentOrders);
        }

        $actions = [];
        if ($waitingOrders > 0) {
            $actions[] = 'Traiter d abord les commandes en attente les plus anciennes.';
        }
        if ($urgentOrders > 0) {
            $actions[] = 'Contacter les clients des commandes > 48h pour confirmer delai et disponibilite.';
        }
        if ($todayOrders >= 5) {
            $actions[] = 'Verifier le stock des produits les plus commandes aujourd hui.';
        }
        if ($totalProduits < 15) {
            $actions[] = 'Enrichir le catalogue pour augmenter la conversion des recherches.';
        }
        if (empty($actions)) {
            $actions[] = 'Aucune alerte critique aujourd hui. Maintenir le rythme de traitement.';
        }

        return [
            'title' => 'Resume IA du jour',
            'subtitle' => sprintf('%s - %s', $parapharmacie->getNom(), $now->format('d/m/Y H:i')),
            'priorite' => $priorite,
            'insights' => $insights,
            'actions' => array_slice($actions, 0, 4),
            'kpis' => [
                'commandes_en_attente' => $waitingOrders,
                'commandes_urgentes' => $urgentOrders,
                'commandes_jour' => $todayOrders,
                'ca_jour' => round($todayRevenue, 2),
            ],
        ];
    }
}

