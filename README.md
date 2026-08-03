# Mindflex matchmaking

Internal admin dashboard and JSON endpoint that match students with tutors.
PHP 8.2, SQLite, no framework.

`index.php` and `api_legacy.php` keep their paths, and the actions `get_tutors`,
`update_rate`, and `match_student` keep working. Existing clients do not break.

## Run it

    composer setup
    composer serve

Open http://localhost:8000 and sign in with `admin` / `mindflex-admin`.
CONTRIBUTING.md covers the rest.

## Refactoring strategy

Four passes, one commit each. Read `git log` to follow them.

1. Reproducible setup. Composer, PSR-4 autoloading, and SQL migrations replaced a repo with
   no manifest and a binary database nobody could rebuild.
2. Fix the schema. Most defects traced back to the data model, so it moved first.
3. Move rules out of the page. The dashboard and the API now share the same services, so a
   rule cannot guard one and skip the other.
4. Prove it. 43 tests, PHPStan level 8, Pint.

### Why this architecture

    public/         entry points, the only web accessible folder
    src/Model       readonly objects that carry domain behaviour
    src/Repository  every SQL statement
    src/Service     business rules shared by both entry points
    src/Http        request, response, controllers, view rendering
    database/       migrations and seed data as reviewable SQL

Repository plus service, not an ORM. Queries here are read heavy and report shaped. Hand
written SQL in one layer stays easier to read and tune, and the dashboard now needs 2
queries where the old page ran dozens.

Readonly models, not arrays. `$assignment->weeklyCost()` keeps the pricing rule beside the
data it uses. Array keys spread that rule across the controller and the template.

Integer cents, not `REAL`. Float cents drift once you sum them, and this system sums them on
every page load.

No router, no DI container, no framework. A 40 line `Container` and a `match` statement
cover the routing this app needs. A framework would bury the domain logic under scaffolding.

## Flaws found and resolved

Retroactive pricing. Assignments stored no rate, so every report read the tutor's current
rate. Three active matches bill $296.50 per week in the seed data. Raise Alice Smith from
$45.00 to $99.99 and the old dashboard reports $406.48 for those same three matches.
`assignments.hourly_rate_cents` now freezes the rate agreed on day one.

Subject matching returned the wrong people. `LIKE '%Science%'` also matched Computer
Science. In the seed data that returns 15 active tutors and none of them teach Science.
Subjects moved into a catalog table with a pivot, matched on an exact slug.

Budget was measured then ignored. `match_student` set `exceeds_budget` to true and still
returned `match_found: true`. Assignment #3 pays $100.00 per week against a $60.00 budget,
67 percent over. A match now fails when its cost passes the remaining budget, where
remaining subtracts every running match.

Twelve SQL statements built by concatenation, nine of them fed by request input. All use
prepared statements now.

No authentication, and delete ran over a GET link. Mutations need a session, POST, and a
CSRF token.

Cancel ran `DELETE` and took revenue history with it. Cancel sets a status now.

`match_score` was hardcoded to `1.0`. The score now weighs rating 40 percent, budget fit 35
percent, and free capacity 25 percent, with Bayesian smoothing so one five star review does
not outrank 50 reviews at 4.8.

Ratings came from the signup form. They come from `tutor_reviews` now.

No capacity model. `tutors.max_weekly_hours` defaults to 40 and blocks overbooking.

N plus one queries on every dashboard load. Both loops became single joined queries.

The template printed 35 values and escaped 7. Everything passes through `e()` now.

Also fixed: `display_errors` always on, executed SQL printed to the page, `mindflex.db`
downloadable from the web root, server local timestamps, HTTP 200 for every API outcome,
empty catch blocks, no unique email, no foreign keys, no indexes.

## Composer dependencies

vlucas/phpdotenv, runtime. Config was hardcoded across two files. This reads `.env` once at
boot and keeps secrets out of the repo.

phpunit/phpunit, dev. Tests run on an in memory SQLite database built from the real
migrations, so they cover the schema you ship.

phpstan/phpstan, dev, level 8. It caught real nullability gaps. The codebase passes clean.

laravel/pint, dev. Zero config PSR-12 formatting.

## How I used AI

I used Warp with Claude as a pair, not as an author.

It drafted mechanical work: the recursive CTE that splits the old CSV `subjects` column, the
repetitive view partials, and first drafts of tests once I described the rules.

I kept the decisions. The layering, the rate snapshot, the budget rule that counts running
commitments, the Bayesian smoothing on ratings, and keeping `api_legacy.php` as a
compatibility shim are mine.

I verified instead of trusting. I profiled the shipped SQLite file before writing any code,
which is where the 15 wrong Science matches and the 67 percent overrun came from. The suite
then caught two defects in AI drafted code: the validator kept the last spelling of a
repeated subject, and a `COALESCE` comparison silently failed because SQLite compared an
integer against a bound string. Both fixes sit in the commit history.

## Known gaps

Auth uses one account from `.env`. Move to a users table when a second admin needs access.

New matches start active. The schema already allows a pending state for a confirmation step.

Reviews have no submission screen. The table and `TutorService::addReview` exist.

Budget logic is weekly only. Real billing needs a period and an invoice model.
