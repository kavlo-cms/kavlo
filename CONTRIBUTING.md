# Contributing

## Getting Started

1. Fork the repository and create a feature branch from `main`.
2. Install dependencies:

   ```bash
   composer install
   npm install
   ```

3. Prepare the app:

   ```bash
   cp .env.example .env
   php artisan key:generate
   php artisan migrate
   ```

4. Run the development environment:

   ```bash
   composer dev
   ```

## Community Expectations

- Be respectful and constructive in issues, pull requests, and discussions.
- Follow the project [Code of Conduct](CODE_OF_CONDUCT.md).

## Development Guidelines

- Keep changes focused and avoid unrelated cleanup.
- Follow existing Laravel, Vue, and TypeScript patterns in the repository.
- Reuse shared helpers before adding new abstractions.
- Update documentation when behavior or workflows change.

## Before Opening a Pull Request

Run the existing checks relevant to your change:

```bash
npm run lint:check
npm run types:check
npm run build
./vendor/bin/phpunit
```

If your environment cannot run the full PHP test suite, document that clearly in the pull request.

## Pull Request Notes

Include:

- a short summary of the change
- screenshots for UI updates when relevant
- migration, config, or environment changes
- any known follow-up work

## Reporting Bugs

When opening a bug report, include:

- expected behavior
- actual behavior
- reproduction steps
- relevant logs or screenshots
- version, branch, or commit information
