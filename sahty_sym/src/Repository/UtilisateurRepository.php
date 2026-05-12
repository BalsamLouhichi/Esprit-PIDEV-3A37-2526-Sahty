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
    /**
     * Valid Doctrine discriminator values for the joined inheritance tree.
     *
     * Keeping this list centralized lets admin listings ignore corrupted rows
     * that have an empty/null discriminator in the base table.
     */
    private const VALID_DISCRIMINATORS = [
        'admin',
        'medecin',
        'patient',
        'responsable_labo',
        'responsable_para',
    ];

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
     * Find user by email (case-insensitive)
     * This is used by Symfony's user provider for authentication
     */
    public function findOneByEmail(string $email): ?Utilisateur
    {
        return $this->createQueryBuilder('u')
            ->where('LOWER(u.email) = LOWER(:email)')
            ->setParameter('email', trim($email))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns only rows that have a valid inheritance discriminator.
     *
     * This protects admin screens from hydration errors when the database
     * contains legacy/corrupted rows in `utilisateur` with an empty `discr`.
     *
     * @return int[]
     */
    private function findValidUserIds(?string $query = null, ?string $role = null): array
    {
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb
            ->select('u.id')
            ->from('utilisateur', 'u')
            ->where('u.discr IS NOT NULL')
            ->andWhere("TRIM(u.discr) <> ''")
            ->andWhere('u.discr IN (:discriminators)')
            ->setParameter('discriminators', self::VALID_DISCRIMINATORS, \Doctrine\DBAL\ArrayParameterType::STRING)
            ->orderBy('u.cree_le', 'DESC');

        if ($query !== null && $query !== '') {
            $qb
                ->andWhere('(LOWER(u.nom) LIKE :query OR LOWER(u.prenom) LIKE :query OR LOWER(u.email) LIKE :query)')
                ->setParameter('query', '%' . mb_strtolower(trim($query)) . '%');
        }

        if ($role !== null && $role !== '') {
            $qb
                ->andWhere('u.role = :role')
                ->setParameter('role', $role);
        }

        return array_map('intval', $qb->executeQuery()->fetchFirstColumn());
    }

    /**
     * @return Utilisateur[]
     */
    public function findAllSafe(): array
    {
        return $this->search();
    }

    /**
     * Recherche avancée d'utilisateurs avec filtres
     */
   /**
     * Recherche avancée d'utilisateurs avec filtres
     */
    public function search(?string $query = null, ?string $role = null): array
    {
        $ids = $this->findValidUserIds($query, $role);

        if ($ids === []) {
            return [];
        }

        return $this->createQueryBuilder('u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('u.creeLe', 'DESC')
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

    //    /**
    //     * @return Utilisateur[] Returns an array of Utilisateur objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Utilisateur
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Recherche avancée d'utilisateurs avec filtres
     */
    

    /**
     * Compte les utilisateurs par rôle
     */
    
}
