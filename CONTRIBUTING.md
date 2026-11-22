# Mitarbeit – Grundregeln

> **Außendarstellung:** Das Projekt tritt immer als **„von der HQ GmbH initiiertes, unabhängiges Community-Projekt“** auf. Es ist kein Produkt der HQ GmbH und, solange die Community-Struktur im Aufbau ist, auch kein bereits gemeinschaftlich getragenes Projekt. Formulierungen wie „HQ-Produkt“, „community-driven“ oder „von der Community entwickelt“ sind nicht zu verwenden. Das gilt für README, SECURITY.md, `plugin.xml`, `composer.json`, Release-Texte und PRs.

Diese Regeln gelten **für alle, die an diesem Repository arbeiten**. Sie sind verbindlich. Wer eine Regel ändern will, macht das per Pull Request an dieser Datei.

## 1. Pflichtlektüre vor jeder Arbeit

| Datei | Wozu |
|---|---|
| [ROADMAP.md](./ROADMAP.md) | Bekannte Befunde (F1 …) mit Priorität und Versionsplan |
| [CHANGELOG.md](./CHANGELOG.md) | Was veröffentlicht ist und was unter `[Unreleased]` wartet |
| [THIRD-PARTY-NOTICES.md](./THIRD-PARTY-NOTICES.md) | Enthaltener Fremdcode mit Herkunft und Lizenz (siehe Abschnitt 6.2) |
| [SECURITY.md](./SECURITY.md) | Umgang mit Sicherheitslücken. Nie öffentlich in Issues oder PRs melden. |

## 2. Shopware-Dokumentation und Entwicklervorgaben (100 %)

Das Plugin hält sich vollständig an die offiziellen Shopware-5-Vorgaben. Es wird nichts aus dem Gedächtnis oder nach Gefühl umgesetzt.

- **Maßgeblich ist zuerst die offizielle Dokumentation unter [github.com/shopware5](https://github.com/shopware5):**
  - [shopware5/devdocs](https://github.com/shopware5/devdocs): Entwicklerdokumentation, u. a. Plugin-System ab 5.2, Events, Services, Konfiguration, Konsole, Sicherheit. Das Repository ist archiviert, ist aber weiterhin die Referenz für 5.7.
  - [shopware5/shopware](https://github.com/shopware5/shopware): Quelltext von Shopware 5, maßgeblich ist das Tag `v5.7.20` (Fallback `v5.7.19`). Sagt die Doku nichts, gilt das tatsächliche Verhalten im Quelltext dieses Tags. Die Fundstelle (Datei:Zeile) gehört in einen Code-Kommentar oder den PR.
  - Die Coding-Standards des Kerns (`.php-cs-fixer.php` im Tag `v5.7.20` von `shopware5/shopware`) gelten auch hier.
- **Plugin-Aufbau nach Shopware-Vorgabe:**
  - Bootstrap `Shopware\Components\Plugin`
  - `plugin.xml`, `Resources/config.xml` und `Resources/services.xml`, jeweils gegen das offizielle XSD validiert; die CI prüft das.
  - Event-Subscriber über das Tag `shopware.event_subscriber`
  - Konsolenbefehle über das Tag `console.command`
- **Keine Änderungen am Shopware-Kern aus dem Plugin heraus:**
  - keine Dateien im Shop-Verzeichnis überschreiben
  - keine Core-Klassen per Autoload ersetzen
  - nur dokumentierte Erweiterungspunkte nutzen: Events, Hooks, Service-Dekoration
- **Unterstützt werden** Shopware 5.7.19 und 5.7.20 mit PHP 8.1 und 8.2. Alles andere ist nicht zugesichert.
- **Keine erfundenen Fakten:** CVE-Nummern, API-Verhalten und Versionsstände nur mit Quelle. Was nicht belegt ist, wird als „zu verifizieren“ markiert.
- **Store-Vorgaben:**
  - kein `die`/`exit`/`var_dump`/`print_r` im Plugin-Code
  - keine externen Aufrufe (CDN, APIs) ohne Freigabe
  - Ausgaben immer escapen

## 3. Commits, Branches, Pull Requests

- **Committet wird immer als die Person, die gerade bei GitHub angemeldet ist.**
  - Git-Name und -E-Mail müssen zum angemeldeten Konto passen: `gh api user` zeigt das Konto; die noreply-Adresse steht unter GitHub → Settings → Emails.
  - Pro Repository einstellen:
    ```bash
    git config user.name "<GitHub-Name>"
    git config user.email "<ID>+<login>@users.noreply.github.com"
    ```
  - Keine fremden Identitäten und keine Autorenangaben oder Trailer von Werkzeugen, z. B. `Co-Authored-By` von KI-Assistenten oder „Generated with …“. Wer mit einem Assistenten arbeitet, committet trotzdem unter dem eigenen angemeldeten Konto.
- **Nie direkt auf `main` arbeiten.**
  - Jede Arbeit läuft auf einem Branch (`feature/…`, `fix/…`, `docs/…`).
  - Der Draft-PR wird **sofort** geöffnet, damit andere sehen, was läuft.
- **Zwischenstände pushen.**
  - Nach jedem Teilschritt committen und pushen.
  - So kann jederzeit jemand anderes übernehmen.
- **Mergen nur bei grüner CI:**
  - Unit-Tests PHP 8.1/8.2, XML-Validierung, Integrationstest gegen echtes Shopware 5.7.20.
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

Was nur als **Referenz gelesen** wird, etwa der Shopware-Quelltext zur Klärung von Verhalten, wird nicht übernommen, sondern mit Fundstelle zitiert, z. B. `engine/Shopware/Core/sExport.php:1108`.

## 7. Lokale Prüfung vor dem Push

```bash
composer validate --strict
```

```bash
git ls-files -z '*.php' | xargs -0 -n1 php -l
```

```bash
vendor/bin/phpunit
```

```bash
bin/build-zip.sh
```

Der Integrationstest läuft in der CI (`.github/workflows/ci.yml`). Lokal läuft er mit `bin/integration-test.sh <shopware-root> <port>` gegen eine Testinstallation, nie gegen einen Live-Shop.
