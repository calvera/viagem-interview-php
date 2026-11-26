# TODO

## Zadání (technická úloha Viagem)

Omezení ze zadání: nasazuje se nahráním změněných souborů bez build kroku a server se upgraduje nezávisle na kódu. Kód proto musí běžet zároveň na PHP 7.4 i 8.5 a nesmí za běhu potřebovat Composer ani jiný build (Composer slouží jen pro vývojové nástroje). Testy žádné nejsou, tým má 2–3 vývojáře a vývoj pro byznys nesmí stát.

0. [x] **Testy jako záchranná síť pro migraci** – napsat teď, nechat projít na PHP 7.4 (zachytí dnešní chování), po migraci stejné testy spustit na 8.5. Jen čtení, žádný test nemění data v DB. (v2.7.4, 2026-11-26)
   - [x] **PHPUnit – konfigurace:** `phpunit.xml` (sady `unit` a `smoke`, deprecations shodí test), `tests/bootstrap.php` (jen autoload a časová zóna; `includes/functions.php` a `class/Database.php` načítají až unit testy, ne `config.php`, takže smoke sada běží na libovolném PHP), skripty `test` a `test:smoke` v `composer.json`.
   - [x] **PHPUnit – unit testy pomocných funkcí** (`tests/Unit/FunctionsTest.php`): `h`, `format_date`, `format_datetime`, `format_area`, `format_share`, `trim_text`, `owner_type_label`, `owner_name`, `mb_ucFirst`, `mb_lcFirst`, `is_ico`, `select_options`.
   - [x] **PHPUnit – unit testy skládání SQL** (`tests/Unit/DatabaseWhereTest.php`): `Database::getWhere()` bez připojení k DB, parametry, `and`, `like`, kolize jmen parametrů.
   - [x] **PHPUnit – smoke testy přes HTTP** (`tests/Smoke/PagesTest.php`, `APP_URL`): všechny stránky vrátí 200, správný nadpis a žádnou PHP chybu; vlastníci s filtry, hledáním, řazením a stránkováním, detail, úprava (jen GET), export CSV, parcely, obce, našeptávač (JSON), 404.
   - [x] **Playwright – konfigurace** (`e2e/`): vlastní `package.json`, projekty `php74` (`:8074`) a `php85` (`:8085`), fixture, která hlídá chyby v konzoli a PHP chyby v těle stránky, `.gitignore`.
   - [x] **Playwright – navigace a výpisy:** menu a nadpisy, 404, vlastníci (filtr, hledání IČO, řazení, stránkování, detail, formulář úpravy), parcely, obce.
   - [x] **Playwright – JavaScript a interakce:** našeptávač obcí, potvrzovací dialog mazání poznámky (zrušit → poznámka zůstane), stažení CSV exportu.
   - [x] **Ověření a dokumentace:** vše zelené na PHP 7.4; na 8.5 zatím očekávaně červené (`match()`); README sekce „Testy“ s příkazy pro obě verze.
1. [x] **Převést aplikaci na PHP 8.5** při zachování kompatibility s 7.4 – viz sekce *Kompatibilita s PHP 8*. (v2.7.5, 2026-11-26)
   - [x] Unit, smoke i Playwright testy (úkol 0) zelené na PHP 7.4 i 8.5.
   - [x] Ověřit noční report v Dockeru na obou verzích (`php74`, `php85`).
   - [x] Statická analýza (PHPStan) pro PHP 7.4 i 8.5, ať se chyby najdou i mimo prošlé stránky.
2. [ ] **Zrychlit výpis vlastníků** – viz sekce *Výkon*.
   - [ ] Nejdřív změřit: doba stránky a počet SQL dotazů (patička), `EXPLAIN` hlavních dotazů, s filtrem obce i bez něj.
   - [ ] Opravit podle měření (N+1 dotazy, index `ownerships.owner_id`, řazení, `count(*)` nad 500 tis. řádků).
   - [ ] Změnu schématu (index) dodat jako SQL migraci s návodem pro nasazení, protože nasazení nemá migrační krok.
   - [ ] Změřit znovu a výsledek před/po zapsat do README.
3. [ ] **Popsat, jak naložit s celým CRM** – nejvýše 2 strany (540 tis. řádků, 32 modulů, vlastní framework, bez testů, nasazení nahráním souborů, 2–3 vývojáři).

Odevzdání:

- [ ] Repozitář se smysluplnou historií commitů (jeden commit na jednu změnu).
- [ ] README: jak aplikaci spustit a proč jsem se rozhodl, jak jsem se rozhodl.

## Známé problémy

Z revize kódu (listopad 2026). Položky označené ✔ jsou ověřené (`php -l` na PHP 8.6), ostatní vycházejí ze čtení kódu a před opravou je potřeba je reprodukovat.

### Kompatibilita s PHP 8

- [x] ✔ `includes/functions.php:51` – `function match()` je od PHP 8.0 rezervované slovo, parse error shodí celou aplikaci. Přejmenovat (např. `regex_match`) včetně volání v `is_ico()` a `owners_functions.php`.
- [x] ✔ `admin_owners/owners_detail.php:50` – vnořený ternární operátor bez závorek je v PHP 8 fatal error. ~~Nahradit voláním `owner_name($owner)`~~ – to by změnilo výsledek u fyzické osoby s titulem a bez křestního jména (`Ing. Novák` místo dnešního `Novák`), proto jen závorky podle vyhodnocení v PHP 7.4 (zleva).
- [x] ✔ `case 'F';` se středníkem (`includes/functions.php`, `cron/nightly_report.php`) – deprecated, nahradit dvojtečkou.
- [x] ✔ Přetypování `(integer)` / `(boolean)` – deprecated, nahradit `(int)` / `(bool)`.
- [x] ✔ `cron/nightly_report.php:62` – operátor backtick je deprecated, použít `gethostname()`.
- [x] `includes/functions.php` – `get_magic_quotes_gpc()` v PHP 8 neexistuje.
- [x] `owners_detail.php` – `count($owner)` na `null` (neexistující vlastník) hází v PHP 8 `TypeError`.
- [x] `cron/nightly_report.php` – `is_resource($ch)` je v PHP 8 pro curl vždy `false` (`CurlHandle` je objekt), ping proto vždy hlásí nedostupnost.
- [x] `strftime()` v exportu je deprecated (PHP 8.1), nahradit `date()`.
- [x] Dynamické vlastnosti v `Database`, `QueryResult` a `Section` jsou od PHP 8.2 deprecated – deklarovat je.
- [x] ✔ `plugin/minify/Minify.php` – přístup ke znaku řetězce složenými závorkami (`$css{$i}`) je od PHP 8.0 parse error; aplikace na 8.5 nenačte hlavičku stránky. Nahrazeno `$css[$i]` (jediná úprava přibalené knihovny).
- [x] ✔ `includes/functions.php` – PHP 8.4 má vestavěné `mb_ucfirst()` a `mb_lcfirst()`, vlastní `mb_ucFirst()` / `mb_lcFirst()` (názvy funkcí nerozlišují velikost písmen) jsou fatal error „Cannot redeclare“. Deklarují se jen tam, kde vestavěné chybí.
- [x] ✔ `owners_functions.php` – implicitně nullable parametr `Section $section = null` je od PHP 8.4 deprecated, nahrazeno `?Section`.
- [x] ✔ PDO MySQL od PHP 8.1 vrací s emulovanými prepared statements čísla jako `int`, ne řetězce. Porovnání `do_not_contact === '1'` (detail, úprava, výpis, export) by na 8.x neplatilo. Nastaveno `PDO::ATTR_STRINGIFY_FETCHES`, typy jsou pak stejné jako na 7.4 (řetězce, `NULL` zůstává).
- [x] ✔ Porovnání `$r['duedate'] == 0` (výpis, úprava, export) je na 7.4 pravda pro `'0000-00-00'`, na 8.x nepravda (řetězec vs. číslo); vlastník bez termínu by měl termín „30. 11. -0001“. Nahrazeno `(int)$r['duedate'] == 0`, výsledek je stejný jako na 7.4.
- [x] `count()` na `null` hází v PHP 8 `TypeError`: `owner_parcels()` vrací `null` pro vlastníka bez parcel a detail ho předává do `count()` (viz *Chyby v logice*).
- [x] ✔ `curl_close()` je od PHP 8.5 deprecated (od 8.0 nic nedělá), odstraněno; na 7.4 se handle uvolní na konci funkce.
- [ ] `null` z databáze do vestavěných funkcí – deprecated od 8.1, na PHP 8 skryté přes `error_reporting`, v PHP 9 chyba; testovací data tyto hodnoty nemají, proto je testy ani běh s `E_ALL` nezachytily: `land_type = NULL` do `mb_ucFirst()` (`admin_parcels_main.php:25`, `:35`, `admin_owners/owners_detail.php:131`, `cron/nightly_report.php:122`; na 8.4+ je to vestavěná funkce), `NULL` jako klíč pole (`admin_parcels_main.php:20` `lv_number`, `:35` `land_type`; deprecated v 8.5), `municipality_name = NULL` do `mb_stripos()` (`includes/functions.php:157`), `number_format(null)` (`class/Section.php:354`).
- [x] Předávání `null` do parametrů vestavěných funkcí (`trim`, `strtolower`, `strlen`, `str_replace`, `preg_match`, `htmlspecialchars`) je od PHP 8.1 deprecated, v PHP 9 chyba: parametry filtrů, hledání a řazení (`Section`), formuláře poznámky a úpravy vlastníka, `regex_match()`, poznámka ve výpisu, `h(null)` (dosud skryté operátorem `@`), našeptávač bez parametru `q`.
- [x] ✔ PDO od PHP 8.0 vyhazuje při chybě SQL výjimku (výchozí `ERRMODE_EXCEPTION`, v 7.4 `ERRMODE_SILENT`). Aplikace počítá s návratovou hodnotou `false`: noční report při druhém běhu za den spadl na fatální chybě (duplicitní `run_date`), stejně by dopadl každý chybný dotaz na stránce. Nastaveno `PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT`.
- [x] ✔ `fputcsv()` bez parametru `$escape` je od PHP 8.4 deprecated (export, každý řádek). Předává se výslovně dosavadní výchozí hodnota `'\\'`.
- [x] ✔ Konstanta `PDO::MYSQL_ATTR_INIT_COMMAND` je od PHP 8.5 deprecated, náhrada `Pdo\Mysql::ATTR_INIT_COMMAND` v 7.4 neexistuje. `Database::connect()` použije tu, která je k dispozici.
- [ ] ✔ Hodnota parametru v URL jako pole shodí na PHP 8 stránku `TypeError`, na 7.4 jen varování: `?url[]=x` (`index.php:7`, `trim()`), `?sort[]=x` (`class/Section.php:168`, `isset()` s polem jako klíčem), `ajax_municipalities.php?q[]=x` (`ajax_municipalities.php:7`, `trim()`) a POST `duedate[]=x` v úpravě vlastníka (`admin_owners/owners_edit.php:34`, `strtotime()`). Parametry, které se při migraci přetypovávají `(string)` (filtry, hledání, směr řazení, formuláře), nespadnou, ale na obou verzích z pole udělají text „Array“ (viz *Chyby v logice*). Jen podvržené požadavky, aplikace takové odkazy netvoří; patří k ošetření vstupů (*Bezpečnost*).
- [ ] Ověřit na produkci locale `cs_CZ.UTF-8`: pokud je nainstalované, PHP 7.4 převádí desetinná čísla na text s čárkou (doba výpočtu v nočním reportu `cron/nightly_report.php:127` a `:160`, `logs/slow_query.log` z `class/Database.php:130`), PHP 8 vždy s tečkou. Do databáze PDO čísla předává nezávisle na locale. V Dockeru locale není, obě verze se chovají stejně. Viz `MIGRATION.md`.

### Bezpečnost

- [ ] SQL injection: hodnoty filtrů a hledání se vkládají do SQL přes `str_replace('{value}', …)` (`class/Section.php`, `getWhereArray()`).
- [ ] SQL injection: `ajax_municipalities.php` vkládá parametr `q` přímo do dotazu.
- [ ] SQL injection: `owners_detail.php` maže poznámku podle neošetřeného `$_GET['delete_note']`.
- [ ] Mazání poznámky: GET bez CSRF tokenu a bez kontroly, že poznámka patří zobrazenému vlastníkovi.
- [ ] XSS: hodnoty filtrů a hledaný výraz se vypisují neescapované (`Section::showFilters()`, `showInfo()`).
- [ ] XSS: text poznámky se vypisuje přes `nl2br()` bez `h()` (`owners_detail.php`, výpis poznámek); na přehledu přes `trim_text()` bez `h()`.
- [ ] XSS: našeptávač vkládá popisky přes `innerHTML` (`js/app.js`).
- [ ] Aplikace nemá přihlášení, uživatel je natvrdo `operator.a` (`APP_USER`).

### Výkon

- [ ] N+1 dotazy ve výpisu vlastníků: název obce a počet parcel pro každý řádek, s filtrem obce navíc všechny parcely vlastníka (`owners_list_row()`).
- [ ] N+1 dotazy ve výpisu parcel (vlastníci každé parcely) a obcí (počet parcel každé obce).
- [ ] Chybí index `ownerships.owner_id` (1,2 mil. řádků).
- [ ] Výchozí řazení vlastníků `concat(last_name, ' ', first_name)` nevyužije index.

### Chyby v logice

- [ ] Export: limit 20 000 se uplatní před vynecháním vlastníků se zákazem oslovení, export pak může mít méně řádků, než odpovídá filtru.
- [x] `owner_parcels()` vrací pro vlastníka bez parcel `null` místo prázdného pole. Opraveno při migraci na PHP 8 (`count(null)` je tam `TypeError`); na 7.4 se chování nemění.
- [ ] `trim_text()` (`includes/functions.php`) porovnává délku v bajtech (`strlen`), ale zkracuje ve znacích (`mb_substr`). Krátký text s diakritikou dostane „…“, i když se nic neuřízlo: `trim_text('žluťoučký', 10)` → `žluťoučký…`. Projeví se na přehledu u poznámek. Zachyceno unit testem.
- [ ] `format_datetime('0000-00-00')` (datum bez času) nebere jako prázdnou hodnotu a vrátí `30. 11. -0001 00:00`. Zachyceno unit testem.
- [ ] `Database::getWhere()` z hodnoty v uvozovkách odstraní všechny apostrofy, ne jen okrajové: `o.last_name = 'O'Brien'` naváže `OBrien`. Zachyceno unit testem.
- [ ] Vlastnický vztah s `share_den = 0` (`ownerships`, schéma to dovoluje): výpočet `výměra × share_num / share_den` v detailu vlastníka (`admin_owners/owners_detail.php:63`, `:134`) a ve sloupci „V obci“ výpisu s filtrem obce (`includes/functions.php:159`). Na PHP 7.4 skryté varování a výměra podílu „inf ha“ (při nulové výměře „nan m²“), na PHP 8.x nezachycená `DivisionByZeroError` – detail a výpis s filtrem obce spadnou. Testovací data `share_den = 0` nemají. Před přepnutím na 8.5 ověřit data na produkci (viz `MIGRATION.md`).
- [ ] Pole v parametrech GET/POST se po přetypování `(string)` (migrace na PHP 8) hledají nebo ukládají jako text „Array“, na 7.4 i 8.x; dříve je 7.4 ignorovalo (hodnota `null`). Např. `index.php?url=owners&search[]=x` hledá „Array“ (`class/Section.php:112`), POST `title[]=x` při úpravě vlastníka uloží titul „Array“ (`admin_owners/owners_edit.php:19`). Jen ručně vytvořené požadavky.
- [ ] `admin_owners/owners_edit.php:81`: ručně odeslaný `duedate` jako `0.5` se po chybě validace zobrazí ve formuláři prázdný místo `0.5` (`(int)'0.5' == 0`, dříve `'0.5' == 0` neplatilo). Jen ručně vytvořený požadavek, prohlížeč posílá datum `RRRR-MM-DD`.

### Ostatní

- [x] Přidat testy (zatím žádné nejsou) – viz úkol 0 v zadání.
