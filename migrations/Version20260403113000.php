<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tenant_slug to users and scope email uniqueness by tenant';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, email, first_name, last_name, roles, password, is_active, totp_secret, totp_enabled, created_at, updated_at, external_tenant_uuid, external_user_uuid FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id BLOB NOT NULL, email VARCHAR(180) NOT NULL, tenant_slug VARCHAR(80) DEFAULT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, is_active BOOLEAN DEFAULT 1 NOT NULL, totp_secret VARCHAR(255) DEFAULT NULL, totp_enabled BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, external_tenant_uuid VARCHAR(36) DEFAULT NULL, external_user_uuid VARCHAR(36) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('INSERT INTO user (id, email, first_name, last_name, roles, password, is_active, totp_secret, totp_enabled, created_at, updated_at, external_tenant_uuid, external_user_uuid) SELECT id, email, first_name, last_name, roles, password, is_active, totp_secret, totp_enabled, created_at, updated_at, external_tenant_uuid, external_user_uuid FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_TENANT_IDENTIFIER_EMAIL ON user (tenant_slug, email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EXTERNAL_TENANT_USER ON user (external_tenant_uuid, external_user_uuid)');
        $this->addSql('CREATE INDEX IDX_USER_TENANT_SLUG ON user (tenant_slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, email, first_name, last_name, roles, password, is_active, totp_secret, totp_enabled, created_at, updated_at, external_tenant_uuid, external_user_uuid FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id BLOB NOT NULL, email VARCHAR(180) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, is_active BOOLEAN DEFAULT 1 NOT NULL, totp_secret VARCHAR(255) DEFAULT NULL, totp_enabled BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, external_tenant_uuid VARCHAR(36) DEFAULT NULL, external_user_uuid VARCHAR(36) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('INSERT INTO user (id, email, first_name, last_name, roles, password, is_active, totp_secret, totp_enabled, created_at, updated_at, external_tenant_uuid, external_user_uuid) SELECT id, email, first_name, last_name, roles, password, is_active, totp_secret, totp_enabled, created_at, updated_at, external_tenant_uuid, external_user_uuid FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON user (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EXTERNAL_TENANT_USER ON user (external_tenant_uuid, external_user_uuid)');
    }
}
