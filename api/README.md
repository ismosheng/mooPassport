# Moo Passport API

This directory contains the Webman backend for Moo Passport, including the
account API, OAuth 2.1 and OpenID Connect endpoints, administration API,
database installer, and backend tests.

See the [project README](../README.md) for installation and deployment. Backend
architecture and contribution rules are documented in
[docs/DEVELOPMENT.md](docs/DEVELOPMENT.md).

## Quality checks

```bash
composer install
composer check
composer audit
```

Moo Passport is licensed under the [MIT License](../LICENSE).

