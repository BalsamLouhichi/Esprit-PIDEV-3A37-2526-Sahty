<?php

namespace App\Tests\Entity;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use PHPUnit\Framework\TestCase;

class CommandeTest extends TestCase
{
    public function testCalculerPrixTotal(): void
    {
        $commande = new Commande();
        $commande->setPrixUnitaire('19.90');
        $commande->setQuantite(3);

        $commande->calculerPrixTotal();

        $this->assertSame('59.70', $commande->getPrixTotal());
    }

    public function testStatutHelpers(): void
    {
        $commande = new Commande();

        $commande->setStatut('en_attente');
        $this->assertSame('En attente', $commande->getStatutLibelle());
        $this->assertSame('warning', $commande->getStatutCouleur());

        $commande->setStatut('inconnu_custom');
        $this->assertSame('inconnu_custom', $commande->getStatutLibelle());
        $this->assertSame('secondary', $commande->getStatutCouleur());
    }

    public function testArticlesSummaryAndDetails(): void
    {
        $commande = new Commande();

        $produitA = new Produit();
        $produitA->setNom('Gel Nettoyant');

        $produitB = new Produit();
        $produitB->setNom('Creme Hydratante');

        $ligneA = new LigneCommande();
        $ligneA->setProduit($produitA);
        $ligneA->setPrixUnitaire('12.00');
        $ligneA->setQuantite(2); // sous_total auto = 24.00

        $ligneB = new LigneCommande();
        $ligneB->setProduit($produitB);
        $ligneB->setPrixUnitaire('8.50');
        $ligneB->setQuantite(1); // sous_total auto = 8.50

        $commande->addLignesCommande($ligneA);
        $commande->addLignesCommande($ligneB);

        $this->assertSame(3, $commande->getNombreTotalArticles());

        $details = $commande->getDetailsArticles();
        $this->assertCount(2, $details);
        $this->assertSame('Gel Nettoyant', $details[0]['produit']);
        $this->assertSame(2, $details[0]['quantite']);
        $this->assertSame('24.00', $details[0]['sous_total']);
        $this->assertSame('Creme Hydratante', $details[1]['produit']);
    }
}

