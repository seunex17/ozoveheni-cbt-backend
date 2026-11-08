# Laravel 13 upgrade

Upgrade reference: https://laravel.com/docs/13.x/upgrade

## Dependency changes

- Laravel framework: 12.49.0 → 13.40.0 (`^13.0`).
- Tinker: 2.11.0 → 3.0.2 (`^3.0`).
- Application PHP constraint: `^8.2` → `^8.3`.
- Resolve Laravel-compatible releases of Inertia, Sanctum, Reverb, FastExcel, Debugbar, Larastan, Boost, Sail, Pail, Collision and Pest's Laravel plugin, with required transitive dependencies. Pest remains on major version 4 and PHPUnit on major version 12.
- Apply compatible security patches to Guzzle, Flysystem, Sodium Compat and Symfony dependencies identified by Composer audit.
- PHPStan is locked to compatible version 2.3.1, available in the local download cache; downloading 2.3.5 timed out. No root dependency pin was added.
- Keep frontend dependency constraints and lockfile unchanged.

The committed Composer lockfile was resolved on PHP 8.5.12. Although Laravel supports PHP 8.3, this lockfile includes Symfony 8 dependencies requiring PHP 8.4 or newer, as the previous lockfile also did. Check the deployment server with `composer check-platform-reqs --no-dev`; do not bypass platform requirements.

## Compatibility changes

- Refresh the repository's tracked package/service discovery manifests for the upgraded providers.
- Update Sanctum's configured CSRF middleware to `PreventRequestForgery`.
- Explicitly retain PHP session serialization so existing session payloads remain readable. Existing session cookie names, cache prefixes and Redis prefixes are already explicitly configured and remain unchanged.
- Set cache `serializable_classes` to `false`. Application code contains no object-cache writes or custom cache store implementations requiring an allow-list.
- No affected application uses were found for empty-conflict-key upserts, renamed queue event properties, custom manager extensions, domain routes, legacy pagination views, or model boot-time instantiation.
- Normalize the report registration-number sort direction from `ASC` to `asc` for the updated query-builder type contract; ordering is unchanged.
- Correct the existing report test expectation from `passed` to `Passed`, matching the service output before the upgrade. Report logic is unchanged.
- Exam endpoints, scoring formulas, database schema and report layouts are unchanged by this upgrade.

## Deployment and rollback

1. Deploy separately from exam API optimization changes and rehearse on a restored test database before an exam.
2. Verify the web/CLI PHP versions and extensions satisfy the lockfile, and take the usual application/database backup.
3. Install the committed lockfile with `composer install --no-dev --optimize-autoloader`; build frontend assets using the existing npm lockfile.
4. Rebuild configuration, route and view caches using the normal deployment process. Restart long-running Reverb, queue and FrankenPHP workers so they use the upgraded code.
5. Smoke-test staff login, exam-client login/loading/saving/submitting, Reverb, report printing and Excel import on the deployment environment.

No database migrations were added. Rollback requires restoring the previous code and Composer lockfile, reinstalling its dependencies, rebuilding application caches and restarting long-running processes. Keep session serialization and cookie names consistent in both versions.

## Local verification

- `php artisan --version`: Laravel Framework 13.40.0.
- `php artisan test`: 21 tests pass, 47 assertions, including all six exam-client API regression tests and three new upgrade tests.
- `npm run build`: passes; existing Svelte configuration and CSS `@property` warnings remain.
- `composer validate --no-check-publish` and Composer platform checks: pass on PHP 8.5.12.
- Composer dependency installation and package discovery: pass. Composer audit reports no security vulnerability advisories.
- Route, configuration and view cache compilation: pass. Generated route/configuration/view caches were cleared after verification; tracked package/service discovery manifests remain updated.
- Pint formatting and `git diff --check`: pass.
- Static analysis still reports the same 10 pre-existing findings in ExaminationController, DashboardController, the dashboard ExamController and StudentLevelService. The newly surfaced report sort-direction finding was fixed. No suppressions or baseline entries were added.

These checks do not replace server load testing or a manual check of printing, Excel import and Reverb on the school's deployment environment.
