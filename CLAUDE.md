# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Fluxio is an open-source CRM/ERP prototype whose differentiator is a **natural-language → action proposal → confirm → execute** flow (the `Actions` module is the core innovation). The repo is an architectural showcase, not a finished product. MVP scope is intentionally narrow: Leads, Tasks, Actions, minimal dashboard. Multi-tenancy, external integrations, reporting, and complex permissions are explicitly out of scope.

## Repo Layout

Monorepo with two top-level trees:

- `apps/api` — Laravel 12 application (PHP ^8.2). This is the runnable host that mounts the modules.
- `apps/web` — frontend (currently empty placeholder).
- `packages/<Module>` — modular-monolith domain packages, each a standalone Composer package consumed via a path repository declared in `apps/api/composer.json` (`repositories.fluxio-path` → `../../packages/*`).

Existing module packages: `Core`, `Identity`, `Leads`, `Tasks`, `Calendar`, `Analytics`, `Notifications`. Only `Calendar` and `Analytics` currently have scaffolding (provider + routes + migration); others are placeholders.

## Module Convention

Every module is a Composer package `fluxio/<name>` autoloaded under `Fluxio\<Name>\` (PSR-4 from `src/`). It registers itself through Laravel package discovery via a service provider at `src/Providers/<Name>ServiceProvider.php` whose `boot()` does two things:

1. Loads `routes/api.php` under middleware `api` and prefix `api/<name>` (see `packages/Calendar/src/Providers/CalendarServiceProvider.php:10`).
2. Calls `loadMigrationsFrom(__DIR__ . '/../../database/migrations')`.

When adding a new module, mirror this exact shape so package discovery and migrations work without changes to `apps/api`. Add the module to `apps/api/composer.json` `require` (e.g. `"fluxio/<name>": "^0.1"`) and run `composer update` inside `apps/api` to symlink it.

## Architectural Rules (from README)

- **No direct cross-domain calls.** Modules communicate via events/listeners, not by importing each other's services or models. Prefer `dispatch(new CreateTaskFromLead(...))` over `Lead::createTaskDirectly()`.
- **Domain ownership:** each module owns its models, migrations, routes, services, and events. Don't reach across boundaries.
- **Modular first, microservices later** — keep boundaries crisp now so extraction is possible later, but don't actually split services.
- **Actions flow** is the reason this project exists: input text → intent resolution → entity extraction → schema validation → proposal → confirmation → execution. The first concrete use case is "create task from natural language" with a rule-based parser. Don't add AI/LLM dependencies before the rule-based path is in place.

## Commands

All commands run from `apps/api` unless noted.

- `composer dev` — runs server + queue listener + log tail (`pail`) + Vite concurrently. This is the primary local-dev entry point.
- `php artisan serve` — HTTP server only.
- `php artisan migrate` — applies migrations from the API app **and** every module's `database/migrations` directory (autoloaded by each provider).
- `composer test` — clears config then runs `php artisan test` (PHPUnit 11). To run a single test: `php artisan test --filter=<TestName>` or `php artisan test tests/Feature/Path.php`.
- `./vendor/bin/pint` — code style (Laravel Pint).

## Notes for Future Edits

- The root `packages/composer.json` is a stale/template file (name `fluxio/`, namespace `Fluxio\\\\`). Real modules live in `packages/<Module>/composer.json`. Don't treat the root file as authoritative.
- `apps/api/composer.json` only lists modules that have been scaffolded (`fluxio/analytics`, `fluxio/calendar`). Adding a require entry is required for a new module to be installed.
- Prefer editing existing module scaffolding over creating new top-level directories — the layout is deliberately uniform.
