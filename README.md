# Community Security Guard for Shopware 5

> **Hinweis / Notice:** Dieses Projekt ist ein unabhängiges Open-Source-Community-Projekt der [HQ GmbH – Shopware Agentur](https://www.hqgmbh.de/unsere-leistungen/shopware-agentur/) und steht in keiner Verbindung zur **shopware AG**. „Shopware“ ist eine eingetragene Marke der shopware AG.

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](./LICENSE)
[![Shopware](https://img.shields.io/badge/Shopware-5.7.19%2B-189eff.svg)](#kompatibilit%C3%A4t--compatibility)
[![PHP](https://img.shields.io/badge/PHP-8.1%20%7C%208.2-777bb4.svg)](#kompatibilit%C3%A4t--compatibility)

Ein unabhängiges Open-Source-Security-Plugin für **Shopware 5.7.19+**, das schrittweise Absicherungen für Shopware-5-Shops bereitstellt – ohne Eingriffe in die Shopware-Core-Dateien. Die aktuelle Version enthält das Modul **HTTP-Security-Header**; weitere Module sind in Planung (siehe [Features](#features)).

*An independent open-source security plugin for **Shopware 5.7.19+** that adds hardening for Shopware 5 shops step by step, without modifying Shopware core files. The current version contains the **HTTP security headers** module; further modules are planned (see [Features](#features)).*

---

## Features

**Verfügbar / Available:**

* **HTTP-Security-Header:** Setzt `X-Content-Type-Options`, `X-Frame-Options` und `Referrer-Policy`, optional `Strict-Transport-Security` (nur über HTTPS). Bereits von Shopware, dem Theme oder anderen Plugins gesetzte Header werden nicht überschrieben.
* **Modularer Aufbau:** Jedes Modul ist gekapselt und lässt sich pro Shop im Shopware-Backend einzeln deaktivieren.
* **Ohne Core-Eingriffe:** Das Plugin arbeitet ausschließlich über Shopware-Events und -Services und verändert keine Core-Dateien. Es ist auf Kompatibilität mit individuellen Themes und Drittanbieter-Plugins ausgelegt; eine Garantie für jede Kombination kann nicht gegeben werden – bitte vorab in einer Staging-Umgebung testen.

**Geplant / Planned** *(noch nicht enthalten / not yet included)*:

* **Request- & Input-Hardening:** Frühzeitiges Blockieren bekannter Angriffsmuster (z. B. Smarty-SSTI-Payloads, Path-Traversal-Versuche).
* **Absicherung bekannter Schwachstellen:** Gezielte Fixes für Shopware-5-Sicherheitslücken, sobald sie umgesetzt und getestet sind; jeder Fix wird im [CHANGELOG](./CHANGELOG.md) dokumentiert.

---

## Kompatibilität / Compatibility

* **Shopware:** `5.7.19` / `5.7.20`
* **PHP:** `8.1` – `8.2` *(PHP 8.3 & 8.4 Support siehe Roadmap)*

---

## Installation

### Option A: Installation via Composer (Empfohlen)

Das Paket hat den Typ `shopware-plugin` und wird über [`composer/installers`](https://github.com/composer/installers) nach `custom/plugins/Sw5CommunitySecurity/` installiert. Seit Composer 2.2 muss das Shop-Projekt dieses Composer-Plugin ausdrücklich erlauben, sonst landet das Plugin in `vendor/` und Shopware findet es nicht:

```bash
composer config allow-plugins.composer/installers true
```

Anschließend installieren und aktivieren:

```bash
composer require hqgmbh/sw5-community-security
bin/console sw:plugin:refresh
bin/console sw:plugin:install --activate Sw5CommunitySecurity
bin/console sw:cache:clear
```

> **Hinweis:** Solange das Paket noch nicht auf Packagist veröffentlicht ist, muss das GitHub-Repository vorher als Paketquelle eingetragen werden:
> `composer config repositories.sw5-community-security vcs https://github.com/HQ-GmbH/Sw5CommunitySecurity`

*Composer installs the package (type `shopware-plugin`) into `custom/plugins/Sw5CommunitySecurity/` via `composer/installers`. Since Composer 2.2 the shop project must allow that Composer plugin first (`composer config allow-plugins.composer/installers true`), otherwise the plugin ends up in `vendor/` and Shopware will not find it.*

### Option B: Installation über den Plugin-Manager (ZIP)
1. Lade das aktuelle Release-ZIP unter [Releases](../../releases) herunter.
2. Öffne im Shopware-Backend **Einstellungen → Plugin-Manager → Installiert**.
3. Klicke auf **Plugin hochladen**, wähle die ZIP-Datei aus und aktiviere das Plugin.
4. Leere anschließend den Shop-Cache.

---

## Roadmap: Zukunftssicherheit für Shopware 5

Dieses Plugin ist der erste Baustein einer mehrstufigen Open-Source-Initiative zum sicheren Weiterbetrieb bestehender Shopware-5-Projekte:
1. **Phase 1 (im Aufbau):** Security-Hardening über dieses Plugin (`Sw5CommunitySecurity`), beginnend mit dem Modul HTTP-Security-Header.
2. **Phase 2 (in Vorbereitung, separates Projekt):** Kompatibilität des Shopware-5.7-Kerns mit **PHP 8.3** und **PHP 8.4**.

*Die Roadmap beschreibt Ziele, keine zugesicherten Funktionen oder Termine. / The roadmap describes goals, not promised features or dates.*

Detaillierter Plan mit allen bekannten Befunden: [ROADMAP.md](./ROADMAP.md)

---

## Entwickelt & gepflegt von / Maintained by

Dieses Open-Source-Projekt wird entwickelt und gepflegt von der **[HQ GmbH – Shopware Agentur](https://www.hqgmbh.de/unsere-leistungen/shopware-agentur/)**.  
Wir unterstützen Händler und Unternehmen bei der technischen Betreuung, Sicherheitswartung von Shopware 5 sowie der strategischen Migration auf Shopware 6.

*This open-source project is developed and maintained by **[HQ GmbH – Shopware Agency](https://www.hqgmbh.de/unsere-leistungen/shopware-agentur/)**, specializing in Shopware 5 security maintenance and Shopware 6 migrations.*

**Impressum / Legal notice:** [www.hqgmbh.de/impressum](https://www.hqgmbh.de/impressum/)

---

## Rechtliche Hinweise & Haftungsausschluss (Legal Notice & Disclaimer)

### Markenrechtlicher Hinweis / Trademark Notice
Dieses Projekt ist ein **unabhängiges Open-Source-Community-Projekt**. Es wird weder von der **shopware AG** entwickelt, gepflegt noch offiziell unterstützt oder zertifiziert. „Shopware“ ist eine eingetragene Marke der shopware AG. Die Nennung dient ausschließlich der Beschreibung der Kompatibilität dieses Plugins.

*This project is an **independent, community-driven open-source project**. It is not developed, maintained, endorsed, or certified by **shopware AG**. "Shopware" is a registered trademark of shopware AG and is used here solely to describe compatibility.*

### Haftungsausschluss / Disclaimer of Liability
Die Bereitstellung dieses Plugins und aller enthaltenen Sicherheits-Patches erfolgt unentgeltlich unter den Bedingungen der **GNU General Public License v3.0 (GPLv3)**, ausschließlich in Version 3 (SPDX: `GPL-3.0-only`) (siehe Datei [`LICENSE`](./LICENSE)).

Die Software wird **„wie besehen“ (AS IS)** ohne jegliche ausdrückliche oder stillschweigende Gewährleistung zur Verfügung gestellt. Die Autoren und Mitwirkenden übernehmen keinerlei Haftung für:
* die vollständige Sicherheit des Zielsystems oder den Schutz vor unbekannten/neuen Schwachstellen (Zero-Days),
* direkte oder indirekte Schäden, Datenverluste oder Betriebsunterbrechungen, die aus der Nutzung oder der Unmöglichkeit der Nutzung dieser Software entstehen,
* Inkompatibilitäten mit individuellen Anpassungen, Themes oder Drittanbieter-Plugins.

Unberührt bleibt die Haftung für Vorsatz und grobe Fahrlässigkeit sowie jede Haftung, die nach zwingendem Recht nicht ausgeschlossen werden kann. Updates sollten stets zuerst in einer Staging-Umgebung getestet und vor dem Einsatz ein aktuelles Backup erstellt werden.

*The software is provided **"AS IS"**, without warranty of any kind, express or implied. To the extent permitted by applicable law, the authors and contributors accept no liability for the complete security of the target system or protection against unknown/new vulnerabilities (zero-days), for direct or indirect damages, data loss or business interruption arising from the use of or inability to use this software, or for incompatibilities with customisations, themes or third-party plugins. Liability for intent and gross negligence, and any liability that cannot be excluded under mandatory law, remains unaffected. Always test updates in a staging environment and create a current backup before deployment.*
