# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung folgt [Semantic Versioning](https://semver.org/lang/de/).
Die Plugin-Version in `plugin.xml` und der Eintrag hier werden bei jedem Release gemeinsam gepflegt;
jedes Release erhält einen Git-Tag `vX.Y.Z`. Weiterentwicklung erfolgt in Patch-Schritten (0.1.1, 0.1.2, …).

## [Unreleased]

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
