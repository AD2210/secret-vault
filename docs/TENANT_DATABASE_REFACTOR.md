# Tenant Database Refactor

## Target

`client_secret_vault` must no longer use a single shared business database.

The implementation now uses:

- one bootstrap Doctrine connection
- one tenant business SQLite database per onboarded tenant
- a deterministic DB path derived from `tenantSlug`

## Request Resolution

Tenant business routes must be prefixed:

- `/t/{tenantSlug}/login`
- `/t/{tenantSlug}/projects`
- `/t/{tenantSlug}/projects/{id}`

The tenant slug is resolved from the route and used to switch Doctrine to:

- `var/tenants/{tenantSlug}.sqlite`

## Data Split

Bootstrap database:

- only used to bootstrap Doctrine before a tenant request is resolved
- must not contain business records relied on at runtime

Tenant database:

- `user`
- `project`
- `secret`
- project invitations and future tenant-local records

## Current Runtime Design

- `TenantRequestSubscriber` extracts `{tenantSlug}` from the URL
- `TenantDatabaseSwitcher` switches the Doctrine DBAL connection before business controllers run
- `TenantAwareRouter` injects the current `tenantSlug` during URL generation
- `InternalProvisioningController` creates the tenant DB file and applies the schema before persisting the onboarded admin

## Why the Current Model Is Not Enough

Filtering records by `externalTenantUuid` inside a single database is not acceptable for this app.

It leaks risk across tenants:

- wrong user lists
- wrong project access scope
- more dangerous migrations and backups
- weaker isolation guarantees

## Required Technical Changes

1. tenant context resolved from `/t/{tenantSlug}`
2. tenant-aware Doctrine access
3. provisioning flow creating and migrating a tenant database
4. tenant-aware login and route generation
5. no business reads from the bootstrap DB

## Current Progress

Already in place:

- onboarding provisioning payload carries `tenant_slug`
- public tenant slug generation is standardized in the mother app
- business routes now live under `/t/{tenantSlug}`
- tenant DB files are created under `var/tenants`
- provisioning creates the tenant DB and writes the admin there
- login and business pages now resolve through the tenant-prefixed URL

Still recommended for future hardening:

- introduce a small explicit registry DB if path indirection becomes necessary
- add dedicated tenant bootstrap commands for support and migrations
- document backup/restore policy per tenant DB

## Rule for Future Child Apps

Any future child app must start directly with this split model instead of beginning with a single shared business database.
