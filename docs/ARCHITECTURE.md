# Architektur und Sicherheitsentscheidungen

Diese Datei beschreibt den Aufbau des Plugins und begründet die wichtigsten Sicherheitsentscheidungen. Sie ist für Mitwirkende, Prüfer und Shopbetreiber gedacht, die nachvollziehen wollen, wie das Plugin arbeitet und warum. Bekannte Befunde und Versionsplan stehen in [ROADMAP.md](../ROADMAP.md), veröffentlichte Änderungen in [CHANGELOG.md](../CHANGELOG.md), verbindliche Regeln in [CONTRIBUTING.md](../CONTRIBUTING.md).

*This file describes the plugin's structure and the reasoning behind its key security decisions. It is written in German; class names, events and file paths are given verbatim.*

Stand: Version 0.1.8 · Shopware 5.7.19/5.7.20 (PHP 8.1/8.2) und 5.6.10 (PHP 7.4)

## 1. Grundsätze

| Grundsatz | Umsetzung |
|---|---|
| **Keine Core-Eingriffe** | Das Plugin ändert keine Dateien von Shopware und ersetzt keine Core-Klassen. Es nutzt ausschließlich dokumentierte Erweiterungspunkte: Controller- und Dispatch-Events, Doctrine-Lifecycle-Events, Filter-Events, den DI-Container und Smarty-Filter. So bleibt es updatefähig und lässt sich jederzeit rückstandsfrei deaktivieren. |
| **Ablehnen statt verändern** | Gefährliche Inhalte werden beim Speichern abgelehnt, nicht stillschweigend „bereinigt“. Eine automatische Bereinigung könnte Vorlagen unbemerkt verändern und wäre selbst eine Fehlerquelle. Gespeicherte Inhalte werden nie verändert. Einzige Ausnahme ist das JavaScript-Escaping: Es ändert nicht die Vorlage, sondern nur den daraus kompilierten, jederzeit neu erzeugbaren Code im Template-Cache. |
| **Mehrere Schutzschichten** | Wo es mehrere Speicherwege gibt (Backend, REST-API, andere Plugins), wird jeder abgedeckt: zuerst vor der Backend-Aktion mit verständlicher Meldung, zusätzlich über Doctrine als Rückfallebene. |
| **Gezielt und abschaltbar** | Jede Regel richtet sich gegen einen dokumentierten Angriffsweg. Jedes Modul lässt sich einzeln abschalten. Die Standardwerte lösen bei Shopwares eigenen Vorlagen und Einstellungen keine Funde aus (geprüft im Integrationstest, Abschnitt 0). |
| **Keine Inhalte nach außen** | Meldungen, Scan-Ausgaben und Protokolle nennen Ort, Feld und Regel, nie den Inhalt, nie Dateinamen, Benutzernamen oder IP-Adressen. |
| **Testbare Kernlogik** | Die Prüfregeln (`TemplateInspector`, `UploadInspector`, `HeaderPolicy`, `JavaScriptEscapeHardening`, `PluginConfig`) sind frei von Shopware-Abhängigkeiten und vollständig per Unit-Test abgedeckt. Die Anbindung an Shopware wird per Integrationstest gegen echtes Shopware 5.6.10, 5.7.19 und 5.7.20 geprüft. |

## 2. Aufbau

```
Sw5CommunitySecurity.php          Plugin-Bootstrap: Cache-Leerung, Log-Stufe, Compiler-Pass, ACL-Ressource
DependencyInjection/
  TemplateFilterCompilerPass      registriert den Smarty-Filter am Service „template“
Resources/
  config.xml                      Einstellungen (DE/EN)
  services.xml                    Services und Event-Subscriber
  menu.xml                        Menüeintrag „Einstellungen > Sicherheits-Check“
  views/backend/sw5cs_security_check/index.tpl   Backend-Seite des Sicherheits-Checks
  snippets/backend/sw5_community_security/messages.ini   Meldungen (DE/EN)
Components/
  Config/          PluginConfig, PluginConfigProvider,    typisierte Konfiguration je Shop
                   DefaultShopConfigSource                 bzw. des Hauptshops
  SecurityHeaders/ HeaderPolicy, HeaderValueNormalizer     welche Header in welchem Modul, Prüfung der Freitext-Werte
  TemplateGuard/   TemplateInspector, TemplateGuard,       Prüfregeln für Smarty-Vorlagen,
                   GuardedSources, StoredTemplateScanner   geschützte Quellen, Scan
  UploadGuard/     UploadInspector                         Prüfregeln für Uploads
  JavaScriptEscape/ JavaScriptEscapeHardening,             Ersetzungsliste aus Smarty 3.1.48,
                   CompiledTemplateFilter                  Smarty-Postfilter
  SecurityCheck/   SecurityCheck, Environment,             Bewertung (ohne Shopware-Abhängigkeit),
                   EnvironmentCollector,                   Datensammlung aus Shopware,
                   SecurityCheckRunner, CheckResult        Ausgabe als Klartext-Zeilen
  ProtectionFiles/ ProtectionFiles, AutoRestore,           Schutz-.htaccess erkennen und anlegen,
                   RestoreResult                           automatische Wiederherstellung
  CustomerSession/ SingleLogin, LoginTokenStore,           eine Anmeldung je Kundenkonto
                   DbLoginTokenStore                       (Token-Hash je Konto)
  TwoFactor/       Totp, Base32, TwoFactorLogin,           Zwei-Faktor-Anmeldung für das Backend
                   TwoFactorStore, DbTwoFactorStore, …     (RFC 6238, Sperre, Speicherung)
  PasswordPolicy/  PasswordPolicy                          Passwortregeln für Backend-Konten
  Messages/        MessageCatalog, MessageFormatter, …     Textbausteine, HTML-Maskierung
  Database/        RowReader                               lesender Datenbankzugriff
  Compat/Sw56, Compat/Sw57                                 Adapter für Shopware 5.6 bzw. 5.7
Subscriber/
  SecurityHeaderSubscriber        Modul „HTTP-Security-Header“
  TemplateGuardSubscriber         Modul „Template-Guard“
  UploadGuardSubscriber           Modul „Upload-Härtung“
  ConfigCacheSubscriber           Cache-Leerung beim Speichern der Einstellungen
  ProtectionFileSubscriber        Schutzdateien beim Leeren des HTTP-Caches wiederherstellen
  CustomerSessionSubscriber       Modul „Kunden-Sessions“
  BackendAccountSubscriber        Modul „Backend-Konten“: Code beim Login, Feld im Login-Formular, Passwortregeln
Controllers/Backend/
  Sw5csSecurityCheck              Backend-Modul „Sicherheits-Check“ (leichtgewichtiges Modul, HTML im iframe)
  Sw5csTwoFactor                  Backend-Modul „Zwei-Faktor-Anmeldung“ (Einrichtung je Benutzer)
Commands/
  TemplateGuardScanCommand        bin/console sw5cs:template-guard:scan
  SecurityCheckCommand            bin/console sw5cs:security-check
  ProtectionFilesRestoreCommand   bin/console sw5cs:protection-files:restore
  TwoFactorResetCommand           bin/console sw5cs:two-factor:reset <Benutzername>
```

## 3. Module

### 3.1 HTTP-Security-Header

- **Event:** `Enlight_Controller_Front_DispatchLoopShutdown`. Die Header werden einmal je Anfrage gesetzt, bevor Shopwares HTTP-Cache die Antwort speichert.
- **Header:** `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, optional `Strict-Transport-Security` und `Content-Security-Policy` bzw. `Content-Security-Policy-Report-Only`.
- **Entscheidungen:**
  - Bereits gesetzte Header (von Shopware, Theme oder anderen Plugins) werden **nie überschrieben**. So entstehen keine Konflikte mit bestehenden, bewusst gewählten Einstellungen.
  - HSTS wird **nur über HTTPS** gesendet. Über HTTP wäre der Header wirkungslos und könnte bei Fehlkonfigurationen zu Verwirrung führen.
  - Im Backend standardmäßig aus, um Wechselwirkungen mit Backend-Modulen zu vermeiden.
  - **Content-Security-Policy standardmäßig aus:** Zahlungsarten, Tracking und Consent-Tools laden Skripte von fremden Quellen, die jede Richtlinie individuell erlauben muss. Empfohlen wird zuerst „Nur melden“. Die mitgelieferten Richtlinien erlauben `'unsafe-inline'`, weil das Responsive-Theme Inline-Skripte nutzt; Nonces würden Eingriffe in alle Templates erfordern. Die Wirkung liegt deshalb bei fremden Skriptquellen, `object-src`, `base-uri` und `frame-ancestors`, nicht bei eingeschleusten Inline-Skripten. Das Backend hat eine eigene Richtlinie, weil Ext JS `'unsafe-eval'` braucht.
  - **Permissions-Policy** sperrt nur Funktionen ohne Nutzen für einen Shop (Kamera, Mikrofon, USB). `payment` und `geolocation` bleiben frei, weil Zahlungs- und Filialsuche-Plugins sie nutzen können.
  - **Freitext wird geprüft, nicht repariert:** `HeaderValueNormalizer` akzeptiert nur druckbares ASCII und die jeweilige Syntax (CSP: keine Kommas, keine doppelten Direktiven; Permissions-Policy: Structured-Field-Dictionary; `report-uri`: nur `https://`). Ungültige Werte werden ganz verworfen und der Header nicht gesendet, damit nie eine halb verstandene Richtlinie entsteht.

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

### 3.4 JavaScript-Escaping

Smarty 3.1.12 maskiert mit `escape:'javascript'` nur Backslash, Anführungszeichen, Zeilenumbrüche und `</`. Smarty 3.1.48 ergänzt `<!--`, `<s`/`<S`, Backticks und `${` ([CVE-2023-28447](https://github.com/smarty-php/smarty/security/advisories/GHSA-7j98-h7fp-4vwj)). Die Standard-Themes und der Code von Shopware 5.7.20 nutzen `escape:'javascript'` nicht; betroffen sind eigene Themes, Plugins und im Backend bearbeitete Vorlagen.

- **Ansatzpunkt:** Bei einem festen Escape-Typ schreibt Smarty die Ersetzungsliste direkt in den kompilierten Code (`engine/Library/Smarty/plugins/modifiercompiler.escape.php`, Fall `javascript`). Ein Smarty-Postfilter (`CompiledTemplateFilter`) ersetzt genau diese Liste durch die vollständige aus Smarty 3.1.48, bevor Smarty den kompilierten Code speichert (`smarty_internal_templatecompilerbase.php`, `compileTemplate()`).
- **Registrierung:** `TemplateFilterCompilerPass` ergänzt die Definition des Services `template` um `registerFilter('post', …)`. Das greift bei jeder Erzeugung der Template-Engine, auch wenn ein anderer Service sie als Abhängigkeit erhält. Kopien der Engine (Belege, Newsletter, Produktexporte) übernehmen den Filter; E-Mails nutzen die Engine über den String-Compiler.
- **Entscheidungen:**
  - **Kompilierten Code anpassen statt `escape` neu registrieren:** Der Produktexport registriert selbst einen Modifier `escape` (`engine/Shopware/Core/sExport.php`, `sInitSmarty()`); Smarty lehnt eine zweite Registrierung mit einer Ausnahme ab. Eigene Funktionen gleichen Namens wie die Smarty-Plugins würden mit Smartys `require_once` kollidieren.
  - **Kein Verweis auf das Plugin im kompilierten Code:** Der Filter setzt nur ein PHP-Array ein. Kompilierte Templates funktionieren deshalb auch, nachdem das Plugin entfernt wurde; bei Aktivieren, Deaktivieren, Update, Deinstallation und beim Speichern der Einstellungen wird der Template-Cache ohnehin geleert.
  - **Einstellung des Hauptshops:** Kompilierte Templates werden von allen Shops geteilt, deshalb gilt der Modulschalter des Hauptshops. Lässt sich die Konfiguration nicht lesen, bleibt die Härtung aktiv.
- **Grenzen:** Steht der Escape-Typ oder ein weiterer Parameter als Variable im Template (z. B. `escape:$typ`), ruft Smarty zur Laufzeit `smarty_modifier_escape()` auf; dieser Weg wird nicht angepasst. Ebenso nicht die eigene `escape`-Funktion des Produktexports, deren Ausgabe in Exportdateien landet.

### 3.5 Sicherheits-Check

- **Zweck:** Übersicht über Einstellungen, die das Plugin nicht selbst absichern kann, mit konkreter Handlungsempfehlung je Punkt. Die Prüfung ändert nichts und sendet nichts nach außen; auch die PHP-Supportdaten sind im Code hinterlegt (Quelle php.net, Stand im Code).
- **Aufbau:** `EnvironmentCollector` liest die Werte aus Shopware (Container-Parameter aus der `config.php`, Datenbank, Dateisystem, PHP-Einstellungen, aktuelle Anfrage). `SecurityCheck` bewertet sie ohne Shopware-Abhängigkeit und ist vollständig per Unit-Test abgedeckt. Status: OK, Hinweis, Warnung, Fehler.
- **Host und Proxys (F9):** Ein eigenes Modul für erlaubte Hosts und Weiterleitungs-Header entfällt. Ohne eingetragene `trustedproxies` wertet Symfony `X-Forwarded-*` nicht aus, bei aktivem HTTP-Cache entfernt `SubRequestHandler` sie vollständig, und im Frontend leitet der Router fremde Hosts auf den Shop-Host um. Geprüft werden deshalb die verbleibenden Konfigurationsfehler: zu weit gefasste `trustedproxies` (z. B. `0.0.0.0/0`, öffentliche IPv6-Bereiche unter /16, `REMOTE_ADDR` ab Symfony 4.4), Weiterleitungs-Header von nicht eingetragenen Adressen und Hauptshops ohne Host.
- **Zugriff:** Die Backend-Seite ist ein leichtgewichtiges Backend-Modul (HTML im iframe, Shopware-Entwicklerdoku „Lightweight backend modules“). Die Anmeldung erzwingt das Auth-Plugin des Kerns für jeden Backend-Controller; zusätzlich legt das Plugin die ACL-Ressource `sw5cssecuritycheck` mit den Rechten „read“ (Seite ansehen) und „restore“ (Schutzdateien wiederherstellen) an; bei Updates wird ein fehlendes Recht ergänzt. Administratoren haben Zugriff, andere Rollen nur nach Freigabe in der Benutzerverwaltung. Die Ressource wird beim Deinstallieren entfernt, außer der Betreiber behält die Daten.
- **CSRF:** Die Seite (`index`) wird per GET ohne CSRF-Token in das iframe geladen und ist deshalb von der CSRF-Prüfung ausgenommen (`CSRFWhitelistAware`); sie liest nur. Die einzige schreibende Aktion `restoreProtectionFiles` ist nicht ausgenommen: Das Formular sendet das Token der Backend-Sitzung als `__csrf_token`, das der Kern vor der Aktion prüft (`CSRFTokenValidator`, 5.6.10 und 5.7.20). Die Aktion nimmt nur POST an, prüft zusätzlich das Recht „restore“ und leitet danach auf die Seite zurück (Post/Redirect/Get).
- **Ausgabe:** Alle Texte, auch Shopnamen und Proxy-Einträge, werden im Template maskiert. Die Seite lädt keine externen Ressourcen.
- **Konsole:** `sw5cs:security-check` zeigt dieselben Prüfungen und endet mit Exit-Code 1, wenn ein Fehler vorliegt (für Monitoring). PHP-Werte stammen dort aus der CLI-Konfiguration; maßgeblich ist die Backend-Ansicht.

### 3.6 Schutzdateien

- **Zweck:** Shopware liefert in acht internen Verzeichnissen eine `.htaccess` mit, die unter Apache den Zugriff von außen sperrt (`bin`, `engine/Library`, `files/backup`, `files/documents`, `media/temp`, `var/cache`, `var/log`, `vendor`; geprüft in 5.6.10 und 5.7.20). Shopware selbst löscht sie nicht: Die Cache-Leerung (Backend, `sw:cache:clear`, `var/cache/clear_cache.sh`) entfernt nur Unterordner von `var/cache`. Verloren gehen sie bei Deployments oder Uploads ohne Punktdateien und beim Neuaufbau von `vendor/`.
- **Inhalt:** eigene Regeln mit derselben Wirkung wie das Original – Apache 2.4 (`Require all denied`) und 2.2 (`Deny from all`); in `engine/Library` nur für PHP-Dateien, weil dort auch das Ext-JS-Backend liegt. Ein Kommentar in der ersten Zeile nennt das Plugin als Herkunft.
- **Sicherheit beim Schreiben:** nur in vorhandenen Verzeichnissen und nur, wenn keine Datei existiert. Angelegt wird exklusiv (`fopen` mit „x“), sodass eine zwischenzeitlich angelegte Datei nie überschrieben wird. Ein Symlink auf eine vorhandene Datei gilt als vorhanden; ein ins Leere zeigender Symlink wird nicht verfolgt (PHP löst Links vor dem Öffnen auf und würde sonst das Ziel anlegen) und als nicht angelegt gemeldet.
- **Auslöser:** Schaltfläche im Sicherheits-Check (nur bei fehlenden Dateien sichtbar), Konsolenbefehl und das Event `Shopware_Plugins_HttpCache_ClearCache`, das `CacheManager::clearHttpCache()` auslöst (Backend „Caches leeren“ mit Frontend- oder HTTP-Cache, Plugin-Installation). Die automatische Wiederherstellung ist abschaltbar (`protectionFilesAutoRestore`) und bricht die Cache-Leerung nie ab; Fehler werden protokolliert.
- **nginx:** liest keine `.htaccess`. Die Dateien schaden dort nicht, schützen aber auch nicht; der Sicherheits-Check weist bei nginx auf die Server-Konfiguration hin.

### 3.7 Kunden-Sessions

- **Eine Anmeldung je Kundenkonto:** `sAdmin::loginUser()` löst `Shopware_Modules_Admin_Login_Successful` aus, bevor es den Kunden in die Sitzung schreibt. Das Plugin erzeugt dann ein Zufallstoken (32 Byte), legt es in der Sitzung ab und speichert nur dessen SHA-256-Hash je Kundenkonto (`s_plugin_sw5cs_customer_login`). `sAdmin::sCheckUser()` läuft bei jedem Aufruf eines angemeldeten Kunden; über den Filter `Shopware_Modules_Admin_CheckUser_FilterGetUser` lehnt das Plugin Sitzungen ab, deren Token nicht mehr das neueste ist. Shopware meldet die Sitzung dann selbst ab (5.6.10 und 5.7.20). Die Spalte `s_user.sessionID` eignet sich dafür nicht, weil `sCheckUser()` sie bei jedem Aufruf mit der aufrufenden Sitzung überschreibt.
- **Verhalten an den Rändern:** Sitzungen ohne Token (von vor dem Einschalten) bleiben gültig, damit das Einschalten nicht alle Kunden abmeldet. Bei Datenbankfehlern bleibt die Sitzung ebenfalls gültig („fail open“); der Fehler wird protokolliert. „Als Kunde anmelden“ im Backend nutzt denselben Login und meldet damit die Sitzung des Kunden ab.
- **SameSite:** Das Session-Cookie entsteht beim Initialisieren des Dienstes „session“. Unmittelbar davor (`Enlight_Bootstrap_InitResource_session`) setzt das Plugin `session.cookie_samesite`. Ein Wert für `cookie_samesite` im Abschnitt `session` der `config.php` wird in Shopware 5.7 danach gesetzt und hat Vorrang.

### 3.8 Backend-Konten

- **Zwei-Faktor-Anmeldung:** `Shopware_Controllers_Backend_Login::loginAction()` prüft Benutzername und Passwort und legt die Identität in der Backend-Sitzung ab. Danach (`PostDispatch_Backend_Login`) prüft das Plugin bei eingerichteten Benutzern den Code aus dem Login-Formular; ohne gültigen Code entfernt es die Identität wieder (`clearIdentity()`), und der Login antwortet wie ein fehlgeschlagener. Benutzer ohne Einrichtung sind nicht betroffen.
- **TOTP:** eigene Implementierung nach RFC 6238/4226 (HMAC-SHA1, 30 Sekunden, 6 Ziffern, ±1 Zeitschritt), per Unit-Test gegen die Testvektoren aus RFC 6238 Anhang B geprüft. Der Schlüssel hat 160 Bit (Base32). Jeder Zeitschritt wird nur einmal akzeptiert (`last_used_step`).
- **Fehlversuche:** Der Auth-Adapter von Shopware setzt `failedlogins` bei richtigem Passwort auf 0, seine Sperre greift für den Code also nicht. Das Plugin zählt falsche Codes deshalb selbst und sperrt ab dem fünften in Folge über `s_core_auth.lockeduntil` (Datenbankzeit), 30 Sekunden je Fehlversuch, höchstens eine Stunde. Ein fehlender Code zählt nicht als Fehlversuch.
- **Speicherung:** `s_plugin_sw5cs_backend_two_factor` (Fremdschlüssel auf `s_core_auth` mit `ON DELETE CASCADE`): Schlüssel, zuletzt genutzter Zeitschritt, Fehlversuche. Wer Lesezugriff auf die Datenbank hat, kann den Schlüssel lesen, wie die Passwort-Hashes in derselben Datenbank.
- **Einrichtung:** leichtgewichtiges Backend-Modul `Sw5csTwoFactor` ohne eigene ACL-Ressource, damit jeder angemeldete Backend-Benutzer sein eigenes Konto absichern kann. Der neue Schlüssel liegt bis zur Bestätigung nur in der Backend-Sitzung. Alle ändernden Aktionen sind POST mit dem CSRF-Token der Backend-Sitzung. Das Entfernen bei anderen Benutzern erfordert das Recht „twofactorreset“ an der Ressource `sw5cssecuritycheck`. Die Seite zeigt den Schlüssel, die `otpauth://`-Adresse und einen QR-Code. Den QR-Code zeichnet `qr-code.js` im Browser als SVG mit der mitgelieferten Bibliothek `qrcode-generator` (MIT, siehe THIRD-PARTY-NOTICES.md); beide Skripte werden aus dem Plugin-Ordner geladen, nur während der Einrichtung, ohne Verbindung nach außen. Ohne JavaScript bleibt die Einrichtung per Schlüssel möglich.
- **Login-Formular:** Das Feld für den Code ergänzt ein Ext-JS-Override im Block `backend/login/view/main/form`.
- **Notfall:** Abschalten des Moduls in den Plugin-Einstellungen (auch per `sw:plugin:config:set`) hebt die Abfrage für alle auf, ohne Einrichtungen zu löschen; `sw5cs:two-factor:reset` entfernt sie für einen Benutzer.
- **Nicht erfasst:** REST-API (API-Schlüssel) und die erneute Passwortabfrage vor geschützten Aktionen (`Login/validatePassword`).
- **Passwortregeln:** `UserManager::updateUserAction()` erhält das neue Passwort im Klartext. Verstößt es gegen eine Regel, überspringt das Plugin die Aktion (`Enlight_Controller_Action_Backend_UserManager_updateUser`) und meldet die verletzten Regeln. `sw:admin:create` auf der Konsole wird nicht geprüft.

## 4. Querschnitt

- **Meldungen:** Textbausteine im Namespace `backend/sw5_community_security/messages` auf Deutsch und Englisch. Sie enthalten keine Smarty-Syntax (Platzhalter im Stil `[name]`). Text und eingesetzte Werte werden HTML-maskiert, auch wenn ein Administrator einen Textbaustein ändert; der Sicherheits-Check maskiert erst im Template.
- **Protokollierung:** plugin-eigener Logger `sw5_community_security.logger`, Stufe „Warning“, auch wenn Shopware im Produktivmodus sonst erst ab „Error“ protokolliert. Protokolliert werden nur Ort, Feld, Regel bzw. Dateiendung.
- **Konfiguration:** je Shop über Shopwares Konfigurations-Reader, typisiert in `PluginConfig` mit sicheren Standardwerten bei fehlenden oder ungültigen Werten. Das JavaScript-Escaping liest die Werte des Hauptshops (Abschnitt 3.4).
- **Cache:** Leerung bei Aktivieren, Deaktivieren und Update; bei Deinstallation nur, wenn das Plugin aktiv war; beim Speichern der Einstellungen im Backend automatisch (Konfigurations-, HTTP- und Template-Cache).

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
| Integrationstest | `bin/integration-test.sh`: echte Shopware-Installation 5.7.19 und 5.7.20 × PHP 8.1 und 8.2 sowie 5.6.10 × PHP 7.4, Installation aus dem Release-ZIP, Backend-Anfragen, Doctrine, Scan, Logs, gerenderte Templates und E-Mails, Deinstallation | CI |
| Abhängigkeiten | `composer audit` | CI |
| XML | Validierung gegen Shopwares XSD-Schemas | CI |

Tests arbeiten nur mit harmlosen Markern, die prüfen, ob eine Schutzmaßnahme greift. Funktionierende Exploits sind nicht Teil des Repositorys.

## 7. Grenzen

- Das Plugin blockiert gezielt bekannte, dokumentierte Angriffswege. Es ersetzt weder Updates von PHP, Server und Plugins noch eine individuelle Sicherheitsprüfung.
- Getestet wird gegen Standardinstallationen. Kombinationen mit individuellen Themes und Drittanbieter-Plugins lassen sich nicht vollständig testen (README, „Grenzen der Tests“).
- Lücken, die nicht über die genannten Erweiterungspunkte erreichbar sind, lassen sich nur im Kern schließen; dafür ist ein separates Projekt vorgesehen (ROADMAP, Abschnitt 4).
