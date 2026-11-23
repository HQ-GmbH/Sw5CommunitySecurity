# Roadmap

Stand: **2026-11-23** · Basis: Shopware **5.7.20** (letzte offizielle Shopware-5-Version) · Plugin-Version: **0.1.5**

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
| TinyMCE (Backend-Editor) | 3.5.8 | liegt formal in den Bereichen zahlreicher XSS-Advisories ([Übersicht](https://github.com/advisories?query=tinymce)); ob die jeweiligen Fehler im 3.x-Code existieren, ist ungeprüft |
| Ext JS (Backend) | 4.1 | [CVE-2018-8046](https://nvd.nist.gov/vuln/detail/CVE-2018-8046) (XSS in `getTip()` von Action Columns), Ausnutzbarkeit im SW5-Backend ungeprüft |
| jQuery (Responsive-Theme) | 3.6.0 | keine offene Advisory bekannt |
| mpdf | v8.1.6 | keine offene Advisory bekannt |
| Zend Framework 1 (Teilbaum) | 1.12.3 (mit Shopware-Backports) | bekannte Fixes zurückportiert (z. B. `Zend_Db_Select`, CRLF-Filter in `Zend_Mail`); Rest nicht im Detail abgeglichen |

### 1.3 Composer-Abhängigkeiten
`composer audit` auf das `composer.lock` von 5.7.20 meldet **38 Advisories in Laufzeit-Abhängigkeiten** (dazu 7 in reinen Entwicklungs-Abhängigkeiten). Vollständige Liste in [Anhang A](#anhang-a-composer-audit-shopware-5720). Nicht jede Lücke ist in Shopware 5 erreichbar – z. B. nutzt der SW5-Code `Symfony\Component\Mime` und den `DataUriNormalizer` gar nicht, `jmespath.php` wird nur indirekt über das AWS-SDK (S3-Medienadapter) geladen.

Nicht in Anhang A und daher nicht belegt: Eine Recherche nennt **CVE-2026-108082** (ReDoS in `HeaderUtils::split()` von `symfony/http-foundation`). Die Advisory-Datenbank, die `composer audit` am 2026-11-20 nutzte, enthielt dazu keinen Eintrag. Der Punkt wird aufgenommen, sobald eine Primärquelle (Symfony-Advisory oder GHSA) vorliegt.

[CVE-2022-24894](https://nvd.nist.gov/vuln/detail/CVE-2022-24894) (`symfony/http-kernel` < 4.4.50) betrifft das `composer.lock` von 5.7.20 nicht, dort ist v4.4.50 eingetragen. Bei eigenen Lockfiles mit älterem Stand ist die Lücke offen.

### 1.4 PHP-Laufzeit
- Shopware 5.7.19/5.7.20 unterstützt laut `composer.json` PHP bis 8.2. Der Sicherheitssupport für PHP 8.2 endet am **31.12.2026** ([php.net](https://www.php.net/supported-versions.php)); 7.4, 8.0 und 8.1 sind bereits ohne Support.
- Für die PHP-Lücken vom Oktober 2026 (**CVE-2026-109618**, **CVE-2026-109619**, beide unauthentifizierte Code-Ausführung, sowie **CVE-2026-115560** rund um `session.upload_progress`) nennt eine Recherche PHP 8.2.35 als erste behobene 8.2-Version. Die Angaben sind hier noch nicht gegen das PHP-Advisory geprüft.
- Diese Lücken schließt kein Plugin, sondern nur ein PHP-Update des Betreibers bzw. Hosters. Das Plugin kann aber warnen (F12).

## 2. Befunde und Zuordnung

| # | Befund | Prio | Ort | Status |
|---|---|---|---|---|
| F1 | **Smarty `{extends}`-Code-Injection** – [CVE-2024-35226](https://github.com/smarty-php/smarty/security/advisories/GHSA-4rmg-292m-wg3w). `smarty_internal_compile_extends.php:76` führt `eval('$include_file = ' . $_attr['file'] . ';')` aus. | P1 | Plugin + Core | abgeschwächt ab 0.1.2/0.1.3 (Template-Guard für alle bekannten Smarty-Quellen außer F16/F17, Scan bestehender Inhalte); vollständig behebbar nur im Kern |
| F2 | **Smarty `{function name=…}`-Code-Injection** – [CVE-2021-26120](https://github.com/smarty-php/smarty/security/advisories/GHSA-3rpf-5rqv-689q). Der Name wird ungeprüft in PHP-Code eingesetzt (`smarty_internal_compile_function.php`). | P1 | Plugin + Core | abgeschwächt ab 0.1.2/0.1.3 (Template-Guard für alle bekannten Smarty-Quellen außer F16/F17, Scan bestehender Inhalte); vollständig behebbar nur im Kern |
| F3 | Weitere Smarty-Lücken mit formal betroffenem Versionsbereich: [CVE-2022-29221](https://github.com/smarty-php/smarty/security/advisories/GHSA-634x-pc3q-cf4c), [CVE-2021-26119](https://github.com/smarty-php/smarty/security/advisories/GHSA-w5hr-jm4j-9jvq), [CVE-2021-29454](https://github.com/smarty-php/smarty/security/advisories/GHSA-29gp-2c3m-3j6m), [CVE-2021-21408](https://github.com/smarty-php/smarty/security/advisories/GHSA-4h9c-v5vg-5m6m), CVE-2026-82531 (zu verifizieren), CVE-2026-62993 (SSRF über `{fetch}`), CVE-2026-62992; Abdeckung im Einzelnen siehe 2.1 | P1/P2 | Plugin + Core | abgeschwächt ab 0.1.2/0.1.3 (Template-Guard für alle bekannten Smarty-Quellen außer F16/F17, Scan bestehender Inhalte); vollständig behebbar nur im Kern |
| F4 | **Smarty `escape:'javascript'`-XSS** – [CVE-2023-28447](https://github.com/smarty-php/smarty/security/advisories/GHSA-7j98-h7fp-4vwj); `modifier.escape.php` enthält die Ersetzungen aus Smarty 3.1.48 nicht. | P2 | Plugin + Core | offen |
| F5 | **Ausführbare Dateien und PHAR-Archive in Uploads.** Die Blacklists für Medien (`Controllers/Backend/MediaManager.php:49–64`) und ESD-Dateien (`Controllers/Backend/Article.php:161–175`) enthalten `phar`, `php7`, `php8`, `pht` u. a. nicht. Medien haben zusätzlich eine Whitelist (`Models/Media/Media.php:1236–1247`), lassen aber Mehrfachendungen wie `bild.phar.jpg` und getarnte PHAR-Inhalte zu. ESD-Dateien haben keine Whitelist. `media/` ist öffentlich, `media/.htaccess` deaktiviert keinen PHP-Handler. Ob eine Datei ausgeführt wird, hängt von der Webserver-Konfiguration ab. Seit PHP 8.0 werden PHAR-Metadaten beim Zugriff über `phar://` nicht mehr automatisch deserialisiert. | P1 | Plugin + Betrieb | abgeschwächt ab 0.1.4 (Modul „Upload-Härtung“) |
| F6 | **Backend-XSS in E-Mail-Vorlagen** (öffentlicher Hinweis ohne Details, eigenständig zu untersuchen) | P2 | Plugin | zu untersuchen |
| F7 | Backend-Bibliotheken TinyMCE 3.5.8 und Ext JS 4.1 mit formal zutreffenden XSS-Advisories | P2 | Core (langfristig), Plugin (CSP als Abschwächung) | zu verifizieren |
| F8 | Composer-Abhängigkeiten mit Advisories, u. a. Guzzle 7.7.0, `guzzlehttp/psr7` 2.5.0, AWS SDK, FPDI, `jmespath.php`, Symfony 4.4/5.4 (Anhang A) | P1–P3 je Paket | Core | offen; Symfony 4.4 erhält keine Fixes mehr |
| F9 | Keine Trusted-Hosts-Prüfung; `trustedproxies` standardmäßig leer – Härtung gegen Host-/Forwarded-Header-Manipulation (vgl. Symfony-Advisories zu generierten URLs und Fragmenten in Anhang A) | P3 | Plugin + Betrieb | offen |
| F10 | **Fehlende Schutz-`.htaccess`** in internen Verzeichnissen (`vendor/`, `var/`, `files/` u. a.) nach Neuaufbau von `vendor/` oder nach Cache-/Ordnerbereinigung. Dadurch werden Bibliotheks- und Projektdateien von außen abrufbar (öffentlicher Hinweis ohne Details, eigenständig zu untersuchen). | P2 | Plugin + Betrieb | zu untersuchen |
| F11 | **Installer-Verzeichnis `recovery/install/` erreichbar**: [CVE-2022-48150](https://nvd.nist.gov/vuln/detail/CVE-2022-48150) (XSS); der Installer ist per `install.lock` gesperrt, das Verzeichnis bleibt aber erreichbar. | P3 | Plugin (Prüfung) + Betrieb | offen |
| F12 | **PHP-Laufzeit**: Lücken vom Oktober 2026 (CVE-2026-109618, CVE-2026-109619, CVE-2026-115560) und Supportende von PHP 8.2 am 31.12.2026, siehe 1.4. | P1 | Betrieb + Kern; Plugin nur Warnung | offen |
| F13 | **Kunden-Sessions**: Parallele Logins mit demselben Kundenkonto beenden ältere Sessions nicht. `SameSite` des Session-Cookies ist nicht einstellbar; ein pauschales `Strict` kann Rücksprünge von Zahlungsanbietern brechen. | P3 | Plugin | offen |
| F14 | **Backend-Konten**: keine Zwei-Faktor-Anmeldung, keine konfigurierbaren Passwortregeln | P2 | Plugin | offen |
| F15 | **Captcha bei „Passwort vergessen“**: nur optional über `passwordResetCaptcha`; ob er nach Updates aktiv ist, hängt von der Konfiguration ab | P3 | Betrieb; Plugin (Prüfung) | offen |
| F16 | **Textbausteine (Snippets) werden als Smarty ausgeführt**: Shopware setzt Snippet-Werte vor dem Kompilieren in den Template-Quelltext ein (`engine/Library/Enlight/Components/Snippet/Resource.php:181–244`). Damit wird jeder im Backend bearbeitbare Textbaustein als Smarty ausgewertet, zusätzlich Fehlermeldungen per `fetch('string:…')` (`Frontend/Account.php:606/672`, `Frontend/Register.php:426`). | P1 | Plugin | abgeschwächt ab 0.1.4 (Template-Guard: Anlegen, Ändern, CSV-Import, Doctrine; Scan) |
| F17 | **Einkaufswelten-Element „HTML Code“**: Feld `smarty` wird im Theme per `{include file="string:{$Data.smarty}"}` ausgewertet (`widgets/emotion/components/component_html_code.tpl`) | P2 | Plugin | abgeschwächt ab 0.1.4 (Template-Guard: Speichern, Vorschau, Übersetzungen, Doctrine; Scan seit 0.1.3) |
| F18 | **Zugriff auf statische Klassen in Smarty-Vorlagen** – [CVE-2021-21408](https://github.com/advisories/GHSA-4h9c-v5vg-5m6m): Shopware lässt `static_classes` leer, Smarty 3.1.12 erlaubt damit jede statische Klasse. | P1 | Plugin + Core | abgeschwächt ab 0.1.4 (Template-Guard-Regel `static-class`, immer aktiv) |

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
| CVE-2026-62992 | [GHSA-f6wf-28g6-769x](https://github.com/advisories/GHSA-f6wf-28g6-769x) | < 4.5.7 | Symlinks aus vertrauenswürdigen Verzeichnissen | **nicht abgedeckt**; betrifft Dateizugriffe über Symlinks im Dateisystem, nicht den Inhalt der Vorlagen; Betrieb: keine fremd beschreibbaren Symlinks in Template-Verzeichnissen |
| CVE-2023-28447 | [GHSA-7j98-h7fp-4vwj](https://github.com/advisories/GHSA-7j98-h7fp-4vwj) | < 3.1.48 | `escape:'javascript'` | nicht durch den Template-Guard; siehe F4 (0.1.6) |
| CVE-2026-82531 | – | – | – | in der GitHub Advisory Database nicht auffindbar; Quelle und Inhalt **zu verifizieren** |

## 3. Versionsplan Plugin

Jede Version durchläuft die CI inklusive Integrationstest gegen echtes Shopware 5.7.20; jeder Fix bekommt dort einen Regressionstest. Die Tests arbeiten mit harmlosen Eingaben, die nur prüfen, ob eine Schutzmaßnahme greift – es wird kein Code geschrieben, der Lücken tatsächlich ausnutzt.

| Version | Inhalt | Befunde |
|---|---|---|
| **0.1.2** ✅ | **Modul „Template-Guard“:** lehnt beim Speichern im Backend E-Mail-Vorlagen (inkl. Übersetzungen) und Produktexporte mit `{extends}`, `{fetch}`, `{php}`, ungültigen `{function}`-/`{block}`-Namen oder `$smarty.template_object` ab, ebenso die Funktion „Smarty prüfen“; zusätzlich Absicherung über Doctrine-Events (REST-API, andere Plugins). | F1–F3 |
| **0.1.3** ✅ | **Template-Guard erweitern:** Newsletter, Beleg-Bausteine, SEO-Vorlagen, E-Mail-Header/-Footer, wesentliche Merkmale, Zahlungsarten- und Länderhinweise (jeweils inkl. Übersetzungen); strenge Prüfung von `{include}` und `{math}` (abschaltbar); Konsolenbefehl `sw5cs:template-guard:scan` für gespeicherte Inhalte. | F1–F3, F17 (Scan) |
| **0.1.4** ✅ | **Template-Guard abschließen:** Textbausteine einschließlich CSV-Import (F16), Einkaufswelten-Element „HTML Code“ (F17), Regel gegen Zugriff auf statische Klassen (F18). **Modul „Upload-Härtung“:** sperrt ausführbare Endungen (auch Mehrfachendungen) und PHAR-Inhalte bei Medien-, ESD- und Import-Uploads sowie allen übrigen Uploads; Doctrine-Absicherung für Medien. | F5, F16–F18 |
| **0.1.5** ✅ | **Shopware-Vorgaben übererfüllen:** Code-Style nach den php-cs-fixer-Regeln des Shopware-Kerns, statische Analyse mit PHPStan auf höchster Stufe inkl. strenger Regeln (der Kern nutzt Stufe 8), Meldungen als Textbausteine (DE/EN), Protokollierung über den plugin-eigenen Logger, Cache-Leerung beim Speichern der Konfiguration und beim Deinstallieren nach den Shopware-5-Plugin-Guidelines; alle Prüfungen fest in der CI. | – |
| **0.1.6** | **JavaScript-Escaping:** sicherer `escape:'javascript'`-Modifier nach Smarty 3.1.48 – Machbarkeit eines Overrides ohne Core-Änderung prüfen. | F4 |
| **0.1.7** | **Header-Modul erweitern:** `Content-Security-Policy` (zuerst `Report-Only`) für Frontend und optional Backend als Abschwächung von XSS (inkl. TinyMCE/Ext JS); `Permissions-Policy`. | F6, F7 |
| **0.1.8** | **Host-Härtung:** optionale Allowlist erlaubter Hosts und Prüfung von `X-Forwarded-*`-Headern gegen konfigurierte Proxys. | F9 |
| **0.2.0** | **Modul „Sicherheits-Check“:** Backend-Übersicht über sicherheitsrelevante Einstellungen mit Handlungsempfehlungen. Geprüft werden:<ul><li>Shopware- und PHP-Version inkl. Warnung bei bekannt verwundbaren PHP-Ständen und PHP-Supportende</li><li>`session.upload_progress.enabled`</li><li>Trusted Proxies</li><li>Template-Sandbox</li><li>vorhandene Schutz-`.htaccess` in internen Verzeichnissen</li><li>erreichbares `recovery/install/`</li><li>Captcha bei „Passwort vergessen“</li><li>aktive Plugin-Module</li></ul>Geprüft wird nur der eigene Shop, und es wird nichts geändert. | F10–F12, F15 |
| **0.2.1** | **Schutzdateien wiederherstellen:** Fehlende Schutz-`.htaccess` (Inhalt wie im Shopware-Original) nach Cache-Leerung bzw. per Backend-Aktion wiederherstellen. Vorher klären, welche Verzeichnisse betroffen sind und ob nginx-Setups eine eigene Lösung brauchen. | F10 |
| **0.2.2** | **Kunden-Session-Härtung:** Optional ältere Sessions beim Login beenden. Optionales `SameSite` für das Session-Cookie, mit Warnhinweis zu Zahlungsanbietern. | F13 |
| **0.3.0** | **Backend-Konto-Härtung:** Zwei-Faktor-Anmeldung (TOTP) und konfigurierbare Passwortregeln für Backend-Konten | F14 |

Reihenfolge und Umfang können sich ändern; das [CHANGELOG](./CHANGELOG.md) dokumentiert, was tatsächlich umgesetzt wurde.

## 4. Kern (separates Projekt)

Nicht im Plugin lösbar, geplant im Projekt zur PHP-8.3/8.4-Kompatibilität des Shopware-5.7-Kerns:

1. Abhängigkeiten aktualisieren, wo es ohne Bruch möglich ist (Guzzle ≥ 7.15.2, `guzzlehttp/psr7` ≥ 2.12.3, AWS SDK, FPDI ≥ 2.6.7, `jmespath.php` ≥ 2.9.1, `symfony/polyfill-intl-idn` ≥ 1.38.1, Symfony-5.4-Komponenten ≥ 5.4.56).
2. Symfony-4.4-Komponenten (`http-foundation`, `http-kernel`, `config`, `validator`, `process`, `expression-language`): keine Upstream-Fixes mehr – Backport der Fixes oder Upgrade bewerten.
3. Smarty 3.1.12: Fixes zurückportieren oder Upgrade bewerten.
4. `firebase/php-jwt` 6.x → 7 (Major-Update) und `league/flysystem` 1.x bewerten.
5. Langfristig: TinyMCE 3.5.8 und Ext JS 4.1 im Backend.

## 5. Betrieb (Empfehlungen an Shop-Betreiber)

- Backend-Zugänge mit Rechten auf E-Mail-, Newsletter-, Beleg-, Export- und SEO-Vorlagen nur vertrauenswürdigen Personen geben (Angriffsfläche F1–F4).
- PHP-Ausführung in Upload-Verzeichnissen abschalten, besonders in `media/`. Unter Apache etwa `RemoveHandler`/`php_admin_flag engine off` bzw. einen passenden `FilesMatch` in der vHost-Konfiguration setzen, unter nginx keine `location` mit PHP-Weitergabe für `media/` und `files/`. Außerdem prüfen, ob der Webserver `.phar`, `.pht` oder `.php7` als PHP ausführt (F5).
- `trustedproxies` in der `config.php` korrekt setzen, wenn der Shop hinter einem Proxy oder Load-Balancer läuft.
- PHP aktuell halten und den Patchstand beim Hoster schriftlich bestätigen lassen, insbesondere wegen der Lücken vom Oktober 2026 (F12). Bis zum Update `session.upload_progress.enabled = 0` setzen; das ist eine Abschwächung, kein Ersatz für das Update.
- Den Betrieb nach dem 31.12.2026 planen: Extended Support für PHP 8.2 beim Hoster, Umstieg auf einen Kern mit PHP 8.3/8.4 (separates Projekt) oder Migration.
- `recovery/install/` auf dem Produktivsystem entfernen oder per Webserver sperren (F11).
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

