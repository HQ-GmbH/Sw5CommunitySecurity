# Mitarbeit – Grundregeln

> **Außendarstellung:** Das Projekt tritt immer als **„von der HQ GmbH initiiertes und derzeit betreutes, unabhängiges Open-Source-Projekt, offen für Beiträge aus der Community“** auf (englisch: *„independent open-source project initiated and currently maintained by HQ GmbH, open to community contributions“*).
> - Die HQ GmbH wird als Initiator, technischer Urheber und derzeitiger Maintainer klar benannt; ihre tatsächliche Leistung (Architektur, Entwicklung, Tests, Releases) darf konkret beschrieben werden.
> - Das Projekt ist unentgeltlich und kein kommerzielles Produkt; Dienstleistungen der HQ GmbH sind davon getrennt.
> - Solange es keine externen Maintainer gibt, ist es kein gemeinschaftlich getragenes Projekt: Formulierungen wie „community-driven“ oder „von der Community entwickelt“ sind nicht zu verwenden.
> - Personen werden nur mit tatsächlicher Rolle und mit ihrer Einwilligung namentlich genannt.
>
> Das gilt für README, SECURITY.md, `plugin.xml`, `composer.json`, Release-Texte und PRs.

Diese Regeln gelten **für alle, die an diesem Repository arbeiten**. Sie sind verbindlich. Wer eine Regel ändern will, macht das per Pull Request an dieser Datei.

## 1. Pflichtlektüre vor jeder Arbeit

| Datei | Wozu |
|---|---|
| [IN-ARBEIT.md](./IN-ARBEIT.md) | Wer gerade an welchem Befund arbeitet. **Vor Beginn eintragen, danach wieder austragen.** |
| [ROADMAP.md](./ROADMAP.md) | Bekannte Befunde (F1 …) mit Priorität und Versionsplan |
| [CHANGELOG.md](./CHANGELOG.md) | Was veröffentlicht ist und was unter `[Unreleased]` wartet |
| [THIRD-PARTY-NOTICES.md](./THIRD-PARTY-NOTICES.md) | Enthaltener Fremdcode mit Herkunft und Lizenz (siehe Abschnitt 6.2) |
| [SECURITY.md](./SECURITY.md) | Umgang mit Sicherheitslücken. Nie öffentlich in Issues oder PRs melden. |

## 2. Shopware-Dokumentation und Entwicklervorgaben (100 %)

Das Plugin hält sich vollständig an die offiziellen Shopware-5-Vorgaben. Es wird nichts aus dem Gedächtnis oder nach Gefühl umgesetzt.

- **Maßgeblich ist zuerst die offizielle Dokumentation unter [github.com/shopware5](https://github.com/shopware5):**
  - [shopware5/devdocs](https://github.com/shopware5/devdocs): Entwicklerdokumentation, u. a. Plugin-System ab 5.2, Events, Services, Konfiguration, Konsole, Sicherheit. Das Repository ist archiviert, ist aber weiterhin die Referenz für 5.7.
  - [shopware5/shopware](https://github.com/shopware5/shopware): Quelltext von Shopware 5, maßgeblich ist das Tag `v5.7.20` (Fallback `v5.7.19`). Sagt die Doku nichts, gilt das tatsächliche Verhalten im Quelltext dieses Tags. Die Fundstelle (Datei:Zeile) gehört in einen Code-Kommentar oder den PR.
  - Die Coding-Standards des Kerns (`.php-cs-fixer.php` im Tag `v5.7.20` von `shopware5/shopware`) gelten auch hier; sie sind in [`.php-cs-fixer.dist.php`](./.php-cs-fixer.dist.php) hinterlegt.
- **Plugin-Aufbau nach Shopware-Vorgabe:**
  - Bootstrap `Shopware\Components\Plugin`
  - `plugin.xml`, `Resources/config.xml` und `Resources/services.xml`, jeweils gegen das offizielle XSD validiert; die CI prüft das.
  - Event-Subscriber über das Tag `shopware.event_subscriber`
  - Konsolenbefehle über das Tag `console.command`
- **Keine Änderungen am Shopware-Kern aus dem Plugin heraus:**
  - keine Dateien im Shop-Verzeichnis überschreiben
  - keine Core-Klassen per Autoload ersetzen
  - nur dokumentierte Erweiterungspunkte nutzen: Events, Hooks, Service-Dekoration
- **Unterstützt werden** Shopware 5.7.19 und 5.7.20 mit PHP 8.1 und 8.2 sowie Shopware 5.6.10 mit PHP 7.4. Alles andere ist nicht zugesichert.
  - **Plugin-Code ist PHP-7.4-kompatibel** (keine Sprachmittel ab PHP 8.0 wie `readonly`, Konstruktor-Promotion, `match`, `mixed` als Typ, Nullsafe-Operator). Unit-Tests und Werkzeuge dürfen PHP 8.1 nutzen.
  - **Unterschiede zwischen Shopware 5.6 und 5.7** werden nur in Adaptern unter `Components/Compat/Sw56` und `Components/Compat/Sw57` behandelt; die Auswahl erfolgt in `Sw5CommunitySecurity::build()`. Jede Fundstelle wird im Quelltext beider Tags (`v5.6.10`, `v5.7.20`) geprüft.
- **Keine erfundenen Fakten:** CVE-Nummern, API-Verhalten und Versionsstände nur mit Quelle. Was nicht belegt ist, wird als „zu verifizieren“ markiert.
- **Store-Vorgaben:**
  - kein `die`/`exit`/`var_dump`/`print_r` im Plugin-Code
  - keine externen Aufrufe (CDN, APIs) ohne Freigabe
  - Ausgaben immer escapen

### 2.1 Qualitätsanspruch: Vorgaben übererfüllen (Pflicht)

Dies ist ein Sicherheits-Plugin. Die Shopware-5-Vorgaben sind deshalb **die Untergrenze, nicht das Ziel**: Jede Änderung erfüllt sie vollständig und geht, wo es möglich ist, darüber hinaus. Gilt eine Regel nicht oder kollidiert sie mit einer anderen, wird das im PR begründet.

**Code**
- **Code-Style:** php-cs-fixer mit den Regeln des Shopware-Kerns (`vendor/bin/php-cs-fixer check`). Abweichungen werden mit `vendor/bin/php-cs-fixer fix` behoben, nicht per Konfiguration ausgenommen.
- **Statische Analyse:** PHPStan auf der **höchsten Stufe** mit strengen, Deprecation- und PHPUnit-Regeln ([`phpstan.neon.dist`](./phpstan.neon.dist) für Shopware 5.7, [`phpstan-sw56.neon.dist`](./phpstan-sw56.neon.dist) für Shopware 5.6, [`phpstan-tests.neon.dist`](./phpstan-tests.neon.dist) für Tests); der Shopware-Kern selbst nutzt Stufe 8. Analysiert wird gegen Shopware 5.6.10, 5.7.19 und 5.7.20.
- **Befunde werden behoben, nicht versteckt:** keine `@phpstan-ignore`-Kommentare, keine Baseline, kein `assert()` oder `@var` zum Überschreiben von Typen, keine Typumwandlungen nur zum Ruhigstellen, keine aufgeweiteten Parameter- oder Rückgabetypen.
- **Typen prüfen statt annehmen:** Werte aus Anfragen, Datenbank, Konfiguration und Shopware-APIs werden vor der Verwendung typgeprüft (`\is_string()`, `instanceof` …).
- **`declare(strict_types=1)`**, `final`-Klassen (außer dem Plugin-Bootstrap), `readonly`-Eigenschaften und Konstruktor-Injection über `Resources/services.xml`; kein Zugriff über `Shopware()` oder den Container, wo Injection möglich ist.
- **Verboten im Plugin-Code:** `die`, `exit`, `var_dump`, `print_r`, `dump`, `eval`, `error_reporting()`, `ini_set()`, `@`-Unterdrückung ohne Prüfung des Rückgabewerts, externe Aufrufe (CDN, APIs, Telemetrie) ohne Freigabe.

**Plugin-Guidelines (Shopware 5 devdocs)**
- **Texte:** Alle Texte für Shopbetreiber und Backend-Nutzer sind Textbausteine in `Resources/snippets` auf **Deutsch und Englisch**. Textbausteine enthalten **keine Smarty-Syntax** (`{`, `}`), weil Shopware sie als Smarty kompiliert; Platzhalter im Shopware-Stil `[name]`.
- **Ausgaben:** Meldungen werden HTML-maskiert, Text und eingesetzte Werte, auch wenn sie aus Textbausteinen stammen.
- **Metadaten:** `plugin.xml` mit allen Pflichtfeldern, Version im Format `X.Y.Z` und Changelog-Eintrag je Version auf Deutsch und Englisch. In `Resources/config.xml` hat **jede Einstellung** Bezeichnung und Beschreibung auf Deutsch und Englisch, ebenso jede Auswahloption. Der Test `Tests/Unit/Metadata/PluginMetadataTest.php` prüft das.
- **Protokollierung:** nur über den plugin-eigenen Logger (`sw5_community_security.logger`). Sicherheitsereignisse als `warning`. Protokolliert werden Ort, Feld, Regel bzw. Dateiendung, **nie** Inhalte, Dateinamen, Benutzernamen, IP-Adressen oder andere personenbezogene Daten.
- **Cache:** nur die Caches leeren, die die Änderung wirklich betrifft. Nicht bei `install`, sondern bei `activate`, `deactivate` und `update`; bei `uninstall` nur, wenn das Plugin aktiv war; beim Speichern der Plugin-Einstellungen im Backend automatisch.
- **Daten:** Speichert das Plugin eigene Daten, beachtet `uninstall` `keepUserData()`. Das Plugin verändert keine gespeicherten Inhalte des Shops ungefragt.
- **Eingaben:** Jede Eingabe wird validiert, im Zweifel abgelehnt (Allowlist statt Blocklist, wo möglich).

**Sicherheit und Datenschutz**
- **Minimaler Eingriff:** Schutzmaßnahmen greifen so gezielt wie möglich und lassen sich einzeln abschalten. Standardwerte sind sicher, aber für Standardinstallationen ohne Funktionsverlust.
- **Keine Inhalte nach außen:** Fehlermeldungen, Scan-Ausgaben und Logs nennen Ort, Feld und Regel, nie den Inhalt.
- **Abhängigkeiten:** `composer audit` ohne Befund; neue Abhängigkeiten nur mit GPL-3.0-kompatibler Lizenz (Abschnitt 6.2).

**Tests**
- Jede Änderung hat Tests: Unit-Test für die Logik, Integrationstest in `bin/integration-test.sh`, wo Shopware beteiligt ist.
- Neue Regeln für Metadaten, Textbausteine oder Konfiguration bekommen einen automatischen Test, damit sie nicht nur auf dem Papier gelten.
- Alle Prüfungen aus Abschnitt 7 laufen lokal vor dem Push und in der CI; gemergt wird nur, wenn alle grün sind.

## 3. Commits, Branches, Pull Requests

- **Committet wird immer als die Person, die gerade bei GitHub angemeldet ist.**
  - Git-Name und -E-Mail müssen zum angemeldeten Konto passen: `gh api user` zeigt das Konto; die noreply-Adresse steht unter GitHub → Settings → Emails.
  - Pro Repository einstellen:
    ```bash
    git config user.name "<GitHub-Name>"
    git config user.email "<ID>+<login>@users.noreply.github.com"
    ```
  - Keine fremden Identitäten und keine automatisch erzeugten Autorenangaben oder Trailer von Werkzeugen (z. B. zusätzliche `Co-Authored-By`-Zeilen oder „Generated with …“).
- **Nie direkt auf `main` arbeiten.** Einzige Ausnahme: das Ein- und Austragen in [IN-ARBEIT.md](./IN-ARBEIT.md).
  - Jede Arbeit läuft auf einem Branch (`feature/…`, `fix/…`, `docs/…`).
  - Der Draft-PR wird **sofort** geöffnet, damit andere sehen, was läuft.
- **Zwischenstände pushen.**
  - Nach jedem Teilschritt committen und pushen.
  - So kann jederzeit jemand anderes übernehmen.
- **Mergen nur bei grüner CI:**
  - Unit-Tests PHP 8.1/8.2, `composer audit`, SBOM-Erzeugung mit Abgleich gegen OSV, Code-Style (php-cs-fixer mit den Regeln des Shopware-Kerns), PHP-7.4-Syntaxprüfung, PHPStan auf höchster Stufe mit strengen Regeln gegen Shopware 5.6.10, 5.7.19 und 5.7.20, XML-Validierung, Integrationstest gegen echtes Shopware 5.6.10 (PHP 7.4) sowie 5.7.19 und 5.7.20 (PHP 8.1/8.2).
  - Befunde werden behoben, nicht unterdrückt: keine `@phpstan-ignore`, keine Baseline, keine Typumwandlungen nur zum Ruhigstellen.
  - Gemergt wird per Merge-Commit `Merge PR #<n>: …`.
- **Commit-Nachrichten:** Deutsch, im Präsens, mit Betreffzeile von höchstens 72 Zeichen.

## 4. Changelog, Version, Release

- **Changelog:**
  - Jede Änderung bekommt im selben PR einen Eintrag unter `[Unreleased]` in [CHANGELOG.md](./CHANGELOG.md), im Format [Keep a Changelog](https://keepachangelog.com/de/1.1.0/).
  - Bei neuen Konfigurationsoptionen zusätzlich `plugin.xml` → `<changelog>` (de/en) pflegen.
- **Versionierung:** [SemVer](https://semver.org/lang/de/), Weiterentwicklung in Patch-Schritten (0.1.1, 0.1.2 …) nach dem Versionsplan der ROADMAP.
- **Release:**
  1. Version in `plugin.xml` hochsetzen.
  2. `[Unreleased]` → `[x.y.z] - JJJJ-MM-TT`.
  3. ROADMAP-Status aktualisieren.
  4. Nach dem Merge den annotierten Tag `vX.Y.Z` setzen (`git tag -a vX.Y.Z -m "Sw5CommunitySecurity X.Y.Z"`).
  5. ZIP (`bin/build-zip.sh vX.Y.Z`) und SBOM (`php bin/build-sbom.php vX.Y.Z`) bauen und beide an das GitHub-Release hängen.
- **Plugin-Updates:** Jede Version muss sich als Update über eine bestehende Installation einspielen lassen, nicht nur als Neuinstallation.

## 5. Roadmap pflegen

- Neue Befunde bekommen eine fortlaufende Nummer (F1, F2 …) mit folgenden Angaben:
  - Priorität (P1–P3)
  - Ort (Plugin, Kern oder Betrieb)
  - Quelle bzw. Quelltextstelle
  - Status
- Erledigte Punkte bleiben mit Status und Version stehen, damit die Historie nachvollziehbar ist.
- Externe Recherchen werden vor der Übernahme gegen Quelltext bzw. `composer audit` geprüft. Nicht belegte Angaben werden gekennzeichnet.

## 6. Sicherheit und Tests

- **Tests nur mit harmlosen Markern.**
  - Tests prüfen nur, ob eine Schutzmaßnahme greift, z. B. `{fetch file="sw5cs-integration-marker.txt"}`.
  - Kein Code, keine Beispiele und keine Issues mit funktionierenden Exploits.
- **Jeder Fix bekommt einen Regressionstest:** Unit-Test, und wo das Verhalten von Shopware abhängt, zusätzlich einen Integrationstest in `bin/integration-test.sh`.
- **Keine Geheimnisse ins Repository:** keine Zugangsdaten, Tokens oder personenbezogenen Daten in Code, Logs, Issues oder PRs. Die CI nutzt nur Wegwerf-Zugänge mit Zufallspasswort.
- **Fehlermeldungen und Scan-Ausgaben** nennen Ort, Feld und Regel, nie den Inhalt einer Vorlage.

### 6.1 Prüfung auf interne und vertrauliche Informationen (Pflicht)

**Alle Branches, Pull Requests und die gesamte Commit-Historie müssen frei von internen oder vertraulichen Informationen sein.** Das gilt nicht nur für den aktuellen Dateistand, sondern auch für:
- jeden einzelnen Commit, inklusive Commit-Nachrichten und gelöschter Dateien
- Branch-Namen
- PR-Titel, PR-Beschreibungen und Kommentare
- Issues, Release-Texte und CI-Logs

Nicht ins Repository gehören insbesondere:
- Zugangsdaten, Passwörter, Tokens, API-Schlüssel, private Schlüssel, `.env`-/`config.php`-Inhalte
- interne Hostnamen, Server- und IP-Adressen, interne URLs, Pfade lokaler Rechner (z. B. `/Users/<name>/…`)
- personenbezogene Daten: Namen, private oder persönliche E-Mail-Adressen, Telefonnummern, Kunden- und Bestelldaten, Logauszüge mit solchen Daten
- Kunden- und Projektnamen, Inhalte von Verträgen (z. B. Partnerverträge), Preise, Angebote, interne Absprachen
- unveröffentlichte Sicherheitslücken mit Ausnutzungsdetails (siehe [SECURITY.md](./SECURITY.md))

Freigegeben sind die öffentlichen Firmenangaben aus Impressum und README sowie `security@hqgmbh.de`.

**So wird geprüft:**
1. **Vor jedem Push** den Diff ansehen. Die Suche nach verdächtigen Mustern muss leer bleiben, Treffer werden einzeln bewertet:

   ```bash
   git diff origin/main...HEAD | grep -inE 'password|passwort|secret|token|api[_-]?key|BEGIN .*PRIVATE KEY|/Users/|/home/|[0-9]{1,3}(\.[0-9]{1,3}){3}|@[a-z0-9.-]+\.(de|com|eu)'
   ```

2. **Vor dem Öffnen und vor dem Merge eines PRs** die gesamte Branch-Historie prüfen (`git log -p origin/main..HEAD`), dazu PR-Titel, Beschreibung und Kommentare. Wer reviewt, bestätigt das im PR.
3. **Vor jedem Release-Tag** die Historie seit dem letzten Tag prüfen (`git log -p vX.Y.Z..HEAD`).
4. **Wird etwas gefunden:**
   - Nicht weiterarbeiten und den Fund nicht in einem neuen Commit „überschreiben“; er bleibt in der Historie.
   - Sofort die Verantwortlichen informieren (nicht öffentlich).
   - Betroffene Zugangsdaten **sofort** widerrufen bzw. ändern.
   - Über das Bereinigen der Historie entscheidet die verantwortliche Person, z. B. mit `git filter-repo` oder dem Entfernen von Branches und PRs über den GitHub-Support. Ein Force-Push auf `main` passiert nur nach ausdrücklicher Freigabe.

### 6.2 Fremdcode und Lizenzen (Pflicht)

Das Projekt steht unter **GPL-3.0-only**. Code, der nicht im Projekt selbst geschrieben wurde, darf nur übernommen werden, wenn seine Lizenz das erlaubt **und** die Übernahme korrekt dokumentiert ist. Das gilt für kopierte, angepasste oder übersetzte Teile genauso wie für gebündelte Bibliotheken, Schemas, Bilder und Texte.

**Zulässig**, weil mit GPL-3.0 vereinbar (jeweils mit Erhalt der Lizenz- und Copyright-Hinweise):
- GPL-3.0, GPL-3.0-or-later, GPL-2.0-or-later, LGPL-2.1-or-later, LGPL-3.0
- permissive Lizenzen wie MIT, BSD-2-Clause, BSD-3-Clause, ISC, Apache-2.0
- MPL-2.0, sofern der Code nicht als „Incompatible With Secondary Licenses“ gekennzeichnet ist

**Nur nach ausdrücklicher Freigabe durch die Maintainer:**
- **AGPL-3.0**, insbesondere Code aus dem Shopware-5-Kern: Er darf mit GPL-3.0-Code nur unter den Bedingungen der AGPL-3.0 kombiniert werden. Übernommene Teile bleiben AGPL-3.0 und müssen als solche gekennzeichnet werden.
- jede Lizenz, die nicht oben steht

**Nicht zulässig:**
- **proprietärer Code**, also Code ohne Open-Source-Lizenz: aus kommerziellen Plugins oder Produkten, aus Kundenprojekten, aus Verträgen oder internen Quellen. Das gilt ausdrücklich auch für Code von Anbietern kommerzieller Shopware-5-Sicherheitsplugins.
- Code **ohne erkennbare Lizenz**, z. B. aus Foren, Blogs, Stack-Overflow-Antworten ohne Lizenzangabe oder Gists ohne Lizenz
- mit GPL-3.0 unvereinbare Lizenzen, z. B. GPL-2.0-only, CC-BY-NC, SSPL oder „nur zur Ansicht“

**Dokumentation bei jeder Übernahme:**
1. In der Datei bzw. am übernommenen Block die Herkunft vermerken: Projekt, Datei, Version bzw. Commit und Link, ursprüngliches Copyright, SPDX-Lizenzkennung. Ursprüngliche Lizenzköpfe bleiben erhalten.
2. In [THIRD-PARTY-NOTICES.md](./THIRD-PARTY-NOTICES.md) einen Eintrag ergänzen. Verlangt die Lizenz es, auch den vollständigen Lizenztext.
3. Im PR die Übernahme ausdrücklich nennen. Die Prüfung von Lizenz und Dokumentation gehört zum Review.
4. Neue Abhängigkeiten in `composer.json` nur mit zulässiger Lizenz. `composer licenses` hilft beim Prüfen.
5. Die SBOM (`bin/build-sbom.php`) muss den Teil abdecken:
   - Eine mitgelieferte Bibliothek liegt in einem Ordner `lib/<name>/` mit `LICENSE` und `ORIGIN.md`. Die `ORIGIN.md` enthält die Zeilen `Version`, `Package URL`, `Datei / file`, `Copyright`, die SPDX-Kennung und den SHA-256-Wert der Datei.
   - Code-Teile in eigenen Dateien werden in `BUNDLED_FRAGMENTS` des Skripts eingetragen.
   - Das Skript bricht ab, wenn ein Eintrag aus THIRD-PARTY-NOTICES.md, Abschnitt „Im Plugin enthalten“, fehlt.

Was nur als **Referenz gelesen** wird, etwa der Shopware-Quelltext zur Klärung von Verhalten, wird nicht übernommen, sondern mit Fundstelle zitiert, z. B. `engine/Shopware/Core/sExport.php:1108`.

## 7. Lokale Prüfung vor dem Push

```bash
composer validate --strict
```

```bash
git ls-files -z '*.php' | xargs -0 -n1 php -l
```

```bash
vendor/bin/php-cs-fixer check --diff
```

```bash
SHOPWARE_ROOT=/pfad/zu/shopware-5.7 vendor/bin/phpstan analyse
```

```bash
SHOPWARE_ROOT=/pfad/zu/shopware-5.6 vendor/bin/phpstan analyse -c phpstan-sw56.neon.dist
```

```bash
SHOPWARE_ROOT=/pfad/zu/shopware-5.7 vendor/bin/phpstan analyse -c phpstan-tests.neon.dist
```

```bash
vendor/bin/phpunit
```

```bash
composer audit
```

```bash
bin/build-zip.sh
```

```bash
php bin/build-sbom.php
```

```bash
php bin/check-sbom-vulnerabilities.php build/Sw5CommunitySecurity-<Version>.cdx.json
```

Der Abgleich fragt die Package URLs der Komponenten bei `api.osv.dev` ab; übertragen werden nur Paketname und Version. Meldet er eine Lücke, wird die Komponente aktualisiert oder die Meldung im PR begründet bewertet, nicht unterdrückt.

Der Integrationstest läuft in der CI (`.github/workflows/ci.yml`). Lokal läuft er mit `bin/integration-test.sh <shopware-root> <port>` gegen eine Testinstallation, nie gegen einen Live-Shop.

## 8. Externe Beiträge

Beiträge von außerhalb der HQ GmbH sind willkommen. Bis weitere Maintainer hinzukommen, prüft und mergt die HQ GmbH als Maintainerin.

- **Ablauf:**
  1. Fork anlegen.
  2. Branch nach Abschnitt 3 benennen.
  3. Pull Request gegen `main` öffnen.

  Größere Änderungen vorher in einem Issue abstimmen.
- **Keine Sicherheitslücken in öffentlichen Issues oder PRs:** Sicherheitslücken werden ausschließlich wie in [SECURITY.md](./SECURITY.md) beschrieben gemeldet. Dazu gehören auch Beschreibungen, Testfälle oder Fixes, die eine noch nicht veröffentlichte Lücke erkennen lassen.
- **Lizenz der Beiträge:** Jeder Beitrag wird unter der Lizenz des Projekts eingereicht (**GPL-3.0-only**). Eine gesonderte Rechteübertragung (CLA) gibt es nicht.
- **Herkunftsbestätigung (DCO):**
  - Mit jedem Commit bestätigt die beitragende Person das [Developer Certificate of Origin 1.1](https://developercertificate.org/): Sie hat den Beitrag selbst erstellt oder darf ihn unter der Projektlizenz einreichen.
  - Die Bestätigung erfolgt mit `git commit -s`; das ergänzt die Zeile `Signed-off-by: Name <E-Mail>`.
  - Name und E-Mail müssen zum GitHub-Konto passen, unter dem der PR eingereicht wird. Die noreply-Adresse von GitHub ist erlaubt.
  - PRs ohne Sign-off werden nicht gemergt.
- **Es gelten dieselben Regeln wie intern:**
  - Shopware-Vorgaben (Abschnitt 2)
  - Fremdcode und Lizenzen (Abschnitt 6.2): kein proprietärer Code, Übernahmen dokumentieren
  - keine vertraulichen Informationen (Abschnitt 6.1)
  - Tests nur mit harmlosen Markern
  - CHANGELOG-Eintrag unter `[Unreleased]`
- **Prüfung vor dem Merge:**
  - Die CI muss grün sein. Bei Erstbeitragenden geben die Maintainer die Workflows nach Sichtung des Codes frei. Workflows aus Forks erhalten nur Leserechte und keine Secrets.
  - Mindestens eine Maintainerin bzw. ein Maintainer reviewt. Im Review werden Lizenz und Herkunft (6.2), vertrauliche Inhalte (6.1) und die Außendarstellung (siehe oben) ausdrücklich geprüft.
- **Nennung:** Beitragende bleiben über die Git-Historie als Autorinnen und Autoren sichtbar. Der Copyright-Hinweis in den Dateiköpfen nennt das Projekt bzw. die HQ GmbH als Initiatorin; eigene Copyright-Zeilen sind bei neuen Dateien zulässig.
