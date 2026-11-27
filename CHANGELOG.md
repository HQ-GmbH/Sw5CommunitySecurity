# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung folgt [Semantic Versioning](https://semver.org/lang/de/).
Die Plugin-Version in `plugin.xml` und der Eintrag hier werden bei jedem Release gemeinsam gepflegt;
jedes Release erhält einen Git-Tag `vX.Y.Z`. Weiterentwicklung erfolgt in Patch-Schritten (0.1.1, 0.1.2, …).

## [Unreleased]

## [0.4.7] - 2026-11-27

### Sicherheit
- **Request-Härtung. Wo:** Anfrage-Header und Adresse der Anfrage (`$_SERVER`), die Vorlagen über `$smarty.server` und Plugins über `getHeader()` bzw. `getServer()` lesen. **Was:** Werte mit gesperrten Smarty-Konstrukten wurden dort nicht geprüft. Die Request-Härtung leert sie jetzt, wie Shopwares eigener Eingabefilter es für seine Muster tut, und entfernt betroffene Header auch aus den Header-Daten der Anfrage. Das Protokoll nennt nur den Namen des Eintrags (z. B. `server.HTTP_USER_AGENT`), nie den Wert.

### Tests
- Integrationstest: Ein Textbaustein gibt einen Anfrage-Header über `$smarty.server` aus; normale Werte erscheinen, Werte mit gesperrten Konstrukten nicht (ohne die Änderung schlägt der Test fehl). Eine Adresse mit unkodierten Klammern liefert weiterhin eine normale Seite.

## [0.4.6] - 2026-11-27

### Sicherheit
- **Request-Härtung. Wo:** Vorlagen und Plugins im Shop, die Anfragewerte über `$smarty.request` bzw. `$_REQUEST` lesen. **Was:** Werte mit gesperrten Smarty-Konstrukten wurden aus Query, Post, Cookies und Routenparametern entfernt, blieben dort aber noch lesbar. Die Request-Härtung bereinigt jetzt auch `$_REQUEST`, wie Shopwares eigener Eingabefilter. `$_SERVER` bleibt unverändert, weil das Entfernen ganzer Werte wie der Anfrage-URL die Erzeugung von Links stören würde.

### Tests
- Integrationstest: Ein Textbaustein gibt `$smarty.request.sSearch` aus; normale Suchbegriffe erscheinen, Werte mit gesperrten Konstrukten nicht (ohne die Änderung schlägt der Test fehl).
- Auf einem echten Shop (Shopware 5.7.15) geprüft: Sicherheits-Check (21 Prüfpunkte), Sicherheits-Header, Request-Härtung und Backend-Module (Bestellungen, Kunden, Artikel, Benutzer, Protokoll, Bewertungen, Kategorien, Textbausteine, Artikel mit Editor) ohne Fehler und ohne doppelt kodierte Zeichen.

## [0.4.5] - 2026-11-27

### Behoben
- **PHP-Patchstand bei Debian- und Ubuntu-Paketen** (F12). Diese Distributionen behalten die Versionsnummer bei (z. B. 8.3.6 unter Ubuntu 24.04) und liefern Sicherheitskorrekturen als Paket-Updates nach. 0.4.4 meldete solche Installationen deshalb als veraltet, auch wenn alle Korrekturen eingespielt waren. Der Prüfpunkt liest jetzt das Changelog des Pakets (`/usr/share/doc/php<Zweig>-common/changelog.Debian.gz`) und sucht darin die kritischen Lücken des Zweigs:
  - alle genannt: OK, mit Paketversion und dem Hinweis, PHP-FPM nach Paket-Updates neu zu starten;
  - einzelne fehlen: Warnung mit den fehlenden CVEs (eine Distribution kann ein Paket auch als nicht betroffen einstufen, daher keine Fehlermeldung).
  - Ist das Changelog nicht lesbar (z. B. wegen `open_basedir`), gilt die Bewertung nach Versionsnummer; die Fehlermeldung weist dann auf zurückportierte Korrekturen hin.
  - Gelesen wird nur diese eine Datei unter festem Pfad; die Prüfung sendet nichts nach außen.

### Tests
- Gegen ein echtes Ubuntu-24.04-System mit PHP 8.3.6 geprüft: Das Paket-Changelog nennt alle kritischen Korrekturen vom 21.10. und 19.11.2026; der Prüfpunkt meldet OK statt Fehler.

## [0.4.4] - 2026-11-27

### Hinzugefügt
- **Sicherheits-Check: Prüfpunkt „PHP-Patchstand“** (F12, jetzt 21 Prüfpunkte). Er vergleicht die laufende PHP-Version mit der ersten Version ihres Zweigs, die alle bis 19.11.2026 in den [Sicherheits-Advisories von PHP](https://github.com/php/php-src/security/advisories) veröffentlichten Korrekturen enthält: 8.2.36, 8.3.37, 8.4.28 und 8.5.13. Darunter fallen kritische Lücken mit möglicher Ausführung von Code aus der Ferne, u. a. CVE-2026-109618 und CVE-2026-109619 (behoben in 8.2.35) sowie CVE-2026-115560 (Upload-Fortschritt der Session, behoben in 8.2.36).
  - Ältere Version: Fehler mit Angabe der Mindestversion.
  - Ältere Version mit Versionszusatz, etwa bei Paketen einer Distribution, die Korrekturen zurückportieren können: Warnung mit der Empfehlung, den Stand vom Hoster schriftlich bestätigen zu lassen.
  - PHP 8.1 und älter erhalten keine Korrekturen mehr; der Punkt verweist dann auf „PHP-Version“.
  - Die Daten sind wie die Supportdaten im Plugin hinterlegt; die Prüfung sendet nichts nach außen. Später veröffentlichte Korrekturen kennt erst eine neue Plugin-Version.

### Dokumentation
- **CVE-2026-62992 eingeordnet** (Smarty, Symlinks aus vertrauenswürdigen Verzeichnissen): Shopware 5 ist nicht betroffen, weil Smarty 3.1.12 Pfade vor der Prüfung mit `realpath()` auf ihr tatsächliches Ziel auflöst. Begründung und Fundstellen in der [ROADMAP](./ROADMAP.md), Abschnitt 2.1.
- ROADMAP 1.4 und F12 gegen die PHP-Advisories geprüft: CVE-2026-109618 und CVE-2026-109619 wurden am 21.10.2026 veröffentlicht, CVE-2026-115560 erst am 19.11.2026 zusammen mit weiteren Lücken.

## [0.4.3] - 2026-11-27

### Behoben
- **Update auf 0.4.2 über den Plugin-Manager sperrte das Backend.** Beim Hochladen einer neuen Version führt Shopware deren Code schon aus, bevor es den Update-Schritt des Plugins aufruft. 0.4.2 las dabei die neue Spalte der Zwei-Faktor-Tabelle, die erst der Update-Schritt anlegt. Die Prüfung schlug fehl und meldete, wie bei Fehlern vorgesehen, die Backend-Sitzung ab, auch die des Administrators, der das Update ausführte. Der Update-Schritt lief nicht mehr, und danach war keine Anmeldung im Backend möglich. Das Plugin legt Tabelle und Spalte jetzt beim ersten Zugriff an, falls sie fehlen. Ein Update über die Konsole (`sw:plugin:update`) war nicht betroffen.
- **Wiederherstellung:** Shops, die 0.4.2 über den Plugin-Manager eingespielt haben und sich nicht mehr anmelden können, führen auf der Konsole im Shop-Verzeichnis `php bin/console sw:plugin:update Sw5CommunitySecurity` und `php bin/console sw:cache:clear` aus (als Benutzer des Webservers). Danach ist die Anmeldung wieder möglich; anschließend 0.4.3 einspielen.
- **Hinweis zum Update:** Backend-Benutzer, die selbst einen zweiten Faktor eingerichtet haben, werden beim Update von einer Version vor 0.4.2 einmal abgemeldet (seit 0.4.2 ist der zweite Faktor ein Zustand der Sitzung). Nach erneuter Anmeldung mit Code lässt sich ein unterbrochenes Update im Plugin-Manager erneut starten.

### Tests
- Lokal geprüft: Update von 0.4.1 über den Plugin-Manager (Dateien ersetzen, Plugin-Liste aktualisieren, Update im Backend) und anschließender Login; mit 0.4.2 nachgestellt (Abmeldung, Update scheitert), mit 0.4.3 erfolgreich. Update über die Konsole mit vollständigem Integrationstest auf der aktualisierten Installation.

## [0.4.2] - 2026-11-27

### Sicherheit
- **Selbst gefundene Lücken in eigenen Schutzfunktionen geschlossen** (F21). Bei einer vollständigen Prüfung des Plugins haben wir Stellen gefunden, an denen Schutzfunktionen des Plugins umgangen werden konnten oder nicht alle Speicherwege erfassten. Wir haben sie geschlossen; Shopware-5-Shops mit älteren Versionen des Plugins sollten aktualisieren.
  - **Zwei-Faktor-Anmeldung im Backend. Wo:** Login des Backends. **Was:** Unter bestimmten Umständen genügte für ein Konto mit eingerichtetem zweiten Faktor das Passwort. Der zweite Faktor ist jetzt ein Zustand der Backend-Sitzung: Vor jeder Backend-Anfrage wird eine Anmeldung ohne geprüften Code wieder entfernt, gleich auf welchem Weg sie entstanden ist. Bestehende Sitzungen von Benutzern mit zweitem Faktor werden nach dem Update einmal abgemeldet.
  - **Sperre nach Fehlversuchen. Wo:** Zwei-Faktor-Anmeldung und Abschalten der eigenen Einrichtung. **Was:** Die Sperre ließ sich verkürzen, galt nicht beim Abschalten und war bei parallelen Anfragen nicht verlässlich; ein Code konnte bei parallelen Anfragen mehrfach gelten. Das Plugin führt die Sperre jetzt selbst (unabhängig von Shopwares Sperre), prüft sie vor jedem Code und vergibt Codes atomar nur einmal. Eine bestehende Einrichtung lässt sich aus einer Sitzung nicht mehr durch eine neue ersetzen.
  - **Template-Guard. Wo:** Prüfregeln für Smarty sowie Speicherwege für Textbausteine (Import), Übersetzungen (Backend, REST-API, Import der Einkaufswelten), E-Mail an Händler im Dashboard und SEO-URL-Vorlagen der Inhaltstypen. **Was:** Bestimmte Schreibweisen gesperrter Smarty-Konstrukte wurden nicht erkannt, und einige Speicherwege wurden nicht geprüft; abgelehnte Konstrukte konnten so gespeichert und ausgeführt werden (Rechte eines Backend- bzw. API-Benutzers nötig). Die Regeln erkennen jetzt alle bekannten Schreibweisen, `{insert}` ist gesperrt, und alle genannten Wege werden vor dem Speichern geprüft. Die Request-Härtung im Shop nutzt dieselben Regeln.
  - **Inhaltsprüfung. Wo:** Prüfregeln für Editor-Inhalte sowie Freitextfelder im Backend, Mehrfachänderung von Artikeln und Import von Übersetzungen der Einkaufswelten. **Was:** Bestimmte Schreibweisen von Skript in HTML wurden nicht erkannt und einige Speicherwege nicht geprüft (Stored Cross-Site-Scripting, Rechte eines Backend- bzw. API-Benutzers nötig). Inhalte, die sich nicht vollständig auswerten lassen, werden jetzt abgelehnt. Zugleich lehnt die Prüfung gewöhnliche Inhalte nicht mehr ab, etwa Links auf Dateien, deren Name mit „javascript“ beginnt; der Editor entfernt solche Adressen nicht mehr.
  - **Upload-Härtung. Wo:** Namensprüfung und Ersetzen von Mediendateien über die REST-API. **Was:** Bestimmte Dateinamen wurden nicht als gesperrt erkannt, und beim Ersetzen einer Mediendatei fehlte die Inhaltsprüfung. Das Protokoll nennt nur noch gesperrte Endungen, keine weiteren Teile des Dateinamens.
  - **Ausgaben im Backend (F20). Wo:** Bäume (z. B. Kategorien), Spalte „Streams“ der Kundenstream-Vorschau, Detailansicht des E-Mail-Logs ohne das zentrale Skript. **Was:** Gespeicherte Werte wurden dort noch als HTML ausgegeben (Stored Cross-Site-Scripting). E-Mail-Adressen in der Kundenstream-Vorschau werden nicht mehr doppelt kodiert.
  - **JavaScript-Escaping (F4):** Als Zeichenkette `Klasse::methode` registrierte `escape`-Funktionen anderer Plugins werden jetzt ebenfalls ergänzt.

### Geändert
- **Gespeicherte Inhalte und Updates:** Template-Guard und Inhaltsprüfung prüfen bei Änderungen über Doctrine nur noch die geänderten Felder. Bestehende Inhalte blockieren damit keine anderen Änderungen mehr, etwa den Versand einer E-Mail mit einer älteren, angepassten Vorlage; der Scan zeigt solche Inhalte weiterhin an. Übersetzungen werden unabhängig von ihrem Typ geprüft.
- **Abschalten der Zwei-Faktor-Anmeldung fällt auf:** Der Sicherheits-Check hat den neuen Prüfpunkt „Modul: Zwei-Faktor-Anmeldung“ (jetzt 20 Prüfpunkte) und warnt, wenn das Modul ausgeschaltet ist, obwohl Backend-Benutzer einen zweiten Faktor eingerichtet haben. Das Plugin-Protokoll vermerkt, wenn das Modul in den Plugin-Einstellungen ausgeschaltet wird, und jeden Login eines eingerichteten Benutzers ohne Code-Prüfung, solange es aus ist (auch nach Abschalten per Konsole). Der Schalter bleibt als Notfallschalter erhalten.
- **Sicherheits-Check:** meldet einen Hinweis statt „OK“, wenn der Scan der Plugin-Verzeichnisse auf den Smarty-Ausgabe-Cache unvollständig war (nicht lesbare Ordner, Dateigrenze), und folgt per Symlink eingebundenen Plugins. Zu weit gefasste Einträge der API-Adressliste (z. B. `/0`) gelten nicht mehr als Einschränkung. IPv4-Adressen in IPv6-Form (`::ffff:…`) passen auf IPv4-Einträge.
- **Template-Guard:** Nicht in Anführungszeichen gesetzte `{block}`-/`{function}`-Namen mit Zeichen wie `.` oder `-` gelten als Verstoß, weil Smarty sie als Ausdruck auswertet; in Anführungszeichen bleiben sie erlaubt. Sehr große oder ungewöhnlich verschachtelte Vorlagen werden wie übergroße Importe abgelehnt (`too-large`).
- **Kunden-Sessions:** `SameSite` des Session-Cookies wird über die Session-API von PHP gesetzt.
- Konsolenbefehle und Scans geben ihre Texte über Textbausteine (Deutsch/Englisch) aus.
- Modullisten in `plugin.xml`, `composer.json` und README vervollständigt (Inhaltsprüfung, Request-Härtung).
- Ausgaben im Backend: Die Absicherung umhüllt jetzt auch in den Mail-Ansichten, der Kundenliste der Bestellungen und dem Titel der Kundendetails die Originalfunktionen, statt sie nachzubilden.

### Tests
- Unit-Tests für alle geänderten Prüfregeln (u. a. alternative Schreibweisen, tiefe Verschachtelung, Fehlalarme), Sperre und Einmal-Codes der Zwei-Faktor-Anmeldung, Snippet-Import und Skripte der Backend-Ausgaben.
- Integrationstest: Login ohne Code auf anderen Wegen, unterbrochener Login, Sperre gegen weitere Versuche, Ersetzen einer Einrichtung, Konsole-Reset mit vorheriger Einrichtung, Recht `twofactorreset`, weitere Speicherwege von Template-Guard und Inhaltsprüfung.

## [0.4.1] - 2026-11-26

### Sicherheit
- **Weitere Stellen von F20 geschlossen** (selbst gefunden, Cross-Site-Scripting im Backend): **Wo:** in Textspalten von Listen im gesamten Backend, die keine eigene Darstellung haben, etwa im Protokoll (Benutzer, Modul, Text) und in der Benutzer- und Rollenverwaltung (Name, Beschreibung), außerdem in der Liste der Kundenstreams (Name und Beschreibung im Tooltip) und bei Name und E-Mail-Adresse in der Benutzerliste. **Was:** Stored Cross-Site-Scripting. Bestimmte gespeicherte Werte wurden dort als HTML ausgegeben und liefen im Browser des Backend-Benutzers mit dessen Rechten. Das Plugin gibt diese Zellen jetzt als Text aus, zentral für alle Listen und Bäume ohne eigene Darstellung, damit auch für Listen anderer Plugins. Spalten mit eigener Darstellung oder ausdrücklich erlaubtem HTML bleiben unverändert; die sichtbare Ausgabe normaler Werte ändert sich nicht.
- Tests: Unit-Test des Skripts; Integrationstest, dass die Startseite des Backends die Absicherung der Listen ausliefert; im Browser geprüft mit gespeicherten Markern in Protokoll, Benutzern, Rollen und Kundenstreams (ohne Plugin 7 als HTML, mit Plugin 0) sowie in Bestellungen, Kunden, Artikeln, Kategorien, Zahlungs- und Versandarten, Plugin-Manager und Textbausteinen ohne Fehler.

## [0.4.0] - 2026-11-26

### Sicherheit
- **Selbst gefundene Sicherheitslücke geschlossen** (F20, Cross-Site-Scripting im Backend): Bei einer Prüfung aller Backend-Ansichten, die gespeicherte Daten anzeigen, haben wir weitere Stellen in Shopware 5 gefunden, an denen Daten als HTML statt als Text ausgegeben wurden. **Wo:** im Backend in den Ansichten Kunden (Liste, Kundendetails mit Adressen, Kundenstreams, Kundenauswahl), Bestellungen (Liste, Details, Positionen, abgebrochene Bestellungen), Bewertungen, Blog-Kommentare, Statistiken (Suchbegriffe, Referer), Benachrichtigungen, Newsletter-Empfänger, Gutscheincodes und Händler-Freischaltung, dazu Anzeigefelder, Listen und Info-Bereiche des Backend-Frameworks. **Was:** Stored Cross-Site-Scripting. Skript in gespeicherten Daten, etwa in Namen, Adressen, Bestellangaben, Bewertungen oder Suchbegriffen, lief im Browser des Backend-Benutzers mit dessen Rechten, sobald er die Ansicht öffnete. Solche Daten konnten etwa über die REST-API, über Importe oder über andere Plugins gespeichert werden. Das Plugin schließt diese Stellen für Shopware 5.6.10 und 5.7.19+.
- **Umsetzung:** Ein Skript auf der Startseite des Backends kodiert die Ausgabe der betroffenen Ansichten, sobald Shopware sie lädt. Es umhüllt nur die vorhandenen Funktionen und ruft sie weiter auf; Spalten, Vorlagen und Funktionen der Module bleiben erhalten, auch für Plugins, die sie erweitern. Gespeicherte Daten bleiben unverändert. Wie die Absicherung der Mail-Daten (0.3.3) ist sie immer aktiv.
- Tests: Unit-Test des Skripts (Registrierung, nur Umhüllen ohne Überschreiben); Integrationstest, dass die Startseite des Backends die Absicherung ausliefert; im Browser geprüft mit gespeicherten Markern in Kunden, Bestellungen, Artikeln, Bewertungen, Blog und Statistiken (ohne Plugin als HTML, mit Plugin als Text, alle Module laden ohne Fehler).

## [0.3.9] - 2026-11-26

### Hinzugefügt
- **Modul „Request-Härtung“** (F19, standardmäßig aktiv, abschaltbar): Entfernt im Shop (Frontend und Widgets) aus Anfragen Werte mit Smarty-Konstrukten bekannter Sicherheitslücken (`{extends}`, `{fetch}`, `{php}`, ungültige `{function}`-/`{block}`-Namen, `$smarty.template_object`, Zugriff auf statische Klassen, `{include}` mit variablem Dateinamen), bevor Shopware oder Plugins sie verarbeiten. Geprüft werden Query, Post, Cookies und Routenparameter. Wie Shopwares eigener Eingabefilter wird der Wert geleert und die Anfrage läuft weiter; normale Eingaben, auch mit geschweiften Klammern oder JSON, bleiben unverändert. Hintergrund: Shopware kompiliert im Shop Fehlermeldungen der Formularprüfung als Smarty; ergänzt ein Plugin dort eine Prüfung, die den eingegebenen Wert in die Meldung setzt, würde Kundeneingabe als Smarty ausgewertet.
- **Sicherheits-Check „Eingabefilter von Shopware“:** warnt, wenn Shopwares Eingabefilter deaktiviert ist oder einzelne Schutzfunktionen (SQL, XSS, Dateipfade, `strip_tags`) abgeschaltet sind. Der Sicherheits-Check hat jetzt 19 Prüfpunkte.
- Tests: Unit-Tests der Request-Härtung (erlaubte Eingaben, entfernte Werte und Schlüssel, Tiefe) und der neuen Prüfpunkte; Integrationstest mit echter Shop-Suche (normale Eingabe bleibt, Smarty-Konstrukt wird entfernt, Protokoll ohne Wert, abgeschaltetes Modul).

## [0.3.8] - 2026-11-26

### Sicherheit
- **Editor im Backend entfernt Skript beim Laden** (F7): Inhalte, die nicht über Backend, REST-API oder Doctrine gespeichert wurden, etwa von Plugins direkt per SQL oder schon vor der Installation, werden jetzt beim Öffnen im Editor bereinigt (`<script>`, Event-Attribute, `javascript:`-Adressen, `srcdoc`, `<noscript>`). Ein Hinweis zeigt das an. Der gespeicherte Inhalt ändert sich erst beim Speichern, die Ausgabe im Shop bleibt unverändert; Plugins, die solche Inhalte schreiben, funktionieren also weiter.
- **Eigene `escape`-Funktionen anderer Plugins** (F4): Ist `escape` von einem Plugin als Funktion, statische Methode oder Objektmethode registriert, ruft der kompilierte Code die Funktion weiter unverändert auf und ergänzt nur bei `javascript` ihr Ergebnis um die Ersetzungen aus Smarty 3.1.48. Eigene Logik der Funktion bleibt erhalten. Für den Produktexport gilt das ebenso; das Ergebnis entspricht dort wie bisher der vollständigen Liste.
- Tests: Unit-Tests (alte Liste plus Ergänzung gleich vollständige Liste, geprüft mit 3.000 Zufallswerten; Funktion, statische Methode, Objektmethode); Integrationstest mit statisch registrierter `escape`-Methode und Auslieferung des Editor-Schutzes; im Browser geprüft mit per SQL gespeichertem Inhalt.

## [0.3.7] - 2026-11-26

### Sicherheit
- **Inhaltsprüfung für Übersetzungen über die REST-API** (F7): Übersetzungen schreibt auch die REST-API ohne Doctrine. Die Inhaltsprüfung prüft jetzt `/api/translations` sowie die Übersetzungen beim Speichern von Artikeln, Varianten und Kategorien und lehnt Skript mit Status 400 und Meldung ab.
- **JavaScript-Escaping im Produktexport** (F4): Der Produktexport bringt eine eigene `escape`-Funktion mit, deren Fall `javascript` die Ersetzungsliste aus Smarty 3.1.12 nutzt. Das Modul „JavaScript-Escaping“ leitet sie für `javascript` jetzt auf die vollständige Liste um; alle anderen Typen (z. B. `csv`, `xml`) bleiben unverändert.
- **JavaScript-Escaping in Nocache-Abschnitten** (F4): Ist der Smarty-Ausgabe-Cache eingeschaltet, legt Smarty nicht zwischengespeicherte Abschnitte als PHP-Zeichenkette ab. Die Härtung greift jetzt auch dort.
- Tests: Unit-Tests für Nocache-Abschnitte und die registrierte `escape`-Methode; Integrationstest mit Produktexport-Modifier, Nocache-Abschnitt bei eingeschaltetem Cache und REST-API-Übersetzungen.

## [0.3.6] - 2026-11-26

### Sicherheit
- **JavaScript-Escaping auch bei variablem Escape-Typ** (F4, [CVE-2023-28447](https://github.com/smarty-php/smarty/security/advisories/GHSA-7j98-h7fp-4vwj)): Steht der Escape-Typ erst zur Laufzeit fest (z. B. `{$wert|escape:$typ}`), ruft Smarty `smarty_modifier_escape()` auf, die noch die alte Ersetzungsliste aus Smarty 3.1.12 nutzt. Das Modul „JavaScript-Escaping“ ersetzt solche Aufrufe im kompilierten Code jetzt durch eine Funktion, die für `javascript` die vollständige Liste aus Smarty 3.1.48 anwendet und alle anderen Typen unverändert an Smarty weitergibt. Der kompilierte Code bleibt ohne Verweis auf das Plugin.

### Hinzugefügt
- **Sicherheits-Check „Smarty-Ausgabe-Cache“:** [CVE-2026-82531](https://www.cve.org/CVERecord?id=CVE-2026-82531) betrifft Smarty nur bei eingeschaltetem Ausgabe-Cache, den Shopware selbst nie einschaltet. Der Prüfpunkt warnt, wenn der Cache eingeschaltet ist oder Plugins ihn im Code einschalten, und nennt diese Plugins. Die Plugin-Verzeichnisse werden dafür nur gelesen (ohne `vendor`- und Test-Ordner).
- Tests: Unit-Tests für den Laufzeitpfad (Ersetzung, Weitergabe anderer Typen, Plugin-Header, Nocache-Abschnitte) und den Scanner; Integrationstest mit variablem Escape-Typ.

## [0.3.5] - 2026-11-26

### Sicherheit
- **Selbst gefundene Sicherheitslücke geschlossen** (F7, Cross-Site-Scripting im Backend-Editor): Bei der Prüfung von F7 haben wir eine Lücke in Shopware 5 gefunden. **Wo:** im Editor des Backends (TinyMCE), also überall, wo Inhalte mit dem Editor bearbeitet werden, etwa Shopseiten, Artikel-, Kategorie-, Hersteller- und Blogtexte, Formulare, Newsletter, Einkaufswelten und HTML-Freitextfelder. **Was:** Stored Cross-Site-Scripting. Skript in einem gespeicherten Inhalt wurde ausgeführt, sobald ein Backend-Benutzer den Inhalt im Editor öffnete, und lief dann mit dessen Rechten. Solche Inhalte konnten von Backend-Benutzern mit eingeschränkten Rechten, über die REST-API, über Importe oder über andere Plugins kommen. Das neue Modul „Inhaltsprüfung“ schließt die Lücke für Shopware 5.6.10 und 5.7.19+.

### Hinzugefügt
- **Modul „Inhaltsprüfung“** (standardmäßig aktiv, abschaltbar): Lehnt beim Speichern Inhalte ab, die im Backend mit dem Editor bearbeitet werden, wenn sie Skript enthalten: `<script>`, Event-Attribute (`on…`), `javascript:`-Adressen oder `srcdoc`. Geprüft werden Shopseiten, Artikel-, Kategorie-, Hersteller- und Blogtexte, Formulare, Antworten auf Bewertungen, Newsletter-Texte, Texte in Einkaufswelten, HTML-Freitextfelder und Übersetzungen, über das Backend, die REST-API und andere Plugins. Links, Bilder, Tabellen und eingebettete Videos bleiben erlaubt; gespeicherte Inhalte werden nicht verändert.
- **Konsolenbefehl `sw5cs:content-guard:scan`:** prüft bereits gespeicherte Editor-Inhalte, nur lesend. Er nennt Quelle, Datensatz, Feld und Regel, nie den Inhalt, und endet mit Exit-Code 1 bei Funden.
- **Sicherheits-Check:** Prüfpunkt „Modul: Inhaltsprüfung“.
- Im Modul *Shopseiten* zeigt das Backend eine Ablehnung jetzt als Meldung an; Shopware selbst zeigt dort Fehler beim Speichern nicht an.
- Tests: Unit-Tests der Prüfregeln (erlaubte und abgelehnte Inhalte, mXSS-Varianten); Integrationstest über Doctrine, Backend-Übersetzungen und REST-API, Scan, Protokoll ohne Inhalt, abgeschaltetes Modul.

## [0.3.4] - 2026-11-26

### Hinzugefügt
- **REST-API auf erlaubte Adressen beschränken** (F14): Die neue Einstellung „REST-API: erlaubte Adressen“ nimmt IP-Adressen und Bereiche (CIDR, IPv4 und IPv6) auf. Ist etwas eingetragen, beantwortet die REST-API Anfragen von anderen Adressen mit Status 403, auch mit gültigem API-Schlüssel. Ungültige Einträge erlauben nichts, damit ein Tippfehler die API nicht unbemerkt öffnet. Hinter einem Proxy zählt die Adresse laut `trustedproxies`. Abgelehnte Anfragen werden ohne Adresse protokolliert. Standard: leer, keine Beschränkung.
- **Sicherheits-Check „Zugang zur REST-API“:** warnt, wenn aktive Backend-Benutzer einen API-Schlüssel haben und die API von jeder Adresse aus erreichbar ist, und meldet ungültige Einträge als Fehler.
- Tests: Unit-Tests für Adressen und Bereiche, Konfiguration und Sicherheits-Check; Integrationstest mit echten API-Anfragen (offen, abgelehnt, erlaubt, ungültige Einträge, Protokoll ohne Adresse).

## [0.3.3] - 2026-11-26

### Sicherheit
- **Selbst gefundene Sicherheitslücke geschlossen** (F6, Cross-Site-Scripting im Backend): Bei der Untersuchung von F6 haben wir eine Lücke in Shopware 5 gefunden. **Wo:** im Backend unter *Einstellungen > E-Mail-Management*, in den Beispielvariablen der E-Mail-Vorlagen und in der Liste des E-Mail-Logs. **Was:** Stored Cross-Site-Scripting. Shopware speichert beim Mailversand die eingesetzten Daten, etwa Kundendaten, und gab sie dort ungeprüft als HTML aus. Skript darin lief im Browser des Backend-Benutzers mit dessen Rechten, sobald er die Vorlage oder das Log öffnete. Solche Daten konnten etwa über die REST-API, über Importe oder von Backend-Benutzern mit eingeschränkten Rechten kommen. Das Plugin schließt die Lücke für Shopware 5.6.10 und 5.7.19+. Das Backend zeigt gespeicherte Daten versendeter Mails an, etwa die Beispielvariablen in *Einstellungen > E-Mail-Management > E-Mail-Vorlagen* sowie Betreff, Absender und Empfänger im *E-Mail-Log*. Das Plugin gibt diese Werte jetzt als Text statt als HTML aus. Gespeicherte Daten und versendete Mails bleiben unverändert. Die Absicherung ist immer aktiv und erweitert die Ext-JS-Ansichten über die vorgesehenen Blöcke, ohne Core-Dateien zu ändern.
- Tests: Unit-Test der Erweiterungen; Integrationstest, dass das Backend die abgesicherten Ansichten ausliefert und nach dem Deaktivieren wieder die ursprünglichen.

### Hinzugefügt
- **Abgleich der SBOM mit der Schwachstellen-Datenbank [OSV](https://osv.dev):** `php bin/check-sbom-vulnerabilities.php <SBOM>` fragt für jede Komponente mit Package URL bekannte Sicherheitslücken ab und schlägt fehl, sobald eine gemeldet ist. Übertragen werden nur Paketname und Version. Die CI prüft bei jedem Lauf, ein eigener Workflow zusätzlich jeden Montag den Stand von `main`, weil neue Meldungen ohne Änderung am Code hinzukommen. Übernommene Code-Teile (Smarty-Listen) werden nicht abgefragt, da sie nicht die vollständige Bibliothek sind.

## [0.3.2] - 2026-11-26

### Hinzugefügt
- **Software-Stückliste (SBOM):** Jedes Release enthält zusätzlich zum ZIP die Datei `Sw5CommunitySecurity-<Version>.cdx.json` im Format CycloneDX 1.6 (JSON). Sie führt das Plugin, seine Composer-Abhängigkeit zur Laufzeit und alle mitgelieferten Teile Dritter mit Version, Lizenz und Herkunft auf, bei der QR-Code-Bibliothek auch mit Package URL und SHA-256. Damit lassen sich die enthaltenen Komponenten mit üblichen Werkzeugen auf bekannte Sicherheitslücken prüfen.
- Erzeugt wird sie mit `php bin/build-sbom.php` über das [CycloneDX-Composer-Plugin](https://github.com/CycloneDX/cyclonedx-php-composer) (Apache-2.0, nur Entwicklungsabhängigkeit, nicht im ZIP). Das Skript prüft gegen das CycloneDX-Schema, gleicht die SHA-256-Werte der mitgelieferten Dateien ab und bricht ab, wenn ein Eintrag aus THIRD-PARTY-NOTICES.md fehlt. Die CI erzeugt die SBOM bei jedem Lauf.

## [0.3.1] - 2026-11-26

### Hinzugefügt
- **QR-Code bei der Einrichtung der Zwei-Faktor-Anmeldung** (F14): Die Einrichtungsseite zeigt zusätzlich zum Schlüssel einen QR-Code, den die Authenticator-App scannen kann. Er wird im Browser erzeugt; der Schlüssel verlässt den Rechner nicht, und es gibt keine Verbindung nach außen. Ohne JavaScript bleibt die Eingabe des Schlüssels möglich.
- Mitgeliefert wird dafür die Bibliothek [qrcode-generator](https://github.com/kazuhikoarase/qrcode-generator) 2.0.4 von Kazuhiko Arase unter der MIT-Lizenz, unverändert und mit Lizenztext; Eintrag in THIRD-PARTY-NOTICES.md.
- Tests: Prüfung von Herkunft und Lizenzangaben der Bibliothek; Integrationstest, dass die Einrichtungsseite den QR-Code anbietet und beide Skripte aus dem Shop geladen werden.

## [0.3.0] - 2026-11-25

### Hinzugefügt
- **Zwei-Faktor-Anmeldung für Backend-Konten** (F14, TOTP nach RFC 6238):
  - Jeder Backend-Benutzer richtet sie unter *Einstellungen > Zwei-Faktor-Anmeldung* für das eigene Konto ein: Schlüssel in die Authenticator-App übernehmen und mit einem Code bestätigen. Ausschalten erfordert einen gültigen Code.
  - Danach verlangt der Login zusätzlich den sechsstelligen Code; das Feld im Login-Formular ist für Benutzer ohne Einrichtung optional.
  - Ein Code gilt nur einmal. Ab dem fünften falschen Code in Folge sperrt das Plugin das Konto über Shopwares eigene Sperre, 30 Sekunden je Fehlversuch, höchstens eine Stunde; das wird protokolliert.
  - Administratoren (ACL-Recht „twofactorreset“) sehen die Benutzer mit Zwei-Faktor-Anmeldung und können sie entfernen, etwa nach Verlust des Geräts. Auf der Konsole: `sw5cs:two-factor:reset <Benutzername>`.
  - Die Abfrage lässt sich in den Plugin-Einstellungen für alle abschalten, ohne die Einrichtungen zu löschen (`backendTwoFactorEnabled`). Die REST-API ist nicht betroffen.
- **Passwortregeln für Backend-Konten** (F14, standardmäßig aus): Neue Passwörter in der Benutzerverwaltung müssen eine Mindestlänge erreichen (Standard 12, einstellbar von 8 bis 64), drei von vier Zeichenarten enthalten und dürfen den Benutzernamen nicht enthalten.
- Tests: Unit-Tests mit den Testvektoren aus RFC 6238 und RFC 4648, für Wiederverwendung, Sperre, Ein- und Ausschalten sowie die Passwortregeln; Integrationstest für Einrichtung, Login mit und ohne Code, Sperre, Entfernen durch Administratoren und Konsole, Abschalten des Moduls und Passwortregeln.

## [0.2.2] - 2026-11-25

### Hinzugefügt
- **Kunden-Sessions** (F13), beide Optionen standardmäßig aus:
  - **Nur eine Anmeldung je Kundenkonto** (`customerSessionSingleLogin`): Nach einer neuen Anmeldung werden ältere Sitzungen desselben Kundenkontos beim nächsten Aufruf abgemeldet. Gespeichert wird je Kundenkonto nur der SHA-256-Hash eines Zufallstokens in der Tabelle `s_plugin_sw5cs_customer_login` (Fremdschlüssel auf `s_user`, wird mit dem Konto gelöscht; beim Deinstallieren entfernt, außer die Daten werden behalten). Sitzungen von vor dem Einschalten bleiben bis zu ihrem Ablauf gültig. Bei Datenbankfehlern bleibt die Sitzung gültig, damit nicht alle Kunden abgemeldet werden.
  - **SameSite für das Session-Cookie** (`customerSessionCookieSameSite`, je Shop): „Lax“ oder „Strict“, mit Warnhinweis zu Zahlungsanbietern, die per Formular in den Shop zurückkehren. Ein Wert in der `config.php` hat Vorrang.
- Tests: Unit-Tests für die Token-Logik und die neuen Einstellungen; Integrationstest mit echtem Kundenkonto (Anmeldung in zwei Sitzungen mit und ohne Option, SameSite „Lax“ und „Strict“, Tabelle bei Installation und Deinstallation).

## [0.2.1] - 2026-11-25

### Hinzugefügt
- **Schutzdateien wiederherstellen** (F10): Fehlende `.htaccess`-Dateien in `bin`, `engine/Library`, `files/backup`, `files/documents`, `media/temp`, `var/cache`, `var/log` und `vendor` werden neu angelegt – über die Schaltfläche im Sicherheits-Check, mit dem Konsolenbefehl `sw5cs:protection-files:restore` (Exit-Code 1, wenn eine Datei nicht angelegt werden kann) und automatisch beim Leeren des HTTP-Caches (Einstellung `protectionFilesAutoRestore`, standardmäßig aktiv). Die Regeln sperren wie im Shopware-Original jeden Zugriff, in `engine/Library` nur auf PHP-Dateien. Vorhandene Dateien werden nie verändert, symbolischen Links wird nicht gefolgt. Wiederhergestellte Dateien werden protokolliert (nur Pfade).
- Neues ACL-Recht „restore“ an der Ressource `sw5cssecuritycheck`; es wird bei Installation und Update angelegt. Administratoren haben es automatisch, andere Rollen nach Freigabe. Die Schaltfläche sendet das CSRF-Token der Backend-Sitzung.
- Tests: Unit-Tests für Erkennung, Wiederherstellung, Symlinks und fehlende Schreibrechte; Integrationstest für Sicherheits-Check (Konsole, Anmeldepflicht, Backend-Seite, ACL-Rechte) und für alle drei Wege der Wiederherstellung.

### Geändert
- Sicherheits-Check: Die Empfehlung bei fehlenden Schutzdateien verweist auf die Schaltfläche und den Konsolenbefehl.

## [0.2.0] - 2026-11-24

### Hinzugefügt
- **Sicherheits-Check** (F9–F12, F15): nur lesende Übersicht über sicherheitsrelevante Einstellungen mit Handlungsempfehlungen, als Backend-Seite (*Einstellungen > Sicherheits-Check*) und als Konsolenbefehl `sw5cs:security-check` (Exit-Code 1 bei Fehlern). Geprüft werden Shopware-Version, PHP-Supportende (Daten von php.net), `session.upload_progress.enabled`, `trustedproxies`, Weiterleitungs-Header von nicht eingetragenen Adressen, Hauptshops ohne Host, Template-Sandbox, Schutz-`.htaccess`, `recovery/install/` samt Sperrdatei, Captcha bei „Passwort vergessen“ (inkl. Schriftfarbe, ohne die Shopware das Captcha nicht prüft) und die Plugin-Module.
- Zugriff auf die Backend-Seite über die neue ACL-Ressource `sw5cssecuritycheck` (Recht „read“): Administratoren automatisch, andere Rollen nach Freigabe. Die Ressource wird beim Deinstallieren entfernt, außer die Daten werden behalten.
- Textbausteine DE/EN für alle Prüfungen; Unit-Tests für jede Bewertung; Integrationstest für Konsolenbefehl, Anmeldepflicht, Backend-Seite und ACL-Ressource.

### Geändert
- ROADMAP: Die geplante Host-Härtung (0.1.9) entfällt als eigenes Modul. Shopware und Symfony filtern nicht vertrauenswürdige Weiterleitungs-Header bereits; die verbleibenden Konfigurationsfehler prüft der Sicherheits-Check (F9).

## [0.1.8] - 2026-11-24

### Hinzugefügt
- Header-Modul: **Content-Security-Policy** mit den Modi „Nicht setzen“ (Standard), „Nur melden“ (`Content-Security-Policy-Report-Only`) und „Durchsetzen“. Mitgeliefert werden Ausgangs-Richtlinien für Frontend (Responsive-Theme) und Backend (Ext JS, mit `'unsafe-eval'`) sowie eine optionale Melde-Adresse (`report-uri`, nur HTTPS).
- Header-Modul: **Permissions-Policy**, standardmäßig `camera=(), microphone=(), usb=()`; Zahlung und Standort bleiben für Plugins nutzbar.
- Prüfung aller frei eingebbaren Header-Werte (`HeaderValueNormalizer`): nur druckbares ASCII, keine Zeilenumbrüche, keine Kommas in der CSP, keine doppelten Direktiven. Ungültige Werte werden nicht gesendet.
- Tests: Unit-Tests für Prüfung und Header-Auswahl, Abgleich der Standardwerte zwischen `config.xml` und `PluginConfig`, Integrationstest für beide CSP-Modi, die Melde-Adresse, ungültige Werte und die Permissions-Policy.

## [0.1.7] - 2026-11-24

### Hinzugefügt
- Neues Modul **„JavaScript-Escaping“** (F4, CVE-2023-28447): Der Smarty-Modifier `escape:'javascript'` maskiert zusätzlich `<!--`, `<s`/`<S`, Backticks und `${` wie Smarty 3.1.48. Umgesetzt als Smarty-Postfilter auf dem kompilierten Code, registriert über einen Compiler-Pass am Service `template`; gilt für Themes, E-Mails, Belege und Newsletter. Neue Einstellung `javaScriptEscapeEnabled` (standardmäßig aktiv, maßgeblich ist der Hauptshop). Nicht abgedeckt: variabler Escape-Typ und die eigene `escape`-Funktion des Produktexports.
- Integrationstest rendert `escape:'javascript'` über die Template-Engine und den E-Mail-String-Compiler, mit aktivem und abgeschaltetem Modul sowie nach dem Deaktivieren.
- THIRD-PARTY-NOTICES: Ersetzungslisten aus Smarty (LGPL-3.0-or-later) mit Lizenztext `LICENSES/LGPL-3.0.txt`, der auch im Release-ZIP enthalten ist.

### Geändert
- Beim Speichern der Einstellungen wird zusätzlich der Template-Cache geleert.
- Projektseite auf hqgmbh.de verlinkt: README (DE/EN), Link im Plugin-Manager (`plugin.xml`), `homepage` in `composer.json`.
- README „Kompatibilität“: Shopware 5.7.0–5.7.18 als „nicht getestet“ statt „nicht unterstützt“ gekennzeichnet, mit Begründung und Empfehlung für 5.7.20; 5.6.0–5.6.9 und älter als „nicht installierbar“ (Mindestversion 5.6.10 in `plugin.xml`).

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
