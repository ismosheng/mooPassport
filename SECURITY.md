# Security Policy

## Supported versions

Security fixes are applied to the latest release and the default development
branch. Deployments should track a supported release and keep PHP, Composer,
Node.js, MySQL, Redis, and all locked dependencies up to date.

## Reporting a vulnerability

Please do not disclose suspected vulnerabilities in a public issue, discussion,
pull request, log, or screenshot. Use
[GitHub private vulnerability reporting](https://github.com/ismosheng/mooPassport/security/advisories/new)
and include:

- the affected version or commit;
- the impacted endpoint or component;
- reproducible steps or a minimal proof of concept;
- the expected security impact;
- any suggested mitigation, if known.

Do not include real passwords, client secrets, authorization codes, access or
refresh tokens, MFA secrets, private signing keys, or personal data. A report
will be acknowledged after it is reviewed, and coordinated disclosure details
will be agreed before publication.

## Deployment responsibility

Moo Passport handles identity and OAuth credentials. Operators must use HTTPS,
disable production debug output, protect environment files and signing keys,
restrict trusted proxies and CORS origins, and maintain tested database backups.
See the production checklist in the project README before exposing a deployment.
