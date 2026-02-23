<?php

namespace App\Repository;

use App\Entity\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }


     public function ajouterEvenement(Evenement $evenement): void
    {
        $this->getEntityManager()->persist($evenement);
        $this->getEntityManager()->flush();
    }

    
    public function modifierEvenement(Evenement $evenement): void
    {
        $evenement->setModifieLe(new \DateTime());
        $this->getEntityManager()->flush();
    }

    
    public function supprimerEvenement(Evenement $evenement): void
    {
        $this->getEntityManager()->remove($evenement);
        $this->getEntityManager()->flush();
    }

    
    public function getEvenements(): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    
    public function getEvenementById(int $id): ?Evenement
    {
        return $this->find($id);
    }

    
    public function getEvenementByStatut(string $statut): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('e.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    
    public function getEvenementByType(string $type): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.type = :type')
            ->setParameter('type', $type)
            ->orderBy('e.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    
    public function rechercherEvenement(string $terme): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.titre LIKE :terme OR e.description LIKE :terme')
            ->setParameter('terme', '%' . $terme . '%')
            ->orderBy('e.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    
    public function getParticipants(Evenement $evenement): array
    {
        return $this->createQueryBuilder('e')
            ->select('u')
            ->leftJoin('e.inscriptions', 'i')
            ->leftJoin('i.utilisateur', 'u')
            ->andWhere('e.id = :id')
            ->setParameter('id', $evenement->getId())
            ->getQuery()
            ->getResult();
    }
    
    public function getEvenementsTries(string $tri = 'dateDebut', string $ordre = 'ASC', ?string $statut = null): array
{
    $qb = $this->createQueryBuilder('e');

    if ($statut) {
        $qb->andWhere('e.statut = :statut')
           ->setParameter('statut', $statut);
    }

    $orderField = $tri === 'dateFin' ? 'e.dateFin' : 'e.dateDebut';
    $qb->orderBy($orderField, $ordre);

    return $qb->getQuery()->getResult();
}


    
    public function getEvenementsParStatut(string $statut, string $tri = 'dateDebut', string $ordre = 'ASC'): array
    {
        return $this->getEvenementsTries($tri, $ordre, $statut);
    }

   
    public function getNombreParticipants(Evenement $evenement): int
    {
        return $this->createQueryBuilder('e')
            ->select('COUNT(i.id)')
            ->leftJoin('e.inscriptions', 'i')
            ->andWhere('e.id = :id')
            ->setParameter('id', $evenement->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    
    public function getTauxRemplissage(Evenement $evenement): float
    {
        if ($evenement->getPlacesMax() === null || $evenement->getPlacesMax() <= 0) {
            return 0;
        }
        
        $nbParticipants = $this->getNombreParticipants($evenement);
        return ($nbParticipants / $evenement->getPlacesMax()) * 100;
    }

   
    public function getEvenementsAvecStats(?string $statut = null, string $tri = 'dateDebut', string $ordre = 'ASC'): array
    {
        $evenements = $this->getEvenementsTries($tri, $ordre, $statut);
        
        
        foreach ($evenements as $evenement) {
            $evenement->nombreParticipants = $this->getNombreParticipants($evenement);
            $evenement->tauxRemplissage = $this->getTauxRemplissage($evenement);
        }
        
        return $evenements;
    }
  
    public function getStatutsDisponibles(): array
    {
        return $this->createQueryBuilder('e')
            ->select('DISTINCT e.statut')
            ->orderBy('e.statut', 'ASC')
            ->getQuery()
            ->getResult();
    }

   public function findByFilters(?string $type = null, ?string $statut = null, ?string $recherche = null): array
{
    $qb = $this->createQueryBuilder('e');

    if ($type) {
        $qb->andWhere('e.type = :type')
           ->setParameter('type', $type);
    }

    if ($statut) {
        $qb->andWhere('e.statut = :statut')
           ->setParameter('statut', $statut);
    }

    if ($recherche) {
        $qb->andWhere('e.titre LIKE :recherche OR e.description LIKE :recherche')
           ->setParameter('recherche', '%' . $recherche . '%');
    }

    return $qb->orderBy('e.dateDebut', 'ASC')
              ->getQuery()
              ->getResult();
}

public function findVisibleEventsForClient($user = null): array {
    $qb = $this->createQueryBuilder('e')
        ->orderBy('e.dateDebut', 'ASC');

    // 1. CRITICAL: Strictly filter ONLY approved statuses
    $qb->andWhere('e.statut IN (:statutsApprouves)')
       ->setParameter('statutsApprouves', ['planifie', 'confirme', 'en_cours']);

    // 2. Filter by Groups (Target Audience)
    if ($user) {
        // SAFELY check if user has getGroupes method
        $userGroups = [];
        if (method_exists($user, 'getGroupes')) {
            $userGroups = $user->getGroupes();
        }
        
        // If userGroups is empty (either no method or empty collection), 
        // we need to handle it differently
        if (empty($userGroups)) {
            // User has no groups, only show public events or their own events
            $qb->leftJoin('e.groupeCibles', 'g')
               ->andWhere(
                   $qb->expr()->orX(
                       'e.groupeCibles IS EMPTY',        // Public events
                       'e.createur = :userId'            // Their own events
                   )
               )
               ->setParameter('userId', $user->getId());
        } else {
            // User has groups, show public events, group events, and their own events
            $qb->leftJoin('e.groupeCibles', 'g')
               ->andWhere(
                   $qb->expr()->orX(
                       'e.groupeCibles IS EMPTY',        // Public events
                       'g IN (:userGroups)',             // Events for their groups
                       'e.createur = :userId'            // Their own events
                   )
               )
               ->setParameter('userGroups', $userGroups)
               ->setParameter('userId', $user->getId());
        }
    } else {
        // If not logged in, only show public events
        $qb->andWhere('e.groupeCibles IS EMPTY');
    }

    return $qb->getQuery()->getResult();
}

public function findAllPendingEvents(): array
{
    return $this->createQueryBuilder('e')
        ->where('e.statut = :statut')
        ->setParameter('statut', 'en_attente_approbation')
        ->orderBy('e.creeLe', 'DESC')
        ->getQuery()
        ->getResult();
}

public function findByStatutDemande(string $statutDemande, ?string $type = null, ?string $recherche = null): array
{
    $qb = $this->createQueryBuilder('e')
        ->where('e.statutDemande = :statutDemande')
        ->setParameter('statutDemande', $statutDemande)
        ->orderBy('e.creeLe', 'DESC');

    if ($type) {
        $qb->andWhere('e.type = :type')
           ->setParameter('type', $type);
    }

    if ($recherche) {
        $qb->andWhere('e.titre LIKE :recherche OR e.description LIKE :recherche')
           ->setParameter('recherche', '%' . $recherche . '%');
    }

    return $qb->getQuery()->getResult();
}


public function findUserEvents(int $userId, \DateTime $start, \DateTime $end): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.inscriptions', 'i')
            ->join('i.utilisateur', 'u')
            ->where('u.id = :userId')
            ->andWhere('e.dateDebut BETWEEN :start AND :end')
            ->setParameter('userId', $userId)
            ->setParameter('start', $start->format('Y-m-d H:i:s'))
            ->setParameter('end', $end->format('Y-m-d H:i:s'))
            ->orderBy('e.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findEventsByType(string $type, \DateTime $start, \DateTime $end): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.type = :type')
            ->andWhere('e.dateDebut BETWEEN :start AND :end')
            ->setParameter('type', $type)
            ->setParameter('start', $start->format('Y-m-d H:i:s'))
            ->setParameter('end', $end->format('Y-m-d H:i:s'))
            ->getQuery()
            ->getResult();
    }

public function findRecommendedEvents(array $categories, array $keywords, \DateTime $start, \DateTime $end): array
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.dateDebut BETWEEN :start AND :end')
            ->setParameter('start', $start->format('Y-m-d H:i:s'))
            ->setParameter('end', $end->format('Y-m-d H:i:s'));
        
        if (!empty($categories)) {
            $qb->andWhere('e.type IN (:categories)')
               ->setParameter('categories', $categories);
        }
        
        if (!empty($keywords)) {
            $keywordConditions = [];
            foreach ($keywords as $i => $keyword) {
                $keywordConditions[] = 'e.titre LIKE :keyword' . $i . ' OR e.description LIKE :keyword' . $i;
                $qb->setParameter('keyword' . $i, '%' . $keyword . '%');
            }
            $qb->andWhere(implode(' OR ', $keywordConditions));
        }
        
        return $qb->orderBy('e.dateDebut', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    public function findSchedulingConflicts(Evenement $evenement, ?int $excludeEventId = null): array
    {
        if (!$evenement->getDateDebut() || !$evenement->getDateFin()) {
            return [];
        }

        $qb = $this->createQueryBuilder('e');
        $qb->andWhere('e.dateDebut < :newEnd')
            ->andWhere('e.dateFin > :newStart')
            ->setParameter('newStart', $evenement->getDateDebut())
            ->setParameter('newEnd', $evenement->getDateFin());

        if ($excludeEventId !== null) {
            $qb->andWhere('e.id != :excludeEventId')
                ->setParameter('excludeEventId', $excludeEventId);
        }

        $visibilityStatuses = [
            'planifie',
            'confirme',
            'en_cours',
            'en_attente_approbation',
        ];
        $qb->andWhere('e.statut IN (:statuses)')
            ->setParameter('statuses', $visibilityStatuses);

        $location = trim((string) $evenement->getLieu());
        $mode = $evenement->getMode();
        $isPhysicalMode = in_array($mode, ['presentiel', 'hybride'], true);

        if ($isPhysicalMode && $location !== '') {
            $qb->andWhere('LOWER(e.lieu) = :location')
                ->andWhere('e.mode IN (:physicalModes)');
            $qb->setParameter('location', mb_strtolower($location))
                ->setParameter('physicalModes', ['presentiel', 'hybride']);
        } else {
            return [];
        }

        $qb->orderBy('e.dateDebut', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
