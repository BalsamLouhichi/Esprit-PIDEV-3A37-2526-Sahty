<?php

namespace App\Tests\Service;

use App\Entity\Evenement;
use App\Entity\InscriptionEvenement;
use App\Entity\Utilisateur;
use App\Service\InscriptionEvenementManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class InscriptionEvenementManagerTest extends TestCase
{
    private InscriptionEvenementManager $manager;

    protected function setUp(): void
    {
        $this->manager = new InscriptionEvenementManager();
    }

    public function testValidateThrowsExceptionWhenEvenementOrUtilisateurMissing(): void
    {
        $inscription = new InscriptionEvenement();
        $inscription->setStatut('en_attente');
        $inscription->setDateInscription(new \DateTimeImmutable('-1 day'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'evenement et l\'utilisateur sont obligatoires.');

        $this->manager->validate($inscription);
    }

    public function testValidateThrowsExceptionWhenDateInscriptionIsFuture(): void
    {
        $inscription = new InscriptionEvenement();
        $inscription->setEvenement(new Evenement());
        $inscription->setUtilisateur(new Utilisateur());
        $inscription->setStatut('en_attente');
        $inscription->setDateInscription(new \DateTimeImmutable('+1 day'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La date d\'inscription ne peut pas etre dans le futur.');

        $this->manager->validate($inscription);
    }

    public function testValidateThrowsExceptionWhenPresentButNotConfirmee(): void
    {
        $inscription = new InscriptionEvenement();
        $inscription->setEvenement(new Evenement());
        $inscription->setUtilisateur(new Utilisateur());
        $inscription->setDateInscription(new \DateTimeImmutable('-1 day'));
        $inscription->setStatut('en_attente');
        $inscription->setPresent(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Une inscription marquee presente doit etre confirmee.');

        $this->manager->validate($inscription);
    }

    public function testValidateReturnsTrueWhenInscriptionIsValid(): void
    {
        $inscription = new InscriptionEvenement();
        $inscription->setEvenement(new Evenement());
        $inscription->setUtilisateur(new Utilisateur());
        $inscription->setDateInscription(new \DateTimeImmutable('-1 day'));
        $inscription->setStatut('confirmee');
        $inscription->setPresent(true);

        self::assertTrue($this->manager->validate($inscription));
    }

    public function testValidateUniqueInscriptionThrowsExceptionWhenDuplicateExists(): void
    {
        $event = new Evenement();
        $user = new Utilisateur();

        $existing = new InscriptionEvenement();
        $existing->setEvenement($event);
        $existing->setUtilisateur($user);
        $existing->setStatut('confirmee');
        $existing->setDateInscription(new \DateTimeImmutable('-2 days'));

        $new = new InscriptionEvenement();
        $new->setEvenement($event);
        $new->setUtilisateur($user);
        $new->setStatut('en_attente');
        $new->setDateInscription(new \DateTimeImmutable('-1 day'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Utilisateur deja inscrit a cet evenement.');

        $this->manager->validateUniqueInscription($new, [$existing]);
    }

    public function testCanMarkPresentReturnsTrueOnlyForConfirmeeStatus(): void
    {
        $inscription = new InscriptionEvenement();
        $inscription->setStatut('confirmee');
        self::assertTrue($this->manager->canMarkPresent($inscription));

        $inscription->setStatut('en_attente');
        self::assertFalse($this->manager->canMarkPresent($inscription));
    }

    public function testCanCancelReturnsFalseWithin24HoursBeforeEvent(): void
    {
        $event = new Evenement();
        $event->setDateDebut(new \DateTime('2026-03-10 10:00:00'));

        $inscription = new InscriptionEvenement();
        $inscription->setEvenement($event);
        $inscription->setUtilisateur(new Utilisateur());

        self::assertFalse(
            $this->manager->canCancel($inscription, new \DateTimeImmutable('2026-03-09 12:00:00'))
        );
    }

    public function testCanCancelReturnsTrueBefore24HoursDeadline(): void
    {
        $event = new Evenement();
        $event->setDateDebut(new \DateTime('2026-03-10 10:00:00'));

        $inscription = new InscriptionEvenement();
        $inscription->setEvenement($event);
        $inscription->setUtilisateur(new Utilisateur());

        self::assertTrue(
            $this->manager->canCancel($inscription, new \DateTimeImmutable('2026-03-08 10:00:00'))
        );
    }

    public function testCreatePersistsAndFlushesInscription(): void
    {
        $inscription = new InscriptionEvenement();
        $inscription->setEvenement(new Evenement());
        $inscription->setUtilisateur(new Utilisateur());
        $inscription->setDateInscription(new \DateTimeImmutable('-1 day'));
        $inscription->setStatut('en_attente');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with($inscription);
        $em->expects(self::once())->method('flush');

        $manager = new InscriptionEvenementManager($em);

        self::assertSame($inscription, $manager->create($inscription));
    }

    public function testFindByIdReturnsInscription(): void
    {
        $inscription = new InscriptionEvenement();
        $repo = $this->createMock(EntityRepository::class);
        $repo->expects(self::once())->method('find')->with(3)->willReturn($inscription);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())
            ->method('getRepository')
            ->with(InscriptionEvenement::class)
            ->willReturn($repo);

        $manager = new InscriptionEvenementManager($em);

        self::assertSame($inscription, $manager->findById(3));
    }

    public function testUpdateFlushesInscription(): void
    {
        $inscription = new InscriptionEvenement();
        $inscription->setEvenement(new Evenement());
        $inscription->setUtilisateur(new Utilisateur());
        $inscription->setDateInscription(new \DateTimeImmutable('-1 day'));
        $inscription->setStatut('en_attente');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $manager = new InscriptionEvenementManager($em);

        self::assertSame($inscription, $manager->update($inscription));
    }

    public function testDeleteRemovesAndFlushesInscription(): void
    {
        $inscription = new InscriptionEvenement();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($inscription);
        $em->expects(self::once())->method('flush');

        $manager = new InscriptionEvenementManager($em);
        $manager->delete($inscription);

        self::assertTrue(true);
    }
}
