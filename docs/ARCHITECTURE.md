# Architektur und Sicherheitsentscheidungen

Diese Datei beschreibt den Aufbau des Plugins und begründet die wichtigsten Sicherheitsentscheidungen. Sie ist für Mitwirkende, Prüfer und Shopbetreiber gedacht, die nachvollziehen wollen, wie das Plugin arbeitet und warum. Bekannte Befunde und Versionsplan stehen in [ROADMAP.md](../ROADMAP.md), veröffentlichte Änderungen in [CHANGELOG.md](../CHANGELOG.md), verbindliche Regeln in [CONTRIBUTING.md](../CONTRIBUTING.md).

*This file describes the plugin's structure and the reasoning behind its key security decisions. It is written in German; class names, events and file paths are given verbatim.*

Stand: Version 0.1.6 · Shopware 5.7.19/5.7.20 (PHP 8.1/8.2) und 5.6.10 (PHP 7.4)

## 1. Grundsätze

| Grundsatz | Umsetzung |
|---|---|
| **Keine Core-Eingriffe** | Das Plugin ändert keine Dateien von Shopware und ersetzt keine Core-Klassen. Es nutzt ausschließlich dokumentierte Erweiterungspunkte: Controller- und Dispatch-Events, Doctrine-Lifecycle-Events, Filter-Events und den DI-Container. So bleibt es updatefähig und lässt sich jederzeit rückstandsfrei deaktivieren. |
| **Ablehnen statt verändern** | Gefährliche Inhalte werden beim Speichern abgelehnt, nicht stillschweigend „bereinigt“. Eine automatische Bereinigung könnte Vorlagen unbemerkt verändern und wäre selbst eine Fehlerquelle. Gespeicherte Inhalte werden nie verändert. |
| **Mehrere Schutzschichten** | Wo es mehrere Speicherwege gibt (Backend, REST-API, andere Plugins), wird jeder abgedeckt: zuerst vor der Backend-Aktion mit verständlicher Meldung, zusätzlich über Doctrine als Rückfallebene. |
| **Gezielt und abschaltbar** | Jede Regel richtet sich gegen einen dokumentierten Angriffsweg. Jedes Modul lässt sich einzeln abschalten. Die Standardwerte lösen bei Shopwares eigenen Vorlagen und Einstellungen keine Funde aus (geprüft im Integrationstest, Abschnitt 0). |
| **Keine Inhalte nach außen** | Meldungen, Scan-Ausgaben und Protokolle nennen Ort, Feld und Regel, nie den Inhalt, nie Dateinamen, Benutzernamen oder IP-Adressen. |
| **Testbare Kernlogik** | Die Prüfregeln (`TemplateInspector`, `UploadInspector`, `HeaderPolicy`, `PluginConfig`) sind frei von Shopware-Abhängigkeiten und vollständig per Unit-Test abgedeckt. Die Anbindung an Shopware wird per Integrationstest gegen echtes Shopware 5.7.19 und 5.7.20 geprüft. |

## 2. Aufbau

```
Sw5CommunitySecurity.php          Plugin-Bootstrap: Cache-Leerung, Log-Stufe
Resources/
  config.xml                      Einstellungen (DE/EN)
  services.xml                    Services und Event-Subscriber
  snippets/backend/sw5_community_security/messages.ini   Meldungen (DE/EN)
Components/
  Config/          PluginConfig, PluginConfigProvider     typisierte Konfiguration je Shop
  SecurityHeaders/ HeaderPolicy                            welche Header in welchem Modul
  TemplateGuard/   TemplateInspector, TemplateGuard,       Prüfregeln für Smarty-Vorlagen,
                   GuardedSources, StoredTemplateScanner   geschützte Quellen, Scan
  UploadGuard/     UploadInspector                         Prüfregeln für Uploads
  Messages/        MessageCatalog, MessageFormatter, …     Textbausteine, HTML-Maskierung
  Database/        RowReader                               lesender Datenbankzugriff
  Compat/Sw56, Compat/Sw57                                 Adapter für Shopware 5.6 bzw. 5.7
Subscriber/
  SecurityHeaderSubscriber        Modul „HTTP-Security-Header“
  TemplateGuardSubscriber         Modul „Template-Guard“
  UploadGuardSubscriber           Modul „Upload-Härtung“
  ConfigCacheSubscriber           Cache-Leerung beim Speichern der Einstellungen
Commands/
  TemplateGuardScanCommand        bin/console sw5cs:template-guard:scan
```

## 3. Module

### 3.1 HTTP-Security-Header

- **Event:** `Enlight_Controller_Front_DispatchLoopShutdown`. Die Header werden einmal je Anfrage gesetzt, bevor Shopwares HTTP-Cache die Antwort speichert.
- **Header:** `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, optional `Strict-Transport-Security`.
- **Entscheidungen:**
  - Bereits gesetzte Header (von Shopware, Theme oder anderen Plugins) werden **nie überschrieben**. So entstehen keine Konflikte mit bestehenden, bewusst gewählten Einstellungen.
  - HSTS wird **nur über HTTPS** gesendet. Über HTTP wäre der Header wirkungslos und könnte bei Fehlkonfigurationen zu Verwirrung führen.
  - Im Backend standardmäßig aus, um Wechselwirkungen mit Backend-Modulen zu vermeiden.

### 3.2 Template-Guard

Shopware 5.7 liefert Smarty 3.1.12 mit, für das mehrere Sicherheitslücken bekannt sind (Abdeckung im Einzelnen: [ROADMAP.md](../ROADMAP.md), Abschnitt 2.1). Viele im Backend bearbeitbare Inhalte wertet Shopware als Smarty aus, darunter E-Mail-Vorlagen, Belege, SEO-Vorlagen und Textbausteine.

- **Prüfregeln (`TemplateInspector`):**
  - immer aktiv: `{extends}`, `{fetch}`, `{php}`, `{include_php}`, ungültige `{function}`-/`{block}`-Namen, `$smarty.template_object`, Zugriff auf statische Klassen (`::` in einem Tag)
  - abschaltbar (strenge Prüfung): `{include}` nur mit festen Dateinamen, `{config}`-Vorlage oder einfacher Variable; `{math}` nur mit einfachen Formeln
- **Schutzschicht 1, Backend:** Listener auf `Enlight_Controller_Action_Backend_<Controller>_<action>` für Mail, ProductFeed, Translation, NewsletterManager, Payment, Config (`saveValues`, `saveForm`), Snippet (inkl. CSV-Import) und Emotion. Gibt der Listener `true` zurück, überspringt Shopware die Aktion (`notifyUntil`). Es wird nichts gespeichert oder kompiliert; das Backend zeigt eine Meldung mit Feld und Regel.
- **Schutzschicht 2, Doctrine:** `prePersist`/`preUpdate` für die betroffenen Entities (u. a. `Mail`, `ProductFeed`, `Newsletter` samt Containern, `Document\Element`, `Payment`, `Country`, `Config\Value`, `Snippet`, `Emotion\Data`). Damit sind auch REST-API und andere Plugins abgedeckt.
- **Bestehende Inhalte:** `sw5cs:template-guard:scan` liest nur, ändert nichts und endet mit Exit-Code 1 bei Funden. Serialisierte Werte werden mit `allowed_classes => false` gelesen, damit beim Scan keine Objekte entstehen.
- **Entscheidungen:**
  - **Ablehnen beim Speichern statt Abfangen beim Rendern:** Beim Rendern ist es zu spät; die Vorlage ist bereits gespeichert und kann an vielen Stellen ausgeführt werden.
  - **Textbausteine werden geprüft**, weil Shopware Snippet-Werte vor dem Kompilieren in den Template-Quelltext einsetzt (`engine/Library/Enlight/Components/Snippet/Resource.php`).
  - **Strenge Prüfung von `{include}`/`{math}` abschaltbar**, weil individuelle Vorlagen diese Tags legitim nutzen können. Die Regel gegen statische Klassen bleibt immer aktiv, da sie keinen legitimen Anwendungsfall in gespeicherten Inhalten hat.
  - **Grenzen:** Verschachtelte Daten werden bis zur Tiefe 8 geprüft, tiefere gelten als Verstoß; Importdateien über 20 MB werden abgelehnt, statt ungeprüft durchgelassen.

### 3.3 Upload-Härtung

- **Prüfregeln (`UploadInspector`):**
  - Endungen, die Webserver häufig als PHP ausführen (`php`, `php3`–`php8`, `phtml`, `pht`, `phar` u. a.), sind in **jedem** Namensteil gesperrt, also auch in `bild.phar.jpg`.
  - Weitere ausführbare Endungen (`cgi`, `pl`, `py`, `sh`, `asp`, `jsp` u. a.) sind nur als **letzte** Endung gesperrt, damit Namen wie `anleitung.pl.pdf` möglich bleiben.
  - Konfigurationsdateien für Webserver und PHP (`.htaccess`, `.user.ini`, `web.config` u. a.) sind gesperrt.
  - Der Inhalt wird auf die PHAR-Kennung `__HALT_COMPILER` geprüft, die jedes PHAR-Archiv enthält, auch wenn es als Bild getarnt ist.
- **Schutzschichten:**
  1. Bekannte Backend-Uploads (Medienverwaltung, ESD, Snippet- und Einkaufswelten-Import) werden vor der Aktion mit Meldung abgelehnt.
  2. Bei allen übrigen Anfragen werden betroffene Dateien vor der Aktion aus der Anfrage entfernt und gelöscht (`Enlight_Controller_Action_PreDispatch`).
  3. Die ESD-Blacklist wird über Shopwares Filter-Event erweitert.
  4. Medien, die auf anderem Weg über Doctrine gespeichert werden, werden beim `prePersist` geprüft. Da Shopware die Datei schon vorher schreibt (`Media::onSave`), wird sie bei einem Fund wieder gelöscht.
- **Entscheidungen:**
  - **Plugin-Installer ausgenommen:** Ein Plugin zu installieren führt seinen Code ohnehin aus; eine Sperre würde keine Sicherheit bringen, aber Updates verhindern.
  - **Inhaltsprüfung zusätzlich zur Endung:** `media/` ist öffentlich erreichbar. Ob eine Datei ausgeführt wird, hängt von der Webserver-Konfiguration ab, die das Plugin nicht kennt.

## 4. Querschnitt

- **Meldungen:** Textbausteine im Namespace `backend/sw5_community_security/messages` auf Deutsch und Englisch. Sie enthalten keine Smarty-Syntax (Platzhalter im Stil `[name]`). Text und eingesetzte Werte werden HTML-maskiert, auch wenn ein Administrator einen Textbaustein ändert.
- **Protokollierung:** plugin-eigener Logger `sw5_community_security.logger`, Stufe „Warning“, auch wenn Shopware im Produktivmodus sonst erst ab „Error“ protokolliert. Protokolliert werden nur Ort, Feld, Regel bzw. Dateiendung.
- **Konfiguration:** je Shop über Shopwares Konfigurations-Reader, typisiert in `PluginConfig` mit sicheren Standardwerten bei fehlenden oder ungültigen Werten.
- **Cache:** Leerung bei Aktivieren, Deaktivieren und Update; bei Deinstallation nur, wenn das Plugin aktiv war; beim Speichern der Einstellungen im Backend automatisch (Konfigurations- und HTTP-Cache).

## 5. Shopware 5.6 und 5.7

Eine Code-Basis für beide Versionen:

- **PHP-7.4-Syntax** im Plugin-Code, damit es unter Shopware 5.6.10 läuft. Typen, die PHP 7.4 nicht kennt (`mixed`), stehen als PHPDoc und werden von PHPStan geprüft.
- **Adapter** für die zwei abweichenden Schnittstellen: Konfigurations-Reader und Doctrine DBAL. `Sw5CommunitySecurity::build()` wählt beim Aufbau des Containers anhand der vorhandenen Klassen die passende Umsetzung; Klassennamen der jeweils anderen Version werden nur als Zeichenkette genannt und nie geladen.
- **Getrennte statische Analyse** gegen Shopware 5.6.10 und 5.7.x; jede Analyse schließt nur die Adapter der anderen Version aus.

Begründung: Eine eigene Code-Linie für 5.6 hätte jede Sicherheitskorrektur doppelt nötig gemacht. Mit einer Code-Basis erhalten beide Versionen dieselben Prüfregeln und dieselben Tests.

## 6. Qualitätssicherung

| Prüfung | Werkzeug | Wo |
|---|---|---|
| Code-Style | php-cs-fixer mit den Regeln des Shopware-Kerns | CI, lokal |
| PHP-7.4-Syntax | `php -l` mit PHP 7.4 für Plugin-Code und Integrationsskripte | CI |
| Statische Analyse | PHPStan, höchste Stufe, strict-, deprecation- und PHPUnit-Regeln, gegen Shopware 5.6.10, 5.7.19 und 5.7.20 | CI, lokal |
| Unit-Tests | PHPUnit, PHP 8.1 und 8.2, inkl. Test der Plugin-Metadaten und Übersetzungen | CI, lokal |
| Integrationstest | `bin/integration-test.sh`: echte Shopware-Installation 5.7.19 und 5.7.20 × PHP 8.1 und 8.2 sowie 5.6.10 × PHP 7.4, Installation aus dem Release-ZIP, Backend-Anfragen, Doctrine, Scan, Logs, Deinstallation | CI |
| Abhängigkeiten | `composer audit` | CI |
| XML | Validierung gegen Shopwares XSD-Schemas | CI |

Tests arbeiten nur mit harmlosen Markern, die prüfen, ob eine Schutzmaßnahme greift. Funktionierende Exploits sind nicht Teil des Repositorys.

## 7. Grenzen

- Das Plugin blockiert gezielt bekannte, dokumentierte Angriffswege. Es ersetzt weder Updates von PHP, Server und Plugins noch eine individuelle Sicherheitsprüfung.
- Getestet wird gegen Standardinstallationen. Kombinationen mit individuellen Themes und Drittanbieter-Plugins lassen sich nicht vollständig testen (README, „Grenzen der Tests“).
- Lücken, die nicht über die genannten Erweiterungspunkte erreichbar sind, lassen sich nur im Kern schließen; dafür ist ein separates Projekt vorgesehen (ROADMAP, Abschnitt 4).
