# Contributing

1. Fork the repository and create a branch from `main`.
2. Run `composer update` with PHP 8.2 or newer.
3. Add a Pest test for the behavior you change.
4. Run `composer test`, `composer analyse`, and `composer format:fix`.
5. Open a pull request that explains the user-facing change.

Keep the core free of a Filament `require`. Version-specific Filament code belongs in `src/Filament/V3` or `src/Filament/V4`. Do not add a database query inside the matrix checkbox path; the page saves one diff.
