<?php

namespace App\Twig;

use App\Entity\ResponsableParapharmacie;
use App\Repository\CommandeRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ResponsableNotificationsExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly CommandeRepository $commandeRepository,
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

        $items = [];
        foreach ($recentOrders as $commande) {
            $items[] = [
                'title' => sprintf('Commande %s - %s (%s)', $commande->getNumero(), $commande->getNomClient(), $commande->getStatutLibelle()),
                'date' => $commande->getDateCreation(),
                'url' => $this->urlGenerator->generate('app_responsable_commande_details', ['id' => $commande->getId()]),
            ];
        }

        return [
            'unreadCount' => count($pendingOrders),
            'items' => $items,
        ];
    }
}
