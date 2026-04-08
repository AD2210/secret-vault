<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add vault key versioning and audit logs';
    }

    public function up(Schema $schema): void
    {
        $secret = $schema->getTable('secret');
        if (!$secret->hasColumn('encryption_key_version')) {
            $secret->addColumn('encryption_key_version', Types::STRING, [
                'length' => 64,
                'notnull' => false,
            ]);
        }

        if (!$schema->hasTable('audit_log')) {
            $table = $schema->createTable('audit_log');
            $table->addColumn('id', Types::GUID);
            $table->addColumn('event_type', Types::STRING, ['length' => 80]);
            $table->addColumn('subject_type', Types::STRING, ['length' => 80, 'notnull' => false]);
            $table->addColumn('subject_id', Types::STRING, ['length' => 64, 'notnull' => false]);
            $table->addColumn('actor_id', Types::GUID, ['notnull' => false]);
            $table->addColumn('ip_address', Types::STRING, ['length' => 45, 'notnull' => false]);
            $table->addColumn('user_agent', Types::STRING, ['length' => 255, 'notnull' => false]);
            $table->addColumn('context', Types::JSON);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['event_type'], 'idx_audit_log_event_type');
            $table->addIndex(['actor_id'], 'idx_audit_log_actor');
            $table->addIndex(['created_at'], 'idx_audit_log_created_at');
            $table->addForeignKeyConstraint('user', ['actor_id'], ['id'], ['onDelete' => 'SET NULL'], 'fk_audit_log_actor');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('audit_log')) {
            $table = $schema->getTable('audit_log');
            if ($table->hasForeignKey('fk_audit_log_actor')) {
                $table->removeForeignKey('fk_audit_log_actor');
            }
            $schema->dropTable('audit_log');
        }

        $secret = $schema->getTable('secret');
        if ($secret->hasColumn('encryption_key_version')) {
            $secret->dropColumn('encryption_key_version');
        }
    }
}
