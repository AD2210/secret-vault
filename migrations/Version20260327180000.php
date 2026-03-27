<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260327180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add project access invitations table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE project_access_invitation (id BLOB NOT NULL, project_id BLOB NOT NULL, invited_by_id BLOB NOT NULL, invitee_user_id BLOB DEFAULT NULL, email VARCHAR(180) NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, recipient_confirmed_at DATETIME DEFAULT NULL, owner_approved_at DATETIME DEFAULT NULL, accepted_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_9B18D3E0166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_9B18D3E0EB7A2CC7 FOREIGN KEY (invited_by_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_9B18D3E02E5E2D2F FOREIGN KEY (invitee_user_id) REFERENCES user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9B18D3E08765097B ON project_access_invitation (token_hash)');
        $this->addSql('CREATE INDEX IDX_9B18D3E0166D1F9C ON project_access_invitation (project_id)');
        $this->addSql('CREATE INDEX IDX_9B18D3E0EB7A2CC7 ON project_access_invitation (invited_by_id)');
        $this->addSql('CREATE INDEX IDX_9B18D3E02E5E2D2F ON project_access_invitation (invitee_user_id)');
        $this->addSql('CREATE INDEX IDX_9B18D3E0E7927C74 ON project_access_invitation (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE project_access_invitation');
    }
}
