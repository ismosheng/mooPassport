# Moo Passport database

The initial schema targets MySQL 8.0+ and uses the `moo_` table prefix. It
contains 18 tables covering accounts, verification, MFA, OAuth 2.1 and OIDC.

## Import

Create a dedicated database, select it, and then run:

```powershell
mysql -u root -p moo_passport < database/schema.sql
```

The schema deliberately stores hashes for session IDs, authorization codes,
access tokens, refresh tokens, and client secrets. Plaintext secret values must
only be returned once at creation time.

Apply or refresh the UTF-8 Chinese table and column comments after importing the
schema:

```powershell
php database/apply_comments.php
```

## Supported integration modes

1. Interactive user login: OpenID Connect Authorization Code with PKCE (`S256`).
2. API access:
   - Use the user's access token when an API call acts on behalf of a user.
   - Use Client Credentials only for service-to-service calls without a user.

Do not accept a user's password through a third-party API client. The OAuth 2.1
password grant is intentionally not supported.
