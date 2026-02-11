<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Utilisateur) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Recherche avancée d'utilisateurs avec filtres
     */
    public function search(?string $query = null, ?string $role = null): array
    {
        $qb = $this->createQueryBuilder('u');
        
        if ($query) {
            $qb->where('LOWER(u.nom) LIKE LOWER(:query) 
                        OR LOWER(u.prenom) LIKE LOWER(:query) 
                        OR LOWER(u.email) LIKE LOWER(:query)')
               ->setParameter('query', '%' . $query . '%');
        }
        
        if ($role) {
            $qb->andWhere('u.role = :role')
               ->setParameter('role', $role);
        }
        
        return $qb->orderBy('u.creeLe', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Compte les utilisateurs par rôle
     */
    public function countByRole(string $role): int
    {
        return $this->count(['role' => $role]);
    }

    /**
     * Compte les utilisateurs actifs/inactifs
     */
    public function countByStatus(bool $estActif): int
    {
        return $this->count(['estActif' => $estActif]);
    }
}