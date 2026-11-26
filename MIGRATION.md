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

`case …;` místo `case …:`, `(integer)` a `(boolean)`, operátor backtick (`gethostname()`), `strftime()` (`date()` se stejným formátem), `get_magic_quotes_gpc()` (v 7.4 vždy `false`), `curl_close()` (8.5), dynamické vlastnosti tříd `Database`, `QueryResult`, `Section` (8.2), implicitně nullable parametr (8.4), `fputcsv()` bez `$escape` (8.4), `PDO::MYSQL_ATTR_INIT_COMMAND` (8.5, náhrada `Pdo\Mysql::ATTR_INIT_COMMAND` v 7.4 není, použije se dostupná), `null` do parametrů vestavěných funkcí (8.1; parametry požadavku a po migraci i hodnoty `NULL` z databáze), `null` jako klíč pole (8.5).

### Nástroje a testy

- Testy z úkolu 0 (unit, smoke, Playwright) beze změny, zelené na 7.4 i 8.5.
- PHPStan úrovně 2 pro 7.4 i 8.5 (`phpstan.neon`, `phpstan-php85.neon`), viz README.
- Ručně: celá aplikace s dočasným `E_ALL` a logem do souboru na obou verzích, na testovacích datech bez jediného hlášení; HTML stránek je na 7.4 i 8.5 shodné. Hodnoty `NULL` v `land_type`, `lv_number`, `municipality_name`, které testovací data nemají, jsou po migraci ošetřené přetypováním a ověřené dočasnými řádky s `NULL` a během s `E_ALL` (na 8.5 bez hlášení, výstup stejný jako předtím). `share_den = 0` a pole v parametrech GET/POST jsou ošetřené (oprava chyb v logice), na PHP 8 už nic neshodí.

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

Pro PHP 8.5 se v databázi nic nemění. Zrychlení výpisu vlastníků přidává indexy (a u `owners` histogramy), které se nasazují samostatně SQL migracemi a na verzi PHP nezávisí (přehled v kapitole 5). Připojení (`utf8mb4`, `sql_mode = ''`, emulované prepared statements, typy a chyby jako v 7.4) nastavuje `Database::connect()`.

- Adresa databáze se čte z proměnné prostředí `DB_HOST` (`includes/config.php:14`, bez ní `db`). Nové PHP ji musí dostat stejně jako staré: Apache (`SetEnv` / `PassEnv`) nebo pool PHP-FPM (`env[DB_HOST] = …`, FPM ve výchozím stavu prostředí maže) a cron (proměnná v crontabu). **ověřit**, kde je nastavená dnes.
- **doporučeno ověřit:** `select count(*) from ownerships where share_den = 0`. Na přepnutí to nemá vliv: neplatný podíl už na PHP 8 nic neshodí, do součtů výměry přispívá nulou a „Výměra podílu“ ukazuje „–“ (`share_area()`). Pokud takové řádky jsou, jde o chybu v datech, kterou je dobré opravit.

### Práva k zápisu

Uživatel, pod kterým běží nové PHP (web i cron), musí mít zápis do `logs/`, `reports/` a do adresáře session (`session.save_path`). **ověřit**, pokud se uživatel nebo pool mění (např. jiný PHP-FPM pool pro 8.5). Na session od opravy bezpečnostních chyb stojí i CSRF token formulářů: když se session neuloží, každé uložení poznámky nebo vlastníka skončí chybou „Platnost formuláře vypršela“.

- **ověřit:** `session.gc_maxlifetime` (výchozí hodnota PHP 1440 s, tj. 24 minut; na Debianu/Ubuntu staré session maže cron podle této hodnoty). Po vypršení session skončí přidání poznámky nebo uložení vlastníka chybou „Platnost formuláře vypršela“ a napsaný text se ztratí. Hodnotu zkontrolovat a podle toho, jak dlouho mají uživatelé formuláře otevřené, zvýšit (např. na pracovní den).

### Locale

`includes/config.php` volá `setlocale(LC_ALL, 'cs_CZ.UTF-8', 'cs_CZ', 'czech')` a hned po něm `setlocale(LC_NUMERIC, 'C')`. Na nainstalovaném locale nic nezávisí, ověřovat ho na serveru není potřeba:

- Desetinná čísla převedená na text (`Doba výpočtu` a „Hotovo za …“ v nočním reportu, doba dotazu v `logs/slow_query.log`) psalo PHP 7.4 s nainstalovaným `cs_CZ.UTF-8` s čárkou (`2,75`), PHP 8 vždy s tečkou. Díky `LC_NUMERIC = C` píšou obě verze tečku. Ověřeno v dočasném kontejneru PHP 7.4 s vygenerovaným `cs_CZ.UTF-8`: před úpravou „Hotovo za 2,77 s“, po ní „2.82 s“.
- Do databáze PDO čísla předává nezávisle na locale (ověřeno tamtéž, `2.76`). `number_format()` má oddělovače v parametrech, `strftime()` aplikace už nepoužívá. `LC_COLLATE` aplikace nepoužívá, `strtolower()` jen na ASCII. Na `LC_CTYPE` závisí třídy znaků v regulárních výrazech bez `/u` (`\s`, `\d`: `class/Database.php:130`, volání `regex_match()`, `admin_owners/owners_edit.php:40`). Pro `cs_CZ.UTF-8` je výsledek na 7.4 i 8.5 stejný; jinak by se mohl lišit jen tehdy, kdyby se použila záložní locale `cs_CZ` / `czech` v kódování ISO-8859-2.

### Cron

Noční report musí spouštět CLI nového PHP:

```
15 2 * * * www-data php /var/www/html/cron/nightly_report.php > /dev/null 2>&1
```

- **ověřit:** `php` v cronu ukazuje na 8.5 (`php -v`), případně uvést plnou cestu (např. `/usr/bin/php8.5`). Pro návrat na 7.4 vrátit i cron.

## 3. Postup nasazení

1. Nahrát nový kód na server s PHP 7.4 (běžné nasazení změněných souborů).
   - Od opravy bezpečnostních chyb se mění zápis filtrů výpisů: místo `'{value}'` (v apostrofech) se píše `{value}` a hodnota se předává jako parametr. `class/Section.php`, `class/Database.php`, `includes/functions.php` a definice výpisů (`admin_owners/owners_functions.php`, `admin_parcels_main.php`, `admin_municipalities/municipalities_section.php`) proto nahrát najednou. Modul mimo tento repozitář, který má ve filtru dál `'{value}'`, selže bezpečně (chyba SQL nebo nic nenajde), nic neprozradí. **ověřit** v celém CRM, že žádný jiný modul nemá `{value}` v apostrofech, ani v podobě `'%{value}%'` u LIKE: projít všechny výskyty `grep -rn "{value}"` a případně je upravit ve stejném nasazení.
   - Od zrychlení výpisu vlastníků načítá výpis data pro všechny řádky stránky najednou. `class/Section.php`, `includes/functions.php`, `admin_owners/owners_functions.php` a `admin_owners/owners_section.php` proto nahrát najednou. S novým `owners_functions.php` a starým `owners_section.php` by výpis nespadl, ale u každého řádku by potichu ukázal prázdnou obec, 0 parcel a šedý řádek (varování aplikace potlačuje).
   - Z `includes/functions.php` zmizely `owner_parcel_count()` a `owner_parcels_in_municipality()` (nahradily je `owners_parcel_counts()`, `owners_parcels()` a `parcels_in_municipality()`). **ověřit** v celém CRM, že je žádný jiný modul nevolá: `grep -rn "owner_parcel_count\|owner_parcels_in_municipality"`; nalezená volání upravit ve stejném nasazení, jinak tam vznikne fatální chyba „Call to undefined function“.
   - Od zrychlení výpisu parcel a obcí: `admin_parcels_main.php` volá novou funkci `parcels_owners()` z `includes/functions.php` a `Section::setRowsPrepareFunction()` z `class/Section.php` (od v2.7.9). `admin_parcels_main.php`, `includes/functions.php` a `class/Section.php` proto nahrát najednou; se starším `functions.php` nebo `Section.php` skončí výpis parcel fatální chybou „Call to undefined function“, resp. „Call to undefined method“. `admin_municipalities/municipalities_section.php` žádnou novou funkci ani metodu nevolá (počet parcel je poddotaz v hlavním dotazu přes `addSelect()`), potřebuje jen `class/Section.php` a `class/Database.php` od v2.7.7 (filtry jako parametry, viz první odrážka), a dá se proto nahrát samostatně. Index ani jiná změna databáze potřeba není.
   - Od odkazu „vlastníci“ podle id obce: `admin_municipalities/municipalities_section.php` posílá `f_municipality_id`, výpis vlastníků ho čte přes `Section::addHiddenFilter()` (`class/Section.php`), `owners_has_municipality_filter()` (`admin_owners/owners_functions.php`, volá ji `admin_owners/owners_section.php`) a `owners_parcels_in_municipality()` (`includes/functions.php`). Těchto pět souborů proto nahrát najednou. S novým `owners_functions.php` a starým `Section.php` skončí výpis vlastníků i export fatální chybou „Call to undefined method“, s novým `owners_section.php` a starým `owners_functions.php` „Call to undefined function“. S novým odkazem a starým výpisem vlastníků výpis parametr potichu ignoruje a ukáže všech 500 tisíc vlastníků bez popisku obce. Databáze ani index se nemění. Staré odkazy a záložky s `f_municipality=<název>` fungují dál (textový filtr, podřetězec).
   - Od odkazu „parcely“ ve výpisu obcí: `admin_parcels_main.php` volá `Section::addHiddenFilter()`, potřebuje proto `class/Section.php` od v2.7.11 (a jako dosud `includes/functions.php` od v2.7.10). `admin_parcels_main.php`, `admin_municipalities/municipalities_section.php` a `css/style.css` nahrát najednou, a pokud server ještě nemá v2.7.11, tak s `class/Section.php`. Se starším `Section.php` skončí výpis parcel fatální chybou „Call to undefined method“. S novým výpisem obcí a starým výpisem parcel odkaz „parcely“ potichu ukáže všech 800 tisíc parcel bez popisku obce. Bez nového `css/style.css` odkazy fungují, jen mají velký styl `a.button` a v úzkém sloupci se zalomí pod sebe. CSS se vkládá do stránky při každém požadavku (`Minify::file()`, cache jen v rámci požadavku), změna se projeví hned. Databáze ani index se nemění.
   - Oprava pořadí vlastníků se stejným příjmením ve výpisu parcel mění jen `order by` v `parcels_owners()`, nahrát tedy jen `includes/functions.php`. Lze samostatně na server s v2.7.10 a novějším (od té verze funkce existuje, volá ji `admin_parcels_main.php`). Databáze ani index se nemění, plán dotazu je stejný. Po nahrání ukáže výpis parcel vlastníky se stejným příjmením seřazené podle jména a id (v testovacích datech parcela 19020 na `index.php?url=parcels&search=st.%20121`: Radek, Václav (64940), Václav (484940) Kratochvíl); návrat je nahrání předchozího souboru.
   - Výchozí řazení výpisu vlastníků podle `o.last_name, o.first_name, o.id` mění jen `admin_owners/owners_functions.php` (`setSortBy()`), nahrát tedy jen tento soubor; lze samostatně na server s v2.7.11 a novějším (od té verze soubor volá `Section::addHiddenFilter()`). Spolu s ním (před nahráním nebo po něm, na pořadí nezáleží) spustit migraci 2 z kapitoly 5. Bez ní výpis funguje se stejným výsledkem, jen řadí všech 500 tisíc vlastníků jako dnes. Po nahrání jsou vlastníci se stejným jménem seřazení podle id, takže na stranách jsou jiní vlastníci než dříve (pořadí jmen se nemění, v testovacích datech ověřeno na celé tabulce). Návrat je nahrání předchozího souboru; index může zůstat.
   - Index na obec vlastníka (migrace 3 z kapitoly 5) nemění žádný soubor a kód na něm nezávisí. S kódem od v2.8.2 je výchozí řazení výpisu i exportu jednoznačné a stránky v něm jsou s indexem i bez něj stejné, spustit ho jde kdykoli (řazení podle sloupce „Založeno“ s filtrem obce viz 5.3). Se starším kódem nic nespadne, ale řazení `concat()` není jednoznačné a změněný plán může přeskupit vlastníky se stejným jménem na stranách s filtrem obce (proto se index nepřidal ve v2.7.9); proto ho spustit až s v2.8.2 nebo po ní.
2. Ověřit na 7.4 (kapitola 4). Kód je pro 7.4 beze změny chování kromě oprav chyb v logice a bezpečnosti (CHANGELOG), které platí stejně na obou verzích.
3. Připravit PHP 8.5 vedle 7.4 (rozšíření, `php.ini`), přepnout web (Apache / PHP-FPM) i cron na 8.5.
4. Ověřit na 8.5 (kapitola 4).
5. Návrat: přepnout web i cron zpět na PHP 7.4. Kód zůstává.

DB migrace z kapitoly 5 se dají spustit kdykoli, před nahráním kódu i po něm, v pořadí z přehledu. Kód na nich nezávisí.

## 4. Ověření

Na serveru (bez testů):

- Otevřít Přehled, Vlastníky (filtr obce, hledání IČO, řazení, další strana), detail a úpravu vlastníka (jen zobrazit), Parcely, Obce a z nich odkaz „vlastníci“ (popisek „Obec: název ×“, počet vlastníků obce, sloupec „V obci“) a odkaz „parcely“ (oba ve tvaru malých tlačítek v jednom řádku; popisek „Obec: název ×“, počet parcel stejný jako ve sloupci „Parcel“ u obce, ve sloupci „Obec“ jen tato obec; × vrátí celý výpis parcel); stáhnout export CSV. Na stránkách nesmí být chyba, v patičce je verze aplikace.
- Spustit `php cron/nightly_report.php` pod stejným PHP jako cron. Musí doběhnout s „Hotovo za …“; „už je v historii zapsaný“ při druhém běhu za den je v pořádku.
- Projít `logs/sql_error.log`, `logs/db_error.log` a error log PHP / Apache.

Testy proti serveru (z vývojářského počítače, jen čtou):

```bash
APP_URL=https://<server> vendor/bin/phpunit --testsuite smoke
```

Smoke testy počítají s testovacími daty (konkrétní vlastníci a IČO), na produkčních datech proto část selže; zelené mají být na kopii s vygenerovanými daty nebo v Dockeru. Lokálně před nasazením: unit, smoke a Playwright na obou verzích a PHPStan, příkazy v README.

## 5. Přehled DB migrací

Nasazení nemá migrační krok, proto je každá změna databáze samostatný SQL soubor v [`sql/migrations/`](sql/migrations/), který se spustí ručně. Tabulka je seznam všeho, co je při nasazení potřeba spustit, v pořadí spuštění. Soubory mají pořadové číslo (`001_`, `002_`…), takže pořadí podle názvu je pořadí spuštění; nový soubor dostane další číslo. Soubory jsou na sobě nezávislé: `001` mění `ownerships`, `002` a `003` sice obě `owners`, ale `003` nepotřebuje index ani histogramy z `002` (5.3); spouštějí se přesto v pořadí čísel. Soubor `001` se do v2.8.1 jmenoval `2026-11-26_ownerships_owner_id.sql`, obsah je stejný až na `ANALYZE TABLE` a `sql_mode`. Doba je naměřená v Dockeru na testovacích datech (500 tis. vlastníků, 1,2 mil. vlastnických vztahů), na produkci záleží na disku a zátěži, řádově jde o sekundy.

| # | Soubor | Verze | Co dělá | Povinná? | Kdy vůči nahrání souborů | Návrat | Doba |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | [`001_ownerships_owner_id.sql`](sql/migrations/001_ownerships_owner_id.sql) | 2.7.9 (`ANALYZE TABLE` doplněn ve v2.8.2) | Index `idx_ownerships_owner (owner_id)` pro počty parcel a parcely vlastníků na stránce výpisu (`where owner_id in (…)`); `ANALYZE TABLE ownerships`. | Ne. Kód funguje i bez ní se stejným výsledkem, jen dotaz na počty parcel (s filtrem obce i na parcely) projde celou tabulku `ownerships`. | Kdykoli, před nahráním i po něm. | `SET SESSION sql_mode = ''; SET SESSION lock_wait_timeout = 10; ALTER TABLE ownerships DROP INDEX IF EXISTS idx_ownerships_owner;` | index asi 1,5 s (~20 MB), `ANALYZE` pod 0,5 s |
| 2 | [`002_owners_name.sql`](sql/migrations/002_owners_name.sql) | 2.8.2 | Index `idx_owners_name (last_name, first_name, id)` pro výchozí řazení výpisu vlastníků; `ANALYZE TABLE owners PERSISTENT FOR COLUMNS (type, ico, municipality_id, do_not_contact) INDEXES ()` (statistiky InnoDB a histogramy filtrovaných sloupců). Před spuštěním kontrola serveru (5.2, **ověřit**). | Ne. Kód funguje i bez ní se stejným výsledkem a pořadím, jen každá strana výpisu seřadí všech 500 tis. vlastníků jako dnes. Spouštět vždy celý soubor: index bez histogramů zhorší filtr podle id obce (po migraci 3 už ne), hledání IČO a kombinaci typu a zákazu oslovení (5.2). | Kdykoli, před nahráním i po něm. Se starým kódem index nevadí: řazení `concat()` ho použít nemůže (plán stránky beze změny), použije ho nanejvýš `count(*)` (menší index místo primárního klíče, stejný počet). | `SET SESSION sql_mode = ''; SET SESSION lock_wait_timeout = 10; ALTER TABLE owners DROP INDEX IF EXISTS idx_owners_name;` Statistiky mohou zůstat (5.2). | celý soubor asi 1,5 s (index ~17 MB), `ANALYZE` 0,4 s |
| 3 | [`003_owners_municipality.sql`](sql/migrations/003_owners_municipality.sql) | 2.8.3 | Index `idx_owners_municipality (municipality_id, last_name, first_name, id)` pro výpis vlastníků s filtrem podle id obce (`o.municipality_id = …` ve výchozím řazení), textový filtr „Obec“ a přehled obcí v nočním reportu; `ANALYZE TABLE owners` (jen statistiky InnoDB, histogramy z `002` zůstávají). | Ne. Kód funguje i bez ní se stejným výsledkem a ve výchozím řazení i se stejným pořadím (řazení podle „Založeno“ viz 5.3), jen filtr podle id obce a textový filtr obce projdou všech 500 tis. vlastníků. | Kdykoli s kódem od v2.8.2, před nahráním i po něm (se starším kódem viz kapitola 3). | `SET SESSION sql_mode = ''; SET SESSION lock_wait_timeout = 10; ALTER TABLE owners DROP INDEX IF EXISTS idx_owners_municipality;` Statistiky není třeba vracet (5.3). | celý soubor asi 1 s (index ~19 MB), `ANALYZE` pod 0,1 s |

### Společné pro všechny migrace

- **Idempotentní.** `ADD INDEX IF NOT EXISTS`: druhé spuštění nic nezmění, skončí poznámkou `Note 1061: Duplicate key name` a návratovým kódem 0. `ANALYZE TABLE` se při opakování jen znovu spočítá (u histogramů ověřeno, že vyjdou stejné). Soubor se proto dá spustit znovu kdykoli, i když si nejste jistí, jestli už proběhl.
- **`sql_mode`.** Soubor nastaví pro svou relaci `sql_mode = ''`, stejně jako aplikace (`Database::connect()`). `owners` má výchozí hodnoty s nulovým datem (`duedate`, `created_at`); se striktním režimem a `NO_ZERO_DATE` (výchozí nastavení serveru nebo klienta správce) skončí `ALTER TABLE owners` chybou `1067 Invalid default value for 'duedate'`. Ověřeno v Dockeru na kopii tabulek: s `STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE` bez tohoto řádku chyba 1067, s ním oba soubory projdou. Nastavení platí jen pro relaci klienta, server ani aplikaci neovlivní. Totéž platí pro návrat: `ALTER TABLE owners DROP INDEX …` skončí se striktním režimem stejnou chybou 1067 (ověřeno na kopii tabulky, `ownerships` projde), proto návratové příkazy v tabulce začínají stejnými dvěma `SET SESSION` jako soubory.
- **Online.** `ALGORITHM=INPLACE, LOCK=NONE`: tabulka se nekopíruje a čtení i zápis běží dál. Kdyby server online variantu nepodporoval, příkaz skončí chybou a nic nezmění (nepřejde potichu na zamčení tabulky).
- **Metadata lock.** Na začátku a na konci potřebuje `ALTER` krátký výhradní metadata lock na tabulku. Pokud nad ní běží dlouhý dotaz nebo otevřená transakce (např. starý výpis vlastníků, noční report ve 2:15), `ALTER` na něj čeká a nové dotazy na tabulku čekají za ním. Soubor proto nastaví `lock_wait_timeout = 10`: když lock do 10 s nedostane, skončí chybou `Lock wait timeout exceeded` a stačí ho spustit znovu. Spouštět mimo noční report a ideálně mimo špičku. Zápisy během stavby indexu se ukládají do dočasného logu (`innodb_online_alter_log_max_size`, výchozí 128 MB), což při běžném provozu nevadí.
- **`ANALYZE TABLE` je v souboru, ne samostatný krok.** Po indexu přepočítá statistiky pro optimalizátor (persistentní statistiky InnoDB, u `owners` i histogramy). Je v souboru, aby se nedal zapomenout a aby index a statistiky, na kterých závisí plán, vznikly vždy spolu. V Dockeru (MariaDB 10.11.19) nic nezamykal: nečekal na běžící dotaz (10 s `sleep` nad tabulkou) a další dotazy nečekaly na něj. **ověřit** na verzi produkčního serveru: u některých (starších) verzí MariaDB a MySQL `ANALYZE TABLE` tabulku vyprázdní z cache tabulek (flush), a pak nové dotazy čekají, až doběhnou dotazy běžící nad tabulkou (stav „Waiting for table flush“). Proto i `ANALYZE` spouštět mimo noční report a dlouhé exporty. Prostý `ANALYZE TABLE` (`001`, `003`) při `use_stat_tables = PREFERABLY_FOR_QUERIES` jen navzorkuje stránky indexů (`innodb_stats_persistent_sample_pages = 20`), trvá zlomek sekundy nezávisle na velikosti tabulky. InnoDB statistiky nového indexu spočítá i sám při `ALTER` (`innodb_stats_auto_recalc`), prostý `ANALYZE` je tedy pojistka. Histogramy (`PERSISTENT FOR`, uložené v `mysql.column_stats`) MariaDB 10.11 ve výchozím nastavení (`use_stat_tables = PREFERABLY_FOR_QUERIES`) sama nesbírá a prostý `ANALYZE` je také nevytvoří; jejich sběr je jeden průchod celou tabulkou (500 tis. řádků 0,4 s). Pokud `ALTER` skončí chybou, klient další příkazy souboru nespustí; po opakování proběhne i `ANALYZE`. S `use_stat_tables = PREFERABLY` nebo `COMPLEMENTARY` prostý `ANALYZE TABLE` místo toho projde celou tabulku a zapíše statistiky všech sloupců a indexů do `mysql.*_stats` (ověřeno na kopii tabulky); u `ownerships` (1,2 mil. řádků) to trvá déle a u `owners` se přepíšou histogramy z `002`. **ověřit** hodnotu před spuštěním `001` i `003` (kontrola serveru v 5.2).
- **Práva.** Uživatel musí mít právo `ALTER` (resp. `INDEX`) na databázi. Aplikační uživatel ho mít nemusí, pak spustit jako správce databáze. `ANALYZE … PERSISTENT FOR` zapisuje do `mysql.column_stats` sám server, stačí práva k databázi aplikace (ověřeno s uživatelem `crm`, který na `mysql.*` práva nemá).

Spuštění na produkci, v pořadí z tabulky (název databáze a uživatele podle serveru):

```bash
mysql -u <admin> -p <databáze> --show-warnings < sql/migrations/001_ownerships_owner_id.sql
mysql -u <admin> -p <databáze> --show-warnings < sql/migrations/002_owners_name.sql
mysql -u <admin> -p <databáze> --show-warnings < sql/migrations/003_owners_municipality.sql
```

Lokálně (Docker): databáze načte `docker/initdb` jen při prvním startu s prázdným svazkem, migrace se tam samy neprovedou. Na existující databázi i po každém `docker compose down -v` je spusťte ručně:

```bash
for f in sql/migrations/*.sql; do docker compose exec -T db mariadb -ucrm -pcrm legacy_crm --show-warnings < "$f"; done
```

Indexy nemění data: `CHECKSUM TABLE` všech tabulek je před migrací a po ní stejný (ověřeno). Index se nemusí odebírat ani při návratu kódu na starší verzi, té nevadí.

### 5.1 Index `ownerships.owner_id` (výpis vlastníků)

Výpis vlastníků načítá počet parcel a parcely vlastníků na stránce dotazem `where owner_id in (…)` (úkol 2 v TODO.md). Bez indexu každý takový dotaz projde celou tabulku `ownerships` (1,2 mil. řádků, `EXPLAIN`: `ALL`), s indexem přečte jen řádky vlastníků na stránce (`range`, 120 řádků). Bez indexu má výpis stejný výsledek a stejný počet dotazů (5, s filtrem obce 6), jen dotaz na počty parcel projde celou tabulku, s filtrem obce navíc dotaz na parcely (2 průchody na stránku).

Ověření: `SHOW INDEX FROM ownerships WHERE Key_name = 'idx_ownerships_owner';` vrátí jeden řádek a `EXPLAIN select count(*) from ownerships where owner_id in (1, 2)` ukazuje `key = idx_ownerships_owner`, `type = range`. V patičce výpisu vlastníků je 5 SQL dotazů (s filtrem obce 6).

Na serveru, kde už soubor proběhl před doplněním `ANALYZE TABLE`, stačí spustit soubor znovu (index přeskočí) nebo jen `ANALYZE TABLE ownerships;`.

### 5.2 Index `owners (last_name, first_name, id)` (výchozí řazení vlastníků)

Výpis vlastníků se ve výchozím stavu řadí `o.last_name, o.first_name, o.id` (dříve `concat(o.last_name, ' ', o.first_name)`, otázka 5 v OPENQUESTIONS.md). S indexem `idx_owners_name` čte MariaDB první strany výpisu přímo z indexu místo řazení všech 500 tis. vlastníků (bez filtru 50 řádků místo 500 000 a filesortu). Plány před a po jsou v README (*Výchozí řazení vlastníků*).

- **Pořadí na indexu nezávisí.** Řazení je jednoznačné (`id` na konci), proto je výpis s indexem i bez něj stejný (HTML bajtově shodné). Index tedy jen zrychluje a na pořadí kroků nezáleží. Nový kód bez indexu řadí stejně jako starý: filesort přes všechny vlastníky.
- **Histogramy jsou součást migrace.** Bez nich optimalizátor předpokládá, že podmínce `o.municipality_id = …` nebo `o.ico = …` (sloupce bez indexu) odpovídá každý řádek, a zvolí čtení indexu v pořadí jmen s dohledáním každého řádku: filtr podle id obce přečetl 180 až 500 tis. řádků přes index (0,5 až 1,5 mil. čtení stránek z buffer poolu místo 4 tis.), hledání IČO celý index, typ P se zákazem oslovení na 28. straně také (1,5 mil. stránek místo 8 tis.). S histogramy zná jejich selektivitu a pro tyto filtry zůstane u dnešního plánu (průchod tabulkou a seřazení nalezených), u méně výběrových filtrů (typ, zákaz oslovení) čte první strany z indexu. Po migraci 3 má filtr podle id obce vlastní index a histogram `municipality_id` už nepotřebuje (5.3); pro IČO a typ se zákazem oslovení zůstává. Histogramy se nemění s daty samy; po velkém importu nebo hromadné změně vlastníků spusťte soubor znovu (nebo jen jeho `ANALYZE`). Zastaralé histogramy výsledky nemění, jen můžou zhoršit plán.
- **Hledání index pro řazení nepoužívá.** Podmínku `like '%…%'` optimalizátor neodhadne (histogram nepomůže) a vzácné jméno by četlo skoro celý index. S vyplněným hledáním proto kód řadí `o.id + 0` (stejné pořadí, z indexu číst nejde) a hledání má plán jako dříve. Na migraci to nezávisí. Vzdálené strany (na testovacích datech zhruba od 75. strany výpisu bez filtru) a `count(*)` s filtry dál procházejí celou tabulku.
- **Návrat.** `SET SESSION sql_mode = ''; SET SESSION lock_wait_timeout = 10; ALTER TABLE owners DROP INDEX IF EXISTS idx_owners_name;` vrátí plány do dnešního stavu. `ANALYZE … PERSISTENT` uložil histogramy do `mysql.column_stats` (4 řádky) a počet řádků tabulky do `mysql.table_stats` (1 řádek; `mysql.index_stats` nic, protože `INDEXES ()`). Mohou zůstat; odebrat je jde jako správce (aplikační uživatel na `mysql.*` práva nemá):

  ```sql
  DELETE FROM mysql.column_stats WHERE db_name = '<databáze>' AND table_name = 'owners';
  DELETE FROM mysql.table_stats WHERE db_name = '<databáze>' AND table_name = 'owners';
  FLUSH TABLES owners;
  ```

Kontrola serveru před spuštěním (**ověřit**; chování histogramů je ověřené jen v Dockeru, MariaDB 10.11.19):

```sql
select @@version, @@use_stat_tables, @@optimizer_use_condition_selectivity, @@histogram_size;
```

V Dockeru (a ve výchozím nastavení 10.11): `10.11.19-MariaDB`, `PREFERABLY_FOR_QUERIES`, `4`, `254`. Očekává se 10.11.x, `use_stat_tables` jiné než `NEVER` (s `NEVER` optimalizátor histogramy nepoužije), `optimizer_use_condition_selectivity` 4 nebo 5 (od 4 používá histogramy) a `histogram_size` větší než 0 (s 0 se histogramy nesbírají). Při jiných hodnotách migraci spustit a hned ověřit plány níže. Hodnota `use_stat_tables` rozhoduje i o prostém `ANALYZE TABLE` v `001` a `003`: s `PREFERABLY` nebo `COMPLEMENTARY` projde celou tabulku a zapíše statistiky všech sloupců a indexů do `mysql.*_stats`, u `owners` přepíše i histogramy z `002` (viz *Společné pro všechny migrace*, bod `ANALYZE TABLE`, a 5.3, Statistiky).

Ověření (jako správce, aplikační uživatel na `mysql.*` práva nemá): `SHOW INDEX FROM owners WHERE Key_name = 'idx_owners_name';` vrátí 3 řádky (`last_name`, `first_name`, `id`), `SELECT column_name FROM mysql.column_stats WHERE db_name = '<databáze>' AND table_name = 'owners';` 4 sloupce (`type`, `ico`, `municipality_id`, `do_not_contact`). `EXPLAIN select o.* from owners o order by o.last_name, o.first_name, o.id limit 0, 50` ukazuje `type = index`, `key = idx_owners_name` bez `Using filesort`; `EXPLAIN select o.* from owners o where o.ico = '01256748' order by o.last_name, o.first_name, o.id limit 0, 50` ukazuje `type = ALL` a `Using filesort` (s histogramy správně, viz výše); stejně `EXPLAIN select o.* from owners o where o.municipality_id = 1 order by o.last_name, o.first_name, o.id limit 0, 50`, dokud neproběhla migrace 3 (po ní `type = ref`, `key = idx_owners_municipality`, 5.3). Pokud ukazuje `type = index` a `key = idx_owners_name`, optimalizátor histogramy nepoužívá a hledání IČO (a před migrací 3 filtr podle id obce) by bylo pomalejší než bez indexu: index odebrat (`SET SESSION sql_mode = ''; SET SESSION lock_wait_timeout = 10; ALTER TABLE owners DROP INDEX IF EXISTS idx_owners_name;`), dokud se příčina nevyřeší (nastavení výše). Kód funguje i bez indexu se stejným výsledkem.

### 5.3 Index `owners (municipality_id, last_name, first_name, id)` (obec vlastníka)

Filtr podle id obce (odkaz „vlastníci“ z výpisu obcí, `o.municipality_id = …`) bez indexu prochází všech 500 tis. vlastníků a nalezené řadí (`ALL` + filesort, asi 4 tis. stránek z buffer poolu na `count(*)` i na stranu). S indexem `idx_owners_municipality` čte jen vlastníky obce, a protože index pokračuje sloupci řazení, čte stranu rovnou v pořadí bez filesortu (1. strana 50 řádků a 156 stránek). Plány před a po jsou v README (*Index na obec vlastníka*).

- **Proč složený index.** Prostý index `(municipality_id)` by stačil na `count(*)` a na výběr řádků, stranu by ale řadil (filesort všech vlastníků obce; na testovacích datech nejvýš 240, 1. strana 876 stránek místo 156). Na produkci může mít obec vlastníků mnohem víc (velké město); složený index pak čte první strany pořád jen po 50 řádcích. Ostatní dotazy používají oba indexy stejně. Cena: index má asi 19 MB místo 7,5 MB a mění se i při změně jména vlastníka. **ověřit** na produkci, kolik vlastníků mají největší obce (`select municipality_id, count(*) c from owners group by municipality_id order by c desc limit 5`); na volbě indexu to nic nemění, jen na tom, jak velký je zisk.
- **Pořadí ve výchozím řazení na indexu nezávisí.** Výchozí řazení výpisu i exportu je jednoznačné (`id` na konci, v2.8.2), proto jsou tyto stránky s indexem i bez něj stejné (HTML bajtově shodné, 41 adres na PHP 7.4 i 8.5). Index jen zrychluje.
- **Řazení podle „Založeno“ (`sort=o.created_at`) jednoznačné není.** `Section::getOrder()` řadí jen podle zvoleného sloupce, bez `id` (TODO.md, *Chyby v logice*, neurčené pořadí, bod b). S filtrem obce (podle id i textovým) migrace mění plán tohoto řazení (`ALL` + filesort → `ref` + filesort, u textového filtru nejdřív obce), takže pořadí vlastníků se stejným `created_at` se může změnit. Stejné zůstane, jen když je `created_at` jedinečné. Na testovacích datech je (500 000 různých hodnot, ověřeno i HTML), na produkci nemusí: sloupec má výchozí hodnotu `0000-00-00 00:00:00` a mohou do něj zapisovat jiné moduly nebo importy. **ověřit:** `select created_at, count(*) c from owners group by created_at having c > 1 limit 5` (prázdný výsledek = pořadí se nezmění). Pokud vrátí řádky, množina nalezených vlastníků a jejich počet se nemění, obsah jednotlivých stránek se ale může: vlastníci se shodným časem se mohou přeskupit mezi stranami (stejně jako dnes při každé změně plánu); oprava je doplnění `id` v uvedené položce TODO.
- **Textový filtr „Obec“ také zrychlí.** Optimalizátor nejdřív najde obce podle názvu (6 250 řádků `municipalities`) a jejich vlastníky čte z indexu. `count(*)` s „Lhota“ čte 825 stránek místo 1 mil. (dříve join s obcí pro každého vlastníka), „Zadní Ves v Údolí“ 1. strana 279 stránek místo 1,46 mil. Strany „Lhota“ (20 000 vlastníků) ale řadí všechny nalezené: samotný dotaz na řádky čte na 1. až 9. straně víc stránek (61 tis. místo 6 až 56 tis.), od 10. strany méně. Celá stránka s `count(*)` je přesto mnohem levnější, na 1. straně asi 16× (62 tis. stránek místo 1 mil.); vyhodnoceno jako zlepšení, protože celá stránka je levnější.
  - **Jak to škáluje.** Bez indexu stojí `count(*)` textového filtru vždy zhruba stejně bez ohledu na počet nalezených (joinuje obec ke každému vlastníkovi, asi 1 mil. stránek, 0,7 až 0,9 s), dotaz na řádky podle plánu navíc. S indexem stojí dotaz na řádky asi 3 stránky na nalezeného vlastníka na každé straně (řadí všechny nalezené) a `count(*)` asi 0,04. Změřeno na 1. straně celé stránky (`count(*)` + řádky): „Zadní Ves v Údolí“ (80 vlastníků) 2,5 mil. → 0,3 tis. stránek, „Lhota“ (20 000) 1,0 mil. → 62 tis., „Horní“ (50 000) 1,06 mil. → 154 tis. (0,8 → 0,2 s), „nad“ (85 000) 1,01 mil. → 261 tis. (0,76 → 0,34 s), ale „e“ (400 640) 1,01 mil. → 1,23 mil. (0,72 → 1,6 s) a „a“ (466 000) 1,01 mil. → 1,44 mil. (0,70 → 1,8 s). Hranice je podle stránek asi u 2/3 všech vlastníků (na testovacích datech asi 330 tis.), podle doby `ANALYZE` (řazení) už asi u 40 % (200 tis.). Horší jsou tedy jen velmi široké filtry (jedno písmeno), běžné části názvu obce jsou levnější.
  - **ověřit** na produkci: `EXPLAIN select count(*) from owners o left join municipalities m on m.id = o.municipality_id where m.name like '%Lhota%'` ukazuje `m`: `ALL`, `o`: `ref` `idx_owners_municipality` (stejně hlavní dotaz s `limit`, navíc filesort). Pro obvyklá zadání textového filtru zjistit počet nalezených vlastníků; blíží-li se 40 % všech vlastníků, je stránka pomalejší než bez indexu.
- **Statistiky.** Soubor končí prostým `ANALYZE TABLE owners` (statistiky InnoDB, také pro nový index). Histogramy z `002` (`mysql.column_stats`, i `municipality_id`) se znovu nesbírají: popisují hodnoty sloupců, ne indexy, a data se nezměnila (po migraci ověřeny beze změny). Pro `o.municipality_id = …` teď optimalizátor odhaduje z indexu; plán s indexem je stejný i bez histogramů (ověřeno v relaci s `use_stat_tables = NEVER`), migrace 3 tedy na `002` nezávisí. Histogramy dál potřebuje hledání IČO a typ se zákazem oslovení (5.2). Vše o prostém `ANALYZE` platí pro `use_stat_tables = PREFERABLY_FOR_QUERIES` (výchozí v 10.11). S `PREFERABLY` nebo `COMPLEMENTARY` prostý `ANALYZE TABLE` projde celou tabulku a zapíše statistiky všech sloupců a indexů do `mysql.column_stats` a `mysql.index_stats` (ověřeno na kopii tabulky: 16 sloupců, 8 řádků indexů), u `owners` tedy přepíše i histogramy z `002`. **ověřit** hodnotu před spuštěním (kontrola serveru v 5.2).
- **Noční report.** Přehled obcí (`select municipality_id, count(*) … group by municipality_id order by cnt desc … limit 10`) čte místo celé tabulky (`ALL`, 60 MB) index (`type = index`, `Using index`, 19 MB); dočasná tabulka a řazení podle počtu zůstávají. Ověřeno jen `EXPLAIN`, report se nespouštěl (zapisuje do `report_runs`).
- **Návrat.** `SET SESSION sql_mode = ''; SET SESSION lock_wait_timeout = 10; ALTER TABLE owners DROP INDEX IF EXISTS idx_owners_municipality;` vrátí plány do stavu po migraci 2 (filtr podle id obce zase `ALL` + filesort, správně díky histogramu). Prostý `ANALYZE TABLE` do `mysql.*_stats` s `use_stat_tables = PREFERABLY_FOR_QUERIES` nic nezapsal, statistiky InnoDB indexu zmizí s ním; vracet nic dalšího není třeba. Při jiné hodnotě `use_stat_tables` (viz Statistiky) zapsal statistiky všech sloupců; ty mohou zůstat, nebo je smazat a znovu spustit `002` (postup v 5.2). Histogramy z `002` po návratu nechat (filtr podle id obce je bez indexu zase potřebuje).
- **Doba.** V Dockeru celý soubor asi 1 s (stavba indexu), `ANALYZE` pod 0,1 s; opakované spuštění 0,2 s s poznámkou `1061`. **ověřit** na produkci (disk, zátěž, velikost tabulky); online, čtení i zápis běží dál.

Ověření (jako správce): `SHOW INDEX FROM owners WHERE Key_name = 'idx_owners_municipality';` vrátí 4 řádky (`municipality_id`, `last_name`, `first_name`, `id`). `EXPLAIN select o.* from owners o where o.municipality_id = 1 order by o.last_name, o.first_name, o.id limit 0, 50` ukazuje `type = ref`, `key = idx_owners_municipality` a bez `Using filesort`; `EXPLAIN select count(*) from owners o where o.municipality_id = 1` `type = ref`, `Using index`. Výpis vlastníků bez filtru má dál `key = idx_owners_name` (5.2). Textový filtr: viz **ověřit** u textového filtru výše. Pokud filtr podle id obce ukazuje `type = ALL` nebo `key = idx_owners_name`, index chybí nebo optimalizátor nemá jeho statistiky: spustit znovu soubor (nebo jen `ANALYZE TABLE owners;`).
