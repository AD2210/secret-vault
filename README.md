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

## Variables à surcharger en vrai environnement

- `APP_SECRET`
- `APP_2FA_ISSUER`
- `VAULT_ENCRYPTION_KEY`
- `CHILD_APP_PROVISIONING_TOKEN`
- `DATABASE_URL`

`VAULT_ENCRYPTION_KEY` doit être une clé hexadécimale de 32 octets.

Exemple de génération:

```bash
php -r "echo bin2hex(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)), PHP_EOL;"
```

## Tests

```bash
composer qa:phpunit
```

## Contrat mère/app fille

Endpoint attendu:

- `POST /internal/provisioning/tenant-admin`
- Auth Bearer via `CHILD_APP_PROVISIONING_TOKEN`
- Idempotence sur le couple `tenant_uuid + user_uuid`

Exemple:

```bash
curl -i -X POST http://127.0.0.1:8000/internal/provisioning/tenant-admin \
  -H "Authorization: Bearer change-this-provisioning-token" \
  -H "Content-Type: application/json" \
  -d '{
    "contract":"tenant-admin-provisioning:v1",
    "tenant_uuid":"11111111-2222-7333-8444-555555555555",
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
