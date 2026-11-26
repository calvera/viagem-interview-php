# Migrace na PHP 8.5

Kód běží beze změny na PHP 7.4 i 8.5. Nejdřív se nasadí nový kód (server je pořád na 7.4), potom se nezávisle přepne PHP. Kdyby se po přepnutí něco pokazilo, stačí přepnout PHP zpět na 7.4; kód se nevrací.

Body označené **ověřit** vycházejí z kódu a z Dockeru, ne z produkčního serveru, a je potřeba je na serveru zkontrolovat.

## 1. Co se v kódu změnilo a proč

Pravidlo: na PHP 7.4 se chování nesmí změnit a na 8.5 musí být stejné jako na 7.4. Používá se jen syntaxe dostupná v 7.4. Opravy chyb v logice po migraci (*Chyby v logice* v TODO.md a CHANGELOG) mění chování na obou verzích stejně.

### Syntaxe (na PHP 8 se soubor vůbec nenačte)

| Kde | Problém | Řešení |
| --- | --- | --- |
| `includes/functions.php`, `admin_owners/owners_functions.php` | `match` je od 8.0 klíčové slovo, funkce `match()` je parse error | přejmenováno na `regex_match()` |
| `admin_owners/owners_detail.php` | vnořený ternární operátor bez závorek je od 8.0 fatal error | závorky podle vyhodnocení v 7.4 (zleva); `owner_name()` z návrhu v TODO by změnilo jméno osoby s titulem bez křestního jména |
| `includes/functions.php` | PHP 8.4 má vestavěné `mb_ucfirst()` / `mb_lcfirst()`, vlastní funkce se stejným jménem jsou fatal error | vlastní funkce se deklarují jen na PHP < 8.4 |
| `plugin/minify/Minify.php` | `$s{$i}` je od 8.0 parse error (přibalená knihovna, načítá ji každá stránka) | `$s[$i]`, jinak knihovna beze změny |

### Chování, které PHP 8 změnilo

| Kde | Problém | Řešení |
| --- | --- | --- |
| `class/Database.php` | PDO od 8.0 při chybě SQL vyhazuje výjimku; aplikace čeká `false` (např. druhý noční report za den spadl na duplicitním `run_date`) | `PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT` (výchozí hodnota v 7.4) |
| `class/Database.php` | PDO MySQL od 8.1 vrací čísla jako `int`; `do_not_contact === '1'` by přestalo platit (štítek „neoslovovat“, export by nevynechal vlastníky se zákazem oslovení) | `PDO::ATTR_STRINGIFY_FETCHES => true`, typy jsou stejné jako v 7.4 |
| výpis, úprava a export vlastníků | `'0000-00-00' == 0` platí v 7.4, v 8 ne (porovnání řetězce s číslem) | `(int)$r['duedate'] == 0` (výpis, export), na obou verzích výsledek jako v 7.4; formulář úpravy po opravě chyb v logice `=== '0000-00-00'`, aby po chybě validace ukázal odeslanou hodnotu |
| detail vlastníka, `owner_parcels()` | `count(null)` hází od 8.0 `TypeError` (neexistující vlastník, vlastník bez parcel) | `if (!$owner)`, `owner_parcels()` vrací prázdné pole |
| `cron/nightly_report.php` | curl handle je od 8.0 objekt, `is_resource()` vrací `false`, report nikdy nepingl službu | `if (!$ch)` |

### Deprecated (dnes jen varování, v PHP 9 chyba)

`case …;` místo `case …:`, `(integer)` a `(boolean)`, operátor backtick (`gethostname()`), `strftime()` (`date()` se stejným formátem), `get_magic_quotes_gpc()` (v 7.4 vždy `false`), `curl_close()` (8.5), dynamické vlastnosti tříd `Database`, `QueryResult`, `Section` (8.2), implicitně nullable parametr (8.4), `fputcsv()` bez `$escape` (8.4), `PDO::MYSQL_ATTR_INIT_COMMAND` (8.5, náhrada `Pdo\Mysql::ATTR_INIT_COMMAND` v 7.4 není, použije se dostupná), `null` do parametrů vestavěných funkcí (8.1).

### Nástroje a testy

- Testy z úkolu 0 (unit, smoke, Playwright) beze změny, zelené na 7.4 i 8.5.
- PHPStan úrovně 2 pro 7.4 i 8.5 (`phpstan.neon`, `phpstan-php85.neon`), viz README.
- Ručně: celá aplikace s dočasným `E_ALL` a logem do souboru na obou verzích, na testovacích datech bez jediného hlášení; HTML stránek je na 7.4 i 8.5 shodné. Hodnoty `NULL` v `land_type`, `lv_number`, `municipality_name`, které testovací data nemají, ošetřené nejsou, jsou zapsané v TODO. `share_den = 0` a pole v parametrech GET/POST jsou ošetřené (oprava chyb v logice), na PHP 8 už nic neshodí.

## 2. Co nastavit na serveru

### Rozšíření PHP

Aplikace používá: `pdo_mysql`, `curl` (noční report), `mbstring`, `session`, `json`, `filter`, `pcre`. Docker image `php:8.5-apache` je má stejně jako `php:7.4-apache` (navíc se instaluje jen `pdo_mysql`, viz `docker/php85/Dockerfile`).

- **ověřit:** `php -m` (web i CLI) na novém PHP obsahuje `pdo_mysql`, `curl`, `mbstring`. Na distribucích bývají v samostatných balíčcích (např. `php8.5-mysql`, `php8.5-curl`, `php8.5-mbstring`).

### php.ini

Aplikace potřebuje jen to, co je v `docker/php/app.ini`: `date.timezone = Europe/Prague`, `memory_limit = 256M`, `max_execution_time = 120`, `log_errors = On`. Přenést do konfigurace PHP 8.5 (web i CLI mají často zvlášť `php.ini`).

- **ověřit:** `default_charset = UTF-8` (výchozí hodnota) a nenastavené `mbstring.internal_encoding`. Na PHP 8.4+ se používají vestavěné `mb_ucfirst()` / `mb_lcfirst()`, které berou kódování z `mb_internal_encoding()` (vlastní funkce měly UTF-8 natvrdo). Vestavěné první písmeno převádějí na titulkovou velikost místo velké; liší se to jen u znaků jako `ǆ` nebo `ß`, v datech aplikace (česká slova) se výsledek neliší.
- `error_reporting` a `display_errors` nastavuje aplikace sama v `includes/config.php`, na `php.ini` nezáleží.
- **ověřit:** pokud starý `php.ini` obsahuje direktivy, které PHP 8 zrušilo nebo označilo za zastaralé (např. `track_errors`, `session.sid_length`, `session.sid_bits_per_character`, `assert.*`), nepřenášet je.
- Apache s PHP 8 nezapíše hlášení `Deprecated` / `Notice` do error logu, pokud je `LogLevel` vyšší než `notice`. Pro ladění po přepnutí lze dočasně nastavit `error_log` na soubor.

### Databáze

Nic se nemění. Připojení (`utf8mb4`, `sql_mode = ''`, emulované prepared statements, typy a chyby jako v 7.4) nastavuje `Database::connect()`.

- Adresa databáze se čte z proměnné prostředí `DB_HOST` (`includes/config.php:14`, bez ní `db`). Nové PHP ji musí dostat stejně jako staré: Apache (`SetEnv` / `PassEnv`) nebo pool PHP-FPM (`env[DB_HOST] = …`, FPM ve výchozím stavu prostředí maže) a cron (proměnná v crontabu). **ověřit**, kde je nastavená dnes.
- **doporučeno ověřit:** `select count(*) from ownerships where share_den = 0`. Na přepnutí to nemá vliv: neplatný podíl už na PHP 8 nic neshodí, do součtů výměry přispívá nulou a „Výměra podílu“ ukazuje „–“ (`share_area()`). Pokud takové řádky jsou, jde o chybu v datech, kterou je dobré opravit.

### Práva k zápisu

Uživatel, pod kterým běží nové PHP (web i cron), musí mít zápis do `logs/`, `reports/` a do adresáře session (`session.save_path`). **ověřit**, pokud se uživatel nebo pool mění (např. jiný PHP-FPM pool pro 8.5). Na session od opravy bezpečnostních chyb stojí i CSRF token formulářů: když se session neuloží, každé uložení poznámky nebo vlastníka skončí chybou „Platnost formuláře vypršela“.

- **ověřit:** `session.gc_maxlifetime` (výchozí hodnota PHP 1440 s, tj. 24 minut; na Debianu/Ubuntu staré session maže cron podle této hodnoty). Po vypršení session skončí přidání poznámky nebo uložení vlastníka chybou „Platnost formuláře vypršela“ a napsaný text se ztratí. Hodnotu zkontrolovat a podle toho, jak dlouho mají uživatelé formuláře otevřené, zvýšit (např. na pracovní den).

### Locale

`includes/config.php` volá `setlocale(LC_ALL, 'cs_CZ.UTF-8', 'cs_CZ', 'czech')`. Po náhradě `strftime()` na locale nic nezávisí, kromě jednoho rozdílu mezi verzemi:

- **ověřit:** je na serveru nainstalované `cs_CZ.UTF-8` (`locale -a`)? Pokud ano, PHP 7.4 píše desetinná čísla s čárkou (`Doba výpočtu: 2,75 s` a „Hotovo za …“ v nočním reportu, `cron/nightly_report.php:127` a `:160`; doba dotazu v `logs/slow_query.log`, `class/Database.php:130`), PHP 8 vždy s tečkou (`2.75`). Do databáze PDO čísla předává nezávisle na locale. Po přepnutí se proto může změnit formát těchto čísel. V Dockeru locale není, obě verze píšou tečku.

### Cron

Noční report musí spouštět CLI nového PHP:

```
15 2 * * * www-data php /var/www/html/cron/nightly_report.php > /dev/null 2>&1
```

- **ověřit:** `php` v cronu ukazuje na 8.5 (`php -v`), případně uvést plnou cestu (např. `/usr/bin/php8.5`). Pro návrat na 7.4 vrátit i cron.

## 3. Postup nasazení

1. Nahrát nový kód na server s PHP 7.4 (běžné nasazení změněných souborů).
   - Od opravy bezpečnostních chyb se mění zápis filtrů výpisů: místo `'{value}'` (v apostrofech) se píše `{value}` a hodnota se předává jako parametr. `class/Section.php`, `class/Database.php`, `includes/functions.php` a definice výpisů (`admin_owners/owners_functions.php`, `admin_parcels_main.php`, `admin_municipalities/municipalities_section.php`) proto nahrát najednou. Modul mimo tento repozitář, který má ve filtru dál `'{value}'`, selže bezpečně (chyba SQL nebo nic nenajde), nic neprozradí. **ověřit** v celém CRM, že žádný jiný modul nemá `{value}` v apostrofech, ani v podobě `'%{value}%'` u LIKE: projít všechny výskyty `grep -rn "{value}"` a případně je upravit ve stejném nasazení.
2. Ověřit na 7.4 (kapitola 4). Kód je pro 7.4 beze změny chování kromě oprav chyb v logice a bezpečnosti (CHANGELOG), které platí stejně na obou verzích.
3. Připravit PHP 8.5 vedle 7.4 (rozšíření, `php.ini`), přepnout web (Apache / PHP-FPM) i cron na 8.5.
4. Ověřit na 8.5 (kapitola 4).
5. Návrat: přepnout web i cron zpět na PHP 7.4. Kód zůstává.

## 4. Ověření

Na serveru (bez testů):

- Otevřít Přehled, Vlastníky (filtr obce, hledání IČO, řazení, další strana), detail a úpravu vlastníka (jen zobrazit), Parcely, Obce; stáhnout export CSV. Na stránkách nesmí být chyba, v patičce je verze aplikace.
- Spustit `php cron/nightly_report.php` pod stejným PHP jako cron. Musí doběhnout s „Hotovo za …“; „už je v historii zapsaný“ při druhém běhu za den je v pořádku.
- Projít `logs/sql_error.log`, `logs/db_error.log` a error log PHP / Apache.

Testy proti serveru (z vývojářského počítače, jen čtou):

```bash
APP_URL=https://<server> vendor/bin/phpunit --testsuite smoke
```

Smoke testy počítají s testovacími daty (konkrétní vlastníci a IČO), na produkčních datech proto část selže; zelené mají být na kopii s vygenerovanými daty nebo v Dockeru. Lokálně před nasazením: unit, smoke a Playwright na obou verzích a PHPStan, příkazy v README.
