<?php
namespace App\Service;

use App\Entity\Evenement;
use Doctrine\ORM\EntityManagerInterface;

class EvenementPlanningAIService
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Suggest a date for the event based on previous events (avoid conflicts, prefer weekends, etc.)
     */
    public function suggestDate(): ?\DateTime
    {
        // Example: suggest next available Saturday
        $date = new \DateTime();
        $weekday = $date->format('N');
        if ($weekday < 6) {
            $date->modify('next Saturday');
        }
        // Check for conflicts (simplified)
        $qb = $this->em->createQueryBuilder();
        $qb->select('e')
            ->from(Evenement::class, 'e')
            ->where('e.dateDebut BETWEEN :start AND :end')
            ->setParameter('start', $date->format('Y-m-d 00:00:00'))
            ->setParameter('end', $date->format('Y-m-d 23:59:59'));
        $conflict = $qb->getQuery()->getOneOrNullResult();
        if ($conflict) {
            $date->modify('+7 days');
        }
        return $date;
    }

    /**
     * Suggest a location based on most used locations for similar events
     */
    public function suggestLieu(): ?string
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('e.lieu, COUNT(e.id) as nb')
            ->from(Evenement::class, 'e')
            ->groupBy('e.lieu')
            ->orderBy('nb', 'DESC')
            ->setMaxResults(1);
        $result = $qb->getQuery()->getOneOrNullResult();
        return $result ? $result['lieu'] : null;
    }

    /**
     * Estimate number of participants based on past events
     */
    public function estimateParticipants(): ?int
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('AVG(e.placesMax) as avgPlaces')
            ->from(Evenement::class, 'e')
            ->where('e.placesMax IS NOT NULL');
        $result = $qb->getQuery()->getSingleScalarResult();
        return $result ? (int) round($result) : null;
    }
}
