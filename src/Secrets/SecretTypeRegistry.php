<?php

declare(strict_types=1);

namespace App\Secrets;

use App\Entity\Secret;

final class SecretTypeRegistry
{
    /**
     * @return array<string, array{
     *     label: string,
     *     description: string,
     *     generator: string|null,
     *     fields: list<array{
     *         key: string,
     *         label: string,
     *         input: string,
     *         required?: bool,
     *         help?: string,
     *         choices?: array<string, string>
     *     }>
     * }>
     */
    public function all(): array
    {
        return [
            Secret::TYPE_SERVER => [
                'label' => 'Serveur',
                'description' => 'IP, accès machine et mot de passe serveur.',
                'generator' => null,
                'fields' => [
                    ['key' => 'username', 'label' => 'Utilisateur', 'input' => 'text'],
                    ['key' => 'port', 'label' => 'Port', 'input' => 'integer'],
                    ['key' => 'password', 'label' => 'Mot de passe', 'input' => 'textarea'],
                    ['key' => 'ip', 'label' => 'IP', 'input' => 'text'],
                    ['key' => 'domain', 'label' => 'Domaine', 'input' => 'text', 'required' => false],
                ],
            ],
            Secret::TYPE_SSH_KEY => [
                'label' => 'Clé SSH',
                'description' => 'Paire de clés SSH pour machine ou CI.',
                'generator' => 'ssh_key',
                'fields' => [
                    [
                        'key' => 'ssh_type',
                        'label' => 'Type',
                        'input' => 'choice',
                        'choices' => [
                            'ED25519' => 'ed25519',
                            'RSA' => 'rsa',
                        ],
                    ],
                    ['key' => 'private_key', 'label' => 'Clé privée', 'input' => 'textarea'],
                    ['key' => 'public_key', 'label' => 'Clé publique', 'input' => 'textarea'],
                    ['key' => 'passphrase', 'label' => 'Passphrase', 'input' => 'text', 'required' => false],
                ],
            ],
            Secret::TYPE_APP => [
                'label' => 'App',
                'description' => 'Secrets applicatifs, APP_SECRET et variantes.',
                'generator' => 'hex',
                'fields' => [
                    ['key' => 'app_secret', 'label' => 'APP_SECRET', 'input' => 'text'],
                    ['key' => 'other_secret', 'label' => 'Autre secret', 'input' => 'textarea', 'required' => false],
                ],
            ],
            Secret::TYPE_DB => [
                'label' => 'Base de données',
                'description' => 'Accès base de données du projet.',
                'generator' => null,
                'fields' => [
                    ['key' => 'db_name', 'label' => 'Nom de base', 'input' => 'text'],
                    ['key' => 'db_user', 'label' => 'Utilisateur DB', 'input' => 'text'],
                    ['key' => 'db_password', 'label' => 'Mot de passe DB', 'input' => 'textarea'],
                    ['key' => 'db_port', 'label' => 'Port DB', 'input' => 'integer'],
                ],
            ],
            Secret::TYPE_API => [
                'label' => 'API',
                'description' => 'Compte API, login technique et clé.',
                'generator' => 'hex',
                'fields' => [
                    ['key' => 'username', 'label' => 'Utilisateur', 'input' => 'text', 'required' => false],
                    ['key' => 'api_key', 'label' => 'Clé API', 'input' => 'textarea'],
                ],
            ],
            Secret::TYPE_PASSWORD => [
                'label' => 'Mot de passe',
                'description' => 'Couple identifiant / mot de passe.',
                'generator' => 'password',
                'fields' => [
                    ['key' => 'username', 'label' => 'Utilisateur', 'input' => 'text', 'required' => false],
                    ['key' => 'password', 'label' => 'Mot de passe', 'input' => 'textarea'],
                ],
            ],
            Secret::TYPE_SECRET => [
                'label' => 'Secret',
                'description' => 'Valeur secrète simple.',
                'generator' => 'hex',
                'fields' => [
                    ['key' => 'secret_value', 'label' => 'Secret', 'input' => 'textarea'],
                ],
            ],
            Secret::TYPE_FTP => [
                'label' => 'FTP',
                'description' => 'Compte FTP ou SFTP du projet.',
                'generator' => 'password',
                'fields' => [
                    ['key' => 'port', 'label' => 'Port', 'input' => 'integer'],
                    ['key' => 'username', 'label' => 'Utilisateur', 'input' => 'text'],
                    ['key' => 'password', 'label' => 'Mot de passe', 'input' => 'textarea'],
                ],
            ],
            Secret::TYPE_OTHER => [
                'label' => 'Autre',
                'description' => 'Format libre pour un besoin non standard.',
                'generator' => null,
                'fields' => [
                    ['key' => 'reference', 'label' => 'Référence', 'input' => 'text', 'required' => false],
                    ['key' => 'username', 'label' => 'Utilisateur', 'input' => 'text', 'required' => false],
                    ['key' => 'password', 'label' => 'Mot de passe', 'input' => 'textarea', 'required' => false],
                    ['key' => 'secret_value', 'label' => 'Secret', 'input' => 'textarea', 'required' => false],
                    ['key' => 'notes', 'label' => 'Notes', 'input' => 'textarea', 'required' => false],
                ],
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, generator: string|null, fields: list<array<string, mixed>>}
     */
    public function get(string $type): array
    {
        $definitions = $this->all();
        if (!isset($definitions[$type])) {
            throw new \InvalidArgumentException(sprintf('Unsupported secret type "%s".', $type));
        }

        return $definitions[$type];
    }

    public function supports(?string $type): bool
    {
        return is_string($type) && isset($this->all()[$type]);
    }

    /**
     * @return array<string, string>
     */
    public function choices(): array
    {
        $choices = [];
        foreach ($this->all() as $type => $definition) {
            $choices[$definition['label']] = $type;
        }

        return $choices;
    }
}
