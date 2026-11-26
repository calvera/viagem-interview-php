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
- [x] ✔ Hodnota parametru v URL jako pole shodí na PHP 8 stránku `TypeError`, na 7.4 jen varování: `?url[]=x` (`index.php:7`, `trim()`), `?sort[]=x` (`class/Section.php:168`, `isset()` s polem jako klíčem), `ajax_municipalities.php?q[]=x` (`ajax_municipalities.php:7`, `trim()`) a POST `duedate[]=x` v úpravě vlastníka (`admin_owners/owners_edit.php:34`, `strtotime()`). Parametry, které se při migraci přetypovávají `(string)` (filtry, hledání, směr řazení, formuláře), nespadnou, ale na obou verzích z pole udělají text „Array“ (viz *Chyby v logice*). Jen podvržené požadavky, aplikace takové odkazy netvoří. Opraveno v *Chyby v logice*: `get_param()` / `post_param()` pole ignorují a vrátí výchozí hodnotu. (v2.7.6, 2026-11-26)
- [ ] Ověřit na produkci locale `cs_CZ.UTF-8`: pokud je nainstalované, PHP 7.4 převádí desetinná čísla na text s čárkou (doba výpočtu v nočním reportu `cron/nightly_report.php:127` a `:160`, `logs/slow_query.log` z `class/Database.php:130`), PHP 8 vždy s tečkou. Do databáze PDO čísla předává nezávisle na locale. V Dockeru locale není, obě verze se chovají stejně. Viz `MIGRATION.md`.

### Bezpečnost

- [x] SQL injection: hodnoty filtrů a hledání se vkládají do SQL přes `str_replace('{value}', …)` (`class/Section.php`, `getWhereArray()`). Ověřeno: hledání `zzzqq%' or 'a' like 'a` v obcích vrátilo všech 6 250 obcí místo 0; filtr okresu s `<>` v hodnotě obešel i vazbu v `getWhere()` (levá strana podmínky zůstala v SQL). Hodnoty filtrů a hledání jsou parametry (`{value}` v definici filtru se nahradí `:f_<id>`, hledání `'%' . hodnota . '%'`), `getWhere()` už navázaný parametr nechá beze změny. Výsledky pro běžné hodnoty jsou stejné. (v2.7.7, 2026-11-26)
- [x] SQL injection: `ajax_municipalities.php` vkládá parametr `q` přímo do dotazu. Ověřeno (`q=Lh'` chyba SQL, `zz%' or 'a' like 'a` vrátil obce). `q` je parametr. (v2.7.7, 2026-11-26)
- [x] SQL injection: `owners_detail.php` maže poznámku podle neošetřeného `$_GET['delete_note']`. Ověřeno (`delete_note=x'` chyba SQL). Mazání je POST s `(int)` ID jako parametrem. (v2.7.7, 2026-11-26)
- [x] Mazání poznámky: GET bez CSRF tokenu a bez kontroly, že poznámka patří zobrazenému vlastníkovi. Maže se formulářem (POST) s CSRF tokenem a jen `where id = :note_id and owner_id = :owner_id`. CSRF token (`csrf_token()`, `csrf_field()`, `csrf_check()`) mají všechny formuláře: přidání a smazání poznámky, úprava vlastníka. Bez platného tokenu přesměrování s chybou, formulář úpravy pak ukáže hodnoty z DB, ne odeslané. (v2.7.7, 2026-11-26)
- [x] Mazání poznámky: `?delete_note[]=x` (`admin_owners/owners_detail.php:5-7`, přímo `$_GET`, ne přes `get_param()`) vytvoří SQL `id = Array`, dotaz potichu selže, ale hláška oznámí „Poznámka byla smazána.“ Patří k ošetření `delete_note` výše. Ověřeno. Hláška o smazání jen tehdy, když se řádek opravdu smazal, jinak chyba. (v2.7.7, 2026-11-26)
- [x] Ošetření vstupů: podvržený POST s `duedate[]=x` nebo `note[]=x` ve formuláři úpravy vlastníka `post_param()` ignoruje jako chybějící pole, takže se uloží prázdný termín oslovení, resp. poznámka `NULL` (`admin_owners/owners_edit.php:27`, `:29`, `:34`, `:38`). Pole ve vstupu by mělo být chybou validace. Ověřeno (bez chyby validace). Pole v kterémkoli poli formuláře je chyba validace (`post_has_array()`), nic se neuloží. (v2.7.7, 2026-11-26)
- [x] XSS: hodnoty filtrů a hledaný výraz se vypisují neescapované (`Section::showFilters()`, `showInfo()`). Ověřeno (`search=<script>…` na stránce beze změny). Escapované přes `h()`. (v2.7.7, 2026-11-26)
- [x] XSS: text poznámky se vypisuje přes `nl2br()` bez `h()` (`owners_detail.php`, výpis poznámek); na přehledu přes `trim_text()` bez `h()`. Ověřeno dočasnou poznámkou s HTML. `nl2br(h(…))`, na přehledu `h(trim_text(…))`. Při kontrole šablon navíc escapovaný kód katastrálního území v detailu vlastníka (text z DB); ostatní neescapované výstupy jsou čísla z DB nebo texty z kódu. (v2.7.7, 2026-11-26)
- [x] XSS: našeptávač vkládá popisky přes `innerHTML` (`js/app.js`). Ověřeno testem Playwright s podvrženou odpovědí. Popisky přes `textContent`. (v2.7.7, 2026-11-26)
- [ ] Aplikace nemá přihlášení, uživatel je natvrdo `operator.a` (`APP_USER`). Nechává se otevřené: přihlášení (kdo jsou uživatelé, odkud se berou, role) je rozhodnutí o produktu, ne oprava chyby.
- [ ] Ošetření vstupů: pole, které v POST úpravy vlastníka chybí úplně, se uloží jako prázdné (`title`, `street`… `''`, `duedate` a `note` `NULL`; `admin_owners/owners_edit.php:25-35`). Vyžaduje platný CSRF token, tedy ručně upravený požadavek z prohlížeče uživatele aplikace. Chybějící pole by mělo být chybou validace nebo ponechat hodnotu z DB.
- [ ] CSV formula injection: export (`admin_owners/owners_export.php:46-62`) zapisuje hodnoty z DB beze změny; text začínající `=`, `+`, `-` nebo `@` (jméno, ulice, e-mail…) Excel po otevření vyhodnotí jako vzorec. Řešení: takovým hodnotám předřadit `'`; pozor na telefon `+420…`, který by se tím změnil.
- [ ] Nepoužívaný kód skládá SQL z neošetřených hodnot: `Database::delete()` (`class/Database.php:289-292`, `$id` přímo v SQL), `prepareData()`, `escape()` a `db_escape()` (`includes/functions.php:3-12`, `addslashes`). Dnes je nic nevolá; odstranit, aby je nikdo nepoužil.

### Výkon

- [ ] N+1 dotazy ve výpisu vlastníků: název obce a počet parcel pro každý řádek, s filtrem obce navíc všechny parcely vlastníka (`owners_list_row()`).
- [ ] Filtr obce ve výpisu vlastníků. Odkaz „vlastníci“ z výpisu obcí (`admin_municipalities/municipalities_section.php:8`) posílá název obce a filtr hledá podřetězec (`m.name like %…%`), takže „Lhota“ najde 20 000 vlastníků místo 240 (i Horní, Dolní… Lhota). Sloupec „V obci“ pro každý řádek načte všechny parcely vlastníka a filtruje je v PHP přes `mb_stripos` (`owner_parcels_in_municipality()`), opět podle podřetězce. Změřeno na PHP 8.5: bez filtru 8,1 s, „Lhota“ 21,3 s, „Horní Lhota“ (2 000 vlastníků) 24,0 s, zdržuje tedy práce na řádku, ne počet nalezených. Řešení: odkaz přes id (`f_municipality_id`, skrytý filtr v `Section`), „V obci“ spočítat v SQL podle `p.municipality_id`. Co filtr znamená (obec vlastníka, nebo vlastníci parcel v obci), viz `OPENQUESTIONS.md`, otázka 1.
- [ ] N+1 dotazy ve výpisu parcel (vlastníci každé parcely) a obcí (počet parcel každé obce).
- [ ] Chybí index `ownerships.owner_id` (1,2 mil. řádků).
- [ ] Výchozí řazení vlastníků `concat(last_name, ' ', first_name)` nevyužije index.

### Chyby v logice

- [x] Export: limit 20 000 se uplatní před vynecháním vlastníků se zákazem oslovení, export pak může mít méně řádků, než odpovídá filtru. Vlastníci se zákazem oslovení se vynechávají už v SQL (bez parametru `all`), limit tak počítá jen exportované řádky; počet vynechaných pro řádek „Vynecháno N…“ dává samostatný `count(*)` a týká se všech vlastníků odpovídajících filtru. (v2.7.6, 2026-11-26)
- [x] `owner_parcels()` vrací pro vlastníka bez parcel `null` místo prázdného pole. Opraveno při migraci na PHP 8 (`count(null)` je tam `TypeError`); na 7.4 se chování nemění.
- [x] `trim_text()` (`includes/functions.php`) porovnává délku v bajtech (`strlen`), ale zkracuje ve znacích (`mb_substr`). Krátký text s diakritikou dostane „…“, i když se nic neuřízlo: `trim_text('žluťoučký', 10)` → `žluťoučký…`. Projeví se na přehledu u poznámek. Délka se porovnává ve znacích (`mb_strlen`). (v2.7.6, 2026-11-26)
- [x] `format_datetime('0000-00-00')` (datum bez času) nebere jako prázdnou hodnotu a vrátí `30. 11. -0001 00:00`. Bere ho jako prázdné stejně jako `format_date()`. (v2.7.6, 2026-11-26)
- [x] `Database::getWhere()` z hodnoty v uvozovkách odstraní všechny apostrofy, ne jen okrajové: `o.last_name = 'O'Brien'` naváže `OBrien`. Odstraňují se jen okrajové apostrofy. (v2.7.6, 2026-11-26)
- [x] Vlastnický vztah s `share_den = 0` (`ownerships`, schéma to dovoluje): výpočet `výměra × share_num / share_den` v detailu vlastníka (`admin_owners/owners_detail.php:63`, `:135`) a ve sloupci „V obci“ výpisu s filtrem obce (`includes/functions.php:174`). Na PHP 7.4 skryté varování a výměra podílu „inf ha“ (při nulové výměře „nan m²“), na PHP 8.x nezachycená `DivisionByZeroError` – detail a výpis s filtrem obce spadnou. Testovací data `share_den = 0` nemají. Výpočet je ve funkci `share_area()`: podíl se jmenovatelem 0 přispívá do součtů nulou a „Výměra podílu“ ukazuje „–“, sloupec „Podíl“ dál `num/den`. Kontrola dat na produkci je doporučená, ne nutná (viz `MIGRATION.md`). (v2.7.6, 2026-11-26)
- [x] Pole v parametrech GET/POST se po přetypování `(string)` (migrace na PHP 8) hledají nebo ukládají jako text „Array“, na 7.4 i 8.x; dříve je 7.4 ignorovalo (hodnota `null`). Např. `index.php?url=owners&search[]=x` hledá „Array“ (`class/Section.php:112`), POST `title[]=x` při úpravě vlastníka uloží titul „Array“ (`admin_owners/owners_edit.php:19`). Jen ručně vytvořené požadavky. `get_param()` / `post_param()` pro pole vrátí výchozí hodnotu (žádný parametr aplikace pole není), jako by parametr chyběl. (v2.7.6, 2026-11-26)
- [x] `admin_owners/owners_edit.php:81`: ručně odeslaný `duedate` jako `0.5` se po chybě validace zobrazí ve formuláři prázdný místo `0.5` (`(int)'0.5' == 0`, dříve `'0.5' == 0` neplatilo). Jen ručně vytvořený požadavek, prohlížeč posílá datum `RRRR-MM-DD`. Prázdné pole se ukáže jen pro nulové datum z DB (`'0000-00-00'`). (v2.7.6, 2026-11-26)
- [ ] Obří hodnota `page` (např. `page=99999999999999999999`): `intval()` dá `PHP_INT_MAX`, `($page - 1) * $limit` je pak `float` a v SQL vznikne `limit 4.6116860184274E+20, 50` – chyba SQL a prázdný výpis místo poslední strany (`class/Section.php:47`, `:198`). Jen ručně zadaná adresa.
- [ ] `format_share('0', '0')` vrátí „celý“ (`num == den`), přitom „Výměra podílu“ vedle ukazuje „–“ jako neplatný podíl (`includes/functions.php:101-105`).

### Ostatní

- [x] Přidat testy (zatím žádné nejsou) – viz úkol 0 v zadání.
