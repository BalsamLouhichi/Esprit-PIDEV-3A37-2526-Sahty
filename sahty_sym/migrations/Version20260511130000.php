<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le champ planning_recommande pour persister les speakers au format JavaFX.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement ADD planning_recommande LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement DROP planning_recommande');
    }
}
