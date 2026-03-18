<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260313194619 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project (id BLOB NOT NULL, name VARCHAR(160) NOT NULL, client VARCHAR(160) NOT NULL, domain VARCHAR(255) DEFAULT NULL, server_ip VARCHAR(45) DEFAULT NULL, ssh_public_key_encrypted CLOB DEFAULT NULL, ssh_private_key_encrypted CLOB DEFAULT NULL, server_user VARCHAR(180) DEFAULT NULL, server_password_encrypted CLOB DEFAULT NULL, ssh_port INTEGER DEFAULT 22 NOT NULL, app_secret_encrypted CLOB DEFAULT NULL, db_name_encrypted CLOB DEFAULT NULL, db_user_encrypted CLOB DEFAULT NULL, db_password_encrypted CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by_id BLOB NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_2FB3D0EEB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_2FB3D0EEB03A8386 ON project (created_by_id)');
        $this->addSql('CREATE TABLE project_members (project_id BLOB NOT NULL, user_id BLOB NOT NULL, PRIMARY KEY (project_id, user_id), CONSTRAINT FK_D3BEDE9A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_D3BEDE9AA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_D3BEDE9A166D1F9C ON project_members (project_id)');
        $this->addSql('CREATE INDEX IDX_D3BEDE9AA76ED395 ON project_members (user_id)');
        $this->addSql('CREATE TABLE secret (id BLOB NOT NULL, name VARCHAR(160) NOT NULL, public_secret_encrypted CLOB DEFAULT NULL, private_secret_encrypted CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, project_id BLOB NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_5CA2E8E5166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_5CA2E8E5166D1F9C ON secret (project_id)');
        $this->addSql('CREATE TABLE "user" (id BLOB NOT NULL, email VARCHAR(180) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, is_active BOOLEAN DEFAULT 1 NOT NULL, totp_secret VARCHAR(255) DEFAULT NULL, totp_enabled BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "user" (email)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE project_members');
        $this->addSql('DROP TABLE secret');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
