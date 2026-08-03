# Contributing

## Requirements

PHP 8.2 or newer with `pdo_sqlite` and `mbstring`, plus Composer 2. Nothing else.

    php -v && php -m | grep -E 'pdo_sqlite|mbstring' && composer --version

No PHP locally? Run `docker compose up` instead.

## Setup

    composer setup

That copies `.env.example` to `.env`, installs dependencies, and builds
`storage/mindflex.db` from migrations and seed data.

    composer serve

Sign in at http://localhost:8000 with `admin` / `mindflex-admin`. Replace the password with
`php bin/console hash:password "your-password"` and paste the hash into `ADMIN_PASSWORD_HASH`.

## Commands

    composer serve      dashboard on port 8000
    composer test       PHPUnit
    composer analyse    PHPStan level 8
    composer lint       check style
    composer format     fix style
    composer check      lint, analyse, and test together
    composer fresh      rebuild the database and reload demo data

Run `composer check` before every commit. CI runs the same command on PHP 8.2 and 8.3.
`make help` lists the same targets.

## Database

Schema lives in `database/migrations` as numbered SQL files. Demo data lives in
`database/seeds`.

Add a change as the next numbered file, then run `php bin/console migrate`. The runner wraps
each file in a transaction, records it in `schema_migrations`, and runs
`PRAGMA foreign_key_check` at the end. Never edit a migration that already shipped.

`php bin/console status` shows applied migrations and row counts.

`php bin/console migrate:legacy` upgrades a copy of the untouched legacy database in
`database/legacy/`. Use it to confirm the migration path keeps all 100 tutor rows.

## Layers

    public/         entry points, the only folder a web server should expose
    src/Model       readonly domain objects
    src/Repository  SQL statements
    src/Service     business rules
    src/Http        request, response, controllers, view rendering
    src/Support     config, database, money, validation, session, csrf
    views/          templates
    tests/          Unit and Feature suites

Two rules keep the layers honest.

Write SQL only inside `src/Repository`.

Put every business rule in `src/Service`. The dashboard and the API share those services, so
a rule written in a controller only guards half the system.

## Tests

Extend `Mindflex\Tests\DatabaseTestCase` when a test touches the database. It builds an in
memory SQLite database from the real migrations and gives you `makeTutor`, `makeStudent`,
and `subjectId`. Extend `PHPUnit\Framework\TestCase` for pure logic.

Name tests after the behaviour:

    public function test_it_blocks_a_match_that_passes_the_student_budget(): void

Cover every business rule you add or change.

## Style

PSR-12 with `declare(strict_types=1)` on every file. Pint enforces it.

Name variables in plain English. Use `$weeklyHours`, not `$wh`.

Write code comments in Indonesian, only where the reason is not obvious, and explain why
rather than what. Write user facing text and commit subjects in English.

## Commits

One change per commit. Prefix with `feat`, `fix`, `refactor`, `test`, `build`, `ci`, or
`docs`. Keep the subject under 72 characters and use the body to explain the reason.

## Review checklist

Every query uses a prepared statement.
Every template value passes through `e()`.
Every mutation uses POST and carries a CSRF token.
Every business rule sits in a service, so the API cannot skip it.
No secret reaches the repository.
