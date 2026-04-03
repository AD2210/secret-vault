# Tenant Database Refactor

## Target

`client_secret_vault` must no longer use a single shared business database.

The implementation now uses:

- one bootstrap Doctrine connection
- one tenant business SQLite database per onboarded tenant
- a deterministic DB path derived from `tenantSlug`

## Request Resolution

Tenant authentication entry routes now resolve on the tenant subdomain:

- `/login`
- `/`

Business routes still use the tenant prefix:

- `/t/{tenantSlug}/projects`
- `/t/{tenantSlug}/projects/{id}`

The tenant slug is resolved from the subdomain, or from the prefixed business route, and used to switch Doctrine to:

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

- `TenantRequestSubscriber` extracts `{tenantSlug}` from the subdomain or prefixed business route
- `TenantDatabaseSwitcher` switches the Doctrine DBAL connection before business controllers run
- `TenantAwareRouter` injects the current `tenantSlug` during URL generation
- `InternalProvisioningController` persists the bootstrap identity while tenant DB creation happens on successful authentication
- legacy `/t/{tenantSlug}/login`-style URLs are no longer first-class routes and fall back to the canonical `/login`

## Why the Current Model Is Not Enough

Filtering records by `externalTenantUuid` inside a single database is not acceptable for this app.

It leaks risk across tenants:

- wrong user lists
- wrong project access scope
- more dangerous migrations and backups
- weaker isolation guarantees

## Required Technical Changes

1. tenant context resolved from the tenant subdomain, with `/t/{tenantSlug}` kept only for current business routes
2. tenant-aware Doctrine access
3. provisioning flow creating and migrating a tenant database
4. tenant-aware login and route generation
5. no business reads from the bootstrap DB

## Current Progress

Already in place:

- onboarding provisioning payload carries `tenant_slug`
- public tenant slug generation is standardized in the mother app
- auth entrypoints now live on the tenant subdomain
- business routes still live under `/t/{tenantSlug}`
- tenant DB files are created under `var/tenants`
- provisioning stores the bootstrap identity and registry metadata
- first successful login creates the tenant DB and syncs the user

Still recommended for future hardening:

- introduce a small explicit registry DB if path indirection becomes necessary
- add dedicated tenant bootstrap commands for support and migrations
- document backup/restore policy per tenant DB

## Rule for Future Child Apps

Any future child app must start directly with this split model instead of beginning with a single shared business database.
