<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop project assignments from team invitations';
    }

    public function up(Schema $schema): void
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
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('user_invitation_projects')) {
            return;
        }

        $table = $schema->createTable('user_invitation_projects');
        $table->addColumn('user_invitation_id', 'guid');
        $table->addColumn('project_id', 'guid');
        $table->setPrimaryKey(['user_invitation_id', 'project_id']);
        $table->addIndex(['user_invitation_id'], 'idx_user_invitation_projects_invitation');
        $table->addIndex(['project_id'], 'idx_user_invitation_projects_project');
        $table->addForeignKeyConstraint('user_invitation', ['user_invitation_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_user_invitation_projects_invitation');
        $table->addForeignKeyConstraint('project', ['project_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_user_invitation_projects_project');
    }
}
