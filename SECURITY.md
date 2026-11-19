# Security Policy

## Project Scope & Maintenance Status

This project is an **independent, community-driven Open Source security plugin** for Shopware 5. It is maintained on a best-effort basis without commercial Service Level Agreements (SLAs) or guaranteed response times.

## Supported Versions

Security patches are only provided for the latest minor/patch release of this plugin, targeting the final Shopware 5.7 branch:

| Plugin Version | Target Shopware Version | Supported          |
| -------------- | ----------------------- | ------------------ |
| 1.x.x (Latest) | Shopware 5.7.19+        | :white_check_mark: |
| < 1.0.0        | Shopware < 5.7.19       | :x:                |

## Reporting a Vulnerability

**Please do NOT report security vulnerabilities through public GitHub issues, discussions, or pull requests.**

If you discover a security vulnerability within this plugin—or an unpatched vulnerability in Shopware 5 that could be mitigated by this plugin—please report it privately so we can develop and test a patch before public disclosure:

1. **Preferred Method (GitHub):** Use GitHub's [Private Vulnerability Reporting](../../security/advisories/new) feature directly in this repository.
2. **Alternative Method (Email):** Send an encrypted or plain-text report to **[security@deine-domain.de]**.

### What to include in your report
* Description of the vulnerability and potential impact (e.g., XSS, SQLi, RCE, CSRF).
* Affected Shopware 5 version(s) and PHP version.
* Step-by-step instructions or a Proof of Concept (PoC) to reproduce the issue.
* Any suggested mitigation or patch ideas (optional).

### Disclosure Process
* We aim to acknowledge receipt of your report within **72 hours**.
* Once verified, we will work on a fix and coordinate a release date with you.
* After the patch is released, we will publish a Security Advisory and credit you (unless you prefer to remain anonymous).

## Disclaimer of Warranty

This software is provided "as is", without warranty of any kind, express or implied. The maintainers do not guarantee that this plugin will protect against all security threats or vulnerabilities. Always test updates in a staging environment before deploying to production.
