# ADR-0001: Admin and User Portal Frontend Strategy

**Status:** Accepted
**Date:** 2026-03-22
**Last Updated:** 2026-03-22
**Author:** Boonyarit Iamsa-ard
**Reviewers:** Boonyarit Iamsa-ard

---

## Table of Contents

1. Context
2. Scope
3. Constraints
4. Options Considered
5. Decision
6. Architecture
7. Migration Path
8. Consequences
9. Open Questions
10. Decision Log

---

## 1. Context

This is a Laravel-based pet shop application in its early stages (auth scaffolding only, no domain models built yet). The application requires two distinct portals: a customer-facing storefront and an internal back-office for managing the business.

A deliberate decision was needed at this point — before any domain features are built — to avoid committing weeks of work to a frontend strategy that would later need to be discarded or rewritten.

The admin portal serves a non-technical operator (familiar with Shopee's seller portal) and needs to cover the standard back-office feature set for a small e-commerce business: orders, products, users, transactions, reports, and a dashboard. The customer portal requires a polished, brand-first experience.

The author has a backend-leaning background and prior Filament experience, making frontend implementation cost a meaningful factor.

---

## 2. Scope

**In scope:**

- Technology choice for the admin portal
- Technology choice for the customer-facing portal
- The relationship between the two portals
- Future decoupling strategy for the customer portal

**Out of scope:**

- Specific Filament resource design (covered during feature implementation)
- Next.js migration timeline and execution (deferred; no near-term plans)
- API versioning strategy for the eventual Next.js migration

---

## 3. Constraints

- PHP 8.4 and Laravel 13 — the backend stack is fixed
- The admin end-user is non-technical; the UI must be immediately familiar without training
- The author has a backend-leaning background; minimizing frontend implementation burden is a priority
- A future migration of the customer portal to Next.js (standalone, API-driven) is anticipated but not imminent

---

## 4. Options Considered

### Option A — Inertia + React for Both Portals ✗ Rejected

Use a single frontend paradigm (Inertia v2 + React 19) for both the customer-facing portal and the admin back-office, building the admin UI with shadcn/ui components.

| Aspect                   | Assessment                                                                          |
| ------------------------ | ----------------------------------------------------------------------------------- |
| Paradigm consistency     | Single stack — one mental model across the whole app                                |
| Admin build effort       | High — tables, pagination, filters, form wiring built from scratch per resource     |
| Admin UI quality         | Dependent on developer effort; no free polish                                       |
| Non-technical UX         | No built-in affordances; must be designed and built manually                        |
| Next.js migration impact | Admin work would be abandoned; Inertia-specific code rewritten or kept indefinitely |
| Author fit               | Frontend-heavy; works against a backend-leaning background                          |

Rejected because it front-loads significant React UI work for a back-office that has no custom UI requirements, and that work would be discarded or orphaned when the customer portal eventually migrates to Next.js.

---

### Option B — Filament for Admin + Inertia + React for Customer Portal ✓ Selected

Use Filament v5 (Blade/Livewire) for the admin panel and keep Inertia v2 + React 19 for the customer-facing portal. The two portals are independent and do not share UI components.

| Aspect                   | Assessment                                                                                 |
| ------------------------ | ------------------------------------------------------------------------------------------ |
| Admin build effort       | Low — a full Filament resource with CRUD, search, filters, and bulk actions in ~30 minutes |
| Admin UI quality         | Production-quality out of the box; accessible and responsive                               |
| Non-technical UX         | Familiar table-driven UI comparable to Shopee seller portal                                |
| Paradigm consistency     | Two paradigms (React + Blade/Livewire), but Filament is self-contained                     |
| Next.js migration impact | Filament is unaffected; stays with Laravel permanently                                     |
| Author fit               | Admin work stays in PHP/Laravel; frontend effort concentrated on customer portal only      |
| Author experience        | Filament already known; zero ramp-up cost                                                  |
| Compatibility            | Filament v5 requires PHP 8.2+, Laravel v11.28+, Livewire v4, Tailwind v4 — all satisfied   |

Selected because it matches the admin's feature profile exactly, serves the non-technical operator well, and is durable against the anticipated Next.js migration.

---

## 5. Decision

Use Filament v5 for the admin portal and Inertia v2 + React 19 for the customer-facing portal, treating the two as independent, non-sharing frontend surfaces.

---

## 6. Architecture

```
┌──────────────────────────────────────────────────┐
│              Laravel 13 Application              │
│                                                  │
│  ┌───────────────────┐  ┌──────────────────────┐ │
│  │  Customer Portal  │  │    Admin Portal      │ │
│  │                   │  │                      │ │
│  │  Inertia v2       │  │  Filament v5         │ │
│  │  React 19         │  │  Livewire v4         │ │
│  │  Tailwind v4      │  │  Tailwind v4         │ │
│  │                   │  │                      │ │
│  │  Route: /         │  │  Route: /admin       │ │
│  └───────────────────┘  └──────────────────────┘ │
│                                                  │
│     Shared: Models · Policies · Business Logic   │
└──────────────────────────────────────────────────┘
```

**Admin MVP feature set:**

| Resource     | Filament Approach                       |
| ------------ | --------------------------------------- |
| Products     | Resource (list, create, edit, delete)   |
| Orders       | Resource with order status management   |
| Users        | Resource with role scoping              |
| Transactions | Read-only Resource                      |
| Reports      | Stats widgets + chart widgets           |
| Dashboard    | Filament dashboard with summary widgets |

---

## 7. Migration Path

When the customer portal is ready to migrate to Next.js, the steps are scoped and mechanical:

1. Remove `inertia-laravel` package
2. Convert controllers from `Inertia::render()` to JSON API responses (or add parallel API routes)
3. Move React page components to the Next.js project — component logic is reusable as-is
4. Filament, auth, models, and all business logic remain unchanged

Inertia-specific surface area is thin: `HandleInertiaRequests` middleware, `Inertia::render()` calls in controllers, and Inertia client imports (`<Link>`, `useForm`, `router`) in React components. This is a well-scoped, low-risk migration.

---

## 8. Consequences

### Positive

- Admin MVP delivered in a fraction of the time compared to a custom React build
- Non-technical operator gets a polished, immediately usable interface with no training required
- Author stays in PHP/Laravel for all admin work, compounding productivity
- Filament is completely isolated from the future Next.js migration — no rework required
- Customer portal React components are directly reusable when migrating to Next.js

### Negative / Tradeoffs Accepted

- Two frontend paradigms exist in the codebase (React and Blade/Livewire); onboarding a new developer requires familiarity with both
- Admin UI customization beyond Filament's defaults requires Livewire knowledge rather than React
- No shared UI components between customer portal and admin (accepted — the portals have no overlapping UI requirements)

---

## 9. Open Questions

| #   | Question                                                                               | Owner               | Priority  | Blocks                                   |
| --- | -------------------------------------------------------------------------------------- | ------------------- | --------- | ---------------------------------------- |
| 1   | Separate admin authentication guard or shared users table with role/permission column? | Boonyarit Iamsa-ard | 🔴 High   | User model design, Filament installation |
| 2   | Next.js migration — standalone API versioning strategy                                 | Boonyarit Iamsa-ard | 🟡 Medium | Deferred; not near-term                  |

---

## 10. Decision Log

| Date       | Decision                                           | Rationale                                                                                                                                 |
| ---------- | -------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| 2026-03-22 | Filament v5 selected for admin portal              | Standard CRUD back-office, non-technical operator, author's backend background, and Next.js migration durability all converge on Filament |
| 2026-03-22 | Inertia v2 + React 19 retained for customer portal | Brand-first storefront requires custom design; React components reusable in future Next.js migration                                      |
| 2026-03-22 | Shared UI between portals explicitly rejected      | No overlapping UI surface identified; portals serve distinct audiences with distinct UX goals                                             |
