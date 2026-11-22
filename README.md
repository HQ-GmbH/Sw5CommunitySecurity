# Community Security Guard for Shopware 5

> **Hinweis / Notice:** Dieses Projekt ist ein **von der HQ GmbH initiiertes, unabhängiges Community-Projekt**. Es ist weder ein Produkt der HQ GmbH noch der **shopware AG** und steht in keiner Verbindung zur shopware AG. Die Community-Struktur befindet sich im Aufbau; bis dahin betreut die HQ GmbH das Projekt. „Shopware“ ist eine eingetragene Marke der shopware AG.
>
> *This is an **independent community project initiated by HQ GmbH**. It is neither a product of HQ GmbH nor of **shopware AG** and is not affiliated with shopware AG. The community structure is still being built; until then, HQ GmbH maintains the project. "Shopware" is a registered trademark of shopware AG.*

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](./LICENSE)
[![Shopware](https://img.shields.io/badge/Shopware-5.7.19%2B-189eff.svg)](#kompatibilit%C3%A4t--compatibility)
[![PHP](https://img.shields.io/badge/PHP-8.1%20%7C%208.2-777bb4.svg)](#kompatibilit%C3%A4t--compatibility)

Ein Open-Source-Security-Plugin für **Shopware 5.7.19+**, das schrittweise zusätzliche Härtungsmaßnahmen (Hardening) für Shopware-5-Shops bereitstellt – ohne Eingriffe in die Shopware-Core-Dateien. Es blockiert gezielt bekannte, dokumentierte Angriffswege und ergänzt so die übrigen Schutzmaßnahmen eines Shops. Regelmäßige Updates von PHP, Server und Plugins sowie eine individuelle Sicherheitsprüfung bleiben daneben weiterhin wichtig. Die aktuelle Version enthält die Module **HTTP-Security-Header**, **Template-Guard** und **Upload-Härtung**; weitere Module sind in Planung (siehe [Features](#features)).

*An open-source security plugin for **Shopware 5.7.19+** that adds additional hardening measures for Shopware 5 shops step by step, without modifying Shopware core files. It specifically blocks known, documented attack vectors and thus complements a shop's other protective measures. Regular updates of PHP, server and plugins as well as an individual security review remain important alongside it. The current version contains the **HTTP security headers**, **template guard** and **upload hardening** modules; further modules are planned (see [Features](#features)).*

---

## Features

**Verfügbar / Available:**

* **HTTP-Security-Header:** Setzt `X-Content-Type-Options`, `X-Frame-Options` und `Referrer-Policy`, optional `Strict-Transport-Security` (nur über HTTPS). Bereits von Shopware, dem Theme oder anderen Plugins gesetzte Header werden nicht überschrieben.
* **Template-Guard:** Lehnt beim Speichern Inhalte ab, die Shopware als Smarty-Vorlage auswertet und die Konstrukte mit bekannten Sicherheitslücken im mitgelieferten Smarty 3.1.12 enthalten:
  * **Geprüfte Konstrukte:** `{extends}`, `{fetch}`, `{php}`, ungültige `{function}`-/`{block}`-Namen, `$smarty.template_object`, Zugriff auf statische Klassen (`Klasse::…`). Zusätzlich gibt es eine abschaltbare strenge Prüfung von `{include}`-Dateinamen und `{math}`-Formeln.
  * **Geprüfte Inhalte:** E-Mail-Vorlagen, Newsletter, Beleg-Bausteine, SEO-URL-Vorlagen, E-Mail-Header/-Footer, „wesentliche Merkmale“, Zusatzbeschreibungen von Zahlungsarten, Länderhinweise, Produktexporte, Textbausteine (Snippets, auch beim CSV-Import) und das Einkaufswelten-Element „HTML Code“, jeweils inkl. Übersetzungen.
  * **Speicherwege:** Geprüft wird über das Backend und zusätzlich über Doctrine, das auch REST-API und andere Plugins nutzen.
  * **Bestehende Inhalte:** Der Konsolenbefehl `bin/console sw5cs:template-guard:scan` prüft bereits gespeicherte Inhalte, nur lesend. Er nennt Ort, Feld und Regel, nie den Inhalt, und endet mit Exit-Code 1, wenn er etwas findet.
  * **Verträglichkeit:** Die Standardvorlagen und Grundeinstellungen von Shopware lösen keine Funde aus; gespeicherte Inhalte werden nie verändert.
* **Upload-Härtung:** Lehnt hochgeladene Dateien ab, die ein Webserver als PHP ausführen könnte, z. B. `.phar`, `.php7`, `.pht`, auch als Mehrfachendung wie `bild.phar.jpg`. Ebenso werden Dateien abgelehnt, die ein PHAR-Archiv enthalten, auch wenn es als Bild getarnt ist. Das gilt für Medienverwaltung, ESD-Dateien, Snippet- und Einkaufswelten-Import sowie alle übrigen Uploads; der Plugin-Installer ist ausgenommen.
* **Modularer Aufbau:** Jedes Modul ist gekapselt und lässt sich im Shopware-Backend einzeln deaktivieren.
* **Ohne Core-Eingriffe:** Das Plugin arbeitet ausschließlich über Shopware-Events und -Services und verändert keine Core-Dateien. Es ist auf Kompatibilität mit individuellen Themes und Drittanbieter-Plugins ausgelegt; eine Garantie für jede Kombination kann nicht gegeben werden – bitte vorab in einer Staging-Umgebung testen.

**Geplant / Planned** *(noch nicht enthalten / not yet included)*:

* **Request- & Input-Hardening:** Frühzeitiges Blockieren bekannter Angriffsmuster (z. B. Smarty-SSTI-Payloads, Path-Traversal-Versuche).
* **Schutzmaßnahmen gegen dokumentierte Schwachstellen:** Gezielte Abschwächung einzelner Shopware-5-Sicherheitslücken, sobald sie umgesetzt und getestet sind; jeder Fix wird im [CHANGELOG](./CHANGELOG.md) dokumentiert.

---

## Kompatibilität / Compatibility

* **Shopware:** `5.7.19` / `5.7.20`
* **PHP:** `8.1` – `8.2` *(PHP 8.3 & 8.4 Support siehe Roadmap)*

### Grenzen der Tests / Limits of testing

Jede Version wird automatisch gegen eine **Standardinstallation** von Shopware 5.7.19 und 5.7.20 mit PHP 8.1 und 8.2 getestet. Ein echter Shop sieht aber fast immer anders aus. Für Shopware 5 gab es nach öffentlichen Angaben rund 2.000 Plugins, dazu Themes, Eigenentwicklungen, Plugin-Versionen und individuelle Einstellungen. Daraus ergeben sich **Millionen bis Milliarden möglicher Kombinationen**: Schon die Paare aus zwei Plugins sind rund 2 Millionen, Dreierkombinationen über 1 Milliarde. Diese Kombinationen **können nicht alle getestet werden**.

Das Plugin ist deshalb so gebaut, dass es möglichst wenig eingreift:
* keine Änderungen an Core-Dateien, keine Template-Overrides
* bereits gesetzte Header werden nicht überschrieben
* jedes Modul lässt sich einzeln abschalten
* der Befehl `sw5cs:template-guard:scan` zeigt vor dem Aktivieren, welche bestehenden Vorlagen der Template-Guard ablehnen würde

Trotzdem gilt: **Vor dem Einsatz im Live-Shop immer in einer Staging-Umgebung mit dem eigenen Plugin- und Theme-Stand testen** und ein aktuelles Backup anlegen.

*Each release is tested automatically against a **standard installation** of Shopware 5.7.19 and 5.7.20 with PHP 8.1 and 8.2. Real shops almost always differ: public sources mention around 2,000 plugins for Shopware 5, plus themes, custom code, plugin versions and individual settings. This results in **millions to billions of possible combinations** – pairs of two plugins alone are about 2 million, combinations of three more than 1 billion – which **cannot all be tested**. The plugin is therefore designed to be minimally invasive (no core changes, no template overrides, existing headers are never overwritten, every module can be disabled; `sw5cs:template-guard:scan` shows beforehand which stored templates the template guard would reject). Always test in a staging environment with your own plugins and theme and create a current backup before using it in a live shop.*

## Fragen und Probleme / Questions and problems

Shopbetreiberinnen und Shopbetreiber, die Fragen haben oder auf Probleme stoßen, etwa einen Konflikt mit einem anderen Plugin, können sich an die Maintainer des Projekts wenden. Wir versuchen gemeinsam, eine Lösung zu finden. Das geschieht freiwillig und ohne Anspruch auf Support, Reaktionszeiten oder eine Lösung.
* Fragen und Fehlerberichte über die [GitHub-Issues](https://github.com/HQ-GmbH/Sw5CommunitySecurity/issues) dieses Repositorys oder über die im [Impressum](https://www.hqgmbh.de/impressum/) genannten Kontaktdaten der HQ GmbH.
* Hilfreich sind die Shopware- und PHP-Version, die Version dieses Plugins, die Liste der aktiven Plugins und die Fehlermeldung bzw. der Eintrag aus `var/log/`. **Keine Zugangsdaten, Kundendaten oder anderen personenbezogenen Daten mitschicken.**
* **Sicherheitslücken bitte nicht öffentlich melden**, sondern wie in [SECURITY.md](./SECURITY.md) beschrieben.

*Shop operators with questions or problems (e.g. a conflict with another plugin) can contact the project maintainers; we will try to find a solution together. This is voluntary and without any entitlement to support, response times or a solution. Use the [GitHub issues](https://github.com/HQ-GmbH/Sw5CommunitySecurity/issues) of this repository or the contact details in HQ GmbH's [legal notice](https://www.hqgmbh.de/impressum/). Please include Shopware, PHP and plugin versions, the list of active plugins and the error message or log entry – never credentials, customer data or other personal data. Report security vulnerabilities privately as described in [SECURITY.md](./SECURITY.md).*

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
1. **Phase 1 (laufend):** Hardening über dieses Plugin (`Sw5CommunitySecurity`). Verfügbar sind die Module HTTP-Security-Header, Template-Guard und Upload-Härtung; weitere Module sind in [ROADMAP.md](./ROADMAP.md) geplant.
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
Dieses Plugin wird **unentgeltlich** unter der **GNU General Public License v3.0** bereitgestellt, ausschließlich in Version 3 (SPDX: `GPL-3.0-only`, siehe [`LICENSE`](./LICENSE)). Die Gewährleistungs- und Haftungsausschlüsse der Abschnitte 15 und 16 der Lizenz gelten, soweit das anwendbare Recht dies zulässt. Für die Anwendung deutschen Rechts gilt ergänzend:

1. **Haftung:** Da die Software unentgeltlich überlassen wird, haften die Urheber und Mitwirkenden nur für **Vorsatz und grobe Fahrlässigkeit** (§ 521 BGB).
2. **Mängel:** Für Sach- und Rechtsmängel der Software wird nur gehaftet, wenn ein Mangel **arglistig verschwiegen** wurde (§§ 523, 524 BGB).
3. **Zwingende Haftung:** Unberührt bleiben die Haftung für Schäden aus der Verletzung des Lebens, des Körpers oder der Gesundheit sowie jede Haftung nach zwingenden gesetzlichen Vorschriften, insbesondere dem Produkthaftungsgesetz.
4. **Kein Sicherheits- oder Kompatibilitätsversprechen:** Das Plugin verringert einzelne, dokumentierte Risiken.
   * Es sagt weder die vollständige Sicherheit eines Shops noch den Schutz vor unbekannten oder neuen Schwachstellen (Zero-Days) zu.
   * Es sagt auch keine Kompatibilität mit jeder Kombination aus Plugins, Themes und Anpassungen zu (siehe [Grenzen der Tests](#grenzen-der-tests--limits-of-testing)).
   * Beschreibungen in README, CHANGELOG und ROADMAP sind keine Garantie im Sinne des § 443 BGB.
5. **Verantwortung des Betreibers:** Einsatz, Konfiguration und Prüfung im eigenen Shop liegen bei der Betreiberin bzw. dem Betreiber. Updates sollten stets zuerst in einer Staging-Umgebung getestet und vor dem Einsatz ein aktuelles Backup erstellt werden.

*This plugin is provided **free of charge** under the **GNU General Public License v3.0**, version 3 only (SPDX: `GPL-3.0-only`, see [`LICENSE`](./LICENSE)). The disclaimer of warranty and limitation of liability in sections 15 and 16 of the license apply to the extent permitted by applicable law. Where German law applies, the following also applies:*

1. ***Liability:** As the software is provided free of charge, the authors and contributors are liable only for **intent and gross negligence** (section 521 German Civil Code, BGB).*
2. ***Defects:** Liability for material defects and defects of title only applies if a defect was **fraudulently concealed** (sections 523, 524 BGB).*
3. ***Mandatory liability:** Liability for injury to life, body or health and any liability under mandatory statutory provisions, in particular the German Product Liability Act (Produkthaftungsgesetz), remains unaffected.*
4. ***No promise of security or compatibility:** The plugin reduces specific, documented risks.*
   * *It does not promise complete security of a shop or protection against unknown or new vulnerabilities (zero-days).*
   * *It does not promise compatibility with every combination of plugins, themes and customisations (see [Limits of testing](#grenzen-der-tests--limits-of-testing)).*
   * *Descriptions in README, CHANGELOG and ROADMAP do not constitute a guarantee within the meaning of section 443 BGB.*
5. ***Operator responsibility:** Use, configuration and verification in the operator's own shop are the operator's responsibility. Always test updates in a staging environment first and create a current backup before deployment.*
