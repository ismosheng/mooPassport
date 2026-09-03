# Contributing to Moo Passport

Thank you for helping improve Moo Passport. Before starting a substantial
change, open an issue describing the behavior, security impact, and intended
compatibility so implementation work can be coordinated.

## Development setup

Follow the local installation steps in [README.md](README.md). Backend changes
must follow [api/docs/DEVELOPMENT.md](api/docs/DEVELOPMENT.md), and frontend
changes must follow [web/docs/DESIGN_SYSTEM.md](web/docs/DESIGN_SYSTEM.md).
Repository-specific agent and architecture constraints are also summarized in
[AGENTS.md](AGENTS.md).

## Before submitting

Run the backend checks from `api`:

```bash
composer install
composer check
composer audit
```

The integration tests require an installed test database configured through
`api/.env`. Run the frontend checks from `web`:

```bash
npm ci
npm run build
npm audit --omit=dev
```

Add focused tests for behavior changes, especially OAuth/OIDC protocol errors,
credential handling, transaction boundaries, permissions, and token replay.
Never commit `.env`, runtime logs, production data, generated secrets, or built
dependencies.

## Pull requests

- Keep each pull request focused on one logical goal.
- Use PHP Attributes for routes and preserve standard OAuth/OIDC response forms.
- Document schema and configuration changes, including upgrade and rollback steps.
- Update tests and documentation when behavior changes.
- Use Conventional Commit prefixes such as `feat:`, `fix:`, `refactor:`,
  `test:`, `docs:`, and `chore:`.

By contributing, you agree that your contribution is licensed under the
project's [MIT License](LICENSE).
