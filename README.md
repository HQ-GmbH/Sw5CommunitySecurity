# Community Security Guard for Shopware 5

> **Hinweis / Notice:** Dieses Projekt ist ein unabhängiges Open-Source-Community-Projekt der [HQ GmbH – Shopware Agentur](https://www.hqgmbh.de/unsere-leistungen/shopware-agentur/) und steht in keiner Verbindung zur **shopware AG**. „Shopware“ ist eine eingetragene Marke der shopware AG.

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](./LICENSE)
[![Shopware](https://img.shields.io/badge/Shopware-5.7.19%2B-189eff.svg)](#kompatibilit%C3%A4t--compatibility)
[![PHP](https://img.shields.io/badge/PHP-8.1%20%7C%208.2-777bb4.svg)](#kompatibilit%C3%A4t--compatibility)

Ein unabhängiges Open-Source-Security-Plugin für **Shopware 5.7.19+**, das bekannte Sicherheitslücken absichert, unsichere Request-Parameter filtert und moderne HTTP-Security-Header durchsetzt – ohne Eingriffe in den Shopware-Core.

*An independent open-source security plugin for **Shopware 5.7.19+** providing vulnerability mitigations, input sanitization, and HTTP security headers without modifying Shopware core files.*

---

## Features

* **Modulare Security-Fixes:** Jeder Patch ist gekapselt und lässt sich bei Bedarf einzeln im Shopware-Backend deaktivieren.
* **Request- & Input-Hardening:** Frühzeitiges Blockieren bekannter Angriffsmuster (z. B. Smarty-SSTI-Payloads, Path-Traversal-Versuche) über Events und Service-Decorators.
* **HTTP Security-Header:** Automatische Durchsetzung von `X-Content-Type-Options`, `X-Frame-Options` und `Referrer-Policy`.
* **Kein Core-Hack:** 100 % kompatibel mit individuellen Themes und Drittanbieter-Plugins.

---

## Kompatibilität / Compatibility

* **Shopware:** `5.7.19` / `5.7.20`
* **PHP:** `8.1` – `8.2` *(PHP 8.3 & 8.4 Support siehe Roadmap)*

---

## Installation

### Option A: Installation via Composer (Empfohlen)
```bash
composer require hqgmbh/sw5-community-security
bin/console sw:plugin:refresh
bin/console sw:plugin:install --activate Sw5CommunitySecurity
bin/console sw:cache:clear
```

### Option B: Installation über den Plugin-Manager (ZIP)
1. Lade das aktuelle Release-ZIP unter [Releases](../../releases) herunter.
2. Öffne im Shopware-Backend **Einstellungen → Plugin-Manager → Installiert**.
3. Klicke auf **Plugin hochladen**, wähle die ZIP-Datei aus und aktiviere das Plugin.
4. Leere anschließend den Shop-Cache.

---

## Roadmap: Zukunftssicherheit für Shopware 5

Dieses Plugin ist der erste Baustein einer mehrstufigen Open-Source-Initiative zum sicheren Weiterbetrieb bestehender Shopware-5-Projekte:
1. **Phase 1 (Aktiv):** Laufende Security-Patches, Hardening und Sicherheits-Checks über dieses Plugin (`Sw5CommunitySecurity`).
2. **Phase 2 (In Planung):** Bereitstellung automatisierter Composer-Kompatibilitätspatches für **PHP 8.3** und **PHP 8.4**.

---

## Entwickelt & gepflegt von / Maintained by

Dieses Open-Source-Projekt wird entwickelt und gepflegt von der **[HQ GmbH – Shopware Agentur](https://www.hqgmbh.de/unsere-leistungen/shopware-agentur/)**.  
Wir unterstützen Händler und Unternehmen bei der technischen Betreuung, Sicherheitswartung von Shopware 5 sowie der strategischen Migration auf Shopware 6.

*This open-source project is developed and maintained by **[HQ GmbH – Shopware Agency](https://www.hqgmbh.de/unsere-leistungen/shopware-agentur/)**, specializing in Shopware 5 security maintenance and Shopware 6 migrations.*

---

## Rechtliche Hinweise & Haftungsausschluss (Legal Notice & Disclaimer)

### Markenrechtlicher Hinweis / Trademark Notice
Dieses Projekt ist ein **unabhängiges Open-Source-Community-Projekt**. Es wird weder von der **shopware AG** entwickelt, gepflegt noch offiziell unterstützt oder zertifiziert. „Shopware“ ist eine eingetragene Marke der shopware AG. Die Nennung dient ausschließlich der Beschreibung der Kompatibilität dieses Plugins.

*This project is an **independent, community-driven open-source project**. It is not developed, maintained, endorsed, or certified by **shopware AG**. "Shopware" is a registered trademark of shopware AG and is used here solely to describe compatibility.*

### Haftungsausschluss / Disclaimer of Liability
Die Bereitstellung dieses Plugins und aller enthaltenen Sicherheits-Patches erfolgt unentgeltlich unter den Bedingungen der **GNU General Public License v3.0 (GPLv3)** (siehe Datei [`LICENSE`](./LICENSE)).

Die Software wird **„wie besehen“ (AS IS)** ohne jegliche ausdrückliche oder stillschweigende Gewährleistung zur Verfügung gestellt. Die Autoren und Mitwirkenden übernehmen keinerlei Haftung für:
* die vollständige Sicherheit des Zielsystems oder den Schutz vor unbekannten/neuen Schwachstellen (Zero-Days),
* direkte oder indirekte Schäden, Datenverluste oder Betriebsunter
