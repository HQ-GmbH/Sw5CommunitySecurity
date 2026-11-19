# Roadmap

Stand: **2026-11-19** · Basis: Shopware **5.7.20** (letzte offizielle Shopware-5-Version) · Plugin-Version: **0.1.1**

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
- Ein kommerzieller Drittanbieter nennt öffentlich weitere, nicht in 5.7.20 enthaltene Fixes, u. a. **Backend-XSS in E-Mail-Vorlagen** und das **Blockieren von PHAR-Uploads/-Ausführung**. Details und CVEs sind nicht öffentlich – diese Punkte werden hier eigenständig untersucht.
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

## 2. Befunde und Zuordnung

| # | Befund | Prio | Ort | Status |
|---|---|---|---|---|
| F1 | **Smarty `{extends}`-Code-Injection** – [CVE-2024-35226](https://github.com/smarty-php/smarty/security/advisories/GHSA-4rmg-292m-wg3w). `smarty_internal_compile_extends.php:76` führt `eval('$include_file = ' . $_attr['file'] . ';')` aus. | P1 | Plugin + Core | offen, Ausnutzbarkeit unter Shopwares Template-Sandbox zu verifizieren |
| F2 | **Smarty `{function name=…}`-Code-Injection** – [CVE-2021-26120](https://github.com/smarty-php/smarty/security/advisories/GHSA-3rpf-5rqv-689q). Der Name wird ungeprüft in PHP-Code eingesetzt (`smarty_internal_compile_function.php`). | P1 | Plugin + Core | offen, zu verifizieren |
| F3 | Weitere Smarty-Lücken mit formal betroffenem Versionsbereich: [CVE-2022-29221](https://github.com/smarty-php/smarty/security/advisories/GHSA-634x-pc3q-cf4c), [CVE-2021-26119](https://github.com/smarty-php/smarty/security/advisories/GHSA-w5hr-jm4j-9jvq), [CVE-2021-29454](https://github.com/smarty-php/smarty/security/advisories/GHSA-29gp-2c3m-3j6m), [CVE-2021-21408](https://github.com/smarty-php/smarty/security/advisories/GHSA-4h9c-v5vg-5m6m), CVE-2026-82531, CVE-2026-62993 (SSRF über `{fetch}`), CVE-2026-62992 | P1/P2 | Plugin + Core | ob Shopware Fixes zurückportiert hat, ist ungeprüft |
| F4 | **Smarty `escape:'javascript'`-XSS** – [CVE-2023-28447](https://github.com/smarty-php/smarty/security/advisories/GHSA-7j98-h7fp-4vwj); `modifier.escape.php` enthält die Ersetzungen aus Smarty 3.1.48 nicht. | P2 | Plugin + Core | offen |
| F5 | **PHAR-Uploads / `phar://`-Ausführung** (Hinweis aus öffentlicher Hinweisliste; Details nicht öffentlich) | P1 | Plugin | zu untersuchen |
| F6 | **Backend-XSS in E-Mail-Vorlagen** (Hinweis aus öffentlicher Hinweisliste; Details nicht öffentlich) | P2 | Plugin | zu untersuchen |
| F7 | Backend-Bibliotheken TinyMCE 3.5.8 und Ext JS 4.1 mit formal zutreffenden XSS-Advisories | P2 | Core (langfristig), Plugin (CSP als Abschwächung) | zu verifizieren |
| F8 | Composer-Abhängigkeiten mit Advisories, u. a. Guzzle 7.7.0, `guzzlehttp/psr7` 2.5.0, AWS SDK, FPDI, `jmespath.php`, Symfony 4.4/5.4 (Anhang A) | P1–P3 je Paket | Core | offen; Symfony 4.4 erhält keine Fixes mehr |
| F9 | Keine Trusted-Hosts-Prüfung; `trustedproxies` standardmäßig leer – Härtung gegen Host-/Forwarded-Header-Manipulation (vgl. Symfony-Advisories zu generierten URLs und Fragmenten in Anhang A) | P3 | Plugin + Betrieb | offen |

**Angriffsfläche Smarty (F1–F4, F6):** Shopware 5 wertet Inhalte aus der Datenbank als Smarty-Templates aus – E-Mail-Vorlagen (`Shopware\Components\StringCompiler`), Newsletter (`Controllers/Backend/Newsletter.php`), Beleg-Vorlagen (`Components/Document.php`), Produktexporte (`Core/sExport.php`) und SEO-URL-Vorlagen (`Core/sRewriteTable.php`). Wer diese im Backend bearbeiten darf, kann je nach Ausnutzbarkeit von F1–F3 bis zur Code-Ausführung auf dem Server eskalieren. Shopware aktiviert zwar eine Smarty-Sandbox (`Shopware\Components\Template\Security` mit Whitelist für PHP-Funktionen), lässt `disabled_tags` aber leer – `{extends}`, `{function}`, `{fetch}` sind erlaubt.

## 3. Versionsplan Plugin

Jede Version durchläuft die CI inklusive Integrationstest gegen echtes Shopware 5.7.20; jeder Fix bekommt dort einen Regressionstest (bei Sicherheitslücken mit Proof of Concept, der vor dem Fix scheitern muss).

| Version | Inhalt | Befunde |
|---|---|---|
| **0.1.2** | **Verifikation:** Proof-of-Concept-Tests im Integrationsjob für F1, F2, F3 (`{fetch}`) und F4 gegen Shopwares Template-Sandbox; Ergebnis wird hier dokumentiert und steuert die folgenden Versionen. | F1–F4 |
| **0.1.3** | **Modul „Template-Guard“:** prüft im Backend gespeicherte Smarty-Inhalte (E-Mail-, Newsletter-, Beleg-, Export- und SEO-Vorlagen) und lehnt gefährliche Konstrukte ab (`{extends}`/`{include}` mit dynamischen Ausdrücken, `{function}` mit ungültigem Namen, `{fetch}`, `{php}`); optional Prüfung bestehender Vorlagen mit Bericht im Backend. | F1–F3, F6 |
| **0.1.4** | **Modul „Upload- & PHAR-Härtung“:** Blockieren von PHAR-Dateien (auch getarnt) bei Medien- und ESD-Uploads; Prüfung, ob der `phar://`-Stream-Wrapper zur Laufzeit sicher deaktiviert werden kann. | F5 |
| **0.1.5** | **JavaScript-Escaping:** sicherer `escape:'javascript'`-Modifier nach Smarty 3.1.48 – Machbarkeit eines Overrides ohne Core-Änderung prüfen. | F4 |
| **0.1.6** | **Header-Modul erweitern:** `Content-Security-Policy` (zuerst `Report-Only`) für Frontend und optional Backend als Abschwächung von XSS (inkl. TinyMCE/Ext JS); `Permissions-Policy`. | F6, F7 |
| **0.1.7** | **Host-Härtung:** optionale Allowlist erlaubter Hosts und Prüfung von `X-Forwarded-*`-Headern gegen konfigurierte Proxys. | F9 |
| **0.2.0** | **Modul „Sicherheits-Check“:** Backend-Übersicht über sicherheitsrelevante Einstellungen (Shopware- und PHP-Version, Trusted Proxies, Template-Sandbox, aktive Plugin-Module) mit Handlungsempfehlungen. | – |

Reihenfolge und Umfang können sich durch die Ergebnisse von 0.1.2 ändern; das [CHANGELOG](./CHANGELOG.md) dokumentiert, was tatsächlich umgesetzt wurde.

## 4. Kern (separates Projekt)

Nicht im Plugin lösbar, geplant im Projekt zur PHP-8.3/8.4-Kompatibilität des Shopware-5.7-Kerns:

1. Abhängigkeiten aktualisieren, wo es ohne Bruch möglich ist (Guzzle ≥ 7.15.2, `guzzlehttp/psr7` ≥ 2.12.3, AWS SDK, FPDI ≥ 2.6.7, `jmespath.php` ≥ 2.9.1, `symfony/polyfill-intl-idn` ≥ 1.38.1, Symfony-5.4-Komponenten ≥ 5.4.56).
2. Symfony-4.4-Komponenten (`http-foundation`, `http-kernel`, `config`, `validator`, `process`, `expression-language`): keine Upstream-Fixes mehr – Backport der Fixes oder Upgrade bewerten.
3. Smarty 3.1.12: Fixes zurückportieren oder Upgrade bewerten.
4. `firebase/php-jwt` 6.x → 7 (Major-Update) und `league/flysystem` 1.x bewerten.
5. Langfristig: TinyMCE 3.5.8 und Ext JS 4.1 im Backend.

## 5. Betrieb (Empfehlungen an Shop-Betreiber)

- Backend-Zugänge mit Rechten auf E-Mail-, Newsletter-, Beleg-, Export- und SEO-Vorlagen nur vertrauenswürdigen Personen geben (Angriffsfläche F1–F4).
- `trustedproxies` in der `config.php` korrekt setzen, wenn der Shop hinter einem Proxy oder Load-Balancer läuft.
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

