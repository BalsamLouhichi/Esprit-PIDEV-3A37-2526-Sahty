<?php

namespace App\DataFixtures;

use App\Entity\TypeAnalyse;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TypeAnalyseFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $dataset = [
            "Zoi Life" => [
                ["Bilan du stress oxydant", "Évaluation du stress oxydatif."],
                ["Bilan cardiovasculaire", "Marqueurs cardio + lipides."],
                ["Bilan nutritionnel", "Carences, vitamines, minéraux."],
                ["Bilan physiologique et psychologique", "Biologie + anamnèse."],
                ["Bilan hématologique et immunitaire", "NFS, immunité, etc."],
                ["Bilan vitaminique", "Vitamine D, B12, folates..."],
            ],
            "Zoi Pulse" => [
                ["Bilan inflammatoire et électrophorèse", "Inflammation + protéines."],
                ["Bilan viral et bactérien", "Dépistages infectieux."],
                ["Bilan endocrinien", "Hormones, thyroïde..."],
                ["Bilan hépatique", "ALAT/ASAT, GGT, bilirubine..."],
                ["Bilan rénal et urologique", "Créatinine, urée..."],
                ["Bilan de l'équilibre hydrominéral", "Na/K/Cl, etc."],
            ],
            "Métabolique" => [
                ["Glycémie à jeun", "Suivi glycémie."],
                ["HbA1c", "Moyenne glycémie 3 mois."],
                ["Insulinémie", "Dosage insuline."],
            ],
            "Hématologie" => [
                ["NFS", "Hémoglobine, GB, plaquettes."],
                ["Ferritine", "Réserves en fer."],
                ["CRP", "Inflammation."],
            ],
        ];

        $repo = $manager->getRepository(TypeAnalyse::class);

        foreach ($dataset as $categorie => $items) {
            foreach ($items as [$nom, $description]) {

                // ✅ évite doublons si tu relances fixtures
                $existing = $repo->findOneBy(['nom' => $nom, 'categorie' => $categorie]);
                if ($existing) {
                    continue;
                }

                $type = new TypeAnalyse();
                $type->setNom($nom);
                $type->setCategorie($categorie);

                if (method_exists($type, 'setDescription')) {
                    $type->setDescription($description);
                }
                if (method_exists($type, 'setActif')) {
                    $type->setActif(true);
                }
                if (method_exists($type, 'setCreeLe')) {
                    $type->setCreeLe(new \DateTime());
                }

                $manager->persist($type);
            }
        }

        $manager->flush();
    }
}
