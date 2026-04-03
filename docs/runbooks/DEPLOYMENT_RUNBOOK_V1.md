# Runbook Deploy Vault V1

## Objectif

Déployer `client_secret_vault` sur son propre sous-domaine, avec un workflow GitHub Actions et un déploiement par release.

## Domaine prévu

- production: `secret-vault.dsn-dev.com`

## Préparation serveur

1. Créer le dossier applicatif:
   ```bash
   sudo mkdir -p /srv/secret-vault/releases
   ```
2. Copier `ops/config/server.env.example` vers `/etc/client-secret-vault/server.env` puis adapter les secrets.
3. Vérifier que le reverse proxy du serveur pointe `secret-vault.dsn-dev.com` vers `127.0.0.1:8090`.
4. Utiliser la même valeur pour:
   - `CHILD_APP_PROVISIONING_TOKEN` dans `/etc/client-secret-vault/server.env`
   - `CHILD_APP_VAULT_API_TOKEN` dans `/etc/saas/server.env`
5. Convention de répertoire:
   - app publiée: `/srv/secret-vault/app`
   - releases internes: `/srv/secret-vault/releases`
   - la version déployée est choisie dans GitHub Actions; le lien `app` sert uniquement au switch atomique côté serveur

## Référencement dans l'app mère

Dans `saas_base`, le routage de provisioning/login vers cette app utilise:

- `CHILD_APP_VAULT_API_URL=https://secret-vault.dsn-dev.com`
- `CHILD_APP_VAULT_LOGIN_URL=https://{tenantSlug}.secret-vault.dsn-dev.com/login`
- `CHILD_APP_VAULT_API_TOKEN=<même token que ci-dessus>`

Pour une future app fille, reprendre exactement le même schéma avec une nouvelle clé applicative.

## Secrets GitHub attendus

- `PROD_SSH_HOST`
- `PROD_SSH_PORT`
- `PROD_SSH_USER`
- `PROD_SSH_PRIVATE_KEY`
- `PROD_DEPLOY_ENV_FILE`

## Déploiement

Les workflows:

- `.github/workflows/cd-prod.yml`

uploade un artefact, puis lancent `ops/server/deploy_release.sh` sur le serveur cible.

## Remarques

- `BASE_URI` est la source de vérité pour l'URL publique et alimente `DEFAULT_URI` et le healthcheck.
- `SERVER_NAME` sert de paramètre explicite pour la stack et le reverse proxy.
