# Evidence vlastníků

Interní webová aplikace pro evidenci vlastníků pozemků. Výsek většího interního CRM na vlastním PHP frameworku (PHP 7.4 i 8.5, MariaDB 10.11). Všechna data jsou syntetická, generují se při prvním startu databáze.

## Funkce

- **Přehled**: počty záznamů, poslední poznámky, historie, poslední noční report.
- **Vlastníci**: výpis s filtry a hledáním, detail s parcelami a podíly, poznámky, úprava, export do CSV.
- **Parcely**: výpis s filtry a hledáním, vlastníci a čísla LV každé parcely.
- **Obce**: výpis s filtry a odkazy na vlastníky a parcely obce.
- **Noční report**: statistiky do `reports/`, záznam do `report_runs`, odeslání na reportovací službu.

Aplikace nemá přihlášení, uživatel je pevně nastavený v `includes/config.php`.

Další dokumenty:

- [GUIDE.md](GUIDE.md): stručný popis řešení k odevzdání.
- [GOAL.md](GOAL.md): jak postupovat s celým CRM.
- [MIGRATION.md](MIGRATION.md): přechod serveru na PHP 8.5 a DB migrace.
- [TODO.md](TODO.md): úkoly a známé problémy.
- [OPENQUESTIONS.md](OPENQUESTIONS.md): otázky pro zadavatele.
- [CHANGELOG.md](CHANGELOG.md): změny.

## Spuštění

Potřebujete Docker (Docker Desktop) a volné porty 8074, 8085 a 33061.

```bash
docker compose up -d
```

- První start trvá déle: stažení a sestavení image několik minut, generování dat 0,5 až 2 minuty (6 250 obcí, 500 000 vlastníků, 800 000 parcel, 1 200 000 vlastnických vztahů).
- Příkaz počká, až bude databáze připravená.
- Obě PHP služby sdílejí kód z adresáře projektu, úpravy se projeví bez restartu.

| Služba | Adresa |
| --- | --- |
| Aplikace na PHP 7.4 | http://localhost:8074 |
| Aplikace na PHP 8.5 | http://localhost:8085 |
| MariaDB | `localhost:33061`, databáze `legacy_crm`, uživatel `crm` / heslo `crm` |

DB migrace (indexy) se lokálně samy neprovedou. Po prvním startu a po každém `docker compose down -v` je spusťte ručně, v pořadí podle názvu:

```bash
for f in sql/migrations/*.sql; do docker compose exec -T db mariadb -ucrm -pcrm legacy_crm --show-warnings < "$f"; done
```

Noční report (v provozu ho spouští cron):

```bash
docker compose exec php74 php cron/nightly_report.php
docker compose exec php85 php cron/nightly_report.php
```

Report se uloží do `reports/`, logy aplikace jsou v `logs/`.

Zastavení: `docker compose down`. Smazání včetně databáze: `docker compose down -v` (při dalším startu se data vygenerují znovu).

## Testy

Testy jsou záchranná síť pro migraci: zachycují chování na PHP 7.4 a stejně procházejí na 8.5.

- Data v DB nemění. Zapisují jen to co běžný provoz: `access_log`, `logs/export.log`, `logs/slow_query.log`.
- Počítají s čerstvě vygenerovanou databází (konkrétní vlastníci, IČO 01256748, poznámka 60000 u vlastníka 420001). Změněná data vrátíte `docker compose down -v` a novým `docker compose up -d`.
- Kontrola „žádná PHP chyba“ najde fatální chyby a chyby syntaxe. Varování aplikace potlačuje (`error_reporting` v `includes/config.php`), ta kontrola neuvidí.

| Sada | Co ověřuje |
| --- | --- |
| `unit` (PHPUnit, `tests/Unit`) | Pomocné funkce z `includes/functions.php`, skládání SQL v `Database` a filtry, hledání a formátování v `Section`. Bez databáze. |
| `smoke` (PHPUnit, `tests/Smoke`) | HTTP požadavky na běžící aplikaci (`APP_URL`): všechny stránky, filtry, hledání, řazení, stránkování, export, našeptávač, odkazy z výpisu obcí, pořadí vlastníků, neplatné vstupy a pokusy o SQL injection a XSS. Bez `APP_URL` se přeskočí. |
| Playwright (`e2e/`) | Totéž v prohlížeči (Chromium) plus JavaScript a klikání: menu, filtry, našeptávač, potvrzení mazání poznámky (vždy se zruší, POST je zablokovaný), stažení CSV. Kontroluje i chyby v konzoli. |

Nejdřív spusťte aplikaci a DB migrace (viz *Spuštění*). Smoke sada trvá asi 20 s na verzi, Playwright asi půl minuty pro obě verze.

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

Všechny sady jsou zelené na PHP 7.4 i 8.5 (unit 175, smoke 83, Playwright 21 testů na každou verzi).

- `composer test` a `composer test:smoke` spouští PHPUnit pod PHP hostitele (v image `php74` Composer není). Unit testy na 7.4 proto pouštějte přes `docker compose run` výše.
- Smoke sada nenačítá kód aplikace, lze ji pustit z PHP libovolné verze proti kterékoli aplikaci.
- Adresy pro Playwright mění proměnné `PHP74_URL` a `PHP85_URL`.

### Statická analýza (PHPStan)

PHPStan (úroveň 2) kontroluje celý kód kromě `vendor/`, `e2e/` a `tests/`, jako PHP 7.4 i jako PHP 8.5. Spouští se z hostitele (PHP 8.x):

```bash
vendor/bin/phpstan analyse                         # PHP 7.4 (phpstan.neon)
vendor/bin/phpstan analyse -c phpstan-php85.neon   # PHP 8.5
```

- Verzi určuje `phpVersion` v konfiguraci.
- `phpstan-baseline.neon` obsahuje jen proměnné šablon z `index.php` (`$db`, `$menu`…).
- `tests/phpstan-bootstrap.php` definuje konstantu `ROOT` (`includes/config.php` se připojuje k DB).

## Migrace na PHP 8.5 – rozhodnutí

- **Jeden kód pro 7.4 i 8.5.** PHP na serveru se upgraduje nezávisle na nasazení kódu. Proto jen syntaxe a funkce ze 7.4. Návrat = přepnout PHP zpět.
- **Chování se nemění.** Kde se PHP 8 chová jinak, kód napodobuje 7.4. Známé chyby se při migraci neopravovaly, opravené jsou zvlášť, stejně na obou verzích.
- **PDO jako v 7.4.** Výchozí hodnoty PHP 8 (výjimky při chybě SQL, čísla jako `int`) jsou v `Database::connect()` vrácené zpět, místo úprav na desítkách míst.
- **Přibalená knihovna `plugin/minify/`** má jedinou úpravu (`$s{$i}` → `$s[$i]`), bez ní se na PHP 8 nenačte žádná stránka.
- **Ověření:** testy a PHPStan na obou verzích, dočasně `E_ALL` s logem do souboru a porovnání HTML stránek mezi 7.4 a 8.5 (shodné až na čas v patičce).

Seznam změn a nastavení serveru je v [MIGRATION.md](MIGRATION.md).

## Zrychlení výpisu vlastníků

Doba stránky se neměřila. Důkazem je počet SQL dotazů z patičky a `EXPLAIN` / `ANALYZE` (MariaDB 10.11, testovací data). U každé změny se ověřilo, že HTML výpisů je před a po na 7.4 i 8.5 shodné (kromě zamýšlené změny).

- **Příčina:** N+1 dotazy. Pro každý z 50 řádků stránky se zvlášť načítal název obce a počet parcel, s filtrem obce i parcely.
  - Dotazy na název obce byly levné (`const` podle primárního klíče).
  - Na `ownerships.owner_id` chyběl index, takže dotazy na počet parcel a na parcely prošly pokaždé 1,2 mil. řádků.
- **Oprava:**
  - `Section::setRowsPrepareFunction()`: výpis zavolá funkci jednou se všemi řádky stránky, data se načtou dávkově (`in (…)`).
  - Index `idx_ownerships_owner`, migrace [`001`](sql/migrations/001_ownerships_owner_id.sql) (nasazení v [MIGRATION.md](MIGRATION.md), kapitola 5).
- **Výsledek:** místo 50 (s filtrem obce 100) průchodů tabulkou `ownerships` čte stránka 2 × 120 řádků z indexu.

| Stránka | SQL dotazů před | po |
| --- | --- | --- |
| výpis bez filtru (i typ, neoslovovat, hledání, řazení, další strany) | 103 | 5 |
| s filtrem obce | 153 | 6 |

| Dotaz (`EXPLAIN`) | před | po |
| --- | --- | --- |
| počet parcel vlastníků (`ownerships`) | po řádcích: `ALL`, 1 196 520 řádků | dávka: `range idx_ownerships_owner`, 120 řádků, `Using index` |
| parcely vlastníků s filtrem obce (`ownerships`) | po řádcích: `ALL` + filesort | dávka: `range idx_ownerships_owner`, 120 řádků; `parcels`, `municipalities`: `eq_ref PRIMARY` |
| názvy obcí (`municipalities`) | po řádcích: `const PRIMARY` | dávka: `range PRIMARY` |
| hlavní dotaz a `count(*)` | beze změny (`ALL` + filesort, resp. `index PRIMARY`) | beze změny |

### Výpis parcel a obcí

- **Příčina:** stejné N+1 (vlastníci každé parcely, počet parcel každé obce). Samotné dotazy jsou levné, problém je jejich počet.
- **Oprava:**
  - Parcely: vlastníci všech parcel stránky jedním dávkovým dotazem.
  - Obce: počet parcel je poddotaz v hlavním dotazu, MariaDB ho počítá jen pro řádky stránky.
  - Nový index není potřeba.

| Stránka | SQL dotazů před | po |
| --- | --- | --- |
| výpis parcel | 54 | 5 |
| výpis obcí | 55 | 5 |

| Dotaz (`EXPLAIN`) | před | po |
| --- | --- | --- |
| vlastníci parcel (`ownerships`) | po řádcích: `ref idx_ownerships_parcel`, 1 řádek | dávka: `range idx_ownerships_parcel`, 73 řádků; `owners`: `eq_ref PRIMARY` |
| počet parcel obce (`parcels`) | po řádcích: `ref idx_parcels_municipality`, 128 řádků | poddotaz: `ref idx_parcels_municipality`, 50 × 128 řádků (`r_loops=50`) |

Proč u obcí poddotaz (přečtené řádky na stránku):

| Varianta | Dotazů | Přečtené řádky |
| --- | --- | --- |
| (a) dávkový dotaz | 2 | 6 250 + 6 400 |
| (b) poddotaz v hlavním dotazu (zvoleno) | 1 | 6 250 + 6 400 |
| (c) `left join … group by` | 1 | 6 250 + 800 000, navíc rozbije počet řádků a stránkování |

U parcel join nejde (parcela má až 17 vlastníků, rozbil by počet a `limit`), proto dávka.

### Odkaz „vlastníci“ podle id obce

- **Příčina:** odkaz posílal název obce a filtr hledal podřetězec. „Lhota“ našla 20 000 vlastníků (i Horní, Dolní… Lhota) místo 240.
- **Oprava:** odkaz posílá id obce, výpis má skrytý filtr `Section::addHiddenFilter()` s popiskem „Obec: název ×“. Staré odkazy s názvem fungují dál.
- **Výsledek:** přesně vlastníci obce, ověřeno proti SQL. Stránka má 7 SQL dotazů.
  - Plán: `owners` `ALL` + filesort (500 000 řádků), „V obci“ `range idx_ownerships_owner`, název obce `const PRIMARY`. Index na obec vlastníka se tehdy záměrně nepřidal (změnil by pořadí vlastníků se stejným jménem), přidán až později (*Index na obec vlastníka*).

### Odkaz „parcely“ podle id obce

- Výpis obcí má i odkaz na parcely obce, se stejným skrytým filtrem.
- Oba odkazy mají vzhled malého tlačítka.
- Stránka má 6 SQL dotazů. Plán: `parcels` `ref idx_parcels_municipality` (128 řádků, bez filesortu), `municipalities` `const PRIMARY`. Nový index není potřeba.

### Pořadí vlastníků se stejným příjmením

- **Příčina:** vlastníci parcely se řadili jen podle příjmení. Vlastníci se stejným příjmením (a jejich čísla LV) měli neurčené pořadí, mezi načteními se lišilo.
- **Oprava:** řazení `o.last_name, o.first_name, o.id, os.id`.
- **Výsledek:** pořadí jednoznačné, opakovaná načtení bajtově shodná. Plán dotazu stejný: `range idx_ownerships_parcel` + filesort asi 70 řádků na stránku.

Podobné shody v detailu vlastníka a při řazení podle sloupce jsou v TODO (*Chyby v logice*).

### Výchozí řazení vlastníků

- **Příčina:** řazení `concat(last_name, ' ', first_name)` nemůže použít index. Každá strana řadila všech 500 tisíc vlastníků a pořadí vlastníků se stejným jménem bylo neurčené.
- **Oprava** (rozhodnutí zadavatele, otázka 5 v [OPENQUESTIONS.md](OPENQUESTIONS.md)):
  - Řazení `o.last_name, o.first_name, o.id`.
  - Index `idx_owners_name (last_name, first_name, id)` a histogramy filtrovaných sloupců, migrace [`002`](sql/migrations/002_owners_name.sql) ([MIGRATION.md](MIGRATION.md), 5.2).
  - S vyplněným hledáním se řadí `o.id + 0`: stejné pořadí, ale index se nepoužije (u vzácného jména by četl skoro celý index).
- **Výsledek:**
  - Pořadí jmen se nezměnilo (ověřeno na celé tabulce), uvnitř stejného jména se nově řadí podle id.
  - Výsledek je s indexem i bez něj stejný, index jen zrychluje.
  - Kde index nepomáhá: vzdálené strany (od zhruba 75.), hledání a filtry na sloupce bez indexu dál procházejí tabulku.
  - Histogramy jsou nutné: bez nich by index zhoršil hledání IČO a filtr podle id obce.
  - Zhoršení: filtr „neoslovovat“ na 5. až 60. straně čte víc stránek (až 21×), podle `ANALYZE` je ale do 30. strany rychlejší.

| Dotaz (strana 50 řádků) | před | po |
| --- | --- | --- |
| bez filtru, 1. strana | `ALL` + filesort, 500 000 řádků, 3 983 stránek | `index idx_owners_name`, 50 řádků, 153 stránek |
| typ F / neoslovovat, 1. strana | `ALL` + filesort, 500 000 řádků | `index idx_owners_name`, 3 522 / 1 682 řádků |
| neoslovovat 10. / 30. / 60. strana | `ALL` + filesort, 5 339 / 8 345 / 12 856 stránek | **víc:** `index idx_owners_name`, 43 / 132 / 265 tis. stránek |
| hledání, IČO, vzdálené strany | `ALL` + filesort | beze změny (`ALL` + filesort) |
| `count(*)` bez filtru | `index PRIMARY`, 3 878 stránek | `index idx_owners_name`, 1 059 stránek |

„Stránky“ jsou čtení stránek z buffer poolu, měřítko práce bez měření času.

### Index na obec vlastníka

- **Příčina:** `owners.municipality_id` neměl index. Filtr podle obce procházel všech 500 tisíc vlastníků.
- **Oprava:** index `idx_owners_municipality (municipality_id, last_name, first_name, id)`, migrace [`003`](sql/migrations/003_owners_municipality.sql) ([MIGRATION.md](MIGRATION.md), 5.3). Kód se nemění.
  - Složený, aby se strana četla rovnou v pořadí. Prostý index na obec by u velké obce řadil všechny její vlastníky na každé straně.
  - Přidán až po jednoznačném výchozím řazení, dřív by změnil pořadí stránek.
- **Výsledek:**
  - Filtr podle id obce: 1. strana 156 stránek místo 3 985, počet 6 místo 3 833.
  - Textový filtr obce zrychlil: „Lhota“ celá 1. strana asi 16× levnější.
  - Velmi široký textový filtr (např. jedno písmeno) je s indexem pomalejší. Hranice je asi 40 % všech vlastníků. Text kratší než 2 znaky se od v2.9.0 ignoruje.
  - Řazení podle „Založeno“ není jednoznačné, s indexem se pořadí shodných časů může změnit (ověření v MIGRATION.md, 5.3).

| Dotaz | před | po |
| --- | --- | --- |
| id obce, 1. strana | `ALL` + filesort, 500 000 řádků, 3 985 stránek | `ref idx_owners_municipality`, 50 řádků, 156 stránek, bez filesortu |
| id obce, `count(*)` | `ALL`, 3 833 stránek | `ref idx_owners_municipality`, `Using index`, 6 stránek |
| textový filtr „Lhota“, `count(*)` | `owners` `ALL`, `municipalities` `eq_ref`, 1 008 993 stránek | `municipalities` `ALL`, `owners` `ref idx_owners_municipality`, 825 stránek |
| textový filtr „e“ / „a“ (80 až 93 % vlastníků), celá stránka | 1,01 mil. stránek | **horší:** 1,23 / 1,44 mil. stránek |

## Struktura

- `index.php` → `includes/config.php` → `includes/loader.php`: vstup, konfigurace, načtení funkcí a tříd, připojení DB.
- `admin_*_main.php`: moduly (Přehled, Vlastníci, Parcely, Obce), adresa `index.php?url=<modul>/<akce>`.
- `admin_owners/`: výpis, detail, úprava a export vlastníků.
- `class/`: `Database` a `Section` (obecný výpis s filtry, řazením a stránkováním).
- `ajax_municipalities.php`: našeptávač obcí.
- `cron/nightly_report.php`: noční report.
- `layout/`, `css/`, `js/`: šablona stránky, styly, skripty.
- `plugin/minify/`: přibalená knihovna pro minifikaci CSS a JS.
- `sql/migrations/`: DB migrace.
- `docker/`: Dockerfile pro PHP 7.4 a 8.5, schéma a generátor dat.
- `tests/` (PHPUnit), `e2e/` (Playwright), `phpstan*.neon` (statická analýza).
