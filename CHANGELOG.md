# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung folgt [Semantic Versioning](https://semver.org/lang/de/).
Die Plugin-Version in `plugin.xml` und der Eintrag hier werden bei jedem Release gemeinsam gepflegt;
jedes Release erhält einen Git-Tag `vX.Y.Z`. Weiterentwicklung erfolgt in Patch-Schritten (0.1.1, 0.1.2, …).

## [Unreleased]

### Hinzugefügt
- README „Grenzen der Tests“: Getestet wird gegen Standardinstallationen. Mit rund 2.000 Plugins für Shopware 5 ergeben sich Millionen bis Milliarden möglicher Kombinationen, die nicht alle getestet werden können. Daraus folgen die Grundsätze für möglichst geringe Eingriffe und die Empfehlung, in einer Staging-Umgebung zu testen.
- README „Fragen und Probleme“: Shopbetreiber können sich an die Maintainer wenden (GitHub-Issues oder Kontaktdaten im Impressum); Lösungssuche freiwillig und ohne Supportanspruch; Sicherheitslücken nur über SECURITY.md.
- `CONTRIBUTING.md` Abschnitt 6.2 „Fremdcode und Lizenzen“: Übernahme fremden Codes nur mit GPL-3.0-kompatibler Lizenz und vollständiger Dokumentation; proprietärer Code und Code ohne Lizenz sind ausgeschlossen, AGPL-3.0-Code (z. B. aus dem Shopware-Kern) nur nach Freigabe.
- `CONTRIBUTING.md` Abschnitt 8 „Externe Beiträge“: Ablauf über Fork und Pull Request, Beiträge unter GPL-3.0-only, Herkunftsbestätigung per DCO 1.1 (`git commit -s`), Sicherheitslücken nur über SECURITY.md, Review- und CI-Pflicht.
- `ROADMAP.md` Abschnitt 2.1: Abdeckung der Smarty-Advisories durch den Template-Guard, abgeglichen mit der GitHub Advisory Database; neuer Befund F18 (Zugriff auf statische Klassen, CVE-2021-21408) für 0.1.4.
- CI-Integrationstest zusätzlich gegen Shopware 5.7.19.
- `THIRD-PARTY-NOTICES.md`: Verzeichnis enthaltenen Fremdcodes (derzeit keiner) und genutzter, nicht ausgelieferter Komponenten mit Lizenz; Teil des Release-ZIPs.

### Geändert
- README: deutscher Haftungsausschluss an deutsches Recht angepasst: Haftung bei unentgeltlicher Überlassung nur für Vorsatz und grobe Fahrlässigkeit (§ 521 BGB), Mängelhaftung nur bei arglistigem Verschweigen (§§ 523, 524 BGB), zwingende Haftung (Leben, Körper, Gesundheit, Produkthaftungsgesetz) unberührt, kein Sicherheits- oder Kompatibilitätsversprechen, keine Garantie nach § 443 BGB. Englischer Text entsprechend angeglichen.
- README defensiver formuliert: „zusätzliche Härtungsmaßnahmen“ statt „Absicherungen“, Hinweis, dass das Plugin gezielt dokumentierte Angriffswege schließt und Updates sowie eine Sicherheitsprüfung ergänzt. Roadmap-Überschrift „Weiterbetrieb und Hardening“ statt „Zukunftssicherheit“; Phase 1 nennt die verfügbaren Module.
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
