## Dependencies installation
### 1- install humbug/php-scoper composer package globally.
https://github.com/humbug/php-scoper#composer (0.15.0 is used)
```
composer global require humbug/php-scoper
```
### 3- install composer packages at /dependencies dir:
```
cd dependencies
composer install
```
### 2- run the following at /dependencies dir:
```
php-scoper add-prefix
composer dump-autoload --working-dir build --classmap-authoritative
```