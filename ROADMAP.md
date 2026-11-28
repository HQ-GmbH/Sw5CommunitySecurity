# Roadmap

Stand: **2026-11-28** · Basis: Shopware **5.7.20** (letzte offizielle Shopware-5-Version) · Plugin-Version: **0.4.9**

> Diese Roadmap beschreibt Ziele und den aktuellen Kenntnisstand, **keine zugesicherten Funktionen oder Termine**. Jeder Fund ist mit einer Quelle oder einer Quelltextstelle in Shopware 5.7.20 belegt; Punkte mit „zu verifizieren“ sind noch nicht durch einen Test bestätigt. Sicherheitslücken bitte nicht öffentlich melden, sondern wie in [SECURITY.md](./SECURITY.md) beschrieben.

## Legende

| Ort | Bedeutung |
|---|---|
| **Plugin** | Absicherung über dieses Plugin, ohne Core-Änderung (Versionen 0.1.x) |
| **Core** | nur durch Änderung des Shopware-Kerns oder seiner Abhängigkeiten lösbar – separates Projekt (PHP 8.3/8.4-Kern) |
| **Betrieb** | Konfiguration von Server bzw. Shop durch den Betreiber |

Priorität: **P1** zuerst (Code-Ausführung, Datenabfluss), **P2** (XSS, Umgehungen mit Voraussetzungen), **P3** (Härtung, geringe Ausnutzbarkeit).

## 1. Ausgangslage

### 1.1 Shopware-5-Code selbst
- Für den Shopware-5.7-Code sind **keine offenen CVEs/GitHub-Advisories** bekannt. Die letzten SW5-Advisories ([CVE-2023-34098](https://github.com/shopware5/shopware/security/advisories/GHSA-q97c-2mh3-pgw9), [CVE-2023-34099](https://github.com/shopware5/shopware/security/advisories/GHSA-gh66-fp7j-98v5)) sind seit 5.7.18 behoben.
- Der Sicherheitssupport von Shopware 5 endete laut [endoflife.date](https://endoflife.date/shopware) am 31.07.2024; das letzte gefundene offizielle Security-Update stammt aus [06/2023](https://docs.shopware.com/en/shopware-5-en/security-updates/security-update-06-2023).
- Nach dem Supportende wurden öffentlich weitere Schwachstellen in Shopware 5 erwähnt, die in 5.7.20 nicht behoben sind, jedoch ohne CVE und ohne technische Details:
  - **Backend-XSS in E-Mail-Vorlagen** (F6)
  - **Ausführung von PHAR-Dateien**, u. a. über ESD-Uploads (F5)
  - **Fehlende Schutz-`.htaccess`** in `vendor/` nach Neuerzeugung des Ordners bzw. nach dem Leeren von Caches oder Systemordnern (F10)
  
  Diese Punkte werden hier eigenständig am Quelltext untersucht und erst nach eigener Bestätigung als Befund geführt.
- Ältere Lücke im Installer: [CVE-2022-48150](https://nvd.nist.gov/vuln/detail/CVE-2022-48150) (XSS unter `/recovery/install/`). In 5.7.20 sperrt sich der Installer über `recovery/install/data/install.lock` selbst (`recovery/install/index.php`). Das Verzeichnis wird aber mit ausgeliefert und bleibt erreichbar (F11).
- Die Advisory-Datenbanken nennen keinen offenen Fehler, wohl aber fehlende Härtungen im Auslieferungszustand:
  - Der Captcha-Schutz für „Passwort vergessen“ ist nur optional; die Einstellung `passwordResetCaptcha` gibt es seit Migration 1713.
  - Parallele Kunden-Sessions werden nicht beendet.
  - Für Backend-Konten fehlen 2FA und konfigurierbare Passwortregeln.
  - Für das Session-Cookie lässt sich `SameSite` nicht einstellen.
  
  Siehe F13–F15.
- Die zahlreichen Advisories zu `shopware/shopware` aus 2025/2026 betreffen **Shopware 6** und sind hier nicht relevant. Geprüft: Die dort beschriebene Admin-Übernahme über manipulierte `Host`-Header ist in SW5 nicht übertragbar – das Backend hat keine Passwort-Reset-Funktion, das Frontend leitet fremde Hosts vor dem Dispatch auf den Shop-Host um (`engine/Shopware/Plugins/Default/Core/Router/Bootstrap.php`).

### 1.2 Mitgelieferte Bibliotheken (nicht über Composer)

| Komponente | Version in 5.7.20 | Befund |
|---|---|---|
| Smarty | 3.1.12 (`engine/Library/Smarty`, von Shopware für PHP 8.x angepasst) | mehrere Code-Injection-Lücken, siehe Abschnitt 2 |
| TinyMCE (Backend-Editor) | 3.5.12 (`engine/Library/TinyMce`; 3.5.8 in Shopware 5.6.10) | liegt formal in den Bereichen zahlreicher XSS-Advisories ([Übersicht](https://github.com/advisories?query=tinymce)); geprüft 2026-11-26, siehe F7: Skript in Editor-Inhalten lehnt das Plugin ab 0.3.5 ab |
| Ext JS (Backend) | 4.1.1.1 | [CVE-2018-8046](https://www.cve.org/CVERecord?id=CVE-2018-8046) (XSS in `getTip()` von Action Columns) nicht anwendbar, die Funktion fehlt in 4.1.1.1 (geprüft 2026-11-26, siehe F7); gespeicherte Werte zeigt das Backend ab 0.4.0/0.4.1 als Text (F20) |
| jQuery (Responsive-Theme) | 3.6.0 | keine offene Advisory bekannt |
| mpdf | v8.1.6 | keine offene Advisory bekannt |
| Zend Framework 1 (Teilbaum) | 1.12.3 (mit Shopware-Backports) | bekannte Fixes zurückportiert (z. B. `Zend_Db_Select`, CRLF-Filter in `Zend_Mail`); Rest nicht im Detail abgeglichen |

### 1.3 Composer-Abhängigkeiten
`composer audit` auf das `composer.lock` von 5.7.20 meldet **38 Advisories in Laufzeit-Abhängigkeiten** (dazu 7 in reinen Entwicklungs-Abhängigkeiten). Vollständige Liste in [Anhang A](#anhang-a-composer-audit-shopware-5720). Nicht jede Lücke ist in Shopware 5 erreichbar – z. B. nutzt der SW5-Code `Symfony\Component\Mime` und den `DataUriNormalizer` gar nicht, `jmespath.php` wird nur indirekt über das AWS-SDK (S3-Medienadapter) geladen.

Nachgetragen (nicht in Anhang A, weil erst nach dem Audit vom 2026-11-20 veröffentlicht): **[CVE-2026-108082](https://github.com/advisories/GHSA-r925-mgvr-h9cc)** (GHSA-r925-mgvr-h9cc, 27.10.2026, Schwere „high“), ReDoS in `HeaderUtils::split()` von `symfony/http-foundation`, erreichbar über den Request-Header `Cache-Control`. Betroffen laut Advisory ≥ 4.1.0 und < 5.4.54; Shopware 5.7.20 enthält v4.4.49, für 4.4 gibt es keine Korrektur. Geprüft am 2026-11-27: Symfony wertet den Header schon beim Anlegen jeder Anfrage aus (`vendor/symfony/http-foundation/HeaderBag.php:160`, `parseCacheControl()`), also ohne Anmeldung und vor jedem Plugin. Laut Advisory kostet eine Anfrage bis knapp eine Sekunde CPU, danach greift `pcre.backtrack_limit`. Im Plugin nicht abfangbar; behebbar nur im Kern (Backport des Fixes, siehe Abschnitt 4).

[CVE-2022-24894](https://nvd.nist.gov/vuln/detail/CVE-2022-24894) (`symfony/http-kernel` < 4.4.50) betrifft das `composer.lock` von 5.7.20 nicht, dort ist v4.4.50 eingetragen. Bei eigenen Lockfiles mit älterem Stand ist die Lücke offen.

### 1.4 PHP-Laufzeit
- Shopware 5.7.19/5.7.20 unterstützt laut `composer.json` PHP bis 8.2. Der Sicherheitssupport für PHP 8.2 endet am **31.12.2026** ([php.net](https://www.php.net/supported-versions.php)); 7.4, 8.0 und 8.1 sind bereits ohne Support.
- Geprüft am 2026-11-27 gegen die [Sicherheits-Advisories von PHP](https://github.com/php/php-src/security/advisories):
  - **21.10.2026:** [CVE-2026-109618](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w) und [CVE-2026-109619](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp), beide kritisch (Code-Ausführung aus der Ferne), behoben in 8.2.35, 8.3.36, 8.4.27 und 8.5.12.
  - **19.11.2026:** 17 weitere Advisories, davon mehrere kritisch, u. a. [CVE-2026-115560](https://github.com/php/php-src/security/advisories/GHSA-658j-r8rw-q6mx) (Upload-Fortschritt der Session mit abweichendem Serialisierer) und [CVE-2026-126714](https://github.com/php/php-src/security/advisories/GHSA-fm2m-fjf5-v3cp) (teilweise überschriebene Session-Dateien). Behoben in 8.2.36, 8.3.37, 8.4.28 und 8.5.13.
  - Für 8.1 nennen die Advisories betroffene Versionen, aber keine Korrektur mehr.
  - Die Abschwächung `session.upload_progress.enabled = 0` betrifft nur CVE-2026-115560, nicht die übrigen Lücken.
- Diese Lücken schließt kein Plugin, sondern nur ein PHP-Update des Betreibers bzw. Hosters. Das Plugin kann aber warnen (F12).

## 2. Befunde und Zuordnung

| # | Befund | Prio | Ort | Status |
|---|---|---|---|---|
| F1 | **Smarty `{extends}`-Code-Injection** – [CVE-2024-35226](https://github.com/smarty-php/smarty/security/advisories/GHSA-4rmg-292m-wg3w). `smarty_internal_compile_extends.php:76` führt `eval('$include_file = ' . $_attr['file'] . ';')` aus. | P1 | Plugin + Core | abgeschwächt ab 0.1.2/0.1.3 (Template-Guard für alle bekannten Smarty-Quellen außer F16/F17, Scan bestehender Inhalte); vollständig behebbar nur im Kern |
| F2 | **Smarty `{function name=…}`-Code-Injection** – [CVE-2021-26120](https://github.com/smarty-php/smarty/security/advisories/GHSA-3rpf-5rqv-689q). Der Name wird ungeprüft in PHP-Code eingesetzt (`smarty_internal_compile_function.php`). | P1 | Plugin + Core | abgeschwächt ab 0.1.2/0.1.3 (Template-Guard für alle bekannten Smarty-Quellen außer F16/F17, Scan bestehender Inhalte); vollständig behebbar nur im Kern |
| F3 | Weitere Smarty-Lücken mit formal betroffenem Versionsbereich: [CVE-2022-29221](https://github.com/smarty-php/smarty/security/advisories/GHSA-634x-pc3q-cf4c), [CVE-2021-26119](https://github.com/smarty-php/smarty/security/advisories/GHSA-w5hr-jm4j-9jvq), [CVE-2021-29454](https://github.com/smarty-php/smarty/security/advisories/GHSA-29gp-2c3m-3j6m), [CVE-2021-21408](https://github.com/smarty-php/smarty/security/advisories/GHSA-4h9c-v5vg-5m6m), [CVE-2026-82531](https://www.cve.org/CVERecord?id=CVE-2026-82531) (nur mit Smarty-Ausgabe-Cache, in Shopware 5 aus), CVE-2026-62993 (SSRF über `{fetch}`), CVE-2026-62992, GHSA-966m-5mq6-5q48 (unkodierte Parameter in `{html_options}` u. a.), GHSA-x8f6-7h7x-vqq8 (`{html_image}`, nur mit freigegebenen Adressen); Abdeckung im Einzelnen siehe 2.1 | P1/P2 | Plugin + Core | abgeschwächt ab 0.1.2/0.1.3 (Template-Guard für alle bekannten Smarty-Quellen außer F16/F17, Scan bestehender Inhalte); vollständig behebbar nur im Kern |
| F4 | **Smarty `escape:'javascript'`-XSS** – [CVE-2023-28447](https://github.com/smarty-php/smarty/security/advisories/GHSA-7j98-h7fp-4vwj); `modifier.escape.php` enthält die Ersetzungen aus Smarty 3.1.48 nicht. | P2 | Plugin + Core | abgeschwächt ab 0.1.7 (Modul „JavaScript-Escaping“: vollständige Ersetzungsliste im kompilierten Code bei festem Escape-Typ), ab 0.3.6 auch bei variablem Escape-Typ (Laufzeitaufruf im kompilierten Code ersetzt); ab 0.3.7 auch die eigene `escape`-Funktion des Produktexports und Nocache-Abschnitte (nur mit Smarty-Ausgabe-Cache) |
| F5 | **Ausführbare Dateien und PHAR-Archive in Uploads.** Die Blacklists für Medien (`Controllers/Backend/MediaManager.php:49–64`) und ESD-Dateien (`Controllers/Backend/Article.php:161–175`) enthalten `phar`, `php7`, `php8`, `pht` u. a. nicht. Medien haben zusätzlich eine Whitelist (`Models/Media/Media.php:1236–1247`), lassen aber Mehrfachendungen wie `bild.phar.jpg` und getarnte PHAR-Inhalte zu. ESD-Dateien haben keine Whitelist. `media/` ist öffentlich, `media/.htaccess` deaktiviert keinen PHP-Handler. Ob eine Datei ausgeführt wird, hängt von der Webserver-Konfiguration ab. Seit PHP 8.0 werden PHAR-Metadaten beim Zugriff über `phar://` nicht mehr automatisch deserialisiert. | P1 | Plugin + Betrieb | abgeschwächt ab 0.1.4 (Modul „Upload-Härtung“) |
| F6 | **Backend-XSS in E-Mail-Vorlagen** (öffentlicher Hinweis ohne Details, eigenständig untersucht): Stored XSS in den Beispielvariablen der E-Mail-Vorlagen und in der Liste des E-Mail-Logs; beim Mailversand gespeicherte Daten wurden dort als HTML ausgegeben | P2 | Plugin | behoben im Plugin ab 0.3.3 (Ausgabe als Text) |
| F7 | Backend-Bibliotheken TinyMCE (3.5.8 in Shopware 5.6.10, 3.5.12 in 5.7.20) und Ext JS 4.1.1.1 mit formal zutreffenden XSS-Advisories | P2 | Core (langfristig), Plugin | geprüft 2026-11-26: [CVE-2018-8046](https://www.cve.org/CVERecord?id=CVE-2018-8046) (Ext JS, `getTip` der Action Column) nicht anwendbar, die Funktion fehlt in 4.1.1.1; die TinyMCE-Advisories betreffen 3.x formal. Bei der Prüfung selbst gefunden: Stored XSS im Editor des Backends; Skript in gespeicherten Editor-Inhalten wurde beim Öffnen im Editor mit den Rechten des Backend-Benutzers ausgeführt. Geschlossen ab 0.3.5 (Modul „Inhaltsprüfung“ lehnt Skript in Editor-Inhalten ab, Scan für den Bestand). Update von TinyMCE und Ext JS nur im Kern möglich |
| F8 | Composer-Abhängigkeiten mit Advisories, u. a. Guzzle 7.7.0, `guzzlehttp/psr7` 2.5.0, AWS SDK, FPDI, `jmespath.php`, Symfony 4.4/5.4 (Anhang A), dazu CVE-2026-108082 (ReDoS über den Header `Cache-Control`, ohne Anmeldung erreichbar, siehe 1.3) | P1–P3 je Paket | Core | offen; Symfony 4.4 erhält keine Fixes mehr |
| F9 | Keine Trusted-Hosts-Prüfung; `trustedproxies` standardmäßig leer – Härtung gegen Host-/Forwarded-Header-Manipulation (vgl. Symfony-Advisories zu generierten URLs und Fragmenten in Anhang A). Geprüft (5.6.10/5.7.20): Ohne eingetragene Proxys wertet Symfony `X-Forwarded-*` nicht aus, bei aktivem HTTP-Cache entfernt `SubRequestHandler` sie ganz; fremde Hosts leitet der Router im Frontend auf den Shop-Host um. Offen bleiben falsch konfigurierte `trustedproxies` und Hauptshops ohne Host (Shopware übernimmt dann den Host der Anfrage). | P3 | Plugin (Prüfung) + Betrieb | Prüfung ab 0.2.0 (Sicherheits-Check); ein eigenes Host-Modul entfällt, weil Shopware/Symfony die Header bereits filtern |
| F10 | **Fehlende Schutz-`.htaccess`** in internen Verzeichnissen (`vendor/`, `var/`, `files/` u. a.) nach Neuaufbau von `vendor/` oder nach Cache-/Ordnerbereinigung. Dadurch werden Bibliotheks- und Projektdateien von außen abrufbar (öffentlicher Hinweis ohne Details, eigenständig zu untersuchen). | P2 | Plugin + Betrieb | Prüfung ab 0.2.0 (Sicherheits-Check); Wiederherstellen ab 0.2.1 (Schaltfläche, Konsole, automatisch beim Leeren des HTTP-Caches); unter nginx Server-Konfiguration nötig |
| F11 | **Installer-Verzeichnis `recovery/install/` erreichbar**: [CVE-2022-48150](https://nvd.nist.gov/vuln/detail/CVE-2022-48150) (XSS); der Installer ist per `install.lock` gesperrt, das Verzeichnis bleibt aber erreichbar. | P3 | Plugin (Prüfung) + Betrieb | Prüfung ab 0.2.0 (Sicherheits-Check, inkl. Sperrdatei) |
| F12 | **PHP-Laufzeit**: Lücken vom Oktober und November 2026 (u. a. CVE-2026-109618, CVE-2026-109619, CVE-2026-115560) und Supportende von PHP 8.2 am 31.12.2026, siehe 1.4. | P1 | Betrieb + Kern; Plugin nur Warnung | Warnung zum PHP-Supportende ab 0.2.0, Prüfpunkt „PHP-Patchstand“ ab 0.4.4, Debian-/Ubuntu-Pakete über ihr Changelog ab 0.4.5 (Sicherheits-Check) |
| F13 | **Kunden-Sessions**: Parallele Logins mit demselben Kundenkonto beenden ältere Sessions nicht. `SameSite` des Session-Cookies ist nicht einstellbar; ein pauschales `Strict` kann Rücksprünge von Zahlungsanbietern brechen. | P3 | Plugin | ab 0.2.2 (Modul „Kunden-Sessions“, beide Optionen standardmäßig aus) |
| F14 | **Backend-Konten**: keine Zwei-Faktor-Anmeldung, keine konfigurierbaren Passwortregeln | P2 | Plugin | ab 0.3.0 (Zwei-Faktor-Anmeldung je Benutzer, Passwortregeln optional); REST-API ab 0.3.4 auf erlaubte Adressen beschränkbar, Warnung im Sicherheits-Check (eine zweite Faktor-Abfrage sieht das Protokoll der API nicht vor) |
| F15 | **Captcha bei „Passwort vergessen“**: nur optional über `passwordResetCaptcha`; ob er nach Updates aktiv ist, hängt von der Konfiguration ab | P3 | Betrieb; Plugin (Prüfung) | Prüfung ab 0.2.0 (Sicherheits-Check) |
| F16 | **Textbausteine (Snippets) werden als Smarty ausgeführt**: Shopware setzt Snippet-Werte vor dem Kompilieren in den Template-Quelltext ein (`engine/Library/Enlight/Components/Snippet/Resource.php:181–244`). Damit wird jeder im Backend bearbeitbare Textbaustein als Smarty ausgewertet, zusätzlich Fehlermeldungen per `fetch('string:…')` (`Frontend/Account.php:606/672`, `Frontend/Register.php:426`). | P1 | Plugin | abgeschwächt ab 0.1.4 (Template-Guard: Anlegen, Ändern, CSV-Import, Doctrine; Scan) |
| F17 | **Einkaufswelten-Element „HTML Code“**: Feld `smarty` wird im Theme per `{include file="string:{$Data.smarty}"}` ausgewertet (`widgets/emotion/components/component_html_code.tpl`) | P2 | Plugin | abgeschwächt ab 0.1.4 (Template-Guard: Speichern, Vorschau, Übersetzungen, Doctrine; Scan seit 0.1.3) |
| F18 | **Zugriff auf statische Klassen in Smarty-Vorlagen** – [CVE-2021-21408](https://github.com/advisories/GHSA-4h9c-v5vg-5m6m): Shopware lässt `static_classes` leer, Smarty 3.1.12 erlaubt damit jede statische Klasse. | P1 | Plugin + Core | abgeschwächt ab 0.1.4 (Template-Guard-Regel `static-class`, immer aktiv) |
| F19 | **Shop-Fehlermeldungen werden als Smarty kompiliert:** Registrierung und Kundenkonto kompilieren Fehlermeldungen der Formularprüfung mit `fetch('string:' . $error->getMessage())` (`Controllers/Frontend/Register.php:426`, `Account.php:606/672`). Kernformulare setzen den eingegebenen Wert nicht in die Meldung ein; ergänzt ein Plugin eine Prüfung, die den eingegebenen Wert in die Meldung setzt, würde Kundeneingabe als Smarty ausgewertet. Shopwares Eingabefilter lässt geschweifte Klammern durch. | P3 | Plugin + Core | abgesichert ab 0.3.9 (Modul „Request-Härtung“ entfernt Werte mit gefährlichen Smarty-Konstrukten aus Shop-Anfragen); vollständig behebbar nur im Kern |
| F20 | **Backend-XSS in weiteren Ansichten** (bei der Prüfung aller Backend-Ansichten selbst gefunden): Stored XSS in Ansichten, die gespeicherte Daten als HTML ausgaben, u. a. Kunden (Liste, Details, Adressen), Bestellungen (Liste, Details, Positionen), Bewertungen, Blog-Kommentare, Statistiken, Benachrichtigungen, Newsletter-Empfänger und Gutscheincodes | P2 | Plugin | behoben im Plugin ab 0.4.0 (Ausgabe als Text); ab 0.4.1 auch Textspalten aller Listen ohne eigene Darstellung (u. a. Protokoll, Benutzer- und Rollenverwaltung) und die Kundenstream-Liste; ab 0.4.8 Klassen-, Symbol- und Linkangaben in Bäumen |
| F21 | **Lücken in eigenen Schutzfunktionen** (bei einer vollständigen Prüfung des Plugins selbst gefunden): Zwei-Faktor-Anmeldung (Anmeldung ohne Code unter bestimmten Umständen, Sperre), Prüfregeln von Template-Guard, Inhaltsprüfung und Upload-Härtung (nicht erkannte Schreibweisen), nicht geprüfte Speicherwege (Textbaustein-Import, Übersetzungen über REST-API und Einkaufswelten-Import, Händler-Mail, Inhaltstypen, Freitextfelder, Mehrfachänderung); Request-Härtung (entfernte Werte über `$smarty.request` noch lesbar, Header und Adresse nicht geprüft) | P1 | Plugin | behoben ab 0.4.2; Request-Härtung ab 0.4.6/0.4.7 |

**Angriffsfläche Smarty (F1–F4, F6):** Shopware 5 wertet Inhalte aus der Datenbank als Smarty-Templates aus – E-Mail-Vorlagen (`Shopware\Components\StringCompiler`), Newsletter (`Controllers/Backend/Newsletter.php`), Beleg-Vorlagen (`Components/Document.php`), Produktexporte (`Core/sExport.php`) und SEO-URL-Vorlagen (`Core/sRewriteTable.php`). Wer diese im Backend bearbeiten darf, kann je nach Ausnutzbarkeit von F1–F3 bis zur Code-Ausführung auf dem Server eskalieren. Shopware aktiviert zwar eine Smarty-Sandbox (`Shopware\Components\Template\Security` mit Whitelist für PHP-Funktionen und -Modifier aus `engine/Shopware/Configs/smarty_functions.php`), lässt `disabled_tags` aber leer – `{extends}`, `{function}`, `{fetch}` sind erlaubt. Auch `static_classes` bleibt leer, was in Smarty 3.1.12 den Zugriff auf **alle** statischen Klassen erlaubt (`smarty_security.php:57–64`, `isTrustedStaticClass()`).

### 2.1 Abdeckung der Smarty-Advisories durch den Template-Guard

Abgeglichen am 2026-11-22 mit der [GitHub Advisory Database](https://github.com/advisories) (Paket `smarty/smarty`). Smarty 3.1.12 liegt bei allen gelisteten Advisories im betroffenen Versionsbereich. „Abgedeckt“ heißt: Der Template-Guard lehnt das auslösende Konstrukt beim Speichern ab, und der Scan meldet es in gespeicherten Inhalten. Die Lücke im Smarty-Code selbst bleibt bestehen und ist nur im Kern behebbar.

| CVE | Advisory | Betroffen laut Advisory | Konstrukt | Abdeckung durch den Template-Guard |
|---|---|---|---|---|
| CVE-2024-35226 | [GHSA-4rmg-292m-wg3w](https://github.com/advisories/GHSA-4rmg-292m-wg3w) | ≥ 3.0.0, < 4.5.3 | `{extends}`-Attribut | abgedeckt seit 0.1.2 (`{extends}` wird immer abgelehnt) |
| CVE-2021-26120 | [GHSA-3rpf-5rqv-689q](https://github.com/advisories/GHSA-3rpf-5rqv-689q) | < 3.1.39 | `{function}`-Name | abgedeckt seit 0.1.2 |
| CVE-2022-29221 | [GHSA-634x-pc3q-cf4c](https://github.com/advisories/GHSA-634x-pc3q-cf4c) | < 3.1.45 | `{block}`-Name, Dateiname | `{block}`-Namen seit 0.1.2; Dateinamen in `{include}` seit 0.1.3 (nur mit strenger Prüfung, Standard an) |
| CVE-2021-26119 | [GHSA-w5hr-jm4j-9jvq](https://github.com/advisories/GHSA-w5hr-jm4j-9jvq) | < 3.1.39 | `$smarty.template_object` | abgedeckt seit 0.1.2 |
| CVE-2021-29454 | [GHSA-29gp-2c3m-3j6m](https://github.com/advisories/GHSA-29gp-2c3m-3j6m) | < 3.1.42 | `{math}` | abgedeckt seit 0.1.3 (nur mit strenger Prüfung, Standard an) |
| CVE-2026-62993 | [GHSA-cq55-c7wv-pxmq](https://github.com/advisories/GHSA-cq55-c7wv-pxmq) | < 4.5.7 | `{fetch}` (SSRF) | abgedeckt seit 0.1.2 (`{fetch}` wird immer abgelehnt) |
| CVE-2021-21408 | [GHSA-4h9c-v5vg-5m6m](https://github.com/advisories/GHSA-4h9c-v5vg-5m6m) | < 3.1.43 | Zugriff auf statische Klassen | abgedeckt seit 0.1.4 (jedes `::` in einem Smarty-Tag wird abgelehnt) |
| CVE-2026-62992 | [GHSA-f6wf-28g6-769x](https://github.com/advisories/GHSA-f6wf-28g6-769x) | < 4.5.7 | Symlinks aus vertrauenswürdigen Verzeichnissen | **In Shopware 5 nicht betroffen** (eingeordnet am 2026-11-27). Laut Advisory entsteht die Lücke, weil die Prüfung den Pfad nur als Zeichenkette normalisiert und Symlinks dabei nicht auflöst. Smarty 3.1.12 in Shopware 5.6.10 und 5.7.20 prüft anders: `Smarty_Security::isTrustedResourceDir()` löst den Pfad mit PHPs `realpath()` auf, das Symlinks folgt (`engine/Library/Smarty/sysplugins/smarty_security.php:358`), und vergleicht das tatsächliche Ziel mit den vertrauenswürdigen Verzeichnissen. Alle Dateizugriffe aus Vorlagen laufen über diese Prüfung: `{include}` und die Vorlagen-Vererbung über die Datei-Ressource (`smarty_internal_resource_file.php:33`), `{fetch}` und `{html_image}` (`plugins/function.fetch.php:48`, `plugins/function.html_image.php:114`). Lokal mit harmlosen Markern nachgeprüft: Eine Datei im Template-Verzeichnis wird eingebunden, ein Symlink dort auf eine Datei außerhalb wird abgelehnt. Kein Handlungsbedarf im Plugin; `{fetch}` lehnt der Template-Guard ohnehin ab. |
| CVE-2023-28447 | [GHSA-7j98-h7fp-4vwj](https://github.com/advisories/GHSA-7j98-h7fp-4vwj) | < 3.1.48 | `escape:'javascript'` | nicht durch den Template-Guard, sondern seit 0.1.7 durch das Modul „JavaScript-Escaping“ (F4); seit 0.3.6–0.3.8 auch bei variablem Escape-Typ, in Nocache-Abschnitten und für registrierte `escape`-Funktionen (Produktexport, Plugins) |
| [CVE-2026-82531](https://www.cve.org/CVERecord?id=CVE-2026-82531) | [GHSA-3w63-v7pm-cq9x](https://github.com/smarty-php/smarty/security/advisories/GHSA-3w63-v7pm-cq9x) | < 4.5.8, 5.x < 5.8.5 | Template-Vererbung (`extends:`) bei eingeschaltetem Smarty-Ausgabe-Cache: Zugewiesene Daten mit gefälschter Nocache-Markierung gelangen als PHP-Code in die Cache-Datei | **In Shopware 5 nicht erreichbar, solange der Smarty-Ausgabe-Cache aus ist.** Smarty 3.1.12 schreibt Cache-Dateien nur bei eingeschaltetem `caching` (`engine/Library/Smarty/sysplugins/smarty_internal_templatebase.php:225`); Standard ist `false` (`Smarty.class.php:282`), und der Kern schaltet ihn nirgends ein (geprüft in 5.7.20, 2026-11-26). Ein Risiko besteht nur, wenn ein Theme oder Drittanbieter-Plugin den Cache über `setCaching()` einschaltet; der Sicherheits-Check warnt ab 0.3.6 in diesem Fall und nennt die Plugins. Smarty 3.1.12 enthält den Fehler nicht: Der `nocache_hash` wird beim Laden der kompilierten Vorlage aus deren Eigenschaften wiederhergestellt (`smarty_internal_template.php:431`) und ist beim Schreiben des Caches nie leer (geprüft 2026-11-27 durch Nachstellen des Ablaufs mit harmlosem Marker, für `extends:`, `{extends}` und `{include}` mit Nocache-Abschnitt; Gegenprobe mit künstlich leerem Hash schlägt an). Auch die beiden Fehler aus [GHSA-p927-2m49-9p39](https://github.com/smarty-php/smarty/security/advisories/GHSA-p927-2m49-9p39) (ebenfalls nur mit Ausgabe-Cache) fehlen in 3.1.12: Das Muster für PHP-Tags enthält dort keinen Script-Tag mit Anführungszeichen und kein aufwendiges Zurückverfolgen (`smarty_internal_templatebase.php:238`). |
| – | [GHSA-966m-5mq6-5q48](https://github.com/smarty-php/smarty/security/advisories/GHSA-966m-5mq6-5q48) | < 4.6.1, 5.x < 5.9.1 | `{html_options}` (`name`, `id`, `class`), `{html_select_date}`/`{html_select_time}` (`prefix`, `field_array`, `*_empty`) | **Smarty 3.1.12 ist betroffen** (Shopware 5.6.10 und 5.7.20, Dateien identisch; geprüft 2026-11-28 mit harmlosen Markern, alle acht Parameter, Gegenprobe über das kodierte Zusatzattribut unauffällig). Kein Konstrukt für den Template-Guard: Ausnutzbar ist es nur, wenn eine Vorlage fremde Daten an diese Parameter übergibt. Die Shopware-5.7.20-Vorlagen nutzen die Funktionen nicht; Themes und Plugins von Drittanbietern können es. Härtung im Plugin offen. |
| – | [GHSA-x8f6-7h7x-vqq8](https://github.com/smarty-php/smarty/security/advisories/GHSA-x8f6-7h7x-vqq8) | < 4.6.1, 5.x < 5.9.1 | `{html_image}` mit entfernter Adresse (SSRF über Weiterleitung) | **In Shopware 5 bei Standardeinstellung nicht erreichbar.** Shopware schaltet die Smarty-Sicherheitsrichtlinie für alle Vorlagen ein (`engine/Shopware/Components/DependencyInjection/Bridge/Template.php:51`), `trusted_uri` ist dort leer; Smarty 3.1.12 lehnt damit jede entfernte Adresse vor dem Abruf ab (`smarty_security.php:393`, geprüft 2026-11-28). Betroffen nur, wenn `template_security.trusted_uri` in der `config.php` Adressen freigibt; der Sicherheits-Check warnt ab 0.4.9 in diesem Fall. |

## 3. Versionsplan Plugin

Jede Version durchläuft die CI inklusive Integrationstest gegen echtes Shopware 5.7.20; jeder Fix bekommt dort einen Regressionstest. Die Tests arbeiten mit harmlosen Eingaben, die nur prüfen, ob eine Schutzmaßnahme greift – es wird kein Code geschrieben, der Lücken tatsächlich ausnutzt.

| Version | Inhalt | Befunde |
|---|---|---|
| **0.1.2** ✅ | **Modul „Template-Guard“:** lehnt beim Speichern im Backend E-Mail-Vorlagen (inkl. Übersetzungen) und Produktexporte mit `{extends}`, `{fetch}`, `{php}`, ungültigen `{function}`-/`{block}`-Namen oder `$smarty.template_object` ab, ebenso die Funktion „Smarty prüfen“; zusätzlich Absicherung über Doctrine-Events (REST-API, andere Plugins). | F1–F3 |
| **0.1.3** ✅ | **Template-Guard erweitern:** Newsletter, Beleg-Bausteine, SEO-Vorlagen, E-Mail-Header/-Footer, wesentliche Merkmale, Zahlungsarten- und Länderhinweise (jeweils inkl. Übersetzungen); strenge Prüfung von `{include}` und `{math}` (abschaltbar); Konsolenbefehl `sw5cs:template-guard:scan` für gespeicherte Inhalte. | F1–F3, F17 (Scan) |
| **0.1.4** ✅ | **Template-Guard abschließen:** Textbausteine einschließlich CSV-Import (F16), Einkaufswelten-Element „HTML Code“ (F17), Regel gegen Zugriff auf statische Klassen (F18). **Modul „Upload-Härtung“:** sperrt ausführbare Endungen (auch Mehrfachendungen) und PHAR-Inhalte bei Medien-, ESD- und Import-Uploads sowie allen übrigen Uploads; Doctrine-Absicherung für Medien. | F5, F16–F18 |
| **0.1.5** ✅ | **Shopware-Vorgaben übererfüllen:** Code-Style nach den php-cs-fixer-Regeln des Shopware-Kerns, statische Analyse mit PHPStan auf höchster Stufe inkl. strenger Regeln (der Kern nutzt Stufe 8), Meldungen als Textbausteine (DE/EN), Protokollierung über den plugin-eigenen Logger, Cache-Leerung beim Speichern der Konfiguration und beim Deinstallieren nach den Shopware-5-Plugin-Guidelines; alle Prüfungen fest in der CI. | – |
| **0.1.6** ✅ | **Shopware 5.6:** alle Module zusätzlich für Shopware 5.6.10 mit PHP 7.4; Plugin-Code PHP-7.4-kompatibel, Adapter für Konfigurations-Reader und Doctrine DBAL, Integrationstest und statische Analyse gegen 5.6.10. Siehe 3.1. | – |
| **0.1.7** ✅ | **Modul „JavaScript-Escaping“:** Smarty-Postfilter ersetzt im kompilierten Code die Ersetzungsliste von `escape:'javascript'` durch die aus Smarty 3.1.48; ohne Core-Änderung, ohne Verweis auf das Plugin im kompilierten Code. Nicht abgedeckt: variabler Escape-Typ und die eigene `escape`-Funktion des Produktexports. Siehe docs/ARCHITECTURE.md, Abschnitt 3.4. | F4 |
| **0.1.8** ✅ | **Header-Modul erweitern:** `Content-Security-Policy` (aus, „Nur melden“ oder „Durchsetzen“) mit Ausgangs-Richtlinien für Frontend und Backend und optionaler `report-uri`; `Permissions-Policy` (standardmäßig Kamera, Mikrofon, USB gesperrt); Prüfung aller frei eingebbaren Header-Werte. Wegen der Inline-Skripte des Themes bleibt `'unsafe-inline'` erlaubt; die CSP schützt daher vor allem vor fremden Skriptquellen, nicht vor eingeschleusten Inline-Skripten. | F6, F7 (Abschwächung) |
| **0.2.0** ✅ | **Modul „Sicherheits-Check“:** Backend-Seite (Einstellungen > Sicherheits-Check, nur für Administratoren bzw. freigegebene Rollen) und Konsolenbefehl `sw5cs:security-check` mit Handlungsempfehlungen. Geprüft werden:<ul><li>Shopware-Version und PHP-Supportende (Daten von php.net)</li><li>`session.upload_progress.enabled`</li><li>`trustedproxies` (zu weit gefasste Einträge, Weiterleitungs-Header von nicht eingetragenen Adressen) und Hauptshops ohne Host (F9)</li><li>Template-Sandbox</li><li>Schutz-`.htaccess` in internen Verzeichnissen</li><li>vorhandenes `recovery/install/` und dessen Sperrdatei</li><li>Captcha bei „Passwort vergessen“</li><li>aktive Plugin-Module</li></ul>Geprüft wird nur der eigene Shop, und es wird nichts geändert. Die Prüfung auf bekannt verwundbare PHP-Patchstände kam nach Prüfung der PHP-Advisories mit 0.4.4 hinzu (1.4). | F9–F12, F15 |
| **0.2.1** ✅ | **Schutzdateien wiederherstellen:** Fehlende Schutz-`.htaccess` in den acht Verzeichnissen, in denen Shopware 5.6.10/5.7.20 sie mitliefert, per Schaltfläche im Sicherheits-Check (ACL-Recht „restore“), per Konsolenbefehl `sw5cs:protection-files:restore` und automatisch beim Leeren des HTTP-Caches anlegen. Regeln wie im Original (in `engine/Library` nur PHP-Dateien gesperrt), vorhandene Dateien bleiben unverändert. Shopware selbst löscht die Dateien nicht (die Cache-Leerung entfernt nur Umgebungsordner unterhalb von `var/cache`); sie gehen bei Deployments oder Uploads ohne Punktdateien und beim Neuaufbau von `vendor/` verloren. nginx liest keine `.htaccess`; dort bleibt die Server-Konfiguration zuständig, der Sicherheits-Check weist darauf hin. | F10 |
| **0.2.2** ✅ | **Kunden-Session-Härtung:** Optional „nur eine Anmeldung je Kundenkonto“: Jede Anmeldung erzeugt ein Zufallstoken in der Sitzung, gespeichert wird nur dessen Hash; ältere Sitzungen werden über `Shopware_Modules_Admin_CheckUser_FilterGetUser` beim nächsten Aufruf abgemeldet. Optionales `SameSite` („Lax“/„Strict“, je Shop) für das Session-Cookie über `session.cookie_samesite`, mit Warnhinweis zu Zahlungsanbietern. | F13 |
| **0.3.0** ✅ | **Backend-Konto-Härtung:** Zwei-Faktor-Anmeldung (TOTP nach RFC 6238, eigene Implementierung ohne Fremdbibliothek) je Backend-Benutzer mit Einrichtungsseite, einmaligen Codes, eigener Fehlversuch-Sperre (Shopware setzt seinen Zähler bei richtigem Passwort zurück), Entfernen durch Administratoren und per Konsole. Optionale Passwortregeln in der Benutzerverwaltung (Mindestlänge, drei von vier Zeichenarten, kein Benutzername). Die Einrichtung erfolgt per Schlüssel bzw. `otpauth://`-Adresse, ab 0.3.1 auch per QR-Code. | F14 |
| **0.3.3** ✅ | **Mail-Daten im Backend als Text:** Beispielvariablen der E-Mail-Vorlagen sowie Betreff, Absender und Empfänger im E-Mail-Log werden kodiert ausgegeben (Ext-JS-Erweiterung, immer aktiv). | F6 |
| **0.3.4** ✅ | **REST-API beschränken:** optionale Liste erlaubter Adressen und Bereiche (IPv4/IPv6, CIDR), Status 403 für andere Adressen, ungültige Einträge erlauben nichts; Prüfpunkt „Zugang zur REST-API“ im Sicherheits-Check. | F14 |
| **0.3.5** ✅ | **Modul „Inhaltsprüfung“:** Editor-Inhalte mit Skript (`<script>`, Event-Attribute, `javascript:`-Adressen, `srcdoc`) werden beim Speichern abgelehnt (Doctrine für Backend, REST-API und Plugins, dazu Backend-Übersetzungen); Konsolenbefehl `sw5cs:content-guard:scan` für den Bestand. | F7 |
| **0.3.6** ✅ | **JavaScript-Escaping zur Laufzeit:** `escape` mit variablem Escape-Typ nutzt für `javascript` die vollständige Ersetzungsliste. **Sicherheits-Check:** Warnung bei eingeschaltetem Smarty-Ausgabe-Cache bzw. Plugins, die ihn einschalten (CVE-2026-82531). | F4, F3 |
| **0.3.7** ✅ | **Restgrenzen geschlossen:** Inhaltsprüfung auch für Übersetzungen über die REST-API (`/api/translations`, Artikel, Varianten, Kategorien); JavaScript-Escaping auch in Nocache-Abschnitten und für die `escape`-Funktion des Produktexports. | F4, F7 |
| **0.3.8** ✅ | **Sonderfälle geschlossen:** Editor im Backend entfernt Skript beim Laden (auch für Inhalte aus Plugins mit eigenem SQL und Altbestand); eigene `escape`-Funktionen anderer Plugins werden aufgerufen und für `javascript` nur ergänzt. | F4, F7 |
| **0.3.9** ✅ | **Modul „Request-Härtung“:** Werte mit Smarty-Konstrukten bekannter Lücken werden aus Shop-Anfragen (Query, Post, Cookies, Routenparameter) entfernt, wie Shopwares Eingabefilter ohne Abbruch der Anfrage. **Sicherheits-Check:** Shopwares Eingabefilter aktiv und vollständig eingeschaltet. | F19, F1–F3 |
| **0.4.0** ✅ | **Gespeicherte Daten im Backend als Text:** Kunden, Bestellungen, Bewertungen, Blog-Kommentare, Statistiken, Benachrichtigungen, Newsletter-Empfänger, Gutscheincodes sowie Anzeigefelder und Listen des Backend-Frameworks (Skript auf der Startseite des Backends, umhüllt nur vorhandene Funktionen, immer aktiv). | F20 |
| **0.4.1** ✅ | **Listen im Backend als Text:** Textspalten ohne eigene Darstellung in allen Listen und Bäumen (auch anderer Plugins), dazu Kundenstream-Liste und Benutzerliste. | F20 |
| **0.4.2** ✅ | **Eigene Schutzfunktionen nachgeschärft:** Zwei-Faktor-Anmeldung als Sitzungszustand mit eigener Sperre, Prüfregeln und weitere Speicherwege von Template-Guard und Inhaltsprüfung, Upload-Namensprüfung, Bäume und Kundenstream-Vorschau im Backend, Prüfung nur geänderter Felder. | F21, F20, F4 |
| **0.4.7** ✅ | **Request-Härtung:** auch Anfrage-Header und Adresse (`$_SERVER`, `$smarty.server`) prüfen. | F21 |
| **0.4.8** ✅ | **Bäume im Backend:** Klassen-, Symbol- und Linkangaben der Knoten (z. B. Album-Symbol im Medienmanager) als Text in Attributen. | F20 |
| **0.4.9** ✅ | **Sicherheits-Check:** Warnung, wenn `template_security.trusted_uri` Adressen für Vorlagen freigibt (GHSA-x8f6-7h7x-vqq8). | F3 |
| **0.4.6** ✅ | **Request-Härtung:** entfernte Werte auch aus `$_REQUEST` (`$smarty.request`) entfernen. | F21 |
| **0.4.5** ✅ | **PHP-Patchstand bei Distributionspaketen:** Debian- und Ubuntu-Pakete behalten die Versionsnummer und liefern Korrekturen nach; bewertet wird dort das Paket-Changelog statt der Versionsnummer. | F12 |
| **0.4.4** ✅ | **Sicherheits-Check: PHP-Patchstand.** Meldet PHP-Versionen ohne die bis 19.11.2026 veröffentlichten Sicherheitskorrekturen und nennt die Mindestversion je Zweig; bei Paketen von Distributionen nur Warnung. | F12 |
| **0.4.3** ✅ | **Update über den Plugin-Manager:** Zwei-Faktor-Tabelle beim ersten Zugriff anlegen bzw. ergänzen, damit ein Update über das Backend nicht alle Benutzer abmeldet. | F21 |

Reihenfolge und Umfang können sich ändern; das [CHANGELOG](./CHANGELOG.md) dokumentiert, was tatsächlich umgesetzt wurde.

### 3.1 Shopware 5.6 (seit 0.1.6)

Seit 0.1.6 laufen alle Module auch unter Shopware 5.6.10 mit PHP 7.4. Ältere 5.6-Versionen (5.6.0–5.6.9) sowie Shopware 5.5 und älter werden nicht unterstützt.

| Shopware | PHP laut `composer.json` des Tags | Smarty | Plugin |
|---|---|---|---|
| 5.4.6 / 5.5.10 | `^5.6.4 \|\| ^7.0` | 3.1.12 | nicht unterstützt |
| 5.6.10 (Mai 2021) | `^7.2` | 3.1.12 | ab 0.1.6, mit PHP 7.4 |
| 5.7.20 | `~7.4.0 \|\| ~8.0.0 \|\| ~8.1.0 \|\| ~8.2.0` | 3.1.12 | ja, mit PHP 8.1/8.2 |

Quelle: `composer.json` und `engine/Library/Smarty/Smarty.class.php` in [shopware5/shopware](https://github.com/shopware5/shopware) zum jeweiligen Tag.

- **Gleich in 5.6 und 5.7:** alle abgesicherten Backend-Aktionen (Mail, ProductFeed, Translation, NewsletterManager, Payment, Config, Snippet, Emotion, MediaManager, Article/ESD), das ESD-Filter-Event, die Doctrine-Entities, der plugin-eigene Logger, Textbausteine aus `Resources/snippets` und der CacheManager.
- **Unterschiedlich, über Adapter gelöst** (`Components/Compat/Sw56`, `Components/Compat/Sw57`):
  - Konfiguration: 5.6 `Shopware\Components\Plugin\CachedConfigReader` (Shop-Objekt), 5.7 `Shopware\Components\Plugin\Configuration\ReaderInterface` (Shop-ID)
  - Datenbank: 5.6 Doctrine DBAL 2.6 (`fetchAll`), 5.7 DBAL 2.13 (`fetchAllAssociative`)
  - `Enlight_Controller_Action::View()` ist in 5.6 als nullable deklariert
- **PHP:** Der Plugin-Code ist PHP-7.4-kompatibel. PHP 7.2 und 7.3 (von Shopware 5.6 ebenfalls zugelassen) werden nicht unterstützt.
- **Grenzen:** Shopware 5.6 erhält keine Updates mehr; Sicherheitskorrekturen späterer Shopware-Versionen fehlen im 5.6-Kern, und PHP 7.4 erhält seit November 2022 keine Sicherheitsupdates. Empfohlen bleibt das Update auf Shopware 5.7.20.

## 4. Kern (separates Projekt)

Nicht im Plugin lösbar, geplant im Projekt zur PHP-8.3/8.4-Kompatibilität des Shopware-5.7-Kerns:

1. Abhängigkeiten aktualisieren, wo es ohne Bruch möglich ist (Guzzle ≥ 7.15.2, `guzzlehttp/psr7` ≥ 2.12.3, AWS SDK, FPDI ≥ 2.6.7, `jmespath.php` ≥ 2.9.1, `symfony/polyfill-intl-idn` ≥ 1.38.1, Symfony-5.4-Komponenten ≥ 5.4.56).
2. Symfony-4.4-Komponenten (`http-foundation`, `http-kernel`, `config`, `validator`, `process`, `expression-language`): keine Upstream-Fixes mehr – Backport der Fixes oder Upgrade bewerten.
3. Smarty 3.1.12: Fixes zurückportieren oder Upgrade bewerten.
4. `firebase/php-jwt` 6.x → 7 (Major-Update) und `league/flysystem` 1.x bewerten.
5. Langfristig: TinyMCE 3.5.x und Ext JS 4.1.1 im Backend.

## 5. Betrieb (Empfehlungen an Shop-Betreiber)

- Backend-Zugänge mit Rechten auf E-Mail-, Newsletter-, Beleg-, Export- und SEO-Vorlagen nur vertrauenswürdigen Personen geben (Angriffsfläche F1–F4).
- PHP-Ausführung in Upload-Verzeichnissen abschalten, besonders in `media/`. Unter Apache etwa `RemoveHandler`/`php_admin_flag engine off` bzw. einen passenden `FilesMatch` in der vHost-Konfiguration setzen, unter nginx keine `location` mit PHP-Weitergabe für `media/` und `files/`. Außerdem prüfen, ob der Webserver `.phar`, `.pht` oder `.php7` als PHP ausführt (F5).
- `trustedproxies` in der `config.php` korrekt setzen, wenn der Shop hinter einem Proxy oder Load-Balancer läuft.
- PHP aktuell halten und den Patchstand beim Hoster schriftlich bestätigen lassen, insbesondere wegen der Lücken vom Oktober und November 2026 (F12); der Sicherheits-Check nennt ab 0.4.4 die Mindestversion. Bis zum Update `session.upload_progress.enabled = 0` setzen; das ist eine Abschwächung, kein Ersatz für das Update.
- Den Betrieb nach dem 31.12.2026 planen: Extended Support für PHP 8.2 beim Hoster, Umstieg auf einen Kern mit PHP 8.3/8.4 (separates Projekt) oder Migration.
- `recovery/install/` auf dem Produktivsystem entfernen oder per Webserver sperren (F11).
- Bis zu einer Korrektur im Kern (CVE-2026-108082, siehe 1.3) Anfragen pro Adresse am Webserver oder Proxy begrenzen. Das ist unsere Empfehlung zur Begrenzung der Last, keine Abhilfe aus dem Advisory; eine bloße Längenbegrenzung des Headers reicht nach dessen Beschreibung nicht, weil die Kosten an der Zahl der Leerzeichen hängen.
- Nach Deployments, `composer install` oder Ordnerbereinigungen prüfen, ob die Schutz-`.htaccess` in internen Verzeichnissen noch vorhanden sind (F10). Unter nginx entsprechende `deny`-Regeln setzen.
- Captcha für „Passwort vergessen“ aktivieren (`passwordResetCaptcha`, F15).
- Für alle installierten Drittanbieter-Plugins prüfen, ob sie noch gepflegt werden. Nicht benötigte Plugins deinstallieren.
- Webserver, Datenbank, Elasticsearch und Betriebssystem auf dem aktuellen Patchstand halten.
- Vor jedem Plugin-Update in einer Staging-Umgebung testen.

## Anhang A: `composer audit` Shopware 5.7.20

Ermittelt am 2026-11-19 mit `composer audit --locked` auf das `composer.lock` von Shopware 5.7.20 (nur Laufzeit-Abhängigkeiten). Schweregrade wie von der Advisory-Quelle angegeben („–“ = nicht angegeben). Ein Eintrag bedeutet nicht automatisch, dass die Lücke in Shopware 5 ausnutzbar ist.

| Paket (Version in 5.7.20) | ID | Schweregrad | Beschreibung |
|---|---|---|---|
| `aws/aws-sdk-php` 3.271.2 | PKSA-4t1p-xpk2-nsss | high | AWS SDK for PHP has CloudFront Policy Document Injection via Special Characters |
| `aws/aws-sdk-php` 3.271.2 | CVE-2025-14761 | medium | Key Commitment Issues in S3 Encryption Clients |
| `aws/aws-sdk-php` 3.271.2 | CVE-2023-51651 | medium | Potential URI resolution path traversal in the AWS SDK for PHP |
| `firebase/php-jwt` v6.5.0 | CVE-2025-45769 | low | php-jwt contains weak encryption |
| `guzzlehttp/guzzle` 7.7.0 | CVE-2026-69246 | high | Guzzle: Noncanonical host can bypass host-based checks |
| `guzzlehttp/guzzle` 7.7.0 | CVE-2026-69245 | medium | Guzzle: Noncanonical cookie domain keeps subdomain scope |
| `guzzlehttp/guzzle` 7.7.0 | CVE-2026-67354 | medium | Guzzle: URI fragments disclosed in redirect Referer headers |
| `guzzlehttp/guzzle` 7.7.0 | CVE-2026-67355 | medium | Guzzle: Host-only cookie scope is not preserved |
| `guzzlehttp/guzzle` 7.7.0 | CVE-2026-67353 | medium | Guzzle: Unbounded response cookies risk denial of service |
| `guzzlehttp/guzzle` 7.7.0 | CVE-2026-59883 | medium | Guzzle: Cookie Disclosure and Injection via IP-Address Domains |
| `guzzlehttp/guzzle` 7.7.0 | CVE-2026-67339 | medium | Guzzle: Proxy-Authorization headers can be sent to origin servers |
| `guzzlehttp/guzzle` 7.7.0 | CVE-2026-55767 | medium | Dot-only cookie domains match all hosts |
| `guzzlehttp/guzzle` 7.7.0 | CVE-2026-55568 | medium | Silent HTTPS proxy downgrade to cleartext |
| `guzzlehttp/psr7` 2.5.0 | CVE-2026-59882 | medium | guzzlehttp/psr7: Host Confusion via Weak URI Host Validation |
| `guzzlehttp/psr7` 2.5.0 | CVE-2026-55766 | medium | CRLF injection in HTTP start-line serialization |
| `guzzlehttp/psr7` 2.5.0 | CVE-2026-49214 | medium | CRLF injection via URI host component |
| `guzzlehttp/psr7` 2.5.0 | CVE-2026-48998 | medium | Host confusion via authority reinterpretation |
| `league/flysystem` 1.1.10 | CVE-2026-102601 | low | Flysystem: WhitespacePathNormalizer's control-character (CorruptedPathDetected) check is bypassed by malformed UTF-8 in the path, affecting every adapter |
| `mtdowling/jmespath.php` 2.6.1 | CVE-2026-54133 | critical | CompilerRuntime code injection via unescaped function names |
| `setasign/fpdi` v2.3.7 | CVE-2026-45802 | medium | FPDI: Memory Exhaustion and Endless Loop in FPDI leads to Denial of Service |
| `setasign/fpdi` v2.3.7 | CVE-2025-54869 | medium | FPDI allows Memory Exhaustion (OOM) in PDF Parser which leads to Denial of Service |
| `symfony/cache` v5.4.23 | CVE-2026-45073 | medium | SQL Injection in PdoAdapter::doClear() via Unsanitized $prefix |
| `symfony/config` v4.4.44 | CVE-2026-123824 | – | XXE (Local File Read) in Config XmlUtils::parse() via validateOnParse = true |
| `symfony/expression-language` v4.4.47 | CVE-2026-109449 | – | Unbounded Nesting in the Expression Parser Lets Untrusted Expressions Crash the Process |
| `symfony/http-foundation` v4.4.49 | CVE-2026-118514 | – | HEAD Requests Bypass the methods Option of security.access_control Rules and Firewalls: Incomplete Fix for CVE-2026-45075 |
| `symfony/http-foundation` v4.4.49 | PKSA-b2gg-vk1g-6d5q | – | Generated URLs Can Point to a Host Chosen by the Client |
| `symfony/http-foundation` v4.4.49 | CVE-2025-64500 | high | Incorrect parsing of PATH_INFO can lead to limited authorization bypass |
| `symfony/http-foundation` v4.4.49 | CVE-2024-50345 | low | Open redirect via browser-sanitized URLs |
| `symfony/http-kernel` v4.4.50 | CVE-2026-123339 | – | Inline Fragments Let Untrusted Forwarded Headers Set the Host, Scheme and Port of the URLs Generated for the Main Request |
| `symfony/mime` v5.4.23 | CVE-2026-45070 | medium | Email Header Injection via Non-Token Characters in Mime Parameter Names |
| `symfony/mime` v5.4.23 | CVE-2026-45067 | high | Email Header / SMTP Command Injection via CRLF in Symfony\Component\Mime\Address |
| `symfony/polyfill-intl-idn` v1.27.0 | CVE-2026-46644 | low | symfony/polyfill-intl-idn accepts xn-- labels whose Punycode payload decodes to ASCII-only: insecure equivalence |
| `symfony/process` v4.4.44 | CVE-2026-24739 | medium | Symfony's incorrect argument escaping under MSYS2/Git Bash can lead to destructive file operations on Windows |
| `symfony/process` v4.4.44 | CVE-2024-51736 | high | Command execution hijack on Windows with Process class |
| `symfony/serializer` v5.4.23 | CVE-2026-121623 | – | DataUriNormalizer Opens Data URIs With a Non-Lowercase Scheme as Local Files, Letting a Client Read Any Server File |
| `symfony/serializer` v5.4.23 | CVE-2026-118515 | – | The Serializer Builds UploadedFile Objects From Request Data, Letting a Client Point an Upload at Any Server File |
| `symfony/validator` v4.4.48 | CVE-2026-123125 | – | Ip Constraint public/private Modes Accept IPv4-Mapped and IPv4-Compatible IPv6 Addresses: SSRF Filter Bypass |
| `symfony/validator` v4.4.48 | CVE-2024-50343 | low | Incorrect response from Validator when input ends with a newline |

