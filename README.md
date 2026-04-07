# Client Secrets Vault

Symfony 7.4 monobase pour stocker les secrets de projets clients dans un coffre interne.

## Fonctionnalités

- authentification par mot de passe
- enrollement 2FA TOTP obligatoire
- chiffrement applicatif des secrets via `libsodium`
- gestion des utilisateurs internes
- gestion des projets, accès serveur, credentials DB et secrets additionnels
- partage d’accès projet entre utilisateurs via invitations

L’intégration multi-tenant est sortie de cette application et sera gérée dans un package Composer séparé.

## Démarrage local

1. Vérifie la configuration active:
   ```bash
   ./bin/vault-console about
   ```
2. Lance les migrations:
   ```bash
   ./bin/vault-console doctrine:migrations:migrate --no-interaction
   ```
3. Crée un premier admin:
   ```bash
   ./bin/vault-console app:user:create admin@example.com Ada Lovelace StrongPassword123! --admin
   ```
4. Démarre l’app:
   ```bash
   symfony server:start -d
   ```

Routes principales:

- dashboard: `/`
- login: `/login`
- projets: `/projects`
- équipe: `/team`

## Stack Docker locale

```bash
make up
```

Accès locaux:

- app: `http://127.0.0.1:8090`
- health: `http://127.0.0.1:8090/healthz`
- PostgreSQL: `127.0.0.1:5452`

Le port hôte Postgres est volontairement `5452` pour éviter le conflit avec un `5432` déjà occupé sur ta machine.

## Variables importantes

- `APP_SECRET`
- `APP_2FA_ISSUER`
- `VAULT_ENCRYPTION_KEY`
- `DATABASE_URL`
- `DEFAULT_URI`

`VAULT_ENCRYPTION_KEY` doit être une clé hexadécimale de 32 octets:

```bash
php -r "echo bin2hex(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)), PHP_EOL;"
```

## Tests

```bash
composer qa:phpunit
```

## Déploiement

Fichiers utiles:

- CI: [ci.yml](/home/andel/PhpstormProjects/client_secret_vault/.github/workflows/ci.yml)
- CD prod: [cd-prod.yml](/home/andel/PhpstormProjects/client_secret_vault/.github/workflows/cd-prod.yml)
- exemple env prod: [.env.prod.example](/home/andel/PhpstormProjects/client_secret_vault/.env.prod.example)
- config serveur: [server.env.example](/home/andel/PhpstormProjects/client_secret_vault/ops/config/server.env.example)
- runbook: [DEPLOYMENT_RUNBOOK_V1.md](/home/andel/PhpstormProjects/client_secret_vault/docs/runbooks/DEPLOYMENT_RUNBOOK_V1.md)
