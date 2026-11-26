# Third-Party Notices

Diese Datei listet Code und Inhalte Dritter, die in diesem Projekt enthalten sind, mit Herkunft und Lizenz. Die Regeln dafür stehen in [CONTRIBUTING.md](./CONTRIBUTING.md), Abschnitt 6.2.

*This file lists third-party code and content included in this project, with origin and license. See [CONTRIBUTING.md](./CONTRIBUTING.md), section 6.2.*

## Im Plugin enthalten / Included in the plugin

Bis auf die folgenden Einträge besteht das Plugin (Release-ZIP) aus eigenem Code unter GPL-3.0-only. LGPL-3.0-or-later und MIT sind mit GPL-3.0 vereinbar (CONTRIBUTING.md, Abschnitt 6.2). Das Plugin als Ganzes steht unter GPL-3.0-only; die enthaltenen Teile behalten ihre eigene Lizenz.

*Apart from the entries below, the plugin (release ZIP) consists of original code under GPL-3.0-only. LGPL-3.0-or-later and MIT are compatible with GPL-3.0 (CONTRIBUTING.md, section 6.2). The plugin as a whole is licensed under GPL-3.0-only; the included parts keep their own license.*

| Komponente / Component | Herkunft / Origin | Version | Lizenz / License (SPDX) | Dateien / Files |
|---|---|---|---|---|
| Ersetzungslisten von `escape:'javascript'` / replacement lists of `escape:'javascript'` | [Smarty](https://github.com/smarty-php/smarty), `libs/plugins/modifiercompiler.escape.php` (Autor/author Rodney Rehm; © New Digital Group, Inc., Uwe Tews): die von 3.1.12 erzeugte Liste (zum Wiedererkennen im kompilierten Code) und die Werte aus 3.1.48 / the list emitted by 3.1.12 (to recognise it in compiled code) and the values from 3.1.48 | 3.1.12, v3.1.48 | LGPL-3.0-or-later, Lizenztext: [LICENSES/LGPL-3.0.txt](./LICENSES/LGPL-3.0.txt) und [LICENSE](./LICENSE) (GPL-3.0) | `Components/JavaScriptEscape/JavaScriptEscapeHardening.php`, `Tests/Unit/Components/JavaScriptEscape/JavaScriptEscapeHardeningTest.php` (nur Entwicklung) |
| QR-Code-Erzeugung für die Einrichtung der Zwei-Faktor-Anmeldung / QR code generation for setting up two-factor login | [qrcode-generator](https://github.com/kazuhikoarase/qrcode-generator) von/by Kazuhiko Arase, npm-Paket `qrcode-generator`, Datei `dist/qrcode.js` unverändert übernommen / included unchanged; Copyright (c) 2009 Kazuhiko Arase | 2.0.4 (Tag `js2.0.4`) | MIT, Lizenztext / license text: [Resources/views/backend/sw5cs_two_factor/lib/qrcode-generator/LICENSE](./Resources/views/backend/sw5cs_two_factor/lib/qrcode-generator/LICENSE) | `Resources/views/backend/sw5cs_two_factor/lib/qrcode-generator/qrcode.js`, Herkunft / origin: `ORIGIN.md` im selben Ordner / in the same folder |

„QR Code“ ist eine eingetragene Marke der DENSO WAVE INCORPORATED. / *"QR Code" is a registered trademark of DENSO WAVE INCORPORATED.*

## Im Repository, nicht im Plugin / In the repository, not in the plugin

| Komponente / Component | Herkunft / Origin | Version | Lizenz / License (SPDX) | Dateien / Files |
|---|---|---|---|---|
| Regelliste des Code-Styles / coding standard rule list | [`.php-cs-fixer.php` aus shopware5/shopware](https://github.com/shopware5/shopware/blob/v5.7.20/.php-cs-fixer.php), übernommen, um die Shopware-Coding-Standards exakt einzuhalten; Datei-Kopf und Pfade angepasst | v5.7.20 | AGPL-3.0-only (Shopware), Lizenztext: [LICENSES/AGPL-3.0.txt](./LICENSES/AGPL-3.0.txt) | `.php-cs-fixer.dist.php` (nur Entwicklung, nicht im Release-ZIP) |

## Nicht enthalten, nur genutzt / Not included, only used

| Komponente / Component | Zweck / Purpose | Lizenz / License (SPDX) |
|---|---|---|
| [composer/installers](https://github.com/composer/installers) | Composer-Abhängigkeit für die Installation nach `custom/plugins/`; wird im Shop-Projekt installiert, nicht mit dem Plugin ausgeliefert | MIT |
| [PHPUnit](https://github.com/sebastianbergmann/phpunit) | Entwicklungsabhängigkeit für Tests; nicht Teil des Release-ZIPs | BSD-3-Clause |
| [PHP CS Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer) und [kubawerlos/php-cs-fixer-custom-fixers](https://github.com/kubawerlos/php-cs-fixer-custom-fixers) | Entwicklungsabhängigkeiten für den Code-Style; nicht Teil des Release-ZIPs | MIT |
| [PHPStan](https://github.com/phpstan/phpstan) mit [strict-rules](https://github.com/phpstan/phpstan-strict-rules), [deprecation-rules](https://github.com/phpstan/phpstan-deprecation-rules) und [phpstan-phpunit](https://github.com/phpstan/phpstan-phpunit) | Entwicklungsabhängigkeiten für die statische Analyse; nicht Teil des Release-ZIPs | MIT |
| [Shopware 5](https://github.com/shopware5/shopware) | Laufzeitumgebung; die XML-Schemas werden für die CI-Prüfung aus einer Shopware-Installation gelesen und nicht in dieses Repository kopiert | AGPL-3.0 |
