<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add team invitation workflow with role and project assignments';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('user_invitation')) {
            $table = $schema->createTable('user_invitation');
            $table->addColumn('id', Types::GUID);
            $table->addColumn('invited_by_id', Types::GUID);
            $table->addColumn('invitee_user_id', Types::GUID, ['notnull' => false]);
            $table->addColumn('email', Types::STRING, ['length' => 180]);
            $table->addColumn('role', Types::STRING, ['length' => 32]);
            $table->addColumn('token_hash', Types::STRING, ['length' => 64]);
            $table->addColumn('expires_at', Types::DATETIME_IMMUTABLE);
            $table->addColumn('accepted_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
            $table->addColumn('revoked_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['token_hash'], 'uniq_user_invitation_token_hash');
            $table->addIndex(['invited_by_id'], 'idx_user_invitation_invited_by');
            $table->addIndex(['invitee_user_id'], 'idx_user_invitation_invitee_user');
            $table->addForeignKeyConstraint('user', ['invited_by_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_user_invitation_invited_by');
            $table->addForeignKeyConstraint('user', ['invitee_user_id'], ['id'], ['onDelete' => 'SET NULL'], 'fk_user_invitation_invitee_user');
        }

        if (!$schema->hasTable('user_invitation_projects')) {
            $table = $schema->createTable('user_invitation_projects');
            $table->addColumn('user_invitation_id', Types::GUID);
            $table->addColumn('project_id', Types::GUID);
            $table->setPrimaryKey(['user_invitation_id', 'project_id']);
            $table->addIndex(['user_invitation_id'], 'idx_user_invitation_projects_invitation');
            $table->addIndex(['project_id'], 'idx_user_invitation_projects_project');
            $table->addForeignKeyConstraint('user_invitation', ['user_invitation_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_user_invitation_projects_invitation');
            $table->addForeignKeyConstraint('project', ['project_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_user_invitation_projects_project');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('user_invitation_projects')) {
            $table = $schema->getTable('user_invitation_projects');
            if ($table->hasForeignKey('fk_user_invitation_projects_invitation')) {
                $table->removeForeignKey('fk_user_invitation_projects_invitation');
            }
            if ($table->hasForeignKey('fk_user_invitation_projects_project')) {
                $table->removeForeignKey('fk_user_invitation_projects_project');
            }
            $schema->dropTable('user_invitation_projects');
        }

        if ($schema->hasTable('user_invitation')) {
            $table = $schema->getTable('user_invitation');
            if ($table->hasForeignKey('fk_user_invitation_invited_by')) {
                $table->removeForeignKey('fk_user_invitation_invited_by');
            }
            if ($table->hasForeignKey('fk_user_invitation_invitee_user')) {
                $table->removeForeignKey('fk_user_invitation_invitee_user');
            }
            $schema->dropTable('user_invitation');
        }
    }
}
