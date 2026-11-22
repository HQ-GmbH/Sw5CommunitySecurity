# Community Security Guard for Shopware 5

> **Hinweis / Notice:** Dieses Projekt ist ein **von der HQ GmbH initiiertes, unabhängiges Community-Projekt**. Es ist weder ein Produkt der HQ GmbH noch der **shopware AG** und steht in keiner Verbindung zur shopware AG. Die Community-Struktur befindet sich im Aufbau; bis dahin betreut die HQ GmbH das Projekt. „Shopware“ ist eine eingetragene Marke der shopware AG.
>
> *This is an **independent community project initiated by HQ GmbH**. It is neither a product of HQ GmbH nor of **shopware AG** and is not affiliated with shopware AG. The community structure is still being built; until then, HQ GmbH maintains the project. "Shopware" is a registered trademark of shopware AG.*

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](./LICENSE)
[![Shopware](https://img.shields.io/badge/Shopware-5.7.19%2B-189eff.svg)](#kompatibilit%C3%A4t--compatibility)
[![PHP](https://img.shields.io/badge/PHP-8.1%20%7C%208.2-777bb4.svg)](#kompatibilit%C3%A4t--compatibility)

Ein Open-Source-Security-Plugin für **Shopware 5.7.19+**, das schrittweise zusätzliche Härtungsmaßnahmen (Hardening) für Shopware-5-Shops bereitstellt – ohne Eingriffe in die Shopware-Core-Dateien. Es verringert bestimmte, dokumentierte Risiken, macht einen Shop aber nicht „sicher“ und ersetzt weder Updates von PHP, Server und Plugins noch eine individuelle Sicherheitsprüfung. Die aktuelle Version enthält die Module **HTTP-Security-Header** und **Template-Guard**; weitere Module sind in Planung (siehe [Features](#features)).

*An open-source security plugin for **Shopware 5.7.19+** that adds additional hardening measures for Shopware 5 shops step by step, without modifying Shopware core files. It reduces specific, documented risks but does not make a shop "secure" and does not replace updates of PHP, server and plugins or an individual security review. The current version contains the **HTTP security headers** and **template guard** modules; further modules are planned (see [Features](#features)).*

---

## Features

**Verfügbar / Available:**

* **HTTP-Security-Header:** Setzt `X-Content-Type-Options`, `X-Frame-Options` und `Referrer-Policy`, optional `Strict-Transport-Security` (nur über HTTPS). Bereits von Shopware, dem Theme oder anderen Plugins gesetzte Header werden nicht überschrieben.
* **Template-Guard:** Lehnt beim Speichern Inhalte ab, die Shopware als Smarty-Vorlage auswertet und die Konstrukte mit bekannten Sicherheitslücken im mitgelieferten Smarty 3.1.12 enthalten:
  * **Geprüfte Konstrukte:** `{extends}`, `{fetch}`, `{php}`, ungültige `{function}`-/`{block}`-Namen, `$smarty.template_object`. Zusätzlich gibt es eine abschaltbare strenge Prüfung von `{include}`-Dateinamen und `{math}`-Formeln.
  * **Geprüfte Inhalte:** E-Mail-Vorlagen, Newsletter, Beleg-Bausteine, SEO-URL-Vorlagen, E-Mail-Header/-Footer, „wesentliche Merkmale“, Zusatzbeschreibungen von Zahlungsarten, Länderhinweise und Produktexporte, jeweils inkl. Übersetzungen.
  * **Speicherwege:** Geprüft wird über das Backend und zusätzlich über Doctrine, das auch REST-API und andere Plugins nutzen.
  * **Bestehende Inhalte:** Der Konsolenbefehl `bin/console sw5cs:template-guard:scan` prüft bereits gespeicherte Inhalte, nur lesend. Er nennt Ort, Feld und Regel, nie den Inhalt, und endet mit Exit-Code 1, wenn er etwas findet.
  * **Verträglichkeit:** Die Standardvorlagen und Grundeinstellungen von Shopware lösen keine Funde aus; gespeicherte Inhalte werden nie verändert.
* **Modularer Aufbau:** Jedes Modul ist gekapselt und lässt sich im Shopware-Backend einzeln deaktivieren.
* **Ohne Core-Eingriffe:** Das Plugin arbeitet ausschließlich über Shopware-Events und -Services und verändert keine Core-Dateien. Es ist auf Kompatibilität mit individuellen Themes und Drittanbieter-Plugins ausgelegt; eine Garantie für jede Kombination kann nicht gegeben werden – bitte vorab in einer Staging-Umgebung testen.

**Geplant / Planned** *(noch nicht enthalten / not yet included)*:

* **Request- & Input-Hardening:** Frühzeitiges Blockieren bekannter Angriffsmuster (z. B. Smarty-SSTI-Payloads, Path-Traversal-Versuche).
* **Schutzmaßnahmen gegen dokumentierte Schwachstellen:** Gezielte Abschwächung einzelner Shopware-5-Sicherheitslücken, sobald sie umgesetzt und getestet sind; jeder Fix wird im [CHANGELOG](./CHANGELOG.md) dokumentiert.

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

## Roadmap: Weiterbetrieb und Hardening für Shopware 5

Dieses Plugin ist der erste Baustein einer mehrstufigen Open-Source-Initiative zum technischen Weiterbetrieb bestehender Shopware-5-Projekte:
1. **Phase 1 (laufend):** Hardening über dieses Plugin (`Sw5CommunitySecurity`). Verfügbar sind die Module HTTP-Security-Header und Template-Guard; weitere Module sind in [ROADMAP.md](./ROADMAP.md) geplant.
2. **Phase 2 (in Vorbereitung, separates Projekt):** Kompatibilität des Shopware-5.7-Kerns mit **PHP 8.3** und **PHP 8.4**.

*Die Roadmap beschreibt Ziele, keine zugesicherten Funktionen oder Termine. / The roadmap describes goals, not promised features or dates.*

Detaillierter Plan mit allen bekannten Befunden: [ROADMAP.md](./ROADMAP.md)

Mitarbeit: Grundregeln in [CONTRIBUTING.md](./CONTRIBUTING.md).

---

## Initiiert von / Initiated by

Das Projekt wurde von der **[HQ GmbH – Shopware Agentur](https://www.hqgmbh.de/unsere-leistungen/shopware-agentur/)** initiiert und wird von ihr betreut, bis die Community-Struktur aufgebaut ist: weitere Maintainer und ein geregelter Ablauf für externe Beiträge. Beiträge sind willkommen; die Regeln stehen in [CONTRIBUTING.md](./CONTRIBUTING.md). Das Projekt ist kein Produkt und keine Dienstleistung der HQ GmbH.

*The project was initiated by **[HQ GmbH – Shopware Agency](https://www.hqgmbh.de/unsere-leistungen/shopware-agentur/)**, which maintains it until the community structure (further maintainers, a defined process for external contributions) is in place. Contributions are welcome; see [CONTRIBUTING.md](./CONTRIBUTING.md). The project is not a product or service of HQ GmbH.*

**Impressum / Legal notice:** [https://www.hqgmbh.de/impressum/](https://www.hqgmbh.de/impressum/)

---

## Rechtliche Hinweise & Haftungsausschluss (Legal Notice & Disclaimer)

### Markenrechtlicher Hinweis / Trademark Notice
Dieses Projekt ist ein **von der HQ GmbH initiiertes, unabhängiges Community-Projekt**. Es wird weder von der **shopware AG** entwickelt, gepflegt noch offiziell unterstützt oder zertifiziert. „Shopware“ ist eine eingetragene Marke der shopware AG. Die Nennung dient ausschließlich der Beschreibung der Kompatibilität dieses Plugins. Die HQ GmbH ist Shopware-Partner; dieses Projekt ist dennoch kein Produkt der shopware AG und wird von ihr weder unterstützt noch zertifiziert.

*This project is an **independent community project initiated by HQ GmbH**. It is not developed, maintained, endorsed, or certified by **shopware AG**. "Shopware" is a registered trademark of shopware AG and is used here solely to describe compatibility. HQ GmbH is a Shopware partner; nevertheless, this project is not a product of shopware AG and is neither endorsed nor certified by it.*

### Haftungsausschluss / Disclaimer of Liability
Die Bereitstellung dieses Plugins und aller enthaltenen Sicherheits-Patches erfolgt unentgeltlich unter den Bedingungen der **GNU General Public License v3.0 (GPLv3)**, ausschließlich in Version 3 (SPDX: `GPL-3.0-only`) (siehe Datei [`LICENSE`](./LICENSE)).

Die Software wird **„wie besehen“ (AS IS)** ohne jegliche ausdrückliche oder stillschweigende Gewährleistung zur Verfügung gestellt. Die Autoren und Mitwirkenden übernehmen keinerlei Haftung für:
* die vollständige Sicherheit des Zielsystems oder den Schutz vor unbekannten/neuen Schwachstellen (Zero-Days),
* direkte oder indirekte Schäden, Datenverluste oder Betriebsunterbrechungen, die aus der Nutzung oder der Unmöglichkeit der Nutzung dieser Software entstehen,
* Inkompatibilitäten mit individuellen Anpassungen, Themes oder Drittanbieter-Plugins.

Unberührt bleibt die Haftung für Vorsatz und grobe Fahrlässigkeit sowie jede Haftung, die nach zwingendem Recht nicht ausgeschlossen werden kann. Updates sollten stets zuerst in einer Staging-Umgebung getestet und vor dem Einsatz ein aktuelles Backup erstellt werden.

*The software is provided **"AS IS"**, without warranty of any kind, express or implied. To the extent permitted by applicable law, the authors and contributors accept no liability for the complete security of the target system or protection against unknown/new vulnerabilities (zero-days), for direct or indirect damages, data loss or business interruption arising from the use of or inability to use this software, or for incompatibilities with customisations, themes or third-party plugins. Liability for intent and gross negligence, and any liability that cannot be excluded under mandatory law, remains unaffected. Always test updates in a staging environment and create a current backup before deployment.*
