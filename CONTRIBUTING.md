# Contributing to OdinCMS

Thank you for helping. This page explains how to report problems and send changes.

## Reporting a bug

Open an issue and include:

- what you did, what you expected and what happened instead;
- your PHP, MySQL and OdinCMS versions;
- the error from `storage/logs/laravel.log`, if there is one.

Do **not** report security problems in an issue: see [SECURITY.md](SECURITY.md).

## Setting up

Follow the installation steps in the [README](README.md). The tests also need an empty MySQL database
named `odincms_test` (see `phpunit.xml`).

## Sending a change

1. Fork the repository and create a branch from `main`.
2. Keep each pull request to one change. Open an issue first for large changes or new modules.
3. Add or update tests for what you change.
4. Run the checks and make sure they pass:

   ```bash
   composer test     # Pint (style), Larastan (static analysis), Pest (tests)
   ```

   Run only one test process at a time, because the tests share one database.
5. If you changed CSS or JS, run `npm run build` and check the admin in a browser.
6. Describe what you changed and why in the pull request.

## Conventions

- New features go in a module. Start with `php artisan cms:make:module` and read
  [docs/modules.md](docs/modules.md).
- Every text shown to users is translated in all active languages (en, ru, ky).
  `tests/Feature/Cms/TranslationsTest.php` fails when one is missing.
- Admin pages use the `<x-admin-layout::…>` layouts and `<x-admin.…>` components, not raw template markup.
- Code style is Pint with the Laravel preset (`composer fix` applies it).

By sending a pull request, you agree that your contribution is released under the project's
[MIT license](LICENSE).
