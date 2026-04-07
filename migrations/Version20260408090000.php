<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial monobase schema for client secrets vault';
    }

    public function up(Schema $schema): void
    {
        $user = $schema->createTable('user');
        $user->addColumn('id', Types::GUID);
        $user->addColumn('email', Types::STRING, ['length' => 180]);
        $user->addColumn('first_name', Types::STRING, ['length' => 100]);
        $user->addColumn('last_name', Types::STRING, ['length' => 100]);
        $user->addColumn('roles', Types::JSON);
        $user->addColumn('password', Types::STRING, ['length' => 255]);
        $user->addColumn('is_active', Types::BOOLEAN, ['default' => true]);
        $user->addColumn('totp_secret', Types::STRING, ['length' => 255, 'notnull' => false]);
        $user->addColumn('totp_enabled', Types::BOOLEAN, ['default' => false]);
        $user->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $user->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
        $user->setPrimaryKey(['id']);
        $user->addUniqueIndex(['email'], 'UNIQ_IDENTIFIER_EMAIL');

        $project = $schema->createTable('project');
        $project->addColumn('id', Types::GUID);
        $project->addColumn('name', Types::STRING, ['length' => 160]);
        $project->addColumn('client', Types::STRING, ['length' => 160]);
        $project->addColumn('domain', Types::STRING, ['length' => 255, 'notnull' => false]);
        $project->addColumn('server_ip', Types::STRING, ['length' => 45, 'notnull' => false]);
        $project->addColumn('ssh_public_key_encrypted', Types::TEXT, ['notnull' => false]);
        $project->addColumn('ssh_private_key_encrypted', Types::TEXT, ['notnull' => false]);
        $project->addColumn('server_user', Types::STRING, ['length' => 180, 'notnull' => false]);
        $project->addColumn('server_password_encrypted', Types::TEXT, ['notnull' => false]);
        $project->addColumn('ssh_port', Types::INTEGER, ['default' => 22]);
        $project->addColumn('app_secret_encrypted', Types::TEXT, ['notnull' => false]);
        $project->addColumn('db_name_encrypted', Types::TEXT, ['notnull' => false]);
        $project->addColumn('db_user_encrypted', Types::TEXT, ['notnull' => false]);
        $project->addColumn('db_password_encrypted', Types::TEXT, ['notnull' => false]);
        $project->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $project->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
        $project->addColumn('created_by_id', Types::GUID);
        $project->setPrimaryKey(['id']);
        $project->addIndex(['created_by_id'], 'IDX_PROJECT_CREATED_BY');
        $project->addForeignKeyConstraint('user', ['created_by_id'], ['id']);

        $projectMembers = $schema->createTable('project_members');
        $projectMembers->addColumn('project_id', Types::GUID);
        $projectMembers->addColumn('user_id', Types::GUID);
        $projectMembers->setPrimaryKey(['project_id', 'user_id']);
        $projectMembers->addIndex(['project_id'], 'IDX_PROJECT_MEMBERS_PROJECT');
        $projectMembers->addIndex(['user_id'], 'IDX_PROJECT_MEMBERS_USER');
        $projectMembers->addForeignKeyConstraint('project', ['project_id'], ['id'], ['onDelete' => 'CASCADE']);
        $projectMembers->addForeignKeyConstraint('user', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);

        $secret = $schema->createTable('secret');
        $secret->addColumn('id', Types::GUID);
        $secret->addColumn('name', Types::STRING, ['length' => 160]);
        $secret->addColumn('public_secret_encrypted', Types::TEXT, ['notnull' => false]);
        $secret->addColumn('private_secret_encrypted', Types::TEXT, ['notnull' => false]);
        $secret->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $secret->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
        $secret->addColumn('project_id', Types::GUID);
        $secret->setPrimaryKey(['id']);
        $secret->addIndex(['project_id'], 'IDX_SECRET_PROJECT');
        $secret->addForeignKeyConstraint('project', ['project_id'], ['id']);

        $invitation = $schema->createTable('project_access_invitation');
        $invitation->addColumn('id', Types::GUID);
        $invitation->addColumn('project_id', Types::GUID);
        $invitation->addColumn('invited_by_id', Types::GUID);
        $invitation->addColumn('invitee_user_id', Types::GUID, ['notnull' => false]);
        $invitation->addColumn('email', Types::STRING, ['length' => 180]);
        $invitation->addColumn('token_hash', Types::STRING, ['length' => 64]);
        $invitation->addColumn('expires_at', Types::DATETIME_IMMUTABLE);
        $invitation->addColumn('recipient_confirmed_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $invitation->addColumn('owner_approved_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $invitation->addColumn('accepted_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $invitation->addColumn('revoked_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $invitation->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $invitation->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
        $invitation->setPrimaryKey(['id']);
        $invitation->addUniqueIndex(['token_hash'], 'UNIQ_PROJECT_ACCESS_INVITATION_TOKEN');
        $invitation->addIndex(['project_id'], 'IDX_PROJECT_ACCESS_INVITATION_PROJECT');
        $invitation->addIndex(['invited_by_id'], 'IDX_PROJECT_ACCESS_INVITATION_INVITED_BY');
        $invitation->addIndex(['invitee_user_id'], 'IDX_PROJECT_ACCESS_INVITATION_INVITEE');
        $invitation->addIndex(['email'], 'IDX_PROJECT_ACCESS_INVITATION_EMAIL');
        $invitation->addForeignKeyConstraint('project', ['project_id'], ['id'], ['onDelete' => 'CASCADE']);
        $invitation->addForeignKeyConstraint('user', ['invited_by_id'], ['id'], ['onDelete' => 'CASCADE']);
        $invitation->addForeignKeyConstraint('user', ['invitee_user_id'], ['id'], ['onDelete' => 'SET NULL']);

        $messages = $schema->createTable('messenger_messages');
        $messages->addColumn('id', Types::BIGINT, ['autoincrement' => true]);
        $messages->addColumn('body', Types::TEXT);
        $messages->addColumn('headers', Types::TEXT);
        $messages->addColumn('queue_name', Types::STRING, ['length' => 190]);
        $messages->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $messages->addColumn('available_at', Types::DATETIME_IMMUTABLE);
        $messages->addColumn('delivered_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $messages->setPrimaryKey(['id']);
        $messages->addIndex(
            ['queue_name', 'available_at', 'delivered_at', 'id'],
            'IDX_MESSENGER_MESSAGES_QUEUE'
        );
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('messenger_messages');
        $schema->dropTable('project_access_invitation');
        $schema->dropTable('secret');
        $schema->dropTable('project_members');
        $schema->dropTable('project');
        $schema->dropTable('user');
    }
}
