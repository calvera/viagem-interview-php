# Evidence vlastníků

Interní webová aplikace pro evidenci vlastníků pozemků: vlastníci, jejich parcely a podíly, poznámky k oslovování, export do CSV a noční report. Je postavená na vlastním PHP frameworku (PHP 7.4 i 8.5, MariaDB 10.11) a je výsekem většího interního CRM.

Všechna data jsou syntetická, generují se při prvním startu databáze.

## Funkce

- **Přehled** – počty záznamů, poslední poznámky, historie a poslední noční report.
- **Vlastníci** – výpis s filtrem podle obce (textové pole s našeptávačem hledá část názvu, odkaz z výpisu obcí vybere přesně jednu obec podle id), typu a zákazu oslovení, hledání podle jména nebo IČO, řazení podle příjmení, jména a id (nebo data založení), detail s parcelami a podíly, poznámky, úprava údajů, export do CSV.
- **Parcely** – výpis s filtrem podle obce (textové pole s našeptávačem hledá část názvu, odkaz „parcely“ z výpisu obcí vybere přesně jednu obec podle id) a druhu pozemku, hledání podle čísla parcely nebo kódu katastrálního území; vlastníci parcely seřazení podle příjmení, jména a id, čísla LV v pořadí vlastníků.
- **Obce** – výpis s filtrem podle okresu a kraje a dvěma odkazy ve tvaru malých tlačítek: „vlastníci“ na vlastníky s obcí (`f_municipality_id`, přesně tato obec; nad výpisem vlastníků popisek „Obec: název ×“, sloupec „V obci“ s parcelami vlastníka v této obci) a „parcely“ na parcely obce (`f_municipality_id`, popisek „Obec: název ×“, počet se rovná sloupci „Parcel“).
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
| `unit` (PHPUnit, `tests/Unit`) | Pomocné funkce z `includes/functions.php` (formátování data, výměry, podílu, jména vlastníka, IČO, escapování, ošetření buněk CSV proti vzorcům, parametry GET/POST, CSRF token…), skládání SQL v `Database::getWhere()`, parametry filtrů a hledání v `Section` (hodnota nikdy není v textu SQL), skrytý filtr `Section::addHiddenFilter()` a formátování buněk výpisu s `NULL`. Bez databáze. |
| `smoke` (PHPUnit, `tests/Smoke`) | HTTP požadavky na běžící aplikaci (`APP_URL`): každá stránka vrátí 200, správný nadpis a žádnou fatální chybu ani chybu syntaxe; vlastníci s filtry, hledáním, řazením a stránkováním, detail, úprava (jen GET), export CSV, parcely, obce, obří číslo strany (ukáže poslední stranu), našeptávač (JSON), 404, parametry jako pole (`url[]`, `search[]`, `sort[]`, `q[]`), vynechání vlastníků se zákazem oslovení v exportu, obec a počet parcel ve výpisu vlastníků (i vlastník bez parcel) a hodnoty „V obci“ s filtrem obce, odkaz „vlastníci“ z výpisu obcí a filtr podle id obce (počet vlastníků, popisek, „V obci“, neplatné id se ignoruje, export), odkaz „parcely“ a výpis parcel podle id obce (počet, popisek, druh pozemku, neplatné id se ignoruje), vlastníci a LV konkrétní parcely (i parcela bez vlastníka) a pořadí vlastníků se stejným příjmením a jejich LV, vlastníci se stejným jménem ve výpisu vlastníků podle id a počet parcel konkrétní obce, hodnoty jako `x' or '1'='1` ve filtrech, hledání a našeptávači nic nenajdou, `<script>` v hledání a filtru je escapovaný, GET `?delete_note=` nic nesmaže. Bez `APP_URL` se přeskočí. |
| Playwright (`e2e/`) | Totéž v prohlížeči (Chromium) a navíc JavaScript a klikání: menu, filtry, řazení, stránkování, našeptávač obcí (popisek se vloží jako text, ne HTML), potvrzovací dialog mazání poznámky (vždy se zruší), stažení CSV. Po každém testu ověří, že v konzoli prohlížeče není chyba a na stránce není výpis fatální chyby ani chyby syntaxe. Test mazání poznámky navíc v prohlížeči zablokuje každý POST, takže poznámku nesmaže, ani kdyby potvrzovací dialog nefungoval. |

Nejdřív spusťte aplikaci (`docker compose up -d`, viz výše) a DB migrace (viz *Zrychlení výpisu vlastníků*). Smoke sada trvá asi 20 s na verzi, Playwright asi půl minuty pro obě verze.

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

Všechny sady jsou zelené na PHP 7.4 i 8.5 (unit 137, smoke 74, Playwright 20 testů na každou verzi).

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
- Index `idx_ownerships_owner` na `ownerships.owner_id` jako SQL migrace [`sql/migrations/001_ownerships_owner_id.sql`](sql/migrations/001_ownerships_owner_id.sql), nasazení v [MIGRATION.md](MIGRATION.md), kapitola 5 (přehled všech DB migrací). Lokálně je po prvním startu Dockeru (a po každém `docker compose down -v`) spusťte ručně, v pořadí podle názvu:

  ```bash
  for f in sql/migrations/*.sql; do docker compose exec -T db mariadb -ucrm -pcrm legacy_crm --show-warnings < "$f"; done
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

Hlavní dotaz a `count(*)` se touto opravou nezměnily (stav před *Výchozím řazením vlastníků* níže):

```
explain select count(*) from owners o;                                  type=index  key=PRIMARY  rows=495922  Using index
explain select o.* from owners o order by concat(o.last_name, ' ', o.first_name) limit 0, 50;
                                                                        type=ALL  rows=495922  Using filesort
explain … left join municipalities m … where m.name like '%Lhota%' …;   o: type=ALL  rows=495922
```

- **Výchozí řazení** podle `concat(…)` nemůže použít index, každá strana řadí všech 500 tisíc vlastníků. Změna řazení ale změní, kteří vlastníci jsou na které straně: 500 tis. vlastníků má jen 1 070 různých jmen a pořadí uvnitř jména je neurčené. Proto zůstalo beze změny jako otázka 5 v [OPENQUESTIONS.md](OPENQUESTIONS.md); po rozhodnutí viz *Výchozí řazení vlastníků* níže.
- **`count(*)`** je jeden průchod indexem za stránku a výpis ukazuje přesný počet, proto beze změny.
- **Filtr obce** hledá podřetězec přes všechny vlastníky (`ALL`). Odkaz z výpisu obcí filtruje podle id (viz *Odkaz „vlastníci“ podle id obce* níže), ale i tak prochází všechny vlastníky. Index na `owners.municipality_id` záměrně není: změní plán dotazu a výpis „Lhota“ pak na 1. straně ukáže jiné vlastníky se stejným jménem (ověřeno dočasným indexem). Filtr je samostatná položka v TODO (*Výkon*) a otázka 1 v OPENQUESTIONS.
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

- **Parcely:** `parcels_list_prepare()` (`Section::setRowsPrepareFunction()`) načte vlastníky všech parcel stránky jedním dotazem `parcels_owners()` (`where os.parcel_id in (…)`, v2.7.10 `order by os.parcel_id, o.last_name`). Každá parcela měla vlastníky ve stejném pořadí jako dříve, kromě vlastníků se stejným příjmením, jejichž pořadí (a s ním pořadí čísel LV) bylo neurčené už dříve. Opraveno samostatně: dotaz řadí `o.last_name, o.first_name, o.id, os.id`, pořadí je jednoznačné (viz *Pořadí vlastníků se stejným příjmením*). HTML buňky dál skládá PHP (`owner_name()`, `h()`, `format_share()`).
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
- **Parcely:** shodné po seřazení vlastníků uvnitř buňky „Vlastníci“ a čísel v buňce „LV“. Pořadí vlastníků se stejným příjmením je neurčené už před změnou: dvě načtení stejné adresy před změnou se lišila (parcela 19020, tři vlastníci „Kratochvíl“ v jiném pořadí). V této položce se neopravovala, opravená je samostatně (*Pořadí vlastníků se stejným příjmením*).

### Odkaz „vlastníci“ podle id obce

Odkaz u obce dřív posílal název (`f_municipality=Lhota`) a filtr hledal podřetězec, takže „Lhota“ našla 20 000 vlastníků (i Horní, Dolní… Lhota) místo 240. Teď posílá id (`f_municipality_id=1`) a výpis vlastníků má skrytý filtr `o.municipality_id = :id` (`Section::addHiddenFilter()`): jen číslice, hodnota jako parametr, popisek s názvem obce a × ve formuláři filtrů před prvním polem, hodnota ve skrytém poli; × zruší filtr a vrátí výpis na 1. stranu. „V obci“ se s tímto filtrem počítá v SQL podle `p.municipality_id`. Zda má odkaz místo obce vlastníka ukazovat vlastníky parcel v obci, je otázka 1 v [OPENQUESTIONS.md](OPENQUESTIONS.md).

Stránka má 7 SQL dotazů: záznam do `access_log`, `count(*)`, řádky stránky, názvy obcí, počty parcel, „V obci“ a název obce pro popisek (textový filtr „Lhota“ 6). Plány:

```
explain select count(*) from owners o where o.municipality_id = 1;            type=ALL  rows=496497  Using where   (ANALYZE: r_rows=500000)
explain select o.* from owners o where o.municipality_id = 1
  order by concat(o.last_name, ' ', o.first_name) limit 0, 50;                type=ALL  rows=496497  Using where; Using filesort   (ANALYZE: r_rows=500000)
explain select os.owner_id, count(*), sum(1e0 * p.area_m2 * os.share_num / nullif(os.share_den, 0)) from ownerships os
  join parcels p on p.id = os.parcel_id where os.owner_id in (…) and p.municipality_id = 1 group by os.owner_id;
                                                                              os: type=range  key=idx_ownerships_owner;  p: eq_ref PRIMARY
explain select name from municipalities where id = 1;                         type=const  key=PRIMARY
```

Hlavní dotaz a `count(*)` dál procházejí všech 500 tisíc vlastníků, na `owners.municipality_id` index není. Záměrně se nepřidal: změní plán dotazu s filtrem obce a tím pořadí vlastníků se stejným jménem na stranách (viz *Co zůstává*). Index je samostatná položka v TODO, až po jednoznačném řazení (otázka 5).

Ověřeno proti SQL na PHP 7.4 i 8.5: počet vlastníků pro šest obcí (i neexistující id) se rovná `select count(*) from owners where municipality_id = ?`; „V obci“ u všech 240 vlastníků Lhoty (5 stran) odpovídá součtu podílů z `ownerships` a `parcels` (vlastník bez parcely v obci „–“); pro čtyři obce s jedinečným názvem je tabulka s filtrem podle id stejná jako s textovým filtrem; export obsahuje právě vlastníky obce. HTML 31 stránek je před a po změně na PHP 7.4 i 8.5 bajtově shodné (kromě doby vygenerování v patičce): 20 výpisů vlastníků bez nového filtru (filtry, hledání, IČO, řazení, 2. a poslední strana, textový filtr „Lhota“ i s 2. a poslední stranou, řazením, hledáním a typem, „Horní Lhota“), 5 výpisů parcel a 6 výpisů obcí; ve výpisu obcí se liší jen adresa odkazu „vlastníci“ (u všech 150 odkazů na třech stranách id odpovídá kódu obce v řádku). Výjimkou je parcela s hledáním „st. 121“, která se lišila už mezi dvěma načteními před změnou (neurčené pořadí vlastníků, viz výše). Export CSV s filtrem typu a s textovým filtrem obce je také shodný.

### Odkaz „parcely“ podle id obce

Výpis obcí má vedle odkazu „vlastníci“ odkaz „parcely“ (`index.php?url=parcels&f_municipality_id=1`). Výpis parcel čte id stejným skrytým filtrem jako výpis vlastníků (`Section::addHiddenFilter()`, `p.municipality_id = :id`): jen číslice, hodnota jako parametr, popisek „Obec: název ×“, hodnota ve skrytém poli, × zruší filtr a stránku. Oba odkazy mají vzhled malého tlačítka (`a.button.small`) a jsou v `span.buttons`, který je drží v jednom řádku.

Stránka má 6 SQL dotazů: záznam do `access_log`, druhy pozemků, `count(*)`, řádky stránky, vlastníci parcel a název obce pro popisek (bez filtru 5). Plány používají existující index, nový není potřeba:

```
explain select count(*) from parcels p left join municipalities m on m.id = p.municipality_id where p.municipality_id = 1;
p: type=ref  key=idx_parcels_municipality  rows=128  Using index
explain select p.*, m.name … where p.municipality_id = 1 order by p.id asc limit 0, 50;
p: type=ref  key=idx_parcels_municipality  rows=128  Using where   (ANALYZE: r_rows=50, bez filesort)
m: type=const  key=PRIMARY
```

Ověřeno na PHP 7.4 i 8.5: počet parcel pro id 1, 2018, 5256, 6250 se rovná `select count(*) from parcels where municipality_id = ?` i sloupci „Parcel“ (v generovaných datech má každá obec 128 parcel, proto i kombinace s druhem pozemku: Lhota a orná půda 18 podle SQL i stránky); neexistující id 999999 ukáže 0 parcel a popisek „999999“; všech 128 řádků Lhoty (3 strany) má obec Lhota. HTML 62 stránek (31 adres × 2 verze PHP) je před a po změně shodné: 13 výpisů parcel bez nového filtru (výchozí, druh, textový filtr „Lhota“ i s 2. stranou a s neexistujícím druhem (prázdný výpis), hledání, řazení podle katastrálního území a výměry v obou směrech, 2. a poslední strana, neplatné id) po seřazení vlastníků v buňce „Vlastníci“ a čísel v buňce „LV“ (tehdy neurčené pořadí vlastníků se stejným příjmením, opravené v *Pořadí vlastníků se stejným příjmením*), 9 výpisů vlastníků (i s filtrem podle id) bajtově, 9 výpisů obcí bajtově kromě buňky s odkazy. Počet SQL dotazů v patičce je na všech těchto stránkách stejný jako před změnou.

### Pořadí vlastníků se stejným příjmením

Buňka „Vlastníci“ ve výpisu parcel řadila vlastníky jen podle příjmení (`order by os.parcel_id, o.last_name`), pořadí vlastníků se stejným příjmením bylo neurčené a s ním i pořadí čísel v buňce „LV“ (skládá se v pořadí vlastníků). Dvě načtení `index.php?url=parcels&search=st.%20121` ukázala tři vlastníky „Kratochvíl“ parcely 19020 pokaždé v jiném pořadí (viz TODO); 13 adres výpisu načtených 10× (5× na každé verzi PHP) dalo u 6 adres 2 až 4 různé verze HTML.

`parcels_owners()` teď řadí `os.parcel_id, o.last_name, o.first_name, o.id, os.id`. Jméno a id vlastníka určují pořadí jednoznačně; `os.id` je poslední klíč, protože tabulka `ownerships` nemá unikátní klíč `(parcel_id, owner_id)` a stejný vlastník by mohl být u parcely dvakrát (v datech 0 případů, osiřelé vztahy také 0). Příjmení zůstává prvním klíčem, takže pořadí různých příjmení se nemění.

```
explain select os.parcel_id, o.id, … where os.parcel_id in (…50 id…) order by os.parcel_id, o.last_name, o.first_name, o.id, os.id;
os: type=range  key=idx_ownerships_parcel  rows=93  Using index condition; Using temporary; Using filesort   (ANALYZE: r_rows=73)
o:  type=eq_ref key=PRIMARY
```

Plán je stejný jako s `o.last_name`: dočasná tabulka a filesort byly i dříve, řadí se asi 70 řádků na stránku.

Ověřeno na PHP 7.4 i 8.5 na 13 adresách výpisu parcel (výchozí výpis, druh, textový filtr „Lhota“ i s 2. stranou, filtr podle id obce, hledání „st. 121“ i poslední strana, řazení podle katastrálního území a výměry v obou směrech, 2. a poslední strana): 3 načtení na každé verzi jsou bajtově shodná a 7.4 se rovná 8.5. Po seřazení vlastníků a čísel LV uvnitř buněk se stránky rovnají stavu před změnou, mění se jen pořadí uvnitř shod. Např. parcela 35 má dvě vlastnice „Černá“ s jiným LV: dříve „883, 1589“ (MUDr. Martina), teď „1589, 883“ (Eva před Martinou).

Detail vlastníka (`owner_parcels()`) a řazení výpisů podle sloupce mají podobné shody v klíči řazení, zatím bez projevu; jsou zapsané v TODO (*Chyby v logice*).

### Výchozí řazení vlastníků

Výpis vlastníků i export CSV se ve výchozím stavu řadily `concat(o.last_name, ' ', o.first_name)`. Na výraz nejde použít index, takže každá strana přečetla a seřadila všech 500 tisíc vlastníků, a pořadí vlastníků se stejným jménem (1 070 jmen na 500 tis. vlastníků) bylo neurčené. Podle rozhodnutí zadavatele (otázka 5 v [OPENQUESTIONS.md](OPENQUESTIONS.md), varianta b) se řadí `o.last_name, o.first_name, o.id` (s vyplněným hledáním `o.id + 0`, viz *Hledání* níže) a přibyl index `idx_owners_name (last_name, first_name, id)` jako SQL migrace [`sql/migrations/002_owners_name.sql`](sql/migrations/002_owners_name.sql) (nasazení v [MIGRATION.md](MIGRATION.md), 5.2).

**Pořadí jmen.** Před změnou se porovnalo pořadí celé tabulky: posloupnost jmen (příjmení, jméno) při řazení `concat()` a při řazení podle sloupců je pro všech 1 070 jmen stejná, rozdíl 0. Rozdíl by vznikl jen u osoby, jejíž příjmení je prvním slovem názvu firmy (`concat` řadí „Nový Agro“ před „Nový Zdeněk“, sloupce osobu před firmu), nebo u `first_name = NULL` (`concat` je pak `NULL` a řádek je na začátku). V testovacích datech nic takového není (právnické osoby mají prázdné `first_name` a název začíná slovem jako „Agro“, „Statek“…, které žádná osoba nemá jako příjmení). Uvnitř jména se teď řadí podle id, a to je jediná změna výpisu.

**Plány.** `ANALYZE` hlavního dotazu stránky (MariaDB 10.11), před = řazení `concat()` bez indexu, po = nové řazení s indexem a histogramy. „Stránky“ jsou čtení stránek z buffer poolu (`Innodb_buffer_pool_read_requests`), měřítko práce bez měření času.

| Dotaz (strana 50 řádků) | před | po |
| --- | --- | --- |
| bez filtru, 1. strana | `ALL` + filesort, 500 000 řádků, 3 983 stránek | `index` `idx_owners_name`, 50 řádků, 153 stránek |
| bez filtru, 2. / 10. / 50. strana | `ALL` + filesort, 500 000 řádků | `index`, 100 / 500 / 2 500 řádků |
| bez filtru, 100., 5000. a poslední (10 000.) strana | `ALL` + filesort | beze změny (`ALL` + filesort), optimalizátor přechází na filesort mezi 70. a 80. stranou |
| typ P, 1. strana | `ALL` + filesort | `index`, 50 řádků |
| typ P, poslední strana (834.) | `ALL` + filesort | beze změny |
| typ F / neoslovovat, 1. strana | `ALL` + filesort | `index`, 3 522 / 1 682 řádků |
| typ P + neoslovovat, 1. / poslední (28.) strana | `ALL` + filesort | `index` 1 682 řádků / beze změny (díky histogramům) |
| neoslovovat 10. / 30. / 60. strana | `ALL` + filesort, 5 339 / 8 345 / 12 856 stránek, ANALYZE 145 / 145 / 182–266 ms | **víc stránek:** `index`, 14 / 44 / 88 tis. řádků, 43 / 132 / 265 tis. stránek, ANALYZE 44 / 103 / 215–291 ms |
| neoslovovat 70., 100., 200. a poslední (342.) strana | `ALL` + filesort | beze změny (optimalizátor přechází mezi 60. a 70. stranou) |
| typ F + neoslovovat 20. / 50. strana, od 60. strany | `ALL` + filesort, 6 835 / 11 348 stránek | `index`, 97 / 230 tis. stránek, ANALYZE 93 / 187 ms proti 179 / 191 ms; od 60. strany beze změny |
| hledání („Nov“, „Agro Lhota“, „Svobodová“ i 3. strana, bez výsledku, s typem) | `ALL` + filesort, asi 4 tis. stránek | beze změny (`o.id + 0`, viz *Hledání*) |
| hledání IČO | `ALL` + filesort | beze změny (díky histogramu `ico`) |
| textový filtr obce „Lhota“, 1. strana | `o`: `ALL` + filesort, `m`: `eq_ref` | `o`: `index`, 1 219 řádků |
| textový filtr obce „Zadní Ves v Údolí“ (80 vlastníků) | `ALL` + filesort, 1,47 mil. stránek | `index`, 292 tis. řádků, 1,46 mil. stránek (bez zlepšení) |
| filtr podle id obce (`f_municipality_id`) | `ALL` + filesort | beze změny (díky histogramu `municipality_id`) |

```
analyze select o.* from owners o order by concat(o.last_name, ' ', o.first_name) asc limit 0, 50;
before: type=ALL    rows=496497  r_rows=50   Using filesort        (Handler_read_rnd_next=500001)
analyze select o.* from owners o order by o.last_name, o.first_name, o.id asc limit 0, 50;
after:  type=index  key=idx_owners_name  rows=500000  r_rows=50   (Handler_read_next=49, žádný filesort)
```

`count(*)` se nemění, je to dál jeden průchod: bez filtru a s hledáním čte nový index (`Using index`, 1 059 stránek indexu místo 3 878 primárního klíče), s typem a filtry obce celou tabulku (`ALL`) jako dříve.

S filtrem čte index na prvních stranách o něco víc stránek z buffer poolu než dřívější průchod tabulkou (asi 4 tis. stránek), protože pro každý přečtený záznam indexu dohledá řádek podle id: typ F 1. strana 10 576 (2,7×), „neoslovovat“ 1. strana 5 052 (1,3×), 2. až 4. strana 9 097 / 13 806 / 17 281 (2,2× až 3,9×), typ P + „neoslovovat“ 1. strana 5 052 (1,3×). Doba podle `ANALYZE` je tam mnohem nižší (7 až 57 ms místo 140 až 380 ms, bez řazení 500 tis. řádků). Filtr „neoslovovat“ (17 091 vlastníků, 3,4 %, sám i s typem F) čte na 5. až 60. straně mnohonásobně víc: 4,7× až 21× (60. strana 265 tis. stránek místo 13 tis.). Doba je tam do 30. strany nižší, na 40. až 60. straně zhruba stejná, od 70. strany se plán vrací k průchodu tabulkou. S chladným buffer poolem na produkci by mohla být horší (náhodná čtení místo jednoho průchodu tabulkou).

Hodnoty ANALYZE v ms jsou jen orientační, stejný plán se mezi běhy liší až dvakrát; porovnání stojí na plánu, řádcích a stránkách.

**Kde index nepomáhá.** Vzdálené strany (od zhruba 75.) mají velký OFFSET a optimalizátor správně volí průchod tabulkou a seřazení; přeskočené řádky se čtou tak jako tak. Filtry na sloupce bez indexu (typ na poslední straně, id obce, IČO) a hledání dál procházejí celou tabulku. Textový filtr obce s málo vlastníky („Zadní Ves v Údolí“) čte přes index 292 tis. řádků, stránek zhruba stejně jako dříve (join s obcí byl i dříve pro každý řádek).

**Hledání.** S indexem pro řazení četlo hledání vzácného jména skoro celý index: „Svobodová“ 408 tis. řádků a 1,2 mil. stránek místo 4 tis., hledání bez výsledku 1,5 mil. Podmínku `like '%…%'` optimalizátor odhadnout neumí (histogramy na `last_name`, `first_name` ověřeny, nepomohou) a předpokládá, že 50 výsledků najde hned na začátku indexu. S vyplněným hledáním proto `owners_build_section()` řadí `o.last_name, o.first_name, o.id + 0`: pořadí je stejné (id jako číslo), ale výraz nejde číst z indexu, takže plán je průchod tabulkou a filesort jako dříve. Hint `IGNORE INDEX FOR ORDER BY (idx_owners_name)` by udělal totéž, ale na databázi bez migrace je chyba SQL (`1176 Key 'idx_owners_name' doesn't exist`), kód by pak bez indexu nefungoval. Běžná hledání („Nov“) tím o zrychlení z indexu přišla, horší než dříve ale není žádné. `Section` se nemění.

**Proč histogramy.** S indexem, ale bez histogramů optimalizátor předpokládal, že podmínce `o.municipality_id = 1` nebo `o.ico = '…'` odpovídá každý řádek (`filtered = 100`), a zvolil čtení indexu v pořadí jmen: filtr podle id obce přečetl 180 tis. až 500 tis. řádků (0,5 až 1,5 mil. stránek), hledání IČO 500 tis. (1,5 mil. stránek), místo 4 tis. stránek průchodu tabulkou. Prostý `ANALYZE TABLE` histogramy v MariaDB 10.11 nesbírá (`use_stat_tables = PREFERABLY_FOR_QUERIES`), migrace proto spouští `ANALYZE TABLE owners PERSISTENT FOR COLUMNS (type, ico, municipality_id, do_not_contact) INDEXES ()`. Stejně typ P se zákazem oslovení (1 367 vlastníků) na 28. straně: bez histogramů index, 500 tis. řádků a 1,5 mil. stránek, s histogramy průchod tabulkou jako dříve (7 950 stránek). S nimi optimalizátor zná selektivitu (`filtered = 0.02` u obce) a pro tyto filtry zůstane u dnešního plánu. Po opravě hledání jsou tedy histogramy pořád potřeba. Histogramy na `last_name` a `first_name` hledání nepomohly (ověřeno), proto v migraci nejsou.

**Export CSV** používá stejnou `Section` (`getRows(false)`, limit 20 000) a stejné řazení (s hledáním také `o.id + 0`): plán beze změny (`o`: `ALL` + filesort, `m`: `eq_ref`), i s filtrem typu, hledáním a id obce. Pořadí jmen je stejné, uvnitř jména podle id, takže u exportu nad 20 000 řádků je na hranici limitu jiný výběr vlastníků se stejným jménem.

**Chování.** Ověřeno na PHP 7.4 i 8.5 na 20 adresách výpisu vlastníků (výchozí výpis, 2., 5000. a poslední strana, typ P i 2. a poslední strana, typ F, neoslovovat, hledání „Nov“ a „Svobodová“ i 3. strana, IČO, textový filtr „Lhota“ i 2. strana, id obce 1 i 5. strana a 2018, řazení podle založení v obou směrech) a 4 exportech (výchozí, typ P, id obce, hledání): posloupnost jmen (příjmení, jméno) je na každé stránce stejná jako před změnou, vlastníci se stejným jménem jsou ve vzestupném pořadí id, mimo tabulku řádků (počet, stránkování, filtry) je HTML bajtově shodné. Řazení podle založení, IČO a id obce 2018 jsou bajtově shodné celé. Dvě načtení na každé verzi a 7.4 proti 8.5 jsou bajtově shodná, s indexem i bez něj (řazení je jednoznačné). Výpisy parcel (6 adres) a obcí (4 adresy) jsou bajtově shodné se stavem před změnou. Stránka má dál 5 SQL dotazů (s filtrem obce 6, podle id 7).

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
