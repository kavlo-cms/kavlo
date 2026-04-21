# Security Policy

## Supported Versions

Security fixes are currently provided for the latest release line only.

| Version | Supported |
| --- | --- |
| 1.x | Yes |
| < 1.0.0 | No |

## Reporting a Vulnerability

Please do **not** open public GitHub issues for security vulnerabilities.

Instead, report vulnerabilities privately to the maintainers with:

- a description of the issue
- affected version or commit
- reproduction steps or proof of concept
- any suggested mitigation, if known

If you do not already have a private reporting channel set up for this repository, add one before publishing `1.0.0` (for example, GitHub Security Advisories or a dedicated security email address).

## Response Expectations

Maintainers should aim to:

1. Acknowledge the report promptly.
2. Confirm impact and affected versions.
3. Prepare and release a fix.
4. Publish remediation guidance once users can safely act on it.

## Scope

Security reports are especially valuable for:

- authentication and authorization flaws
- privilege escalation in admin routes
- plugin or theme execution issues
- file upload or archive extraction issues
- backup or API key exposure
- GraphQL or public endpoint data leaks
