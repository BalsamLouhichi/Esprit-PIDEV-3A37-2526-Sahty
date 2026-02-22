<?php
// src/DataFixtures/TypeAnalyseFixtures.php

namespace App\DataFixtures;

use App\Entity\TypeAnalyse;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class TypeAnalyseFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $dataset = [
            "Zoi Life" => [
                ["Bilan du stress oxydant", "Évaluation du stress oxydatif - radicaux libres, antioxydants."],
                ["Bilan cardiovasculaire", "Marqueurs cardiaques, profil lipidique complet (cholestérol, triglycérides)."],
                ["Bilan nutritionnel", "Analyse des carences, vitamines, minéraux et oligo-éléments."],
                ["Bilan physiologique et psychologique", "Biologie médicale associée à une anamnèse psychologique."],
                ["Bilan hématologique et immunitaire", "Numération formule sanguine, exploration immunitaire."],
                ["Bilan vitaminique", "Dosage des vitamines (D, B12, folates, B6...)."],
            ],
            "Zoi Pulse" => [
                ["Bilan inflammatoire et électrophorèse", "Marqueurs inflammatoires (CRP, VS) + électrophorèse des protéines."],
                ["Bilan viral et bactérien", "Sérologies, PCR, dépistages infectieux (EBV, CMV, VIH, hépatites)."],
                ["Bilan endocrinien", "Exploration hormonale (thyroïde, surrénales, hormones sexuelles)."],
                ["Bilan hépatique", "Transaminases (ALAT/ASAT), Gamma-GT, phosphatases alcalines, bilirubine."],
                ["Bilan rénal et urologique", "Créatinine, urée, acide urique, clairance."],
                ["Bilan de l'équilibre hydrominéral", "Ionogramme sanguin (Na, K, Cl, Ca, Mg, P)."],
            ],
            "Métabolique" => [
                ["Glycémie à jeun", "Mesure du glucose sanguin après 8h de jeûne."],
                ["HbA1c", "Hémoglobine glyquée - moyenne de la glycémie sur 3 mois."],
                ["Insulinémie", "Dosage de l'insuline à jeun - évaluation de l'insulinorésistance."],
            ],
            "Hématologie" => [
                ["NFS", "Hémoglobine, hématies, leucocytes, plaquettes, VGM, CCMH."],
                ["Ferritine", "Dosage des réserves en fer."],
                ["CRP", "Protéine C réactive - marqueur de l'inflammation."],
                ["Bilan martial complet", "Fer sérique, transferrine, coefficient de saturation."],
            ],
            "Biochimie générale" => [
                ["Ionogramme sanguin", "Sodium, Potassium, Chlore, Calcium, Magnésium."],
                ["Bilan lipidique", "Cholestérol total, HDL, LDL, triglycérides."],
                ["Bilan pancréatique", "Lipase, amylase - exploration pancréatique."],
            ],
        ];

        foreach ($dataset as $categorie => $items) {
            foreach ($items as [$nom, $description]) {
                $type = new TypeAnalyse();
                $type->setNom($nom);
                $type->setCategorie($categorie);
                $type->setDescription($description);
                $type->setActif(true);
                $type->setCreeLe(new \DateTime());
                
                $manager->persist($type);
            }
        }

        $manager->flush();
        
        echo "✅ Types d'analyse ajoutés avec succès !\n";
    }
    
    public static function getGroups(): array
    {
        return ['TypeAnalyseFixtures', 'type_analyse'];
    }
}