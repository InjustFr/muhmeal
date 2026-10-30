<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030165421 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ingredient ADD months_bits INT DEFAULT 4095 NOT NULL');
        $this->addSql('ALTER TABLE ingredient ADD variant_of_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE ingredient ADD CONSTRAINT FK_6BAF7870DA873C21 FOREIGN KEY (variant_of_id) REFERENCES ingredient (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_6BAF7870DA873C21 ON ingredient (variant_of_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ingredient DROP CONSTRAINT FK_6BAF7870DA873C21');
        $this->addSql('DROP INDEX IDX_6BAF7870DA873C21');
        $this->addSql('ALTER TABLE ingredient DROP months_bits');
        $this->addSql('ALTER TABLE ingredient DROP variant_of_id');
    }
}
