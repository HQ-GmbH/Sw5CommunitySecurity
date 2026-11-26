# Community Security Guard for Shopware 5

> **Hinweis / Notice:** Dieses Projekt ist ein **von der HQ GmbH initiiertes und derzeit betreutes, unabhängiges Open-Source-Projekt**, offen für Beiträge aus der Community. Es ist unentgeltlich, kein Produkt der **shopware AG** und steht in keiner Verbindung zur shopware AG. „Shopware“ ist eine eingetragene Marke der shopware AG.
>
> *This is an **independent open-source project initiated and currently maintained by HQ GmbH**, open to community contributions. It is free of charge, not a product of **shopware AG** and not affiliated with shopware AG. "Shopware" is a registered trademark of shopware AG.*

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](./LICENSE)
[![Shopware](https://img.shields.io/badge/Shopware-5.6.10%20%7C%205.7.19%2B-189eff.svg)](#kompatibilit%C3%A4t--compatibility)
[![PHP](https://img.shields.io/badge/PHP-7.4%20%7C%208.1%20%7C%208.2-777bb4.svg)](#kompatibilit%C3%A4t--compatibility)

Ein Open-Source-Security-Plugin für **Shopware 5.6.10 und 5.7.19+**, das schrittweise zusätzliche Härtungsmaßnahmen (Hardening) für Shopware-5-Shops bereitstellt – ohne Eingriffe in die Shopware-Core-Dateien. Es blockiert gezielt bekannte, dokumentierte Angriffswege und ergänzt so die übrigen Schutzmaßnahmen eines Shops. Regelmäßige Updates von PHP, Server und Plugins sowie eine individuelle Sicherheitsprüfung bleiben daneben weiterhin wichtig. Die aktuelle Version enthält die Module **HTTP-Security-Header**, **Template-Guard**, **Upload-Härtung** und **JavaScript-Escaping**; weitere Module sind in Planung (siehe [Features](#features)).

*An open-source security plugin for **Shopware 5.6.10 and 5.7.19+** that adds additional hardening measures for Shopware 5 shops step by step, without modifying Shopware core files. It specifically blocks known, documented attack vectors and thus complements a shop's other protective measures. Regular updates of PHP, server and plugins as well as an individual security review remain important alongside it. The current version contains the **HTTP security headers**, **template guard**, **upload hardening** and **JavaScript escaping** modules; further modules are planned (see [Features](#features)).*

---

## Features

**Verfügbar / Available:**

* **HTTP-Security-Header:** Setzt `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` und `Permissions-Policy` (sperrt Kamera, Mikrofon und USB), optional `Strict-Transport-Security` (nur über HTTPS). Bereits von Shopware, dem Theme oder anderen Plugins gesetzte Header werden nicht überschrieben.
  * **Content-Security-Policy:** optional, standardmäßig aus. Zuerst im Modus „Nur melden“ (`Content-Security-Policy-Report-Only`) einsetzen, dann durchsetzen. Mitgeliefert werden je eine Ausgangs-Richtlinie für das Responsive-Theme und das Backend. Eigene Quellen wie Zahlungsanbieter, Tracking oder CDN müssen ergänzt werden. Optional lässt sich eine Melde-Adresse (`report-uri`) eintragen. Weil das Theme Inline-Skripte nutzt, bleibt `'unsafe-inline'` erlaubt. Die Richtlinie verhindert deshalb vor allem das Nachladen fremder Skripte, Plugins (`object-src`), manipulierte `<base>`-Tags und das Einbetten in fremde Seiten; eingeschleuste Inline-Skripte blockiert sie nicht.
  * Ungültige Angaben (z. B. Zeilenumbrüche, Kommas in der CSP, `http://`-Melde-Adresse) werden nicht gesendet.
* **Template-Guard:** Lehnt beim Speichern Inhalte ab, die Shopware als Smarty-Vorlage auswertet und die Konstrukte mit bekannten Sicherheitslücken im mitgelieferten Smarty 3.1.12 enthalten:
  * **Geprüfte Konstrukte:** `{extends}`, `{fetch}`, `{php}`, ungültige `{function}`-/`{block}`-Namen, `$smarty.template_object`, Zugriff auf statische Klassen (`Klasse::…`). Zusätzlich gibt es eine abschaltbare strenge Prüfung von `{include}`-Dateinamen und `{math}`-Formeln.
  * **Geprüfte Inhalte:** E-Mail-Vorlagen, Newsletter, Beleg-Bausteine, SEO-URL-Vorlagen, E-Mail-Header/-Footer, „wesentliche Merkmale“, Zusatzbeschreibungen von Zahlungsarten, Länderhinweise, Produktexporte, Textbausteine (Snippets, auch beim CSV-Import) und das Einkaufswelten-Element „HTML Code“, jeweils inkl. Übersetzungen.
  * **Speicherwege:** Geprüft wird über das Backend und zusätzlich über Doctrine, das auch REST-API und andere Plugins nutzen.
  * **Bestehende Inhalte:** Der Konsolenbefehl `bin/console sw5cs:template-guard:scan` prüft bereits gespeicherte Inhalte, nur lesend. Er nennt Ort, Feld und Regel, nie den Inhalt, und endet mit Exit-Code 1, wenn er etwas findet.
  * **Verträglichkeit:** Die Standardvorlagen und Grundeinstellungen von Shopware lösen keine Funde aus; gespeicherte Inhalte werden nie verändert.
* **Inhaltsprüfung:** Lehnt beim Speichern Inhalte ab, die im Backend mit dem Editor bearbeitet werden, wenn sie Skript enthalten: `<script>`, Event-Attribute (`on…`), `javascript:`-Adressen oder `srcdoc`.
  * **Geprüfte Inhalte:** Shopseiten, Artikel-, Kategorie-, Hersteller- und Blogtexte, Formulare, Antworten auf Bewertungen, Newsletter-Texte, Texte in Einkaufswelten, HTML-Freitextfelder und Übersetzungen.
  * **Speicherwege:** Backend, REST-API und andere Plugins (über Doctrine) sowie Übersetzungen im Backend und über die REST-API (`/api/translations` und die Übersetzungen von Artikeln, Varianten und Kategorien; Ablehnung mit Status 400).
  * **Bestehende Inhalte:** `bin/console sw5cs:content-guard:scan` prüft gespeicherte Inhalte, nur lesend, und nennt Quelle, Datensatz, Feld und Regel, nie den Inhalt.
  * **Schutz im Editor:** Inhalte, die auf anderem Weg gespeichert wurden (z. B. von Plugins per SQL oder vor der Installation), bereinigt der Editor im Backend beim Laden und zeigt einen Hinweis. Gespeichert wird erst, wenn jemand speichert; im Shop bleibt die Ausgabe unverändert.
  * **Verträglichkeit:** Links, Bilder, Tabellen und eingebettete Videos bleiben erlaubt; gespeicherte Inhalte werden nie verändert. Wer Skripte bewusst in Shopseiten oder Beschreibungen einbindet (z. B. Bewertungs-Widgets), muss sie über das Theme oder ein Plugin einbinden oder das Modul abschalten.
* **Upload-Härtung:** Lehnt hochgeladene Dateien ab, die ein Webserver als PHP ausführen könnte, z. B. `.phar`, `.php7`, `.pht`, auch als Mehrfachendung wie `bild.phar.jpg`. Ebenso werden Dateien abgelehnt, die ein PHAR-Archiv enthalten, auch wenn es als Bild getarnt ist. Das gilt für Medienverwaltung, ESD-Dateien, Snippet- und Einkaufswelten-Import sowie alle übrigen Uploads; der Plugin-Installer ist ausgenommen.
* **JavaScript-Escaping:** Ergänzt den Smarty-Modifier `escape:'javascript'` um die Ersetzungen aus Smarty 3.1.48 ([CVE-2023-28447](https://github.com/smarty-php/smarty/security/advisories/GHSA-7j98-h7fp-4vwj)): Zusätzlich maskiert werden `<!--`, `<s`/`<S` (z. B. `<script`), Backticks und `${`. Das Plugin passt dafür nur den kompilierten Template-Code an; Vorlagen, gespeicherte Inhalte und Core-Dateien bleiben unverändert. Wirkt auf Themes, E-Mails, Belege und Newsletter. Das gilt auch für `escape` mit einer Variable als Escape-Typ (z. B. `escape:$typ`), im Produktexport und für eigene `escape`-Funktionen anderer Plugins, deren Ergebnis erhalten bleibt und nur ergänzt wird; andere Escape-Typen bleiben unverändert. Die Standard-Themes von Shopware nutzen `escape:'javascript'` nicht; das Modul schützt eigene Themes, Plugins und im Backend bearbeitete Vorlagen.
* **Sicherheits-Check:** Nur lesende Übersicht im Backend unter *Einstellungen > Sicherheits-Check* und als Konsolenbefehl `bin/console sw5cs:security-check`. Geprüft werden Shopware-Version, PHP-Supportende, `session.upload_progress.enabled`, `trustedproxies` (zu weit gefasste Einträge, Weiterleitungs-Header von nicht eingetragenen Adressen), Hauptshops ohne Host, Template-Sandbox, Schutz-`.htaccess` in internen Verzeichnissen, ein verbliebenes `recovery/install/`, das Captcha bei „Passwort vergessen“, der Zugang zur REST-API (API-Schlüssel ohne Adressbeschränkung), der Smarty-Ausgabe-Cache (eingeschaltet oder von Plugins eingeschaltet, CVE-2026-82531) und die Plugin-Module (inkl. Inhaltsprüfung). Jeder Punkt nennt eine konkrete Empfehlung; die Prüfung selbst ändert nichts. Die Seite sehen Administratoren; andere Backend-Rollen erst nach Freigabe in der Benutzerverwaltung. Der Konsolenbefehl endet mit Exit-Code 1, wenn ein Fehler vorliegt.
* **Schutzdateien wiederherstellen:** Fehlen die `.htaccess`-Dateien, mit denen Shopware interne Verzeichnisse (`bin`, `engine/Library`, `files/backup`, `files/documents`, `media/temp`, `var/cache`, `var/log`, `vendor`) unter Apache sperrt, legt das Plugin sie neu an: per Schaltfläche im Sicherheits-Check, mit `bin/console sw5cs:protection-files:restore` und automatisch beim Leeren des HTTP-Caches (abschaltbar). Vorhandene Dateien werden nie verändert. Die Schaltfläche erfordert das ACL-Recht „restore“, das Administratoren automatisch haben. Unter nginx wirkungslos; dort muss die Server-Konfiguration diese Verzeichnisse sperren.
* **Kunden-Sessions** (beide Optionen standardmäßig aus):
  * **Nur eine Anmeldung je Kundenkonto:** Meldet sich ein Kunde an, werden seine älteren Sitzungen beim nächsten Aufruf abgemeldet, etwa auf einem anderen Gerät oder eine übernommene Sitzung. Auch „Als Kunde anmelden“ im Backend zählt als Anmeldung. Gespeichert wird je Kundenkonto nur der Hash eines Zufallswerts.
  * **SameSite für das Session-Cookie** (je Shop): „Lax“ oder „Strict“. Kehrt ein Zahlungsanbieter per Formular in den Shop zurück, sendet der Browser das Cookie dann nicht mit, und der Kunde wirkt abgemeldet. Vor dem Einschalten jede Zahlungsart mit einer Testbestellung prüfen.
* **Backend-Konten:**
  * **Zwei-Faktor-Anmeldung:** Jeder Backend-Benutzer kann unter *Einstellungen > Zwei-Faktor-Anmeldung* eine Authenticator-App einrichten (TOTP, z. B. Aegis, FreeOTP, Google oder Microsoft Authenticator), per QR-Code oder durch Eingabe des Schlüssels. Der QR-Code entsteht im Browser, ohne Verbindung nach außen. Danach verlangt der Login zusätzlich den sechsstelligen Code. Nach fünf falschen Codes in Folge wird das Konto zeitweise gesperrt. Administratoren können die Einrichtung anderer Benutzer entfernen, auf der Konsole geht das mit `bin/console sw5cs:two-factor:reset <Benutzername>`. Die REST-API ist nicht betroffen.
  * **REST-API beschränken** (standardmäßig aus): Unter „REST-API: erlaubte Adressen“ lassen sich IP-Adressen und Bereiche eintragen (z. B. `203.0.113.10` oder `198.51.100.0/24`, auch IPv6). Dann nimmt die REST-API nur noch Anfragen von dort an, andere erhalten Status 403, auch mit gültigem API-Schlüssel. Ungültige Einträge erlauben nichts. Hinter einem Proxy zählt die Adresse laut `trustedproxies`.
  * **Passwortregeln** (standardmäßig aus): Neue Passwörter in der Benutzerverwaltung brauchen eine Mindestlänge, drei von vier Zeichenarten und dürfen den Benutzernamen nicht enthalten.
* **Mail-Daten im Backend als Text:** Gespeicherte Daten versendeter Mails (Beispielvariablen der E-Mail-Vorlagen, Betreff, Absender und Empfänger im E-Mail-Log) zeigt das Backend als Text statt als HTML an. Immer aktiv; gespeicherte Daten und Mails bleiben unverändert.
* **Protokollierung:** Abgelehnte Vorlagen und Dateien werden in `var/log/sw5_community_security_<Umgebung>-<Datum>.log` protokolliert, mit Ort, Feld und Regel bzw. Dateiendung. Inhalte, Dateinamen, Benutzernamen und IP-Adressen werden nicht protokolliert. Protokolliert wird ab der Stufe „Warning“, auch wenn Shopware im Produktivmodus sonst erst ab „Error“ protokolliert.
* **Meldungen als Textbausteine:** Alle Meldungen liegen als Textbausteine auf Deutsch und Englisch vor (Namespace `backend/sw5_community_security/messages`) und lassen sich im Backend anpassen.
* **Modularer Aufbau:** Jedes Modul ist gekapselt und lässt sich im Shopware-Backend einzeln deaktivieren. Beim Speichern der Einstellungen im Backend leert das Plugin den Konfigurations-, HTTP- und Template-Cache selbst.
* **Code-Qualität:** Code-Style nach den Regeln des Shopware-Kerns, statische Analyse mit PHPStan auf höchster Stufe inkl. strenger Regeln, Unit- und Integrationstests gegen Shopware 5.6.10, 5.7.19 und 5.7.20 bei jeder Änderung.
* **Ohne Core-Eingriffe:** Das Plugin arbeitet ausschließlich über Shopware-Events und -Services und verändert keine Core-Dateien. Es ist auf Kompatibilität mit individuellen Themes und Drittanbieter-Plugins ausgelegt; eine Garantie für jede Kombination kann nicht gegeben werden – bitte vorab in einer Staging-Umgebung testen.

**Geplant / Planned** *(noch nicht enthalten / not yet included)*:

* **Request- & Input-Hardening:** Frühzeitiges Blockieren bekannter Angriffsmuster (z. B. Smarty-SSTI-Payloads, Path-Traversal-Versuche).
* **Schutzmaßnahmen gegen dokumentierte Schwachstellen:** Gezielte Abschwächung einzelner Shopware-5-Sicherheitslücken, sobald sie umgesetzt und getestet sind; jeder Fix wird im [CHANGELOG](./CHANGELOG.md) dokumentiert.

---

## Kompatibilität / Compatibility

| Shopware | PHP | Getestet / Tested |
|---|---|---|
| `5.7.19` / `5.7.20` | `8.1` / `8.2` | ✅ |
| `5.6.10` | `7.4` | ✅ |
| `5.7.0`–`5.7.18` | – | ⚠️ nicht getestet, Update auf 5.7.20 empfohlen / not tested, update to 5.7.20 recommended |
| `5.6.0`–`5.6.9`, `5.5` und älter / and older | – | ❌ nicht installierbar (Mindestversion 5.6.10) / not installable (minimum version 5.6.10) |

*PHP 8.3 & 8.4 für den Shopware-5.7-Kern: siehe Roadmap. / PHP 8.3 & 8.4 for the Shopware 5.7 core: see roadmap.*

> **Ältere Shopware-5.7-Versionen:** Das Plugin lässt sich unter 5.7.0–5.7.18 installieren, wird dort aber nicht getestet. Ob alle Module wie vorgesehen greifen, ist deshalb nicht belegt. Ältere 5.7-Versionen enthalten außerdem Sicherheitslücken im Shopware-Kern, die erst in späteren Versionen behoben wurden; diese kann kein Plugin schließen. Empfohlen ist daher das Update auf **Shopware 5.7.20**.
>
> *Older Shopware 5.7 versions: the plugin can be installed on 5.7.0–5.7.18 but is not tested there, so it is not verified that all modules work as intended. Older 5.7 versions also contain security vulnerabilities in the Shopware core that were only fixed in later releases; no plugin can close these. Updating to **Shopware 5.7.20** is therefore recommended.*

> **Shopware 5.6:** Die Module laufen auch unter Shopware 5.6.10. Empfohlen ist trotzdem das Update auf **Shopware 5.7.20**: Shopware 5.6 läuft höchstens mit PHP 7.4, das seit November 2022 keine Sicherheitsupdates mehr erhält, und Shopware 5.6.10 (Mai 2021) war die letzte 5.6-Version: Sicherheitskorrekturen späterer Shopware-Versionen sind im 5.6-Kern nicht enthalten. Diese Risiken kann kein Plugin ausgleichen.
>
> *Shopware 5.6: the modules also run on Shopware 5.6.10. Updating to **Shopware 5.7.20** is still recommended: Shopware 5.6 runs on PHP 7.4 at most, which has not received security updates since November 2022, and Shopware 5.6.10 (May 2021) was the last 5.6 release: security fixes from later Shopware versions are not included in the 5.6 core. No plugin can compensate for these risks.*

### Grenzen der Tests / Limits of testing

Jede Version wird automatisch gegen eine **Standardinstallation** von Shopware 5.7.19 und 5.7.20 mit PHP 8.1 und 8.2 sowie Shopware 5.6.10 mit PHP 7.4 getestet. Ein echter Shop sieht aber fast immer anders aus. Für Shopware 5 gab es nach öffentlichen Angaben rund 2.000 Plugins, dazu Themes, Eigenentwicklungen, Plugin-Versionen und individuelle Einstellungen. Daraus ergeben sich **Millionen bis Milliarden möglicher Kombinationen**: Schon die Paare aus zwei Plugins sind rund 2 Millionen, Dreierkombinationen über 1 Milliarde. Diese Kombinationen **können nicht alle getestet werden**.

Das Plugin ist deshalb so gebaut, dass es möglichst wenig eingreift:
* keine Änderungen an Core-Dateien, keine Template-Overrides
* bereits gesetzte Header werden nicht überschrieben
* jedes Modul lässt sich einzeln abschalten
* der Befehl `sw5cs:template-guard:scan` zeigt vor dem Aktivieren, welche bestehenden Vorlagen der Template-Guard ablehnen würde

Trotzdem gilt: **Vor dem Einsatz im Live-Shop immer in einer Staging-Umgebung mit dem eigenen Plugin- und Theme-Stand testen** und ein aktuelles Backup anlegen.

*Each release is tested automatically against a **standard installation** of Shopware 5.7.19 and 5.7.20 with PHP 8.1 and 8.2 and Shopware 5.6.10 with PHP 7.4. Real shops almost always differ: public sources mention around 2,000 plugins for Shopware 5, plus themes, custom code, plugin versions and individual settings. This results in **millions to billions of possible combinations** – pairs of two plugins alone are about 2 million, combinations of three more than 1 billion – which **cannot all be tested**. The plugin is therefore designed to be minimally invasive (no core changes, no template overrides, existing headers are never overwritten, every module can be disabled; `sw5cs:template-guard:scan` shows beforehand which stored templates the template guard would reject). Always test in a staging environment with your own plugins and theme and create a current backup before using it in a live shop.*

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

### Software-Stückliste (SBOM) / Software bill of materials
Jedes Release enthält neben dem ZIP die Datei `Sw5CommunitySecurity-<Version>.cdx.json` im Format [CycloneDX](https://cyclonedx.org/) 1.6. Sie listet das Plugin, seine Composer-Abhängigkeit und alle mitgelieferten Teile Dritter mit Version, Lizenz und Herkunft und lässt sich mit üblichen Werkzeugen auf bekannte Sicherheitslücken prüfen. Das Projekt gleicht sie bei jeder Änderung und wöchentlich mit der Datenbank [OSV](https://osv.dev) ab.

*Every release ships the file `Sw5CommunitySecurity-<version>.cdx.json` next to the ZIP, in [CycloneDX](https://cyclonedx.org/) 1.6 format. It lists the plugin, its Composer dependency and all included third-party parts with version, license and origin, and can be checked for known vulnerabilities with common tools. The project checks it against the [OSV](https://osv.dev) database on every change and weekly.*

---

## Roadmap: Weiterbetrieb und Hardening für Shopware 5

Dieses Plugin ist der erste Baustein einer mehrstufigen Open-Source-Initiative zum technischen Weiterbetrieb bestehender Shopware-5-Projekte:
1. **Phase 1 (laufend):** Hardening über dieses Plugin (`Sw5CommunitySecurity`). Verfügbar sind die Module HTTP-Security-Header, Template-Guard, Upload-Härtung, JavaScript-Escaping, Kunden-Sessions und Backend-Konten sowie der Sicherheits-Check mit Wiederherstellung der Schutzdateien; weitere Module sind in [ROADMAP.md](./ROADMAP.md) geplant.
2. **Phase 2 (in Vorbereitung, separates Projekt):** Kompatibilität des Shopware-5.7-Kerns mit **PHP 8.3** und **PHP 8.4**.

*Die Roadmap beschreibt Ziele, keine zugesicherten Funktionen oder Termine. / The roadmap describes goals, not promised features or dates.*

Detaillierter Plan mit allen bekannten Befunden: [ROADMAP.md](./ROADMAP.md)

Mitarbeit: Grundregeln in [CONTRIBUTING.md](./CONTRIBUTING.md).

---

## Über das Projekt / About the project

Das Projekt wurde von der **[HQ GmbH](https://www.hqgmbh.de/unsere-leistungen/shopware-agentur/)** initiiert, einer E-Commerce- und Shopware-Agentur aus Deutschland, die seit etwa 2009 mit Shopware arbeitet (damals Shopware 3). Anlass ist das Ende der Sicherheitsupdates für Shopware 5 am 31.07.2024: Viele Shops laufen weiter, und bekannte Lücken bleiben im Kern offen.

Die HQ GmbH hat Architektur und Sicherheitskonzept entworfen, die bisherigen Versionen einschließlich der automatisierten Tests und der CI entwickelt und verantwortet derzeit Wartung und Releases. Quellcode, Entwicklungsverlauf, Tests und Sicherheitsentscheidungen sind öffentlich nachvollziehbar, siehe [docs/ARCHITECTURE.md](./docs/ARCHITECTURE.md), [ROADMAP.md](./ROADMAP.md) und [CHANGELOG.md](./CHANGELOG.md). Entwickler, Agenturen und Shopbetreiber können den Code prüfen, Fehler melden und Verbesserungen beitragen ([CONTRIBUTING.md](./CONTRIBUTING.md)). Ziel ist ein breiterer Kreis von Maintainern; bis dahin koordiniert die HQ GmbH das Projekt.

**Projektseite:** [www.hqgmbh.de/shopware-5-community-security-guard/](https://www.hqgmbh.de/shopware-5-community-security-guard/?utm_source=github&utm_medium=referral&utm_campaign=community-security-guard) mit Hintergrund, Funktionsweise und Kompatibilität.

**Projektleitung und Maintainer:** Mathias Goldhan, HQ GmbH ([Autorenprofil](https://www.hqgmbh.de/autoren/mathias-goldhan/), GitHub: [`hqgmbh`](https://github.com/hqgmbh))

Das Projekt ist unentgeltlich und kein kommerzielles Produkt. Beratung, Wartung und Notfallsupport für Shopware-5-Shops bietet die HQ GmbH getrennt davon an: [Shopware 5 Wartung & Support](https://www.hqgmbh.de/unsere-leistungen/shopware-5-wartung-support/).

*The project was initiated by **[HQ GmbH](https://www.hqgmbh.de/unsere-leistungen/shopware-agentur/)**, an e-commerce and Shopware agency from Germany working with Shopware since around 2009 (then Shopware 3), after security updates for Shopware 5 ended on 31 July 2024: many shops keep running, and known vulnerabilities remain open in the core.*

*HQ GmbH designed the architecture and security concept, developed the releases so far including the automated tests and CI, and is currently responsible for maintenance and releases. Source code, development history, tests and security decisions are publicly traceable, see [docs/ARCHITECTURE.md](./docs/ARCHITECTURE.md), [ROADMAP.md](./ROADMAP.md) and [CHANGELOG.md](./CHANGELOG.md). Developers, agencies and shop operators can review the code, report issues and contribute improvements ([CONTRIBUTING.md](./CONTRIBUTING.md)). The goal is a broader group of maintainers; until then, HQ GmbH coordinates the project.*

***Project page:** [www.hqgmbh.de/en/community-security-guard-shopware-5/](https://www.hqgmbh.de/en/community-security-guard-shopware-5/?utm_source=github&utm_medium=referral&utm_campaign=community-security-guard) with background, how it works and compatibility.*

***Project lead and maintainer:** Mathias Goldhan, HQ GmbH ([author profile](https://www.hqgmbh.de/autoren/mathias-goldhan/), GitHub: [`hqgmbh`](https://github.com/hqgmbh))*

*The project is free of charge and not a commercial product. Consulting, maintenance and emergency support for Shopware 5 shops are offered separately by HQ GmbH: [Shopware 5 maintenance & support](https://www.hqgmbh.de/en/our-services/shopware-5-maintenance-support/).*

**Impressum / Legal notice:** [https://www.hqgmbh.de/impressum/](https://www.hqgmbh.de/impressum/)

---

## Rechtliche Hinweise & Haftungsausschluss (Legal Notice & Disclaimer)

### Markenrechtlicher Hinweis / Trademark Notice
Dieses Projekt ist ein **von der HQ GmbH initiiertes und derzeit betreutes, unabhängiges Open-Source-Projekt**. Es wird weder von der **shopware AG** entwickelt, gepflegt noch offiziell unterstützt oder zertifiziert. „Shopware“ ist eine eingetragene Marke der shopware AG. Die Nennung dient ausschließlich der Beschreibung der Kompatibilität dieses Plugins. Die HQ GmbH ist Shopware-Partner; dieses Projekt ist dennoch kein Produkt der shopware AG und wird von ihr weder unterstützt noch zertifiziert.

*This project is an **independent open-source project initiated and currently maintained by HQ GmbH**. It is not developed, maintained, endorsed, or certified by **shopware AG**. "Shopware" is a registered trademark of shopware AG and is used here solely to describe compatibility. HQ GmbH is a Shopware partner; nevertheless, this project is not a product of shopware AG and is neither endorsed nor certified by it.*

### Haftungsausschluss / Disclaimer of Liability
Dieses Plugin wird **unentgeltlich** unter der **GNU General Public License v3.0** bereitgestellt, ausschließlich in Version 3 (SPDX: `GPL-3.0-only`, siehe [`LICENSE`](./LICENSE)). Enthaltene Teile Dritter behalten ihre eigene, mit der GPL-3.0 vereinbare Lizenz (LGPL-3.0-or-later, MIT); sie sind mit Herkunft und Lizenztext in [`THIRD-PARTY-NOTICES.md`](./THIRD-PARTY-NOTICES.md) aufgeführt. Die Gewährleistungs- und Haftungsausschlüsse der Abschnitte 15 und 16 der Lizenz gelten, soweit das anwendbare Recht dies zulässt. Für die Anwendung deutschen Rechts gilt ergänzend:

1. **Haftung:** Da die Software unentgeltlich überlassen wird, haften die Urheber und Mitwirkenden nur für **Vorsatz und grobe Fahrlässigkeit** (§ 521 BGB).
2. **Mängel:** Für Sach- und Rechtsmängel der Software wird nur gehaftet, wenn ein Mangel **arglistig verschwiegen** wurde (§§ 523, 524 BGB).
3. **Zwingende Haftung:** Unberührt bleiben die Haftung für Schäden aus der Verletzung des Lebens, des Körpers oder der Gesundheit sowie jede Haftung nach zwingenden gesetzlichen Vorschriften, insbesondere dem Produkthaftungsgesetz.
4. **Kein Sicherheits- oder Kompatibilitätsversprechen:** Das Plugin verringert einzelne, dokumentierte Risiken.
   * Es sagt weder die vollständige Sicherheit eines Shops noch den Schutz vor unbekannten oder neuen Schwachstellen (Zero-Days) zu.
   * Es sagt auch keine Kompatibilität mit jeder Kombination aus Plugins, Themes und Anpassungen zu (siehe [Grenzen der Tests](#grenzen-der-tests--limits-of-testing)).
   * Beschreibungen in README, CHANGELOG und ROADMAP sind keine Garantie im Sinne des § 443 BGB.
5. **Verantwortung des Betreibers:** Einsatz, Konfiguration und Prüfung im eigenen Shop liegen bei der Betreiberin bzw. dem Betreiber. Updates sollten stets zuerst in einer Staging-Umgebung getestet und vor dem Einsatz ein aktuelles Backup erstellt werden.

*This plugin is provided **free of charge** under the **GNU General Public License v3.0**, version 3 only (SPDX: `GPL-3.0-only`, see [`LICENSE`](./LICENSE)). Included third-party parts keep their own GPL-3.0-compatible license (LGPL-3.0-or-later, MIT); they are listed with origin and license text in [`THIRD-PARTY-NOTICES.md`](./THIRD-PARTY-NOTICES.md). The disclaimer of warranty and limitation of liability in sections 15 and 16 of the license apply to the extent permitted by applicable law. Where German law applies, the following also applies:*

1. ***Liability:** As the software is provided free of charge, the authors and contributors are liable only for **intent and gross negligence** (section 521 German Civil Code, BGB).*
2. ***Defects:** Liability for material defects and defects of title only applies if a defect was **fraudulently concealed** (sections 523, 524 BGB).*
3. ***Mandatory liability:** Liability for injury to life, body or health and any liability under mandatory statutory provisions, in particular the German Product Liability Act (Produkthaftungsgesetz), remains unaffected.*
4. ***No promise of security or compatibility:** The plugin reduces specific, documented risks.*
   * *It does not promise complete security of a shop or protection against unknown or new vulnerabilities (zero-days).*
   * *It does not promise compatibility with every combination of plugins, themes and customisations (see [Limits of testing](#grenzen-der-tests--limits-of-testing)).*
   * *Descriptions in README, CHANGELOG and ROADMAP do not constitute a guarantee within the meaning of section 443 BGB.*
5. ***Operator responsibility:** Use, configuration and verification in the operator's own shop are the operator's responsibility. Always test updates in a staging environment first and create a current backup before deployment.*
