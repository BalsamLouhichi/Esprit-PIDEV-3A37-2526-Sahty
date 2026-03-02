<?php

namespace App\Tests\Service;

use App\Service\rendezvousManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class rendezvousManagerTest extends TestCase
{
    public function testValidateMotifReturnsTrimmedMotifWhenValid(): void
    {
        $manager = new rendezvousManager();

        $result = $manager->validateMotif('   Consultation generale pour douleur abdominale   ');

        $this->assertSame('Consultation generale pour douleur abdominale', $result);
    }

    public function testValidateMotifThrowsWhenEmpty(): void
    {
        $manager = new rendezvousManager();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le motif du rendez-vous ne doit pas être vide.');

        $manager->validateMotif('   ');
    }

    public function testValidateMotifThrowsWhenNull(): void
    {
        $manager = new rendezvousManager();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le motif du rendez-vous ne doit pas être vide.');

        $manager->validateMotif(null);
    }

    public function testValidateMotifThrowsWhenTooShort(): void
    {
        $manager = new rendezvousManager();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le motif doit contenir au moins 5 caractères.');

        $manager->validateMotif('abcd');
    }

    public function testValidateMotifAcceptsExactlyMinLength(): void
    {
        $manager = new rendezvousManager();

        $result = $manager->validateMotif('abcde');

        $this->assertSame('abcde', $result);
    }

    public function testValidateMotifThrowsWhenTooLong(): void
    {
        $manager = new rendezvousManager();
        $longMotif = str_repeat('a', rendezvousManager::MAX_MOTIF_LENGTH + 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le motif ne peut pas dépasser 1000 caractères.');

        $manager->validateMotif($longMotif);
    }

    public function testValidateMotifAcceptsExactlyMaxLength(): void
    {
        $manager = new rendezvousManager();
        $maxMotif = str_repeat('a', rendezvousManager::MAX_MOTIF_LENGTH);

        $result = $manager->validateMotif($maxMotif);

        $this->assertSame($maxMotif, $result);
    }

    public function testValidateMotifKeepsAccentedCharacters(): void
    {
        $manager = new rendezvousManager();

        $result = $manager->validateMotif('Suivi médical: douleur à l’épaule');

        $this->assertSame('Suivi médical: douleur à l’épaule', $result);
    }

    public function testValidateMotifTrimsNewLinesAndTabsAroundText(): void
    {
        $manager = new rendezvousManager();

        $result = $manager->validateMotif("\n\t  Bilan annuel complet  \t\n");

        $this->assertSame('Bilan annuel complet', $result);
    }

    public function testValidateMotifAcceptsNumbers(): void
    {
        $manager = new rendezvousManager();

        $result = $manager->validateMotif('Douleur depuis 3 jours');

        $this->assertSame('Douleur depuis 3 jours', $result);
    }

    public function testValidateMotifAcceptsPunctuation(): void
    {
        $manager = new rendezvousManager();

        $result = $manager->validateMotif('Migraine, nausées; fièvre?');

        $this->assertSame('Migraine, nausées; fièvre?', $result);
    }

    public function testValidateMotifAcceptsMultilineInsideText(): void
    {
        $manager = new rendezvousManager();
        $input = "Douleur thoracique\navec essoufflement";

        $result = $manager->validateMotif($input);

        $this->assertSame($input, $result);
    }

    public function testValidateMotifRejectsOnlyTabsAndNewlines(): void
    {
        $manager = new rendezvousManager();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le motif du rendez-vous ne doit pas être vide.');

        $manager->validateMotif("\n\t\r");
    }

    public function testValidateMotifAcceptsUnicodeCharacters(): void
    {
        $manager = new rendezvousManager();

        $result = $manager->validateMotif('Contrôle post-opératoire — état stable');

        $this->assertSame('Contrôle post-opératoire — état stable', $result);
    }

    public function testValidateMotifAcceptsFiveUnicodeChars(): void
    {
        $manager = new rendezvousManager();

        $result = $manager->validateMotif('éàùçô');

        $this->assertSame('éàùçô', $result);
    }

    public function testValidateMotifLongWithSpacesStillValidWhenWithinLimit(): void
    {
        $manager = new rendezvousManager();
        $base = str_repeat('ab ', 300); // ~900 chars
        $input = trim($base);

        $result = $manager->validateMotif($input);

        $this->assertSame($input, $result);
    }

    public function testValidateMotifErrorMessageForShortInputIsExact(): void
    {
        $manager = new rendezvousManager();

        try {
            $manager->validateMotif('abc');
            $this->fail('Exception attendue');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Le motif doit contenir au moins 5 caractères.', $e->getMessage());
        }
    }

    public function testValidateMotifErrorMessageForLongInputIsExact(): void
    {
        $manager = new rendezvousManager();
        $input = str_repeat('x', 1001);

        try {
            $manager->validateMotif($input);
            $this->fail('Exception attendue');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Le motif ne peut pas dépasser 1000 caractères.', $e->getMessage());
        }
    }
}
