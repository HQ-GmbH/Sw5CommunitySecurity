# Architektur und Sicherheitsentscheidungen

Diese Datei beschreibt den Aufbau des Plugins und begründet die wichtigsten Sicherheitsentscheidungen. Sie ist für Mitwirkende, Prüfer und Shopbetreiber gedacht, die nachvollziehen wollen, wie das Plugin arbeitet und warum. Bekannte Befunde und Versionsplan stehen in [ROADMAP.md](../ROADMAP.md), veröffentlichte Änderungen in [CHANGELOG.md](../CHANGELOG.md), verbindliche Regeln in [CONTRIBUTING.md](../CONTRIBUTING.md).

*This file describes the plugin's structure and the reasoning behind its key security decisions. It is written in German; class names, events and file paths are given verbatim.*

Stand: Version 0.4.3 · Shopware 5.7.19/5.7.20 (PHP 8.1/8.2) und 5.6.10 (PHP 7.4)

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
  ApiAccess/       ApiAccessPolicy                        erlaubte Adressen der REST-API (IPv4/IPv6, CIDR)
  Config/          PluginConfig, PluginConfigProvider,    typisierte Konfiguration je Shop
                   DefaultShopConfigSource                 bzw. des Hauptshops
  SecurityHeaders/ HeaderPolicy, HeaderValueNormalizer     welche Header in welchem Modul, Prüfung der Freitext-Werte
  TemplateGuard/   TemplateInspector, TemplateGuard,       Prüfregeln für Smarty-Vorlagen,
                   GuardedSources, StoredTemplateScanner   geschützte Quellen, Scan
  RequestGuard/    RequestInspector                        Smarty-Konstrukte in Shop-Anfragen (Regeln des Template-Guards)
  ContentGuard/    HtmlInspector, ContentGuard,            Prüfregeln für Editor-Inhalte (HTML),
                   StoredContentScanner                    Scan
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
  ContentGuardSubscriber          Modul „Inhaltsprüfung“: Übersetzungen im Backend, Meldung bei Shopseiten
  ContentGuardDoctrineSubscriber  Modul „Inhaltsprüfung“: Doctrine (alle Entities, auch Freitextfelder)
  RequestGuardSubscriber          Modul „Request-Härtung“: Shop-Anfragen bereinigen
  UploadGuardSubscriber           Modul „Upload-Härtung“
  ConfigCacheSubscriber           Cache-Leerung beim Speichern der Einstellungen
  ProtectionFileSubscriber        Schutzdateien beim Leeren des HTTP-Caches wiederherstellen
  CustomerSessionSubscriber       Modul „Kunden-Sessions“
  BackendAccountSubscriber        Modul „Backend-Konten“: Code beim Login, Feld im Login-Formular, Passwortregeln
  BackendOutputSubscriber         Gespeicherte Daten im Backend als Text (Ext-JS-Erweiterungen, immer aktiv)
  ApiAccessSubscriber             Modul „Backend-Konten“: REST-API auf erlaubte Adressen beschränken
Controllers/Backend/
  Sw5csSecurityCheck              Backend-Modul „Sicherheits-Check“ (leichtgewichtiges Modul, HTML im iframe)
  Sw5csTwoFactor                  Backend-Modul „Zwei-Faktor-Anmeldung“ (Einrichtung je Benutzer)
Commands/
  TemplateGuardScanCommand        bin/console sw5cs:template-guard:scan
  ContentGuardScanCommand         bin/console sw5cs:content-guard:scan
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
- **Schutzschicht 1, Backend:** Listener auf `Enlight_Controller_Action_Backend_<Controller>_<action>` für Mail (inkl. `verifySmarty`), ProductFeed, Translation (alle Übersetzungstypen, weil die Datenbank den Typ ohne Rücksicht auf Groß-/Kleinschreibung und Akzente vergleicht), NewsletterManager, Payment, Config (`saveValues`, `saveForm`), Snippet (CSV-Import: jeder Wert in der Form, in der Shopware ihn speichert, also nach dem Dekodieren von HTML-Entities), Emotion (inkl. `importTranslations`), Widgets (`sendMailToMerchant`, kompiliert ohne Speichern) und ContentTypeManager (SEO-URL-Vorlage). Übersetzungen über die REST-API (`Api_Translations` sowie der Teil `translations` von Artikeln, Varianten und Kategorien) werden mit Status 400 abgelehnt. Gibt der Listener `true` zurück, überspringt Shopware die Aktion (`notifyUntil`). Es wird nichts gespeichert oder kompiliert; das Backend zeigt eine Meldung mit Feld und Regel.
- **Schutzschicht 2, Doctrine:** `prePersist`/`preUpdate` für die betroffenen Entities (u. a. `Mail`, `ProductFeed`, `Newsletter` samt Containern, `Document\Element`, `Payment`, `Country`, `Config\Value`, `Snippet`, `Emotion\Data`). Damit sind auch REST-API und andere Plugins abgedeckt. Bei `preUpdate` wird nur geprüft, wenn ein Vorlagenfeld im Change-Set steht; so blockieren bestehende Inhalte keine anderen Änderungen (Shopware schreibt die Mail-Entity z. B. bei jedem Versand mit dem Kontext neu).
- **Bestehende Inhalte:** `sw5cs:template-guard:scan` liest nur, ändert nichts und endet mit Exit-Code 1 bei Funden. Serialisierte Werte werden mit `allowed_classes => false` gelesen, damit beim Scan keine Objekte entstehen.
- **Entscheidungen:**
  - **Ablehnen beim Speichern statt Abfangen beim Rendern:** Beim Rendern ist es zu spät; die Vorlage ist bereits gespeichert und kann an vielen Stellen ausgeführt werden.
  - **Textbausteine werden geprüft**, weil Shopware Snippet-Werte vor dem Kompilieren in den Template-Quelltext einsetzt (`engine/Library/Enlight/Components/Snippet/Resource.php`).
  - **Strenge Prüfung von `{include}`/`{math}` abschaltbar**, weil individuelle Vorlagen diese Tags legitim nutzen können. Die Regel gegen statische Klassen bleibt immer aktiv, da sie keinen legitimen Anwendungsfall in gespeicherten Inhalten hat.
  - **Grenzen:** Verschachtelte Daten werden bis zur Tiefe 8 geprüft, tiefere gelten als Verstoß; Importdateien über 20 MB werden abgelehnt, statt ungeprüft durchgelassen.

### 3.2b Request-Härtung

- **Anlass (F19):** Shopware kompiliert im Shop Fehlermeldungen der Formularprüfung als Smarty (`Controllers/Frontend/Register.php:426`, `Account.php:606/672`). Setzt ein Plugin den eingegebenen Wert in eine solche Meldung ein, würde Kundeneingabe als Smarty ausgewertet. Auch Plugins, die Eingaben selbst per `string:` rendern, wären betroffen. Shopwares Eingabefilter (`Plugins/Default/Frontend/InputFilter`) entfernt HTML-Tags und prüft SQL-, XSS- und Pfadmuster, lässt geschweifte Klammern aber durch.
- **Umsetzung:** `RequestGuardSubscriber` läuft wie der Eingabefilter bei `Enlight_Controller_Front_RouteShutdown` (nach ihm) für die Module `frontend` und `widgets` und prüft Query, Post, Cookies und Routenparameter, auch Schlüssel, bis Tiefe 8. Geprüft wird mit dem `TemplateInspector` des Template-Guards (strenge Regeln): `{extends}`, `{fetch}`, `{php}`, ungültige `{function}`-/`{block}`-Namen, `$smarty.template_object`, statische Klassen, `{include}` mit variablem Dateinamen. Betroffene Werte werden wie beim Eingabefilter geleert (`null`), die Anfrage läuft weiter.
- **Verträglichkeit:** Ohne `{` wird nichts geprüft; einfache Variablen (`{$var}`), JSON, Texte mit Klammern und `{math}` mit fester Formel bleiben erlaubt. Backend und REST-API sind ausgenommen, weil dort Vorlagen bewusst bearbeitet werden (Template-Guard).
- **Protokoll:** Route, Parametername und Regel, nie der Wert; ist ein Schlüssel selbst betroffen, steht `[key]` statt des Namens.
- **Sicherheits-Check:** Prüfpunkt „Eingabefilter von Shopware“: Plugin aktiv, `sql_protection`, `xss_protection`, `rfi_protection` und `strip_tags` an (Werte des Hauptshops, sonst Standard).

### 3.2a Inhaltsprüfung

Shopware konfiguriert den Editor im Backend (TinyMCE 3.5) ohne Bereinigung und erlaubt ausdrücklich iframes, eingebettete Objekte und Event-Handler an Medien-Elementen (`themes/Backend/ExtJs/backend/base/component/Shopware.form.field.TinyMCE.js`). Die Inhaltsprüfung verhindert, dass Inhalte mit Skript gespeichert werden.

- **Prüfregeln (`HtmlInspector`):** `<script>`, Event-Attribute (`on…`), `javascript:`-, `vbscript:`- und HTML-`data:`-Adressen in URL-Attributen, `srcdoc`. Das HTML wird wie im Browser ausgewertet (`DOMDocument`, Entities aufgelöst, Steuer- und Leerzeichen im Schema ignoriert). Zusätzlich wird der Rohtext nach Tags mit Event-Handlern oder Skript-Adressen durchsucht, damit Markup, das Browser anders lesen als der Parser (z. B. in `<noscript>` oder Attributwerten), ebenfalls auffällt. Reiner Text ohne `<` wird nicht geparst.
- **Schutzschicht Doctrine (`ContentGuardDoctrineSubscriber`):** Doctrine-Subscriber (DI-Tag `doctrine.event_subscriber`, in 5.6.10 und 5.7.20 vorhanden) für `prePersist`/`preUpdate` aller Entities. Geprüft werden `Site::html`, `Article::descriptionLong`, `Category::cmsText`, `Supplier::description`, `Blog::description`, `Form::text`/`text2`, `Vote::answer`, Newsletter-Text, Einkaufswelten-Felder mit `x_type = tinymce` und Freitextfelder vom Typ `html` (`s_attribute_configuration`). Die Services werden erst beim ersten Speichern geladen, weil Doctrine den Subscriber zusammen mit dem Entity-Manager erzeugt.
- **Schutzschicht Backend (`ContentGuardSubscriber`):** Übersetzungen speichert Shopware per SQL, nicht über Doctrine; `Backend_Translation::saveTranslation` wird deshalb vor der Aktion geprüft (alle Werte; reiner Text besteht immer). Im Modul *Shopseiten* zeigt Shopware Fehler beim Speichern nicht an; eine Ext-JS-Erweiterung im Block `backend/site/app` zeigt die Ablehnung des Plugins. Weitere Wege ohne Doctrine werden ebenfalls vor der Aktion geprüft: Freitextfelder (`AttributeData::saveData`, `DataPersister` per DBAL), Mehrfachänderung von Artikeln (`ArticleList::createQueue`, DQL-Bulk-Update) und der Übersetzungsimport der Einkaufswelten. Die Doctrine-Schicht prüft bei Änderungen nur geänderte Felder und erkennt Attribut-Entities auch als Doctrine-Proxy. Lässt sich ein Inhalt nicht vollständig auswerten (Fehler des HTML-Parsers, z. B. bei sehr tiefer Verschachtelung), wird er abgelehnt.
- **Bestehende Inhalte:** `sw5cs:content-guard:scan` liest seitenweise (500 Datensätze, nur Zeilen mit `<`), ändert nichts und endet mit Exit-Code 1 bei Funden.
- **REST-API, Übersetzungen:** Auch die API schreibt Übersetzungen per SQL (`Components/Api/Resource/Translation.php`, `Article.php`, `Category.php`). Listener auf `Enlight_Controller_Action_Api_<Controller>_<post|put|batch>` prüfen vor der Aktion bei `Translations` alle gesendeten Werte, bei `Articles`, `Variants` und `Categories` den Teil `translations`; abgelehnt wird mit Status 400 und der Meldung.
- **Schutzschicht Editor (ab 0.3.8):** Für Inhalte, die nicht über diese Wege gespeichert wurden (Plugins mit eigenem SQL, Bestand vor der Installation), entfernt der Editor im Backend Skript beim Laden. Die Backend-Startseite bindet dazu im Block `backend/base/header/javascript` ein Skript ein, das `initComponent` von `Shopware.form.field.TinyMCE` umhüllt (das Original läuft unverändert zuerst; ein `Ext.define`-Override mit `callParent` funktioniert bei diesem Feld in Ext JS 4.1.1 nicht) und sich an TinyMCEs `onBeforeSetContent` hängt. Das HTML wird in einem inerten `DOMParser`-Dokument nach denselben Regeln bereinigt wie serverseitig, zusätzlich werden `<noscript>`-Elemente entfernt; unveränderter Inhalt wird nicht neu serialisiert. Ein Hinweis erscheint. Gespeichert wird der bereinigte Inhalt erst, wenn der Benutzer speichert; die Ausgabe im Shop bleibt unverändert.
- **Grenzen:** Der Scan zeigt Inhalte mit Skript, die noch nicht bearbeitet wurden.

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
- **Variabler Escape-Typ:** Steht der Escape-Typ oder ein weiterer Parameter als Variable im Template (z. B. `escape:$typ`), ruft Smarty zur Laufzeit `smarty_modifier_escape()` auf (`plugins/modifier.escape.php`, alte Liste). Ab 0.3.6 ersetzt der Postfilter diese Aufrufe im kompilierten Code durch eine sofort aufgerufene Closure mit derselben Signatur: für `javascript` `strtr()` mit der vollständigen Liste, sonst `call_user_func('smarty_modifier_escape', …)`. Aufrufe in Anführungszeichen (Plugin-Kopf des kompilierten Templates) bleiben unberührt; die Ersetzung ist idempotent.
- **Nocache-Abschnitte:** Bei eingeschaltetem Smarty-Ausgabe-Cache legt Smarty nicht zwischengespeicherte Abschnitte als PHP-Zeichenkette ab (`addcslashes(…, "'\\")`, `smarty_internal_templatecompilerbase.php`). Ab 0.3.7 werden diese Abschnitte getrennt behandelt und der Ersatzcode mit derselben Maskierung eingesetzt.
- **Registrierte `escape`-Funktionen (Produktexport, andere Plugins):** Der Produktexport registriert `escape` als Objektmethode (`Core/sExport.php`, `sEscapeString`, eigener `javascript`-Fall mit alter Liste), andere Plugins ggf. als Funktion oder statische Methode (als Array oder als Zeichenkette `Klasse::methode`). Smarty kompiliert einen direkten Aufruf (`…registered_plugins[Smarty::PLUGIN_MODIFIER]['escape'][0][0]->methode(…)`, `Klasse::methode(…)` bzw. `funktion(…)`). Der Postfilter liest die registrierte Funktion aus dem Template (Smarty übergibt es Postfiltern) und ersetzt genau diesen Aufruf durch eine Closure, die die registrierte Funktion unverändert aufruft und nur bei `javascript` ihr Ergebnis um die in 3.1.48 ergänzten Ersetzungen erweitert (`EXTRA_REPLACEMENTS`). Für ein Ergebnis der alten Liste ergibt das genau die vollständige Liste (per Unit-Test mit Zufallswerten geprüft); eigene Logik der Funktion bleibt erhalten.

### 3.5 Sicherheits-Check

- **Zweck:** Übersicht über Einstellungen, die das Plugin nicht selbst absichern kann, mit konkreter Handlungsempfehlung je Punkt. Die Prüfung ändert nichts und sendet nichts nach außen; auch die PHP-Supportdaten sind im Code hinterlegt (Quelle php.net, Stand im Code).
- **Aufbau:** `EnvironmentCollector` liest die Werte aus Shopware (Container-Parameter aus der `config.php`, Datenbank, Dateisystem, PHP-Einstellungen, aktuelle Anfrage). `SecurityCheck` bewertet sie ohne Shopware-Abhängigkeit und ist vollständig per Unit-Test abgedeckt. Status: OK, Hinweis, Warnung, Fehler.
- **Host und Proxys (F9):** Ein eigenes Modul für erlaubte Hosts und Weiterleitungs-Header entfällt. Ohne eingetragene `trustedproxies` wertet Symfony `X-Forwarded-*` nicht aus, bei aktivem HTTP-Cache entfernt `SubRequestHandler` sie vollständig, und im Frontend leitet der Router fremde Hosts auf den Shop-Host um. Geprüft werden deshalb die verbleibenden Konfigurationsfehler: zu weit gefasste `trustedproxies` (z. B. `0.0.0.0/0`, öffentliche IPv6-Bereiche unter /16, `REMOTE_ADDR` ab Symfony 4.4), Weiterleitungs-Header von nicht eingetragenen Adressen und Hauptshops ohne Host.
- **Smarty-Ausgabe-Cache (CVE-2026-82531):** Die Lücke setzt den Ausgabe-Cache voraus, den Shopware nie einschaltet. Geprüft werden der Wert `caching` des Template-Services und, nur lesend, der PHP-Code der Plugin-Verzeichnisse (`custom/plugins`, `custom/project`, `engine/Shopware/Plugins/Community` und `Local`, ohne `vendor`-, Test- und `node_modules`-Ordner, höchstens 20.000 Dateien) auf `setCaching()` mit eingeschaltetem Wert oder Zuweisungen an `->caching` (`SmartyCacheScanner`). Ein Fund ist ein Hinweis, das Plugin zu prüfen, kein Nachweis einer Lücke.
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

- **Zwei-Faktor-Anmeldung:** `Shopware_Controllers_Backend_Login::loginAction()` prüft Benutzername und Passwort und legt die Identität in der Backend-Sitzung ab. Danach (`PostDispatch_Backend_Login`) prüft das Plugin bei eingerichteten Benutzern den Code aus dem Login-Formular; ohne gültigen Code entfernt es die Identität wieder (`clearIdentity()`), und der Login antwortet wie ein fehlgeschlagener. Benutzer ohne Einrichtung sind nicht betroffen. Der zweite Faktor ist zusätzlich ein Zustand der Backend-Sitzung: Ein gültiger Code (oder ein Benutzer ohne Einrichtung) markiert die Sitzung mit der Benutzer-ID. Vor jeder Backend-Anfrage (`Enlight_Controller_Front_PreDispatch`, vor dem Auth-Plugin des Kerns) entfernt das Plugin die Identität eingerichteter Benutzer ohne diese Markierung. Damit hängt die Prüfung nicht an Aktionsnamen oder daran, dass die Login-Aktion vollständig durchläuft; ein neuer Login muss den Code immer erneut nachweisen.
- **TOTP:** eigene Implementierung nach RFC 6238/4226 (HMAC-SHA1, 30 Sekunden, 6 Ziffern, ±1 Zeitschritt), per Unit-Test gegen die Testvektoren aus RFC 6238 Anhang B geprüft. Der Schlüssel hat 160 Bit (Base32). Jeder Zeitschritt wird nur einmal akzeptiert (`last_used_step`).
- **Fehlversuche:** Der Auth-Adapter von Shopware setzt `failedlogins` bei richtigem Passwort auf 0, seine Sperre greift für den Code also nicht. Das Plugin zählt Code-Versuche deshalb selbst und sperrt ab dem fünften Versuch ohne gültigen Code in Folge, 30 Sekunden je Versuch, höchstens eine Stunde. Die Sperre steht in der eigenen Tabelle (`locked_until`) und wird vor jeder Prüfung eines Codes atomar gezählt und gesetzt (ein `UPDATE` mit Bedingung), auch beim Abschalten der eigenen Einrichtung; parallele Anfragen können so keine weiteren Codes prüfen, und Shopwares eigene Sperre kann sie nicht verkürzen. `s_core_auth.lockeduntil` wird zusätzlich gesetzt, damit das Login-Formular die Sperre anzeigt. Ein fehlender Code zählt nicht als Versuch. Auch die Einmalverwendung eines Zeitschritts ist ein bedingtes `UPDATE`.
- **Speicherung:** `s_plugin_sw5cs_backend_two_factor` (Fremdschlüssel auf `s_core_auth` mit `ON DELETE CASCADE`): Schlüssel, zuletzt genutzter Zeitschritt, Versuche, Ende der Sperre. Tabelle und Spalten werden bei Installation und Update angelegt und zusätzlich beim ersten Zugriff geprüft, weil Shopware beim Hochladen einer neuen Version im Plugin-Manager deren Code schon vor dem Update-Schritt ausführt. Eine bestehende Einrichtung wird nie überschrieben; sie muss erst mit gültigem Code abgeschaltet oder von einem Administrator entfernt werden. Wer Lesezugriff auf die Datenbank hat, kann den Schlüssel lesen, wie die Passwort-Hashes in derselben Datenbank.
- **Einrichtung:** leichtgewichtiges Backend-Modul `Sw5csTwoFactor` ohne eigene ACL-Ressource, damit jeder angemeldete Backend-Benutzer sein eigenes Konto absichern kann. Der neue Schlüssel liegt bis zur Bestätigung nur in der Backend-Sitzung. Alle ändernden Aktionen sind POST mit dem CSRF-Token der Backend-Sitzung. Das Entfernen bei anderen Benutzern erfordert das Recht „twofactorreset“ an der Ressource `sw5cssecuritycheck`. Die Seite zeigt den Schlüssel, die `otpauth://`-Adresse und einen QR-Code. Den QR-Code zeichnet `qr-code.js` im Browser als SVG mit der mitgelieferten Bibliothek `qrcode-generator` (MIT, siehe THIRD-PARTY-NOTICES.md); beide Skripte werden aus dem Plugin-Ordner geladen, nur während der Einrichtung, ohne Verbindung nach außen. Ohne JavaScript bleibt die Einrichtung per Schlüssel möglich.
- **Login-Formular:** Das Feld für den Code ergänzt ein Ext-JS-Override im Block `backend/login/view/main/form`.
- **Notfall:** Abschalten des Moduls in den Plugin-Einstellungen (auch per `sw:plugin:config:set`) hebt die Abfrage für alle auf, ohne Einrichtungen zu löschen. Damit das nicht unbemerkt bleibt, warnt der Sicherheits-Check, solange das Modul aus ist und Benutzer eingerichtet sind; das Protokoll vermerkt das Abschalten im Backend und jeden Login eines eingerichteten Benutzers ohne Code-Prüfung; `sw5cs:two-factor:reset` entfernt sie für einen Benutzer.
- **REST-API:** Die API prüft Benutzername und API-Schlüssel per HTTP Basic/Digest (`engine/Shopware/Plugins/Default/Core/RestApi/Bootstrap.php`, `onFrontPreDispatch`); einen zweiten Faktor sieht dieses Protokoll nicht vor. `ApiAccessSubscriber` leitet Anfragen von Adressen außerhalb der Einstellung `apiAllowedAddresses` in `Enlight_Controller_Front_PreDispatch` an den Fehler-Controller der API (`noAuth`) und setzt Status 403, unabhängig von den Zugangsdaten. Die Adresse liefert `Request::getClientIp()` (berücksichtigt `trustedproxies`). Die Liste wertet `ApiAccessPolicy` aus (ohne Shopware-Abhängigkeiten, IPv4/IPv6, CIDR); ist etwas eingetragen, erlauben nur gültige Einträge den Zugang. Der Sicherheits-Check zählt aktive Benutzer mit API-Schlüssel.
- **Nicht erfasst:** die erneute Passwortabfrage vor geschützten Aktionen (`Login/validatePassword`).
- **Passwortregeln:** `UserManager::updateUserAction()` erhält das neue Passwort im Klartext. Verstößt es gegen eine Regel, überspringt das Plugin die Aktion (`Enlight_Controller_Action_Backend_UserManager_updateUser`) und meldet die verletzten Regeln. `sw:admin:create` auf der Konsole wird nicht geprüft.

### 3.9 Gespeicherte Daten im Backend

- **Ziel:** Gespeicherte Daten versendeter Mails werden in den Backend-Ansichten „E-Mail-Vorlagen“ (Beispielvariablen) und „E-Mail-Log“ (Betreff, Absender, Empfänger) als Text ausgegeben.
- **Umsetzung:** `BackendOutputSubscriber` hängt bei `PostDispatchSecure_Backend_Mail` und `_MailLog` (Aktionen `index` und `load`) Ext-JS-Overrides an die Blöcke `backend/mail/view/main/info`, `backend/mail_log/view/list/mail_log` und `backend/mail_log/view/detail/form_mixin` an. Die Overrides rufen die Originale auf und kodieren deren Eingaben bzw. Platzhalter (`{value:htmlEncode}`, `Ext.String.htmlEncode`); in der Detailansicht des E-Mail-Logs `htmlEncode` zusammen mit `allowHtml`, weil Shopwares Override von `Ext.form.field.Display` den Wert sonst wieder dekodiert. Gleich in Shopware 5.6.10 und 5.7.20.
- **Nicht verändert:** gespeicherte Daten, versendete Mails, die Autovervollständigung im Vorlagen-Editor (kodiert bereits selbst) und die Inhaltsansicht des E-Mail-Logs (bereits in einem `sandbox`-iframe).
- **Weitere Ansichten (ab 0.4.0):** `BackendOutputSubscriber` hängt bei `PostDispatchSecure_Backend_Index` an den Block `backend/base/header/javascript` der Backend-Startseite ein Skript an (`backend/sw5cs_backend_output/index/header.tpl`, nur für angemeldete Benutzer). Es wartet mit `Ext.ClassManager.onCreated` auf die nachgeladenen Klassen der Module und umhüllt deren Prototyp-Methoden (Renderer, `getColumns`/`createColumns`, Vorlagen-Methoden); das Original wird weiter aufgerufen, nur das Ergebnis bzw. die übergebenen Werte werden kodiert. Vorlagen (`Ext.XTemplate`) erhalten an jedem Platzhalter `:htmlEncode`, `data-qtip`-Attribute werden doppelt kodiert, weil Ext JS den Tooltip-Text als HTML ausgibt. Global umhüllt werden `Ext.form.field.Display.getDisplayValue` (außer bei `allowHtml`), `Shopware.grid.Panel.createColumn` (Textspalten ohne eigenen Renderer) und `associationColumnRenderer` sowie `Shopware.listing.InfoPanel.createTemplateForField`. Bewusst kein `Ext.define`-Override mit `callParent`: Das bricht bei Klassen, die selbst Overrides haben; ein Unit-Test stellt sicher, dass das Skript nur umhüllt.
- **Listen (ab 0.4.1):** Shopware bereitet Zellen in `Ext.grid.header.Container::prepareData` auf (`engine/Library/ExtJs/overrides/Ext.grid.header.Container.js`, gleich in 5.6.10 und 5.7.20). Das Skript umhüllt diese Methode und kodiert danach die Zellen von Spalten ohne eigenen Renderer und ohne `allowHtml`; leere Zellen (`emptyCellText`) bleiben. Das gilt für alle Listen, auch anderer Plugins. Bäume setzen immer einen eigenen Renderer (`Ext.tree.Column::treeRenderer`); das Skript umhüllt ihn und kodiert den Wert, den er an den Renderer der Spalte weitergibt. Spalten mit Renderer werden wie oben einzeln abgesichert (zusätzlich Kundenstream-Liste mit doppelt kodiertem Tooltip, Benutzerliste).
- **Immer aktiv:** Die Änderung betrifft nur die Darstellung; ein Schalter würde nur die unsichere Ausgabe wiederherstellen.

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
| Software-Stückliste | `bin/build-sbom.php`: CycloneDX 1.6 (JSON) mit Composer-Abhängigkeiten und mitgelieferten Teilen Dritter, Schemaprüfung, Abgleich mit THIRD-PARTY-NOTICES.md und den SHA-256-Werten; Anhang jedes Releases | CI, Release |
| Bekannte Lücken in Komponenten | `bin/check-sbom-vulnerabilities.php`: Package URLs der SBOM gegen [OSV](https://osv.dev) (nur Name und Version werden übertragen) | CI, wöchentlich (`sbom-audit.yml`) |
| XML | Validierung gegen Shopwares XSD-Schemas | CI |

Tests arbeiten nur mit harmlosen Markern, die prüfen, ob eine Schutzmaßnahme greift. Funktionierende Exploits sind nicht Teil des Repositorys.

## 7. Grenzen

- Das Plugin blockiert gezielt bekannte, dokumentierte Angriffswege. Es ersetzt weder Updates von PHP, Server und Plugins noch eine individuelle Sicherheitsprüfung.
- Getestet wird gegen Standardinstallationen. Kombinationen mit individuellen Themes und Drittanbieter-Plugins lassen sich nicht vollständig testen (README, „Grenzen der Tests“).
- Lücken, die nicht über die genannten Erweiterungspunkte erreichbar sind, lassen sich nur im Kern schließen; dafür ist ein separates Projekt vorgesehen (ROADMAP, Abschnitt 4).
