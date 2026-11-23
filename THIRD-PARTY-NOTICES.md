# Third-Party Notices

Diese Datei listet Code und Inhalte Dritter, die in diesem Projekt enthalten sind, mit Herkunft und Lizenz. Die Regeln dafür stehen in [CONTRIBUTING.md](./CONTRIBUTING.md), Abschnitt 6.2.

*This file lists third-party code and content included in this project, with origin and license. See [CONTRIBUTING.md](./CONTRIBUTING.md), section 6.2.*

## Im Plugin enthalten / Included in the plugin

Derzeit keine. Das Plugin (Release-ZIP) besteht ausschließlich aus eigenem Code unter GPL-3.0-only.

*None at present. The plugin (release ZIP) consists solely of original code under GPL-3.0-only.*

| Komponente / Component | Herkunft / Origin | Version | Lizenz / License (SPDX) | Dateien / Files |
|---|---|---|---|---|
| – | – | – | – | – |

## Im Repository, nicht im Plugin / In the repository, not in the plugin

| Komponente / Component | Herkunft / Origin | Version | Lizenz / License (SPDX) | Dateien / Files |
|---|---|---|---|---|
| Regelliste des Code-Styles / coding standard rule list | [`.php-cs-fixer.php` aus shopware5/shopware](https://github.com/shopware5/shopware/blob/v5.7.20/.php-cs-fixer.php), übernommen, um die Shopware-Coding-Standards exakt einzuhalten; Datei-Kopf und Pfade angepasst | v5.7.20 | AGPL-3.0-only (Shopware) | `.php-cs-fixer.dist.php` (nur Entwicklung, nicht im Release-ZIP) |

## Nicht enthalten, nur genutzt / Not included, only used

| Komponente / Component | Zweck / Purpose | Lizenz / License (SPDX) |
|---|---|---|
| [composer/installers](https://github.com/composer/installers) | Composer-Abhängigkeit für die Installation nach `custom/plugins/`; wird im Shop-Projekt installiert, nicht mit dem Plugin ausgeliefert | MIT |
| [PHPUnit](https://github.com/sebastianbergmann/phpunit) | Entwicklungsabhängigkeit für Tests; nicht Teil des Release-ZIPs | BSD-3-Clause |
| [PHP CS Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer) und [kubawerlos/php-cs-fixer-custom-fixers](https://github.com/kubawerlos/php-cs-fixer-custom-fixers) | Entwicklungsabhängigkeiten für den Code-Style; nicht Teil des Release-ZIPs | MIT |
| [PHPStan](https://github.com/phpstan/phpstan) mit [strict-rules](https://github.com/phpstan/phpstan-strict-rules), [deprecation-rules](https://github.com/phpstan/phpstan-deprecation-rules) und [phpstan-phpunit](https://github.com/phpstan/phpstan-phpunit) | Entwicklungsabhängigkeiten für die statische Analyse; nicht Teil des Release-ZIPs | MIT |
| [Shopware 5](https://github.com/shopware5/shopware) | Laufzeitumgebung; die XML-Schemas werden für die CI-Prüfung aus einer Shopware-Installation gelesen und nicht in dieses Repository kopiert | AGPL-3.0 |
