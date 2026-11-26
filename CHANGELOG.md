# Changelog

Formát vychází z [Keep a Changelog](https://keepachangelog.com/cs/1.1.0/). Starší záznamy jsou doplněné zpětně z historie gitu. Verze aplikace (`APP_VERSION` v `includes/config.php`) se dříve k jednotlivým změnám nezapisovala; od 2.7.4 má každá verze vlastní záznam a git tag.

## [Nevydáno]

## [2.7.4] – 2026-11-26

### Přidáno

- `TODO.md` se seznamem známých problémů (kompatibilita s PHP 8, bezpečnost, výkon).
- `CHANGELOG.md`.
- Composer s vývojovými nástroji (PHPStan 2.3, PHPUnit 9.6), platforma nastavená na PHP 7.4, aby nástroje běžely na 7.4 i 8.x.
- `CLAUDE.md` s pravidly pro práci AI agentů (každou změnu dělá worker, kontroluje ji read-only review, dokud není čisté).
- Skilly pro AI agenty v `.agents/skills/` (`analyse-with-phpstan`, `php-best-practices`), propojené symlinky do `.claude/skills/` a `.junie/skills/`, verze zamčené v `skills-lock.json`.
- Testy jako záchranná síť pro migraci na PHP 8.5 (jen čtou, data v DB nemění):
  - PHPUnit: `phpunit.xml` se sadami `unit` a `smoke`, skripty `composer test` a `composer test:smoke`.
  - Unit testy pomocných funkcí z `includes/functions.php` a skládání SQL v `Database::getWhere()`.
  - Smoke testy přes HTTP (`APP_URL`): všechny stránky, filtry, hledání, řazení, stránkování, detail, úprava (jen GET), export CSV, našeptávač, 404; na stránce nesmí být výpis fatální chyby ani chyby syntaxe.
  - Playwright v `e2e/` (projekty `php74` a `php85`): navigace, výpisy, našeptávač, potvrzovací dialog mazání poznámky, stažení CSV; hlídá chyby v konzoli prohlížeče a výpis fatální chyby nebo chyby syntaxe na stránce (varování aplikace potlačuje přes `error_reporting`); test mazání poznámky blokuje požadavek `delete_note=`.

### Změněno

- README: přehled funkcí a odkazy na TODO a changelog.
- `CLAUDE.md`: review jen čte kód, testy nespouští (to dělá worker); release každé položky TODO s verzí, datem a git tagem.
- `.gitignore`: ignoruje `/vendor/` a výstupy Playwrightu (`e2e/node_modules/`, `e2e/test-results/`, `e2e/playwright-report/`).

## 2026-10-27

### Přidáno

- Lokální prostředí v Dockeru: PHP 7.4 a 8.5, MariaDB 10.11, generátor syntetických dat.
- README.

## 2021-09-02

### Přidáno

- Export vlastníků do CSV.
- Našeptávač obcí.
- Úprava údajů vlastníka.
- Výpis parcel.
- Noční report (`cron/nightly_report.php`).

## 2019-03-14

### Přidáno

- Evidence vlastníků: výpis, detail a poznámky.
