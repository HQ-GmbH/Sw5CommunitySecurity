# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung folgt [Semantic Versioning](https://semver.org/lang/de/).
Die Plugin-Version in `plugin.xml` und der Eintrag hier werden bei jedem Release gemeinsam gepflegt;
jedes Release erhält einen Git-Tag `vX.Y.Z`. Weiterentwicklung erfolgt in Patch-Schritten (0.1.1, 0.1.2, …).

## [Unreleased]

## [0.1.6] - 2026-11-23

### Hinzugefügt
- Unterstützung für **Shopware 5.6.10 mit PHP 7.4**: alle Module (HTTP-Security-Header, Template-Guard, Upload-Härtung) laufen zusätzlich unter Shopware 5.6.10. Unterschiede zwischen 5.6 und 5.7 (Konfigurations-Reader, Doctrine DBAL, nullable `View()`) werden über Adapter abgefangen.
- CI: PHP-7.4-Syntaxprüfung, PHPStan gegen Shopware 5.6.10 und Integrationstest gegen echtes Shopware 5.6.10 mit PHP 7.4.
- `docs/ARCHITECTURE.md`: Aufbau des Plugins, Schutzschichten je Modul und Begründung der Sicherheitsentscheidungen.
- README „Kompatibilität“: Tabelle der unterstützten Shopware- und PHP-Versionen mit Hinweis zu den Grenzen von Shopware 5.6 und der Empfehlung, auf 5.7.20 zu aktualisieren; ROADMAP Abschnitt 3.1 zu Shopware 5.6.

### Geändert
- Backend-Action-Events werden versionsunabhängig ausgewertet (Shopware 5.6 übergibt einfache Event-Argumente); Event-Subscriber und Konsolenbefehl sind öffentliche Services, wie Shopware 5.6 es verlangt.
- Meldungen werden ausfallsicher geladen: Lässt sich ein Textbaustein nicht laden, gilt der eingebaute Standardtext; die Ablehnung greift immer.
- Plugin-Code PHP-7.4-kompatibel; Mindestversion in `plugin.xml` 5.6.10, in `composer.json` PHP `^7.4 || ^8.0`. Statische Analyse getrennt für Shopware 5.6, 5.7 und die Tests.
- Außendarstellung: „von der HQ GmbH initiiertes und derzeit betreutes, unabhängiges Open-Source-Projekt, offen für Beiträge aus der Community“ in README, SECURITY.md, CONTRIBUTING.md, `plugin.xml` und `composer.json`.
- README „Über das Projekt“: Anlass, Rolle der HQ GmbH (Architektur, Entwicklung, Tests, Wartung und Releases), Projektleitung und Maintainer, Abgrenzung zu Dienstleistungen.

## [0.1.5] - 2026-11-23

### Hinzugefügt
- Protokollierung abgelehnter Vorlagen und Dateien über den plugin-eigenen Logger (`var/log/sw5_community_security_<Umgebung>-<Datum>.log`, ab Stufe „Warning“) mit Ort, Feld und Regel bzw. Dateiendung; Inhalte, Dateinamen, Benutzernamen und IP-Adressen werden nicht protokolliert.
- Meldungen als Textbausteine auf Deutsch und Englisch (`Resources/snippets`, Namespace `backend/sw5_community_security/messages`), im Backend anpassbar; alle Texte und eingesetzten Werte werden HTML-maskiert.
- Beim Speichern der Plugin-Einstellungen im Backend werden Konfigurations- und HTTP-Cache automatisch geleert.
- Beschreibungen auf Deutsch und Englisch für alle Einstellungen.
- Qualitätsprüfungen in der CI: Code-Style nach den php-cs-fixer-Regeln des Shopware-Kerns, PHPStan auf höchster Stufe mit strengen Regeln gegen Shopware 5.7.19 und 5.7.20, `composer audit`, Test der Plugin-Metadaten (Pflichtfelder, Übersetzungen, Textbausteine).
- Integrationstests für Cache-Leerung beim Speichern, Textbausteine, Protokollierung und Deinstallation.

### Geändert
- Beim Deinstallieren werden die Caches nur geleert, wenn das Plugin aktiv war.
- Typen beim Entfernen von Upload-Dateien und beim Lesen von Anfrage- und Datenbankwerten präzisiert; scheitert das Löschen einer abgelehnten temporären Upload-Datei, wird das protokolliert.
- `CONTRIBUTING.md` Abschnitt 2.1 „Qualitätsanspruch: Vorgaben übererfüllen“: verbindliche Regeln für Code, Plugin-Guidelines, Sicherheit, Datenschutz und Tests für alle Mitwirkenden.
- `SECURITY.md`: Unterstützt wird jeweils das neueste Release; die Tabelle nennt keine festen Versionsnummern mehr. Betreuung verweist auf die Seite „Shopware 5 Wartung & Support“.

## [0.1.4] - 2026-11-22

### Hinzugefügt
- Template-Guard für Textbausteine (Snippets): Anlegen und Ändern im Backend, CSV-Import und Doctrine. Shopware setzt Snippet-Werte vor dem Kompilieren in den Template-Quelltext ein, daher wird jeder Textbaustein als Smarty ausgeführt (F16).
- Template-Guard für das Einkaufswelten-Element „HTML Code“: Speichern, Vorschau, Übersetzungen (`emotionElement`) und Doctrine (F17).
- Template-Guard-Regel `static-class`: lehnt Zugriff auf statische Klassen (`Klasse::…`) in Smarty-Tags ab (CVE-2021-21408, F18); immer aktiv.
- Modul „Upload-Härtung“ (Einstellung `uploadGuardEnabled`, standardmäßig aktiv):
  - sperrt Dateien mit Endungen, die Webserver als PHP ausführen können, z. B. `.phar`, `.php7`, `.pht`, auch als Mehrfachendung wie `bild.phar.jpg`
  - sperrt Dateien mit PHAR-Inhalt, auch wenn sie als Bild getarnt sind
  - gilt für Medienverwaltung, ESD-Dateien und Snippet- bzw. Einkaufswelten-Import mit Fehlermeldung; bei allen übrigen Uploads werden solche Dateien vor der Verarbeitung entfernt, der Plugin-Installer ist ausgenommen
  - erweitert die ESD-Blacklist über Shopwares Filter-Event
  - prüft Medien zusätzlich beim Speichern über Doctrine und entfernt eine abgelehnte Datei wieder aus dem Speicher (F5)
- Scan-Befehl prüft zusätzlich Textbausteine und Übersetzungen von Einkaufswelten-Elementen.
- Integrationstest für alle neuen Wege gegen Shopware 5.7.19 und 5.7.20.
- `IN-ARBEIT.md`: Wer an welchem Befund arbeitet (GitHub-Nutzer, Befund, Branch), damit mehrere Personen parallel arbeiten können; Ein- und Austragen als einzige erlaubte direkte Änderung auf `main`. Nicht Teil des Release-ZIPs.
- README „Grenzen der Tests“: Getestet wird gegen Standardinstallationen. Mit rund 2.000 Plugins für Shopware 5 ergeben sich Millionen bis Milliarden möglicher Kombinationen, die nicht alle getestet werden können. Daraus folgen die Grundsätze für möglichst geringe Eingriffe und die Empfehlung, in einer Staging-Umgebung zu testen.
- README „Fragen und Probleme“: Shopbetreiber können sich an die Maintainer wenden (GitHub-Issues oder Kontaktdaten im Impressum); Lösungssuche freiwillig und ohne Supportanspruch; Sicherheitslücken nur über SECURITY.md.
- `CONTRIBUTING.md` Abschnitt 6.2 „Fremdcode und Lizenzen“: Übernahme fremden Codes nur mit GPL-3.0-kompatibler Lizenz und vollständiger Dokumentation; proprietärer Code und Code ohne Lizenz sind ausgeschlossen, AGPL-3.0-Code (z. B. aus dem Shopware-Kern) nur nach Freigabe.
- `CONTRIBUTING.md` Abschnitt 8 „Externe Beiträge“: Ablauf über Fork und Pull Request, Beiträge unter GPL-3.0-only, Herkunftsbestätigung per DCO 1.1 (`git commit -s`), Sicherheitslücken nur über SECURITY.md, Review- und CI-Pflicht.
- `ROADMAP.md` Abschnitt 2.1: Abdeckung der Smarty-Advisories durch den Template-Guard, abgeglichen mit der GitHub Advisory Database; neuer Befund F18 (Zugriff auf statische Klassen, CVE-2021-21408) für 0.1.4.
- CI-Integrationstest zusätzlich gegen Shopware 5.7.19.
- `THIRD-PARTY-NOTICES.md`: Verzeichnis enthaltenen Fremdcodes (derzeit keiner) und genutzter, nicht ausgelieferter Komponenten mit Lizenz; Teil des Release-ZIPs.

### Geändert
- README, `plugin.xml`, `composer.json` und Einstellungstexte: neues Modul und erweiterter Umfang des Template-Guards. `ROADMAP.md`: F5, F16, F17 und F18 auf den Stand von 0.1.4 gebracht, Betreiber-Empfehlung zur PHP-Ausführung in Upload-Verzeichnissen.
- README: deutscher Haftungsausschluss an deutsches Recht angepasst: Haftung bei unentgeltlicher Überlassung nur für Vorsatz und grobe Fahrlässigkeit (§ 521 BGB), Mängelhaftung nur bei arglistigem Verschweigen (§§ 523, 524 BGB), zwingende Haftung (Leben, Körper, Gesundheit, Produkthaftungsgesetz) unberührt, kein Sicherheits- oder Kompatibilitätsversprechen, keine Garantie nach § 443 BGB. Englischer Text entsprechend angeglichen.
- README defensiver formuliert: „zusätzliche Härtungsmaßnahmen“ statt „Absicherungen“, Hinweis, dass das Plugin gezielt dokumentierte Angriffswege blockiert und Updates sowie eine Sicherheitsprüfung ergänzt. Roadmap-Überschrift „Weiterbetrieb und Hardening“ statt „Zukunftssicherheit“; Phase 1 nennt die verfügbaren Module.
- README: Markenhinweis auch im englischen Hinweisblock; Impressum-Link mit vollständiger URL als Linktext.
- Lizenzkopf in `bin/validate-xml.php`, `bin/build-zip.sh` und `bin/integration-test.sh` ergänzt.

## [0.1.3] - 2026-11-20

### Hinzugefügt
- Template-Guard für alle weiteren Stellen, an denen Shopware 5.7.20 gespeicherte Inhalte als Smarty auswertet:
  - Newsletter: Betreff, Absender, Container-Beschreibung, Text- und Link-Container
  - Beleg-Bausteine
  - SEO-URL-Vorlagen
  - E-Mail-Header/-Footer und „wesentliche Merkmale“ in den Grundeinstellungen
  - Zusatzbeschreibungen von Zahlungsarten
  - Länderhinweise
  - Übersetzungen der Typen `documents`, `config_payment` und `config_countries`

  Geprüft wird im Backend vor dem Speichern und zusätzlich über die Doctrine-Events der betroffenen Modelle. Bei den Grundeinstellungen erfolgt die Prüfung, bevor Shopware die bisherigen Werte löscht.
- Strenge Prüfung von `{include}` und `{math}` (CVE-2022-29221, CVE-2021-29454), neue Einstellung `templateGuardStrictIncludeMath`, standardmäßig aktiv:
  - `{include}` erlaubt nur Vorlagenpfade ohne `..` und die von Shopware selbst genutzten Formen `string:{config name=…}` und `string:{$variable}`.
  - `{math}` erlaubt nur feste Formeln.
- Konsolenbefehl `sw5cs:template-guard:scan`:
  - prüft bereits gespeicherte Inhalte, einschließlich des Einkaufswelten-Elements „HTML Code“, und ändert dabei nichts
  - nennt nur Ort, Feld und Regel
  - Exit-Code 1 bei Funden; `--relaxed` ohne die strengen `{include}`-/`{math}`-Regeln
- Verschachtelte Daten (z. B. Newsletter-Container, Beleg-Übersetzungen) werden rekursiv geprüft. Unplausibel tief verschachtelte Daten werden abgelehnt.
- Integrationstest gegen Shopware 5.7.20:
  - Scan einer frischen Installation ohne Funde
  - alle neuen Backend-Wege und Doctrine-Fälle
  - Scan erkennt einen direkt in der Datenbank abgelegten Marker, ohne dessen Inhalt auszugeben
- `CONTRIBUTING.md` mit verbindlichen Grundregeln für alle Mitarbeitenden (Roadmap, Changelog, Commits als angemeldetes GitHub-Konto, Shopware-5-Vorgaben, Tests); nicht Teil des Release-ZIPs. Pflichtprüfung aller Branches, PRs und der Commit-Historie auf interne bzw. vertrauliche Informationen (CONTRIBUTING.md Abschnitt 6.1).

### Geändert
- `ROADMAP.md` um Befunde F10–F15 ergänzt:
  - Fehlende Schutz-`.htaccess`
  - Erreichbares `recovery/install/` (CVE-2022-48150)
  - PHP-Lücken vom Oktober 2026 und Supportende von PHP 8.2
  - Kunden-Sessions und SameSite
  - 2FA und Passwortregeln für Backend-Konten
  - Captcha bei „Passwort vergessen“
  
  Außerdem ein neuer Abschnitt zur PHP-Laufzeit, erweiterte Betreiber-Empfehlungen, Versionsplan bis 0.3.0 und Hinweise zu nicht belegten (CVE-2026-108082) bzw. im Lockfile von 5.7.20 nicht zutreffenden Einträgen (CVE-2022-24894).
- `ROADMAP.md`: F1–F3 auf den Stand von 0.1.3 gebracht, neue Befunde F16 (Smarty in Validierungsmeldungen aus Snippets) und F17 (Einkaufswelten-Element „HTML Code“).
- README, `plugin.xml`, Einstellungstexte: Umfang des Template-Guards aktualisiert.
- Einheitliche Außendarstellung in README, SECURITY.md, `plugin.xml` und `composer.json`: „von der HQ GmbH initiiertes, unabhängiges Community-Projekt“, weder Produkt der HQ GmbH noch der shopware AG; Community-Struktur im Aufbau, bis dahin betreut durch die HQ GmbH. Die Formulierung „community-driven“ wurde entfernt.

## [0.1.2] - 2026-11-20

### Hinzugefügt
- Modul „Template-Guard“ (Konfiguration `templateGuardEnabled`, standardmäßig aktiv): lehnt beim Speichern im Backend E-Mail-Vorlagen, deren Übersetzungen und Produktexporte ab, die `{extends}`, `{fetch}`, `{php}`/`{include_php}`, `{function}`- bzw. `{block}`-Tags mit ungültigem Namen oder `$smarty.template_object` enthalten (Smarty 3.1.12: CVE-2024-35226, CVE-2021-26120, CVE-2021-26119, CVE-2026-62993 sowie CVE-2022-29221 für `{block}`-Namen). Gilt auch für „Smarty prüfen“ im E-Mail-Modul, das Vorlagen ohne Speichern kompiliert. Zusätzliche Absicherung über die Doctrine-Events der Modelle `Mail` und `ProductFeed`. Die Fehlermeldung nennt nur Feld und Regel, nie den Vorlageninhalt.
- Unit-Tests für die Erkennung; Integrationstest gegen Shopware 5.7.20 über Backend (Login mit CI-eigenem Test-Benutzer) und Doctrine – mit harmlosen Eingaben, die nur das Greifen der Prüfung testen.
- `ROADMAP.md`: bekannte Sicherheitsbefunde für Shopware 5.7.20 (Smarty, Backend-Bibliotheken, Composer-Abhängigkeiten) mit Priorität, Zuordnung (Plugin/Kern/Betrieb) und Versionsplan ab 0.1.2.

### Geändert
- README, `plugin.xml`, `composer.json`: Template-Guard als verfügbares Modul aufgeführt; ROADMAP.md: Versionsplan angepasst (0.1.2 Template-Guard statt Proof-of-Concept-Tests).
- README: Markenhinweis um den Partnerstatus der HQ GmbH ergänzt, verbunden mit der Klarstellung, dass das Projekt kein Produkt der shopware AG ist und von ihr weder unterstützt noch zertifiziert wird.

## [0.1.1] - 2026-11-19

### Geändert
- Lizenzangabe präzisiert: ausschließlich GPL-3.0 (SPDX `GPL-3.0-only` statt `GPL-3.0-or-later`) in `composer.json`, `plugin.xml` und README.
- README: Funktionsumfang nach verfügbaren und geplanten Modulen getrennt, absolute Kompatibilitätsaussage („100 %“) entfernt, Roadmap als unverbindliche Ziele gekennzeichnet, Impressum verlinkt; Beschreibungen in `plugin.xml` und `composer.json` entsprechend angepasst.

## [0.1.0] - 2026-11-19

### Hinzugefügt
- Plugin-Grundgerüst für Shopware 5.7.19+ (`Sw5CommunitySecurity`) nach dem Plugin-System ab Shopware 5.2: Bootstrap, `plugin.xml`, `Resources/config.xml`, `Resources/services.xml`.
- Modulare, pro Shop konfigurierbare Einstellungen; jedes Modul lässt sich einzeln abschalten.
- Modul „HTTP-Security-Header“: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` sowie optional `Strict-Transport-Security` (standardmäßig aus, nur über HTTPS). Bereits vorhandene Header werden nie überschrieben; alle Werte werden gegen eine Whitelist geprüft.
- Leeren des HTTP-Caches bei Aktivierung, Deaktivierung, Update und Deinstallation, da Header in gecachten Antworten gespeichert werden.
- `composer.json` für die Installation per Composer (`type: shopware-plugin`, Installation nach `custom/plugins/Sw5CommunitySecurity`).
- Unit-Tests für die Shopware-unabhängigen Klassen (`PluginConfig`, `HeaderPolicy`).
- `bin/build-zip.sh` zum Erzeugen eines Release-ZIPs für den Plugin-Manager.
- GitHub-Actions-Workflow (PHP 8.1 und 8.2): `composer validate`, PHP-Lint, XML-Prüfung, Unit-Tests, Release-ZIP-Prüfung; Actions auf Commit-SHAs gepinnt.
- CI-Integrationsjob mit echtem Shopware 5.7.20 (PHP 8.1 und 8.2, MySQL 8.0): XSD-Validierung der Plugin-XML gegen die Shopware-Schemas (`bin/validate-xml.php`), Installation aus dem Release-ZIP, Header-Prüfung echter Antworten inkl. Konfigurationswechsel und Deaktivierung (`bin/integration-test.sh`).

### Geändert
- SECURITY.md: leeren Listenpunkt entfernt, Meldeadresse als `mailto:`-Link.
- README: abgeschnittenen Haftungsausschluss vervollständigt (DE/EN); Composer-Installation um `allow-plugins` für `composer/installers` und die Paketquelle bis zur Packagist-Veröffentlichung ergänzt.
- Repository in die Organisation `HQ-GmbH` verschoben; Links in `SECURITY.md`, `plugin.xml` und `composer.json` zeigen auf `github.com/HQ-GmbH/Sw5CommunitySecurity`.
