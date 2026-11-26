# Evidence vlastníků

Interní webová aplikace pro evidenci vlastníků pozemků: vlastníci, jejich parcely a podíly, poznámky k oslovování, export do CSV a noční report. Je postavená na vlastním PHP frameworku (PHP 7.4 i 8.5, MariaDB 10.11) a je výsekem většího interního CRM.

Všechna data jsou syntetická, generují se při prvním startu databáze.

## Funkce

- **Přehled** – počty záznamů, poslední poznámky, historie a poslední noční report.
- **Vlastníci** – výpis s filtrem podle obce, typu a zákazu oslovení, hledání podle jména nebo IČO, detail s parcelami a podíly, poznámky, úprava údajů, export do CSV.
- **Parcely** – výpis s filtrem podle obce a druhu pozemku, hledání podle čísla parcely nebo kódu katastrálního území.
- **Obce** – výpis s filtrem podle okresu a kraje, odkaz na vlastníky v obci.
- **Noční report** – souhrnné statistiky do `reports/`, záznam do `report_runs` a odeslání na reportovací službu.

Aplikace nemá přihlášení, uživatel je pevně nastavený v `includes/config.php`.

Stručný popis řešení k odevzdání je v [GUIDE.md](GUIDE.md). Známé problémy jsou v [TODO.md](TODO.md), otázky pro zadavatele v [OPENQUESTIONS.md](OPENQUESTIONS.md), změny v [CHANGELOG.md](CHANGELOG.md), přechod serveru na PHP 8.5 v [MIGRATION.md](MIGRATION.md).

## Spuštění

Potřebujete Docker (Docker Desktop) a volné porty 8074, 8085 a 33061.

```bash
docker compose up -d
```

První spuštění trvá déle: stáhnou se image, sestaví se PHP kontejnery a databáze si vygeneruje testovací data (6 250 obcí, 500 000 vlastníků, 800 000 parcel, 1 200 000 vlastnických vztahů). Příkaz počká, až bude databáze připravená. Samotné generování dat trvá podle stroje zhruba 0,5 až 2 minuty, stažení a sestavení image při prvním startu několik minut navíc.

| Služba | Adresa |
| --- | --- |
| Aplikace na PHP 7.4 | http://localhost:8074 |
| Aplikace na PHP 8.5 | http://localhost:8085 |
| MariaDB | `localhost:33061`, databáze `legacy_crm`, uživatel `crm` / heslo `crm` |

Obě PHP služby používají stejný kód (adresář projektu je do kontejnerů připojený), takže úpravy se projeví bez restartu.

Noční report (v provozu ho spouští cron):

```bash
docker compose exec php74 php cron/nightly_report.php
docker compose exec php85 php cron/nightly_report.php
```

Report se uloží do `reports/`, logy aplikace jsou v `logs/`.

Zastavení: `docker compose down`. Smazání včetně databáze: `docker compose down -v` (při dalším startu se data vygenerují znovu).

## Testy

Testy jsou záchranná síť pro migraci na PHP 8.5: zachycují chování na PHP 7.4 a stejně procházejí i na 8.5. Jen čtou, data v DB nemění. Vedlejší účinky jsou stejné jako při běžném provozu: každý požadavek na stránku zapíše řádek do `access_log`, export přidá řádek do `logs/export.log` a pomalé dotazy se zapíšou do `logs/slow_query.log` (logy git ignoruje).

Testy počítají s čerstvě vygenerovanou databází (konkrétní vlastníci, IČO 01256748, poznámka s ID 60000 u vlastníka 420001). Pokud se data změnila, vraťte je příkazem `docker compose down -v` a novým `docker compose up -d`.

Kontrola „žádná PHP chyba“ hledá na stránce výpis fatální chyby a chyby syntaxe (`Fatal error`, `Parse error`, `Uncaught`). Varování a upozornění aplikace sama potlačuje (`error_reporting(E_ERROR | E_PARSE)` v `includes/config.php`), takže je kontrola neuvidí, i když hledá i `Warning:`, `Notice:` a `Deprecated:`.

| Sada | Co ověřuje |
| --- | --- |
| `unit` (PHPUnit, `tests/Unit`) | Pomocné funkce z `includes/functions.php` (formátování data, výměry, podílu, jména vlastníka, IČO, escapování, ošetření buněk CSV proti vzorcům, parametry GET/POST, CSRF token…), skládání SQL v `Database::getWhere()`, parametry filtrů a hledání v `Section` (hodnota nikdy není v textu SQL) a formátování buněk výpisu s `NULL`. Bez databáze. |
| `smoke` (PHPUnit, `tests/Smoke`) | HTTP požadavky na běžící aplikaci (`APP_URL`): každá stránka vrátí 200, správný nadpis a žádnou fatální chybu ani chybu syntaxe; vlastníci s filtry, hledáním, řazením a stránkováním, detail, úprava (jen GET), export CSV, parcely, obce, obří číslo strany (ukáže poslední stranu), našeptávač (JSON), 404, parametry jako pole (`url[]`, `search[]`, `sort[]`, `q[]`), vynechání vlastníků se zákazem oslovení v exportu, obec a počet parcel ve výpisu vlastníků (i vlastník bez parcel) a hodnoty „V obci“ s filtrem obce, vlastníci a LV konkrétní parcely (i parcela bez vlastníka) a počet parcel konkrétní obce, hodnoty jako `x' or '1'='1` ve filtrech, hledání a našeptávači nic nenajdou, `<script>` v hledání a filtru je escapovaný, GET `?delete_note=` nic nesmaže. Bez `APP_URL` se přeskočí. |
| Playwright (`e2e/`) | Totéž v prohlížeči (Chromium) a navíc JavaScript a klikání: menu, filtry, řazení, stránkování, našeptávač obcí (popisek se vloží jako text, ne HTML), potvrzovací dialog mazání poznámky (vždy se zruší), stažení CSV. Po každém testu ověří, že v konzoli prohlížeče není chyba a na stránce není výpis fatální chyby ani chyby syntaxe. Test mazání poznámky navíc v prohlížeči zablokuje každý POST, takže poznámku nesmaže, ani kdyby potvrzovací dialog nefungoval. |

Nejdřív spusťte aplikaci (`docker compose up -d`, viz výše) a migraci indexu (viz *Zrychlení výpisu vlastníků*). Smoke sada trvá asi 20 s na verzi, Playwright asi půl minuty pro obě verze.

PHP 7.4:

```bash
# unit testy uvnitř kontejneru s PHP 7.4
docker compose run --rm --no-deps php74 vendor/bin/phpunit --testsuite unit

# smoke testy – z hostitele (stačí libovolné PHP s curl) nebo uvnitř kontejneru
APP_URL=http://localhost:8074 vendor/bin/phpunit --testsuite smoke
docker compose exec -e APP_URL=http://localhost php74 vendor/bin/phpunit --testsuite smoke

# Playwright (Node.js), první spuštění s instalací
cd e2e
npm install
npx playwright install chromium
npx playwright test --project=php74
```

PHP 8.5:

```bash
docker compose run --rm --no-deps php85 vendor/bin/phpunit --testsuite unit
APP_URL=http://localhost:8085 vendor/bin/phpunit --testsuite smoke
cd e2e && npx playwright test --project=php85
```

Všechny sady jsou zelené na PHP 7.4 i 8.5 (unit 122, smoke 48, Playwright 19 testů na každou verzi).

Skripty `composer test` a `composer test:smoke` spouští PHPUnit pod PHP hostitele (v image `php74` Composer není). Pro unit testy na PHP 7.4 proto použijte příkaz s `docker compose run` výše.

Smoke sada záměrně nenačítá kód aplikace (`tests/bootstrap.php` načte jen autoload), takže ji lze pustit z PHP libovolné verze proti kterékoli z obou aplikací. Adresy pro Playwright lze změnit proměnnými `PHP74_URL` a `PHP85_URL`.

### Statická analýza (PHPStan)

PHPStan (úroveň 2) kontroluje celý kód aplikace kromě `vendor/`, `e2e/` a `tests/`, i stránky, kam testy nedojdou. Stejný kód se analyzuje jako PHP 7.4 a jako PHP 8.5:

```bash
vendor/bin/phpstan analyse                         # PHP 7.4 (phpstan.neon)
vendor/bin/phpstan analyse -c phpstan-php85.neon   # PHP 8.5
```

Spouští se z hostitele (PHP 8.x), verzi, pro kterou se kód kontroluje, určuje `phpVersion` v konfiguraci. `phpstan-baseline.neon` obsahuje jen proměnné ze šablon (`$db`, `$menu`…), které definuje `index.php` a PHPStan je v includovaném souboru nevidí. `tests/phpstan-bootstrap.php` definuje konstantu `ROOT` (`includes/config.php` se načíst nedá, připojuje se k databázi).

## Migrace na PHP 8.5 – rozhodnutí

- **Jeden kód pro 7.4 i 8.5.** Nasazuje se nahráním souborů a PHP na serveru se upgraduje nezávisle, takže kód musí fungovat před přepnutím i po něm. Používá se jen syntaxe a funkce dostupné v 7.4 (žádné `match`, enumy, `str_contains`…). Návrat při problému je přepnutí PHP zpět, kód se nevrací.
- **Chování se nemění.** Kde se PHP 8 chová jinak, kód napodobuje chování 7.4, i když není ideální (např. vnořený ternární operátor v detailu vlastníka je uzávorkovaný tak, jak ho vyhodnocovalo 7.4; návrh v TODO na `owner_name()` by změnil jméno fyzické osoby s titulem bez křestního jména). Známé chyby (`trim_text`, `format_datetime('0000-00-00')`, apostrofy v `getWhere`, pole v parametrech, `duedate` jako `0.5` a další z *Chyby v logice* v TODO.md) se při migraci neopravovaly; opravené jsou zvlášť po migraci, stejně na obou verzích.
- **PDO jako v 7.4.** PHP 8 změnilo dvě výchozí hodnoty PDO: chyby SQL vyhazují výjimku a čísla z MySQL chodí jako `int`. Obojí je v `Database::connect()` nastavené zpět (`ERRMODE_SILENT`, `ATTR_STRINGIFY_FETCHES`), místo úprav na desítkách míst, která s chováním 7.4 počítají (`=== '1'`, `if (!$res)`).
- **Porovnání řetězce s číslem** (`$r['duedate'] == 0`) se v PHP 8 změnilo; `(int)$r['duedate'] == 0` dává na obou verzích výsledek jako 7.4.
- **Přibalená knihovna `plugin/minify/`** má jedinou úpravu (`$s{$i}` → `$s[$i]`), bez ní se na PHP 8 nenačte žádná stránka.
- **Ověření:** testy a PHPStan na obou verzích; navíc se dočasně zapnulo `E_ALL` s logem do souboru a vygenerované stránky se porovnaly mezi 7.4 a 8.5 (shodné až na čas v patičce). Apache log varování PHP 8 typu Deprecated nezapíše (úroveň `notice` pod výchozím `LogLevel warn`), proto log do souboru.

Co a proč se změnilo a co nastavit na serveru je v [MIGRATION.md](MIGRATION.md).

## Zrychlení výpisu vlastníků

Doba stránky se neměřila. Důkazem jsou počet SQL dotazů z patičky stránky a `EXPLAIN` / `ANALYZE` dotazů (MariaDB 10.11, 500 tis. vlastníků, 1,2 mil. vlastnických vztahů).

### Příčina: N+1 dotazy a chybějící index

`owners_list_row()` pro každý z 50 řádků stránky zjišťoval název obce a počet parcel, s filtrem obce navíc všechny parcely vlastníka. Dotaz na počet parcel a dotaz na parcely procházely celou tabulku `ownerships`, protože na `owner_id` chyběl index:

```
explain select count(*) from ownerships where owner_id = 123;
type=ALL  rows=1196520  Using where               (ANALYZE: r_rows=1200000)

explain select … from ownerships os left join parcels p … left join municipalities m …
  where os.owner_id = 123 order by m.name, …;
os: type=ALL  rows=1196520  Using where; Using temporary; Using filesort
```

Název obce (`where id = …`) je `const`, levný, ale také jeden dotaz na řádek.

### Oprava

- `Section::setRowsPrepareFunction()`: funkce, kterou výpis zavolá jednou se všemi řádky stránky. `owners_list_prepare()` v ní načte názvy obcí, počty parcel a s filtrem obce parcely pro „V obci“ třemi dotazy `in (…)`. Sloupec „V obci“ se dál počítá v PHP stejným `mb_stripos` (porovnání `LIKE` s kolací `utf8mb4_czech_ci` by mohlo najít jiné obce).
- Index `idx_ownerships_owner` na `ownerships.owner_id` jako SQL migrace [`sql/migrations/2026-11-26_ownerships_owner_id.sql`](sql/migrations/2026-11-26_ownerships_owner_id.sql), nasazení v [MIGRATION.md](MIGRATION.md), kapitola 5. Lokálně ji po prvním startu Dockeru (a po každém `docker compose down -v`) spusťte ručně:

  ```bash
  docker compose exec -T db mariadb -ucrm -pcrm legacy_crm --show-warnings < sql/migrations/2026-11-26_ownerships_owner_id.sql
  ```

### Před a po

| Stránka | SQL dotazů před | po |
| --- | --- | --- |
| výpis bez filtru (i typ, neoslovovat, hledání, řazení, další strany) | 103 | 5 |
| s filtrem obce (`f_municipality=Lhota`, `Horní Lhota`) | 153 | 6 |

Hledání IČO najde jeden řádek, mělo 5 dotazů před i po.

Po opravě jsou na stránce: záznam do `access_log`, `count(*)`, řádky stránky, názvy obcí, počty parcel, s filtrem obce parcely. Dávkové dotazy pro 50 vlastníků stránky:

```
explain select owner_id, count(*) from ownerships where owner_id in (…50 id…) group by owner_id;
bez indexu: type=ALL    rows=1196520  Using where; Using temporary; Using filesort
s indexem:  type=range  key=idx_ownerships_owner  rows=120  Using where; Using index   (ANALYZE: r_rows=120)

explain select … from ownerships os left join parcels p … where os.owner_id in (…50 id…) order by os.owner_id, m.name, …;
bez indexu: os: type=ALL    rows=1196520
s indexem:  os: type=range  key=idx_ownerships_owner  rows=120  Using index condition
            p, m: eq_ref PRIMARY

explain select id, name from municipalities where id in (…);
type=range  key=PRIMARY
```

Místo 50 (s filtrem obce 100) průchodů tabulkou `ownerships` na stránku čte výpis 2 × 120 řádků z indexu. I bez indexu jde jen o 1 průchod (počty parcel), s filtrem obce o 2.

Chování se nezměnilo: HTML výpisu (informace o počtu, tabulka, stránkování) je před a po změně bajtově shodné na PHP 7.4 i 8.5, bez indexu i s ním. Porovnáno pro výchozí výpis, typ F a P, neoslovovat, hledání jména (i 2. strana), hledání IČO, řazení podle založení vzestupně i sestupně, 2. a poslední stranu, filtr obce „Lhota“ (i 2. a poslední strana, řazení, hledání) a „Horní Lhota“.

### Co zůstává

Hlavní dotaz a `count(*)` se nezměnily:

```
explain select count(*) from owners o;                                  type=index  key=PRIMARY  rows=495922  Using index
explain select o.* from owners o order by concat(o.last_name, ' ', o.first_name) limit 0, 50;
                                                                        type=ALL  rows=495922  Using filesort
explain … left join municipalities m … where m.name like '%Lhota%' …;   o: type=ALL  rows=495922
```

- **Výchozí řazení** podle `concat(…)` nemůže použít index, každá strana řadí všech 500 tisíc vlastníků. Změna řazení ale změní, kteří vlastníci jsou na které straně: 500 tis. vlastníků má jen 1 070 různých jmen a pořadí uvnitř jména je neurčené. Proto zůstává beze změny jako otázka 5 v [OPENQUESTIONS.md](OPENQUESTIONS.md).
- **`count(*)`** je jeden průchod indexem za stránku a výpis ukazuje přesný počet, proto beze změny.
- **Filtr obce** hledá podřetězec přes všechny vlastníky (`ALL`). Index na `owners.municipality_id` záměrně není: změní plán dotazu a výpis „Lhota“ pak na 1. straně ukáže jiné vlastníky se stejným jménem (ověřeno dočasným indexem). Filtr je samostatná položka v TODO (*Výkon*) a otázka 1 v OPENQUESTIONS.
- N+1 ve výpisu parcel a obcí: viz *Výpis parcel a obcí* níže.

### Výpis parcel a obcí

Stejný postup jako u vlastníků: počet SQL dotazů z patičky a `EXPLAIN` / `ANALYZE`, doba stránky se neměřila.

#### Příčina: N+1 dotazy, plány jsou v pořádku

Výpis parcel načítal vlastníky každé parcely (`parcels_row()`), výpis obcí počet parcel každé obce (`municipalities_row()`), tedy 50 dotazů na stránku navíc. Samotné dotazy jsou levné, problém je jejich počet:

```
explain select o.id, … from ownerships os left join owners o on o.id = os.owner_id where os.parcel_id = 15 order by o.last_name;
os: type=ref  key=idx_ownerships_parcel  rows=1   o: eq_ref PRIMARY
explain select count(*) from parcels where municipality_id = 1;
type=ref  key=idx_parcels_municipality  rows=128  Using index   (ANALYZE: r_rows=128)
```

#### Oprava

- **Parcely:** `parcels_list_prepare()` (`Section::setRowsPrepareFunction()`) načte vlastníky všech parcel stránky jedním dotazem `parcels_owners()` (`where os.parcel_id in (…) order by os.parcel_id, o.last_name`). Každá parcela má vlastníky ve stejném pořadí jako dříve, kromě vlastníků se stejným příjmením, jejichž pořadí (a s ním pořadí čísel LV) bylo neurčené už dříve (viz *Chyby v logice* v TODO). HTML buňky dál skládá PHP (`owner_name()`, `h()`, `format_share()`).
- **Obce:** počet parcel je korelovaný poddotaz v hlavním dotazu výpisu: `(select count(*) from parcels p where p.municipality_id = municipalities.id) as parcels`. MariaDB ho vyhodnotí jen pro řádky stránky (po řazení a `limit`), ne pro všech 6 250 obcí.
- Nový index není potřeba: dávkový dotaz i poddotaz používají existující indexy `idx_ownerships_parcel` a `idx_parcels_municipality`.

#### Před a po

| Stránka | SQL dotazů před | po |
| --- | --- | --- |
| výpis parcel (i filtr druhu a obce, hledání, řazení, další strany) | 54 | 5 |
| výpis obcí (i filtr okresu a kraje, hledání, řazení, další strany) | 55 | 5 |

Poslední strana obcí s filtrem kraje (38 řádků) měla 43 dotazů, teď 5. Po opravě jsou na stránce parcel: `access_log`, druhy pozemků do filtru, `count(*)`, řádky stránky, vlastníci; na stránce obcí: `access_log`, okresy, kraje, `count(*)`, řádky stránky s počty parcel.

```
explain select os.parcel_id, o.id, … from ownerships os left join owners o … where os.parcel_id in (…50 id…) order by os.parcel_id, o.last_name;
os: type=range  key=idx_ownerships_parcel  rows=93  Using index condition; Using temporary; Using filesort   (ANALYZE: r_rows=73)
o:  type=eq_ref key=PRIMARY

analyze select municipalities.*, (select count(*) from parcels p where p.municipality_id = municipalities.id) as parcels
  from municipalities order by name limit 6200, 50;
municipalities: type=ALL  r_rows=6250  Using filesort         (stejně jako hlavní dotaz dříve)
p (DEPENDENT SUBQUERY): type=ref  key=idx_parcels_municipality  r_loops=50  r_rows=128  Using index
```

#### Proč u obcí poddotaz a ne dávka nebo join

Porovnáno `ANALYZE FORMAT=JSON` pro stranu 1, 63 a 125, s výchozím řazením podle názvu i podle okresu (čísla jsou na všech stranách a u obou řazení stejná):

| Varianta | Dotazů | Přečtené řádky na stránku |
| --- | --- | --- |
| (a) dávka `select municipality_id, count(*) from parcels where municipality_id in (…) group by municipality_id` | 2 | 6 250 (obce) + 6 400 z indexu (`range`) |
| (b) korelovaný poddotaz v hlavním dotazu | 1 | 6 250 (obce) + 50 × 128 = 6 400 z indexu (`ref`, `r_loops=50`) |
| (c) `left join parcels … group by m.id` v hlavním dotazu | 1 | 6 250 (obce) + 6 250 × 128 = 800 000 z indexu (`r_loops=6250`) |

- (b) čte stejně jako (a), o dotaz méně a bez pomocné funkce. Poddotaz se vyhodnotí jen pro vypsané řádky i s filtrem (poslední strana „Západní kraj“: `r_loops=38`).
- (c) spočítá parcely všech obcí před řazením a `limit`. Navíc `Section::getCount()` skládá `count(*)` ze stejné tabulky a joinů bez `group by`, takže by výpis ukázal 800 000 místo 6 250 obcí a stránkování by nesedělo.
- U parcel join nejde: parcela má až 17 vlastníků, join dá 1 547 245 řádků místo 800 000 a rozbije `count(*)` i `limit`. `group_concat` by přesunul skládání HTML (`owner_name()`, escapování) do SQL a výstup by nebyl stejný. Proto dávka.

#### Chování se nezměnilo

Část výpisu (informace o počtu, tabulka, stránkování) se uložila před změnou (dvakrát) a po ní, na PHP 7.4 i 8.5. Parcely: výchozí výpis, druh „orná půda“, filtr obce „Lhota“ (i 2. strana), hledání „st. 121“, řazení podle katastrálního území a výměry v obou směrech, 2. a poslední strana. Obce: výchozí výpis, okres, kraj (i poslední strana), hledání, řazení podle kódu, názvu, okresu a kraje, 2. a poslední strana.

- **Obce:** bajtově shodné.
- **Parcely:** shodné po seřazení vlastníků uvnitř buňky „Vlastníci“ a čísel v buňce „LV“. Pořadí vlastníků se stejným příjmením je neurčené už před změnou: dvě načtení stejné adresy před změnou se lišila (parcela 19020, tři vlastníci „Kratochvíl“ v jiném pořadí). Chyba je zapsaná v TODO (*Chyby v logice*), v této položce se neopravuje.

## Struktura

- `index.php` → `includes/config.php` → `includes/loader.php` (načte `includes/functions.php`, `class/Database.php`, `class/Section.php`, připojí DB)
- `admin_*_main.php` – moduly (Přehled, Vlastníci, Parcely, Obce), adresa `index.php?url=<modul>/<akce>`
- `admin_owners/` – výpis, detail, úprava a export vlastníků
- `class/Section.php` – obecný výpis s filtry, hledáním, řazením a stránkováním
- `ajax_municipalities.php` – našeptávač obcí (`?q=`)
- `cron/nightly_report.php` – noční report
- `layout/`, `css/`, `js/` – hlavička a patička stránky, styly, klientské skripty (potvrzení mazání, našeptávač)
- `plugin/minify/` – přibalená knihovna třetí strany pro minifikaci CSS a JS
- `docker/` – Dockerfile pro PHP 7.4 a 8.5, schéma a generátor dat
- `tests/` – PHPUnit testy (`Unit`, `Smoke`), `e2e/` – Playwright testy
- `phpstan.neon`, `phpstan-php85.neon`, `phpstan-baseline.neon` – statická analýza pro PHP 7.4 a 8.5
