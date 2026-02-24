<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Entity\Parapharmacie;
use App\Repository\ParapharmacieRepository;
use App\Repository\ProduitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ParapharmacieController extends AbstractController
{
    #[Route('/parapharmacies', name: 'app_parapharmacie_list')]
    public function list(ParapharmacieRepository $parapharmacieRepository): Response
    {
        $parapharmacies = $parapharmacieRepository->findAll();

        return $this->render('parapharmacie/index.html.twig', [
            'parapharmacies' => $parapharmacies,
        ]);
    }

    #[Route('/parapharmacies-produits', name: 'app_parapharmacie_produits')]
    public function listAll(ParapharmacieRepository $parapharmacieRepository, ProduitRepository $produitRepository): Response
    {
        $parapharmacies = $parapharmacieRepository->findAll();
        $produits = $produitRepository->findAll();

        // Afficher chaque produit une seule fois (meme nom => un seul item)
        $seenNames = [];
        $uniqueProduits = [];
        foreach ($produits as $produit) {
            if (!$produit instanceof Produit) {
                continue;
            }
            $name = mb_strtolower(trim((string) $produit->getNom()), 'UTF-8');
            if ($name === '' || isset($seenNames[$name])) {
                continue;
            }
            $seenNames[$name] = true;
            $uniqueProduits[] = $produit;
        }

        return $this->render('parapharmacie/list_all.html.twig', [
            'parapharmacies' => $parapharmacies,
            'produits' => $uniqueProduits,
        ]);
    }

    #[Route('/parapharmacie/{id}', name: 'app_parapharmacie_details', requirements: ['id' => '\d+'])]
    public function detailsParapharmacie(Parapharmacie $parapharmacie): Response
    {
        return $this->render('parapharmacie/details.html.twig', [
            'parapharmacie' => $parapharmacie,
        ]);
    }

    #[Route('/produit/{id}', name: 'app_produit_details')]
    public function details(
        Produit $produit,
        ParapharmacieRepository $parapharmacieRepository,
        ProduitRepository $produitRepository
    ): Response {
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
        $toutesParapharmacies = $parapharmacieRepository->findAll();

        return $this->render('produit/search_results.html.twig', [
            'produit' => $produit,
            'pharmaciesAvecProduit' => $pharmaciesAvecProduit,
            'pharmacieOffers' => $pharmacieOffers,
            'toutesParapharmacies' => $toutesParapharmacies,
        ]);
    }
}


