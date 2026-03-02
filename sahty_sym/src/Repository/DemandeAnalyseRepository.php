<?php

namespace App\Repository;

use App\Entity\DemandeAnalyse;
use App\Entity\Patient;
use Doctrine\DBAL\ParameterType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DemandeAnalyse>
 */
class DemandeAnalyseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeAnalyse::class);
    }

    /**
     * @return array<int, array{
     *     id:int,
     *     programmeLe:string|null,
     *     typeBilan:string,
     *     priorite:string,
     *     resultatPdf:?string,
     *     laboratoireNom:?string,
     *     medecinNom:?string,
     *     medecinPrenom:?string,
     *     medecinSpecialite:?string
     * }>
     */
    public function findMesDemandesForPatient(Patient $patient, ?string $typeBilan = null, int $limit = 100): array
    {
        $maxRows = max(1, min($limit, 100));
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT
                d.id AS id,
                d.programme_le AS programmeLe,
                d.type_bilan AS typeBilan,
                d.priorite AS priorite,
                d.resultat_pdf AS resultatPdf,
                l.nom AS laboratoireNom,
                um.nom AS medecinNom,
                um.prenom AS medecinPrenom,
                m.specialite AS medecinSpecialite
            FROM demande_analyse d
            LEFT JOIN laboratoire l ON l.id = d.laboratoire_id
            LEFT JOIN medecin m ON m.id = d.medecin_id
            LEFT JOIN utilisateur um ON um.id = m.id
            WHERE d.patient_id = :patientId
        SQL;

        $params = [
            'patientId' => $patient->getId(),
            'limitRows' => $maxRows,
        ];
        $types = [
            'patientId' => ParameterType::INTEGER,
            'limitRows' => ParameterType::INTEGER,
        ];

        if ($typeBilan !== null && $typeBilan !== '') {
            $sql .= ' AND d.type_bilan = :typeBilan';
            $params['typeBilan'] = $typeBilan;
        }

        $sql .= ' ORDER BY d.programme_le DESC, d.id DESC LIMIT :limitRows';

        /** @var array<int, array{
         *     id:int,
         *     programmeLe:string|null,
         *     typeBilan:string,
         *     priorite:string,
         *     resultatPdf:?string,
         *     laboratoireNom:?string,
         *     medecinNom:?string,
         *     medecinPrenom:?string,
         *     medecinSpecialite:?string
         * }> $rows
         */
        $rows = $conn->executeQuery($sql, $params, $types)->fetchAllAssociative();

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function findTypeBilanOptionsForPatient(Patient $patient): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('DISTINCT d.type_bilan AS typeBilan')
            ->where('d.patient = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('d.type_bilan', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $options = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row['typeBilan'] ?? ''));
            if ($value !== '') {
                $options[] = $value;
            }
        }

        return $options;
    }

    //    /**
    //     * @return DemandeAnalyse[] Returns an array of DemandeAnalyse objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('d.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?DemandeAnalyse
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
