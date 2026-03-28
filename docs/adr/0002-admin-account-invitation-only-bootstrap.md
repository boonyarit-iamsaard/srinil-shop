# ADR-0002: Admin Account Invitation-Only Bootstrap

**Status:** Draft
**Date:** 2026-03-28
**Last Updated:** 2026-03-28
**Author:** TBD
**Reviewers:** TBD

---

## Table of Contents

1. Context
2. Scope
3. Constraints
4. Options Considered
5. Decision
6. Bootstrap Flow
7. Invitation Model
8. Security Design
9. Consequences
10. Open Questions
11. Decision Log

---

## 1. Context

Srinil Shop requires an admin panel (Filament) accessible only to administrators. The system
needs a secure mechanism to onboard the very first administrator — a bootstrap problem: you
cannot invite an admin if no admin yet exists.

The initial approach used a database seeder (`AdministratorSeeder`) that created an admin
account directly from `.env` variables (`ADMIN_EMAIL`, `ADMIN_NAME`, `ADMIN_PASSWORD`). This
approach has two problems: it only ran in the local environment, and it created a fully active
account with a known password stored in environment configuration — a security anti-pattern.

A deliberate architectural decision was required to define how the initial admin account is
created, how subsequent admins are invited, and how the system behaves idempotently across
repeated invocations without requiring infrastructure automation.

---

## 2. Scope

**In scope:**

- Bootstrap mechanism for the very first admin account
- Invitation lifecycle model (creation, expiry, acceptance)
- Idempotency rules for the bootstrap command
- Manual operator trigger mechanism via GitHub Actions
- Subsequent admin invitation flow via Filament panel

**Out of scope (addressed elsewhere or deferred):**

- Customer self-registration flow (remains open via Fortify)
- Two-factor authentication enforcement for admins
- Role-based permissions beyond `Admin` / `Customer` enum
- Specific GitHub Actions workflow YAML implementation
- Acceptance page field definitions beyond name and password

---

## 3. Constraints

- No fully active admin account may be created without the account holder setting their own password — the operator must never know the admin's password.
- The bootstrap mechanism must be idempotent — safe to run repeatedly without side effects.
- No automation (schedulers, boot hooks, queue workers) may be assumed as available infrastructure at decision time.
- The operator trigger must require no direct SSH access to the server.
- All invitation records must be retained for audit purposes — no hard deletes.
- A single `User` model with `UserRole::Admin | Customer` enum is the existing architecture; no new models for admin identity.
- Role values are stored as strings in the database; enum enforcement is at the application level only — consistent with the existing `users.role` column convention.

---

## 4. Options Considered

### Option A — Environment Variable Seeder (Existing Approach) ✗ Rejected

The existing `AdministratorSeeder` reads `ADMIN_EMAIL`, `ADMIN_NAME`, and `ADMIN_PASSWORD`
from `.env` and creates a fully active admin account.

| Aspect               | Assessment                                                |
| -------------------- | --------------------------------------------------------- |
| Security             | Poor — password stored in env config, operator knows it   |
| Environment coverage | Local only — not suitable for production                  |
| Idempotency          | Partial — skips duplicate email, but not invitation-aware |
| Operator control     | None — runs automatically with `db:seed`                  |
| Audit trail          | None — no record of who bootstrapped or when              |

Rejected because it creates a fully active account with a known password, runs only in local
environments, and provides no audit trail.

### Option B — Self-Healing Boot Hook ✗ Rejected

Run a bootstrap check in `AppServiceProvider::boot()` with a cache guard. On every request,
check a cache key; on miss, perform a DB check and create an invitation if needed.

| Aspect           | Assessment                                                                  |
| ---------------- | --------------------------------------------------------------------------- |
| Automation       | Fully automatic — no operator action required                               |
| Request overhead | Cache read on every single request — not truly "cheap"                      |
| Reliability      | Cache invalidation edge cases; cache driver failures cause repeated DB hits |
| Conceptual fit   | Boot logic mixed into request lifecycle — wrong separation of concerns      |
| Infrastructure   | No extra infrastructure needed                                              |

Rejected because per-request boot logic is architecturally wrong and the cache guard
introduces its own failure modes.

### Option C — Scheduled Artisan Command ✗ Rejected

A Laravel scheduler job runs periodically (e.g. every hour) to check for expired unaccepted
bootstrap invitations and re-invite automatically.

| Aspect         | Assessment                                                |
| -------------- | --------------------------------------------------------- |
| Automation     | Fully automatic re-invite on expiry                       |
| Infrastructure | Requires cron entry on server — not yet available         |
| Observability  | Harder to audit — fires without operator intent           |
| Complexity     | Adds scheduled infrastructure with no other confirmed use |

Rejected because the scheduler is not confirmed infrastructure, and automatic re-invitation
without operator intent reduces accountability.

### Option D — Manual Artisan Command via GitHub Actions ✓ Selected

A dedicated `php artisan admin:bootstrap --email=...` command is triggered manually by an
operator via a GitHub Actions `workflow_dispatch` workflow. The email address is passed as
a workflow input at trigger time.

| Aspect                      | Assessment                                                             |
| --------------------------- | ---------------------------------------------------------------------- |
| Security                    | High — near-zero-trust; no credentials in env, no automatic execution  |
| Operator control            | Explicit human intent required for every invocation                    |
| Auditability                | GitHub Actions log records who triggered it, when, and with what input |
| Idempotency                 | Command is safe to run repeatedly — skips if active invitation exists  |
| Infrastructure              | No scheduler, no queue worker, no SSH required                         |
| Expired invitation handling | Operator re-runs command manually when notified                        |

Selected because it satisfies all hard constraints, requires no additional infrastructure,
and provides the strongest audit trail with the least attack surface.

---

## 5. Decision

Use a manual `php artisan admin:bootstrap --email=<email>` Artisan command, triggered by
operators via GitHub Actions `workflow_dispatch`, as the sole mechanism for bootstrapping
the initial admin account. All admin accounts — including the first — must be created via
an invitation flow where the account holder sets their own password.

Sub-decisions:

- Remove `ADMIN_EMAIL`, `ADMIN_NAME`, and `ADMIN_PASSWORD` from `.env` entirely.
- Delete the existing `AdministratorSeeder`.
- Subsequent admin invitations are sent from the Filament Administrators resource via a panel action.
- The invitation acceptance page is a dedicated route, separate from Fortify registration.

---

## 6. Bootstrap Flow

```
Operator triggers GitHub Actions workflow_dispatch
  └─ Input: email address
       │
       ▼
php artisan admin:bootstrap --email=admin@example.com
       │
       ├─ Is there an active invitation for this email?
       │   (not expired AND not accepted)
       │   YES → skip, exit 0 (idempotent)
       │
       ├─ Is this email already an accepted admin?
       │   YES → skip, exit 0 (idempotent)
       │
       └─ NO to both → create Invitation record
                         expires_at = now() + 24 hours
                         send invitation email
                         exit 0
```

**Invitation acceptance flow:**

```
Admin receives email → clicks link
  └─ GET /admin/invitations/{token}
       │
       ├─ Token not found OR expired OR already accepted → show error
       │
       └─ Valid → show acceptance form
                   (name, password, confirm password)
                        │
                        ▼
                   POST /admin/invitations/{token}/accept
                        │
                        ├─ Create User (role: 'admin', email_verified_at: now())
                        ├─ Mark invitation accepted_at = now()
                        └─ Redirect to /admin (authenticated)
```

---

## 7. Invitation Model

```
invitations
├── id                  (ulid or uuid)
├── email               (string, indexed)
├── invited_by          (nullable FK → users.id — null for bootstrap)
├── token               (string, unique, cryptographically random)
├── role                (string — value: 'admin'; enum enforced at application level)
├── expires_at          (timestamp — now() + 24 hours)
├── accepted_at         (nullable timestamp — set on acceptance)
└── timestamps
```

**Active invitation:** `expires_at > now()` AND `accepted_at IS NULL`

**Audit:** No rows are deleted. Expired and accepted records remain permanently.

**Expiry window:** 24 hours — sufficient for an operator-initiated, intentional action.

**Role storage:** Stored as a plain string (`'admin'`) in the database. The application
casts this to the `UserRole` enum — consistent with the `users.role` column convention.

---

## 8. Security Design

| Concern                    | Mitigation                                                    |
| -------------------------- | ------------------------------------------------------------- |
| Token guessing             | Cryptographically random token (e.g. `Str::random(64)`)       |
| Token reuse                | `accepted_at` set on first use — subsequent requests rejected |
| Credential exposure        | Operator never sets or knows the admin password               |
| Env credential leakage     | `ADMIN_PASSWORD` removed entirely from `.env`                 |
| Unauthorised bootstrap     | GitHub repository permissions gate `workflow_dispatch` access |
| Audit trail                | GitHub Actions log + `invitations` table provide full record  |
| Acceptance route isolation | Dedicated route — not reachable via Fortify registration flow |

---

## 9. Consequences

### Positive

- No admin password is ever known to the operator or stored in environment configuration.
- Full audit trail: GitHub Actions log records operator identity; `invitations` table records invitation lifecycle.
- Idempotent command is safe to include in any deploy pipeline or run ad-hoc without state checks.
- No additional infrastructure (scheduler, queue worker) required.
- Acceptance page is isolated from customer registration — no cross-contamination of auth flows.

### Negative / Tradeoffs Accepted

- Expired bootstrap invitation requires operator to manually re-run the command — no self-healing.
- Operator must have GitHub repository access with `workflow_dispatch` permission to bootstrap.
- Invitation acceptance page is a net-new route and UI not covered by existing Fortify scaffolding.
- `AdministratorSeeder` is deleted — local development bootstrap now requires running the Artisan command manually.

---

## 10. Open Questions

| #   | Question                                                                                                                   | Owner | Priority  | Blocks                                    |
| --- | -------------------------------------------------------------------------------------------------------------------------- | ----- | --------- | ----------------------------------------- |
| 1   | What expiry duration applies to subsequent admin invitations sent from the Filament panel — same 24 hours or configurable? | TBD   | 🟡 Medium | Filament invitation action implementation |
| 2   | What fields beyond name and password are required on the acceptance form?                                                  | TBD   | 🟡 Medium | Acceptance page implementation            |
| 3   | Should expired-but-unaccepted invitations from the Filament panel allow operator re-invite, or expire silently?            | TBD   | 🟡 Medium | Filament invitation action implementation |
| 4   | What hosting platform will run `php artisan admin:bootstrap` from GitHub Actions?                                          | TBD   | 🔴 High   | GitHub Actions workflow implementation    |
| 5   | Should queue infrastructure be required before email sending, or is synchronous mail acceptable for the bootstrap command? | TBD   | 🟡 Medium | Bootstrap command implementation          |

---

## 11. Decision Log

| Date       | Decision                                                              | Rationale                                                                            |
| ---------- | --------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| 2026-03-28 | Reject env-var seeder approach                                        | Creates fully active account with known password; local-only; no audit trail         |
| 2026-03-28 | Reject boot hook approach                                             | Per-request overhead; cache reliability issues; wrong separation of concerns         |
| 2026-03-28 | Reject scheduled command approach                                     | Requires scheduler infrastructure not yet available; reduces operator accountability |
| 2026-03-28 | Select manual Artisan command via GitHub Actions `workflow_dispatch`  | Near-zero-trust; no infrastructure dependencies; full audit trail; idempotent        |
| 2026-03-28 | Remove `ADMIN_EMAIL`, `ADMIN_NAME`, `ADMIN_PASSWORD` from `.env`      | Email passed as workflow input; password never known to operator                     |
| 2026-03-28 | Delete `AdministratorSeeder`                                          | Replaced entirely by bootstrap command; single canonical path                        |
| 2026-03-28 | Set invitation expiry to 24 hours                                     | Operator-initiated action; short window appropriate; easy to re-invite if expired    |
| 2026-03-28 | `accepted_at` timestamp marks used invitations; rows never deleted    | Audit trail requirement                                                              |
| 2026-03-28 | Acceptance page is a dedicated route, not Fortify registration        | Isolation from customer auth flow; admin-only surface                                |
| 2026-03-28 | Subsequent admin invitations via Filament panel action                | Consistent with admin-only management surface                                        |
| 2026-03-28 | Role stored as string in database, enum enforced at application level | Consistent with existing `users.role` column convention                              |
