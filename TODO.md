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
1. [ ] **Převést aplikaci na PHP 8.5** při zachování kompatibility s 7.4 – viz sekce *Kompatibilita s PHP 8*.
   - [ ] Unit, smoke i Playwright testy (úkol 0) zelené na PHP 7.4 i 8.5.
   - [ ] Ověřit noční report v Dockeru na obou verzích (`php74`, `php85`).
   - [ ] Statická analýza (PHPStan) pro PHP 7.4 i 8.5, ať se chyby najdou i mimo prošlé stránky.
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

- [ ] ✔ `includes/functions.php:51` – `function match()` je od PHP 8.0 rezervované slovo, parse error shodí celou aplikaci. Přejmenovat (např. `regex_match`) včetně volání v `is_ico()` a `owners_functions.php`.
- [ ] ✔ `admin_owners/owners_detail.php:50` – vnořený ternární operátor bez závorek je v PHP 8 fatal error. Nahradit voláním `owner_name($owner)`.
- [ ] ✔ `case 'F';` se středníkem (`includes/functions.php`, `cron/nightly_report.php`) – deprecated, nahradit dvojtečkou.
- [ ] ✔ Přetypování `(integer)` / `(boolean)` – deprecated, nahradit `(int)` / `(bool)`.
- [ ] ✔ `cron/nightly_report.php:62` – operátor backtick je deprecated, použít `gethostname()`.
- [ ] `includes/functions.php` – `get_magic_quotes_gpc()` v PHP 8 neexistuje.
- [ ] `owners_detail.php` – `count($owner)` na `null` (neexistující vlastník) hází v PHP 8 `TypeError`.
- [ ] `cron/nightly_report.php` – `is_resource($ch)` je v PHP 8 pro curl vždy `false` (`CurlHandle` je objekt), ping proto vždy hlásí nedostupnost.
- [ ] `strftime()` v exportu je deprecated (PHP 8.1), nahradit `date()`.
- [ ] Dynamické vlastnosti v `Database`, `QueryResult` a `Section` jsou od PHP 8.2 deprecated – deklarovat je.

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
- [ ] `owner_parcels()` vrací pro vlastníka bez parcel `null` místo prázdného pole.
- [ ] `trim_text()` (`includes/functions.php`) porovnává délku v bajtech (`strlen`), ale zkracuje ve znacích (`mb_substr`). Krátký text s diakritikou dostane „…“, i když se nic neuřízlo: `trim_text('žluťoučký', 10)` → `žluťoučký…`. Projeví se na přehledu u poznámek. Zachyceno unit testem.
- [ ] `format_datetime('0000-00-00')` (datum bez času) nebere jako prázdnou hodnotu a vrátí `30. 11. -0001 00:00`. Zachyceno unit testem.
- [ ] `Database::getWhere()` z hodnoty v uvozovkách odstraní všechny apostrofy, ne jen okrajové: `o.last_name = 'O'Brien'` naváže `OBrien`. Zachyceno unit testem.

### Ostatní

- [x] Přidat testy (zatím žádné nejsou) – viz úkol 0 v zadání.
