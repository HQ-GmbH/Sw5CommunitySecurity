# Security Policy

## Project Scope & Maintenance Status

This project is an **independent open-source project initiated and currently maintained by HQ GmbH** (open-source security plugin for Shopware 5), open to community contributions. It is free of charge and not a product of shopware AG. Project lead and maintainer is Mathias Goldhan, HQ GmbH (GitHub: `hqgmbh`); commercial support for Shopware 5 shops is offered separately as [HQ GmbH – Shopware 5 Wartung & Support](https://www.hqgmbh.de/unsere-leistungen/shopware-5-wartung-support/). It is provided on a **best-effort basis** without commercial Service Level Agreements (SLAs) or guaranteed response times.

## Supported Versions

Security patches are only provided for the latest release of this plugin, targeting Shopware `5.7.19` / `5.7.20` (PHP 8.1/8.2) and Shopware `5.6.10` (PHP 7.4). Please always update to the latest release.

| Plugin Version  | Target Shopware Version | Supported          |
| --------------- | ----------------------- | ------------------ |
| Latest release  | 5.7.19 / 5.7.20, 5.6.10 | :white_check_mark: |
| Older releases  | –                       | :x:                |

## Reporting a Vulnerability

**Please do NOT report security vulnerabilities through public GitHub issues, discussions, or pull requests.**

If you discover a security vulnerability within this plugin—or an unpatched vulnerability in Shopware 5 that could be mitigated by this plugin—please report it privately so we can develop and test a patch before public disclosure:

1. **Preferred Method (GitHub):** Use GitHub's [Private Vulnerability Reporting](https://github.com/HQ-GmbH/Sw5CommunitySecurity/security/advisories/new) feature directly in this repository.
2. **Alternative Method (Email):** Send a report to **[security@hqgmbh.de](mailto:security@hqgmbh.de)**.

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
