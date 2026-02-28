<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217161000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add online meeting platform and link fields to evenement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement ADD meeting_platform VARCHAR(30) DEFAULT NULL, ADD meeting_link VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement DROP meeting_platform, DROP meeting_link');
    }
}

