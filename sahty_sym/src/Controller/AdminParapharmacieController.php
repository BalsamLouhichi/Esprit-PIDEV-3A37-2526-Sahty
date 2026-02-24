<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Parapharmacie;
use App\Entity\Produit;
use App\Repository\CommandeRepository;
use App\Repository\ParapharmacieRepository;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin', name: 'admin_')]
class AdminParapharmacieController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/parapharmacies', name: 'parapharmacies', methods: ['GET'])]
    public function index(ParapharmacieRepository $parapharmacieRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $parapharmacies = $parapharmacieRepo->findAll();

        $produitsCounts = $this->getProduitsCountByParapharmacie();
        $commandesStats = $this->getCommandesStatsByParapharmacie();

        return $this->render('admin/parapharmacies/index.html.twig', [
            'parapharmacies' => $parapharmacies,
            'produits_counts' => $produitsCounts,
            'commandes_stats' => $commandesStats,
        ]);
    }

    #[Route('/parapharmacies/new', name: 'parapharmacie_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $parapharmacie = new Parapharmacie();
        $form = $this->createFormBuilder($parapharmacie)
            ->add('nom')
            ->add('adresse')
            ->add('telephone')
            ->add('email')
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($parapharmacie);
            $this->em->flush();

            $this->addFlash('success', 'Parapharmacie ajoutee avec succes.');
            return $this->redirectToRoute('admin_parapharmacies');
        }

        return $this->render('admin/parapharmacies/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/parapharmacies/produits', name: 'produits_parapharmacie', methods: ['GET'])]
    public function produits(ProduitRepository $produitRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $rows = $this->em->createQueryBuilder()
            ->select('p as produit, COUNT(pa.id) as total')
            ->from(Produit::class, 'p')
            ->leftJoin('p.parapharmacies', 'pa')
            ->groupBy('p.id')
            ->getQuery()
            ->getResult();

        return $this->render('admin/parapharmacies/produits.html.twig', [
            'rows' => $rows,
        ]);
    }

    #[Route('/parapharmacies/commandes', name: 'commandes_parapharmacie', methods: ['GET'])]
    public function commandes(CommandeRepository $commandeRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $commandes = $commandeRepo->findBy([], ['dateCreation' => 'DESC']);

        return $this->render('admin/parapharmacies/commandes.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    #[Route('/parapharmacies/stats', name: 'parapharmacies_stats', methods: ['GET'])]
    public function stats(
        ParapharmacieRepository $parapharmacieRepo,
        ProduitRepository $produitRepo,
        CommandeRepository $commandeRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $totalParapharmacies = $parapharmacieRepo->count([]);
        $totalProduits = $produitRepo->count([]);
        $totalCommandes = $commandeRepo->count([]);
        $commandeStats = $commandeRepo->getStats();

        $statusRows = $this->em->createQueryBuilder()
            ->select('c.statut as statut, COUNT(c.id) as total')
            ->from(Commande::class, 'c')
            ->groupBy('c.statut')
            ->getQuery()
            ->getResult();

        $topRows = $this->em->createQueryBuilder()
            ->select('p.id as id, p.nom as nom, COUNT(c.id) as commandes, COALESCE(SUM(c.prixTotal), 0) as revenu')
            ->from(Parapharmacie::class, 'p')
            ->leftJoin(Commande::class, 'c', 'WITH', 'c.parapharmacie = p')
            ->groupBy('p.id')
            ->getQuery()
            ->getResult();

        $topByCommandes = $topRows;
        usort($topByCommandes, static fn (array $a, array $b): int => (int) $b['commandes'] <=> (int) $a['commandes']);
        $topByCommandes = array_slice($topByCommandes, 0, 5);

        $topByRevenu = $topRows;
        usort($topByRevenu, static fn (array $a, array $b): int => (float) $b['revenu'] <=> (float) $a['revenu']);
        $topByRevenu = array_slice($topByRevenu, 0, 5);

        $statusLabels = array_map(static fn (array $row): string => (string) $row['statut'], $statusRows);
        $statusCounts = array_map(static fn (array $row): int => (int) $row['total'], $statusRows);
        $topCommandesLabels = array_map(static fn (array $row): string => (string) ($row['nom'] ?? ''), $topByCommandes);
        $topCommandesCounts = array_map(static fn (array $row): int => (int) $row['commandes'], $topByCommandes);
        $topRevenuLabels = array_map(static fn (array $row): string => (string) ($row['nom'] ?? ''), $topByRevenu);
        $topRevenuValues = array_map(static fn (array $row): float => (float) $row['revenu'], $topByRevenu);

        return $this->render('admin/parapharmacies/stats.html.twig', [
            'total_parapharmacies' => $totalParapharmacies,
            'total_produits' => $totalProduits,
            'total_commandes' => $totalCommandes,
            'commande_stats' => $commandeStats,
            'status_rows' => $statusRows,
            'top_commandes' => $topByCommandes,
            'top_revenu' => $topByRevenu,
            'status_labels' => $statusLabels,
            'status_counts' => $statusCounts,
            'top_commandes_labels' => $topCommandesLabels,
            'top_commandes_counts' => $topCommandesCounts,
            'top_revenu_labels' => $topRevenuLabels,
            'top_revenu_values' => $topRevenuValues,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function getProduitsCountByParapharmacie(): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('p.id as id, COUNT(prod.id) as total')
            ->from(Parapharmacie::class, 'p')
            ->leftJoin('p.produits', 'prod')
            ->groupBy('p.id')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = (int) $row['total'];
        }
        return $map;
    }

    /**
     * @return array<int, array{total:int, revenu:float}>
     */
    private function getCommandesStatsByParapharmacie(): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(c.parapharmacie) as id, COUNT(c.id) as total, COALESCE(SUM(c.prixTotal), 0) as revenu')
            ->from(Commande::class, 'c')
            ->groupBy('c.parapharmacie')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = [
                'total' => (int) $row['total'],
                'revenu' => (float) $row['revenu'],
            ];
        }
        return $map;
    }
}
