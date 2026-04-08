<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408130500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Force drop obsolete user invitation projects join table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS user_invitation_projects');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS user_invitation_projects (user_invitation_id UUID NOT NULL, project_id UUID NOT NULL, PRIMARY KEY(user_invitation_id, project_id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_user_invitation_projects_invitation ON user_invitation_projects (user_invitation_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_user_invitation_projects_project ON user_invitation_projects (project_id)');
    }
}
