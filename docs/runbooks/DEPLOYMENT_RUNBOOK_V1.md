# Runbook Deploy Vault V1

## Objectif

Déployer `client_secret_vault` comme application Symfony monobase avec PostgreSQL dédié.

## Domaine prévu

- production: `secret-vault.dsn-dev.com`

## Préparation serveur

1. Créer le dossier applicatif:
   ```bash
   sudo mkdir -p /srv/secret-vault/releases
   ```
2. Copier `ops/config/server.env.example` vers `/etc/client-secret-vault/server.env` puis adapter les secrets.
3. Vérifier que le reverse proxy du serveur pointe `secret-vault.dsn-dev.com` vers `127.0.0.1:8090`.
4. Vérifier les variables PostgreSQL:
   - `POSTGRES_DB`
   - `POSTGRES_USER`
   - `POSTGRES_PASSWORD`
   - `DATABASE_URL`
5. Convention de répertoire:
   - app publiée: `/srv/secret-vault/app`
   - releases internes: `/srv/secret-vault/releases`
   - le lien `app` sert au switch atomique côté serveur

## Secrets GitHub attendus

- `PROD_SSH_HOST`
- `PROD_SSH_PORT`
- `PROD_SSH_USER`
- `PROD_SSH_PRIVATE_KEY`
- `PROD_DEPLOY_ENV_FILE`

## Déploiement

Le workflow principal est [cd-prod.yml](/home/andel/PhpstormProjects/client_secret_vault/.github/workflows/cd-prod.yml).

## Vérification post-déploiement

1. Vérifier la santé simple:
   ```bash
   curl -fsS https://secret-vault.dsn-dev.com/healthz
   ```
2. Vérifier la readiness applicative:
   ```bash
   curl -i https://secret-vault.dsn-dev.com/ready
   ```
3. Attendu:
   - `200 READY` si la base est joignable et la table `user` présente
   - `503` avec détail si la base n’est pas migrée ou n’est pas accessible

## Remarques

- `BASE_URI` reste la source de vérité pour l’URL publique.
- la stack Docker embarque un service PostgreSQL accessible en interne sur `database:5432`
- le port hôte Postgres recommandé reste `5452`
