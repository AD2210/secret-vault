# Client Secrets Vault

Application fille Symfony 7.4 pensée comme coffre-fort interne pour stocker les secrets de projets clients.

## Ce que la V1 livre

- authentification par mot de passe
- activation obligatoire du 2FA TOTP à la première connexion
- stockage chiffré applicativement via `libsodium`
- gestion des utilisateurs internes
- gestion des projets, accès serveur, credentials DB et secrets additionnels
- partage des projets entre plusieurs utilisateurs
- endpoint de provisioning interne compatible `tenant-admin-provisioning:v1`

## Point sécurité important

La biométrie mobile ne doit pas être stockée dans l’application. Pour répondre au besoin `mot de passe + biométrie`, la bonne suite est d’ajouter des passkeys/WebAuthn. Cette V1 implémente `mot de passe + authenticator TOTP`, qui reste la voie rapide et saine.

## Démarrage local

1. Positionne-toi dans le projet.
2. Si ton shell exporte déjà `DATABASE_URL`, neutralise-le pour cette app:
   ```bash
   ./bin/vault-console about
   ```
3. Lance la migration SQLite locale:
   ```bash
   ./bin/vault-console doctrine:migrations:migrate --no-interaction
   ```
4. Crée un premier admin:
   ```bash
   ./bin/vault-console app:user:create admin@example.com Ada Lovelace StrongPassword123! --admin
   ```
5. Démarre le serveur:
   ```bash
   symfony server:start -d
   ```

## Stack Docker locale

Si tu veux lancer le projet comme une vraie stack applicative:

```bash
make up
```

Accès locaux:

- app: `http://127.0.0.1:8090`
- health: `http://127.0.0.1:8090/healthz`
- PostgreSQL: `127.0.0.1:5452`

## Variables à surcharger en vrai environnement

- `APP_SECRET`
- `APP_2FA_ISSUER`
- `VAULT_ENCRYPTION_KEY`
- `CHILD_APP_PROVISIONING_TOKEN`
- `DATABASE_URL`
- `DEFAULT_URI`

`VAULT_ENCRYPTION_KEY` doit être une clé hexadécimale de 32 octets.

Exemple de génération:

```bash
php -r "echo bin2hex(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)), PHP_EOL;"
```

## Tests

```bash
composer qa:phpunit
```

## Déploiement et domaine

Le projet est préparé pour être déployé sur:

- `secret-vault.dsn-dev.com`

Fichiers utiles:

- workflow CI: [ci.yml](/home/andel/PhpstormProjects/client_secret_vault/.github/workflows/ci.yml)
- CD prod: [cd-prod.yml](/home/andel/PhpstormProjects/client_secret_vault/.github/workflows/cd-prod.yml)
- production server env template: `.env.prod.example` (copy to `.env` on the server deploy path)
- config serveur exemple: [server.env.example](/home/andel/PhpstormProjects/client_secret_vault/ops/config/server.env.example)
- runbook de déploiement: [DEPLOYMENT_RUNBOOK_V1.md](/home/andel/PhpstormProjects/client_secret_vault/docs/runbooks/DEPLOYMENT_RUNBOOK_V1.md)

## Contrat mère/app fille

Endpoint attendu:

- `POST /internal/provisioning/tenant-admin`
- Auth Bearer via `CHILD_APP_PROVISIONING_TOKEN`
- Idempotence sur le couple `tenant_uuid + user_uuid`
- Le contrat peut aussi recevoir `child_app_key`, `child_app_name`, `tenant_slug`, `tenant_name`

Exemple:

```bash
curl -i -X POST http://127.0.0.1:8000/internal/provisioning/tenant-admin \
  -H "Authorization: Bearer change-this-provisioning-token" \
  -H "Content-Type: application/json" \
  -d '{
    "contract":"tenant-admin-provisioning:v1",
    "child_app_key":"vault",
    "child_app_name":"Client Secrets Vault",
    "tenant_uuid":"11111111-2222-7333-8444-555555555555",
    "tenant_slug":"acme-demo",
    "tenant_name":"Acme Demo",
    "user_uuid":"aaaaaaaa-bbbb-7ccc-8ddd-eeeeeeeeeeee",
    "email":"admin@example.com",
    "first_name":"Ada",
    "last_name":"Lovelace",
    "status":"active",
    "created_at":"2026-03-13T20:00:00+00:00",
    "updated_at":"2026-03-13T20:00:00+00:00",
    "password":"StrongPassword123!"
  }'
```
