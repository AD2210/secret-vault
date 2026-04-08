<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add typed payload and owner to secrets';
    }

    public function up(Schema $schema): void
    {
        $secret = $schema->getTable('secret');

        if (!$secret->hasColumn('type')) {
            $secret->addColumn('type', Types::STRING, [
                'length' => 40,
                'default' => 'secret',
            ]);
        }

        if (!$secret->hasColumn('payload_encrypted')) {
            $secret->addColumn('payload_encrypted', Types::TEXT, [
                'notnull' => false,
            ]);
        }

        if (!$secret->hasColumn('created_by_id')) {
            $secret->addColumn('created_by_id', Types::GUID, [
                'notnull' => false,
            ]);
            $secret->addIndex(['created_by_id'], 'IDX_SECRET_CREATED_BY');
            $secret->addForeignKeyConstraint('user', ['created_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_SECRET_CREATED_BY');
        }
    }

    public function down(Schema $schema): void
    {
        $secret = $schema->getTable('secret');

        if ($secret->hasForeignKey('FK_SECRET_CREATED_BY')) {
            $secret->removeForeignKey('FK_SECRET_CREATED_BY');
        }

        if ($secret->hasIndex('IDX_SECRET_CREATED_BY')) {
            $secret->dropIndex('IDX_SECRET_CREATED_BY');
        }

        if ($secret->hasColumn('created_by_id')) {
            $secret->dropColumn('created_by_id');
        }

        if ($secret->hasColumn('payload_encrypted')) {
            $secret->dropColumn('payload_encrypted');
        }

        if ($secret->hasColumn('type')) {
            $secret->dropColumn('type');
        }
    }
}
