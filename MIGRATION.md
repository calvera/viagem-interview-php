# Migrace na PHP 8.5

Kód běží beze změny na PHP 7.4 i 8.5.

- Nejdřív se nasadí nový kód (server je pořád na 7.4), potom se nezávisle přepne PHP.
- Návrat: přepnout PHP zpět na 7.4, kód se nevrací.
- Body označené **ověřit** vycházejí z kódu a Dockeru, na produkčním serveru je potřeba je zkontrolovat.

## 1. Co se v kódu změnilo

Pravidlo: chování na 7.4 se nemění a na 8.5 je stejné. Jen syntaxe dostupná v 7.4. Opravy chyb po migraci (CHANGELOG) platí stejně na obou verzích.

Syntaxe (na PHP 8 se soubor vůbec nenačte):

- `match()` přejmenováno na `regex_match()` (`match` je klíčové slovo).
- Vnořený ternární operátor v detailu vlastníka uzávorkovaný podle vyhodnocení v 7.4.
- Vlastní `mb_ucfirst()` / `mb_lcfirst()` se deklarují jen na PHP < 8.4 (od 8.4 jsou vestavěné).
- `plugin/minify/Minify.php`: `$s{$i}` → `$s[$i]`, jinak knihovna beze změny.

Chování, které PHP 8 změnilo (kód dává výsledek jako v 7.4):

- PDO: chyby SQL bez výjimek a čísla jako řetězce (`ERRMODE_SILENT`, `ATTR_STRINGIFY_FETCHES` v `Database::connect()`).
- Porovnání řetězce s číslem (`duedate`).
- `count(null)` u neexistujícího vlastníka nebo vlastníka bez parcel.
- curl handle je objekt: noční report znovu odesílá na reportovací službu.
- Zaokrouhlení výměr v `format_area()` a v detailu vlastníka napodobuje 7.4.

Deprecated (dnes varování, v PHP 9 chyba) odstraněné: `case …;`, `(integer)` / `(boolean)`, backtick, `strftime()`, `get_magic_quotes_gpc()`, `curl_close()`, dynamické vlastnosti tříd, implicitně nullable parametry, `fputcsv()` bez `$escape`, `PDO::MYSQL_ATTR_INIT_COMMAND`, `null` do vestavěných funkcí a jako klíč pole.

Ověření: testy (unit, smoke, Playwright) a PHPStan zelené na 7.4 i 8.5, ruční běh s `E_ALL` bez hlášení, HTML stránek na obou verzích shodné.

## 2. Co nastavit na serveru

### Rozšíření PHP

Aplikace používá `pdo_mysql`, `curl` (noční report), `mbstring`, `session`, `json`, `filter`, `pcre`.

- **ověřit:** `php -m` (web i CLI) na novém PHP obsahuje `pdo_mysql`, `curl`, `mbstring`. Na distribucích bývají v samostatných balíčcích (např. `php8.5-mysql`, `php8.5-curl`, `php8.5-mbstring`).

### php.ini

Přenést do konfigurace PHP 8.5 (web i CLI mají často zvlášť `php.ini`) hodnoty z `docker/php/app.ini`:

- `date.timezone = Europe/Prague`
- `memory_limit = 256M`
- `max_execution_time = 120`
- `log_errors = On`

Dále:

- **ověřit:** `default_charset = UTF-8` (výchozí) a nenastavené `mbstring.internal_encoding`. Vestavěné `mb_ucfirst()` na 8.4+ bere kódování z `mb_internal_encoding()`.
- **ověřit:** nepřenášet direktivy, které PHP 8 zrušilo nebo označilo za zastaralé (např. `track_errors`, `session.sid_length`, `session.sid_bits_per_character`, `assert.*`).
- `error_reporting` a `display_errors` nastavuje aplikace sama v `includes/config.php`.
- Apache nezapíše `Deprecated` / `Notice`, pokud je `LogLevel` vyšší než `notice`. Pro ladění po přepnutí lze dočasně nastavit `error_log` na soubor.

### Databáze

Pro PHP 8.5 se v databázi nic nemění. Indexy pro zrychlení výpisů jsou samostatné SQL migrace nezávislé na verzi PHP (kapitola 5).

- Adresa databáze se čte z proměnné prostředí `DB_HOST` (bez ní `db`). Nové PHP ji musí dostat stejně jako staré: Apache (`SetEnv` / `PassEnv`), pool PHP-FPM (`env[DB_HOST] = …`, FPM ve výchozím stavu prostředí maže) i cron. **ověřit**, kde je nastavená dnes.
- **doporučeno ověřit:** `select count(*) from ownerships where share_den = 0`. Na přepnutí to vliv nemá, ale jde o chybu v datech, kterou je dobré opravit.

### Práva k zápisu

Uživatel nového PHP (web i cron) musí mít zápis do `logs/`, `reports/` a do adresáře session (`session.save_path`). **ověřit**, pokud se uživatel nebo pool mění. Bez uložené session skončí každé uložení formuláře chybou „Platnost formuláře vypršela“ (CSRF token).

- **ověřit:** `session.gc_maxlifetime` (výchozí 1440 s = 24 minut). Po vypršení se ztratí napsaný text formuláře. Zvýšit podle toho, jak dlouho mají uživatelé formuláře otevřené (např. na pracovní den).

### Locale

Na nainstalovaném locale nic nezávisí, ověřovat ho není potřeba. `includes/config.php` nastaví `cs_CZ.UTF-8` a hned `LC_NUMERIC = C`, takže desetinná čísla mají na obou verzích tečku (PHP 7.4 s českým locale psalo čárku).

- Na `LC_CTYPE` závisí třídy znaků v regulárních výrazech bez `/u` (`\s`, `\d`: `class/Database.php`, `regex_match()`, `admin_owners/owners_edit.php`). Pro `cs_CZ.UTF-8` je výsledek na 7.4 i 8.5 stejný; lišit by se mohl jen se záložní locale `cs_CZ` / `czech` v kódování ISO-8859-2.

### Cron

Noční report musí spouštět CLI nového PHP:

```
15 2 * * * www-data php /var/www/html/cron/nightly_report.php > /dev/null 2>&1
```

- **ověřit:** `php` v cronu ukazuje na 8.5 (`php -v`), případně plná cesta (např. `/usr/bin/php8.5`). Při návratu na 7.4 vrátit i cron.

## 3. Postup nasazení

1. Nahrát nový kód na server s PHP 7.4. Tyto skupiny souborů nahrát vždy najednou:
   - **Filtry jako parametry SQL:** `class/Section.php`, `class/Database.php`, `includes/functions.php`, `admin_owners/owners_functions.php`, `admin_parcels_main.php`, `admin_municipalities/municipalities_section.php`.
     - **ověřit** v celém CRM, že žádný jiný modul nemá `{value}` v apostrofech (ani `'%{value}%'`): `grep -rn "{value}"`, případně upravit ve stejném nasazení. Neupravený modul selže bezpečně (chyba SQL nebo nic nenajde).
   - **Výpis vlastníků:** `class/Section.php`, `includes/functions.php`, `admin_owners/owners_functions.php`, `admin_owners/owners_section.php`. Při nesouladu výpis potichu ukáže prázdné obce a 0 parcel.
     - **ověřit**, že jiné moduly nevolají odstraněné funkce: `grep -rn "owner_parcel_count\|owner_parcels_in_municipality"`. Jinak tam vznikne fatální chyba „Call to undefined function“.
   - **Výpis parcel:** `admin_parcels_main.php`, `includes/functions.php`, `class/Section.php`. Se starším `functions.php` nebo `Section.php` skončí výpis parcel fatální chybou.
   - **Odkaz „vlastníci“ z obcí:** `admin_municipalities/municipalities_section.php`, `class/Section.php`, `admin_owners/owners_functions.php`, `admin_owners/owners_section.php`, `includes/functions.php`. Se starým `Section.php` nebo `owners_functions.php` fatální chyba; se starým výpisem vlastníků odkaz potichu ukáže všechny vlastníky. Staré odkazy s názvem obce fungují dál.
   - **Odkaz „parcely“ z obcí:** `admin_parcels_main.php`, `admin_municipalities/municipalities_section.php`, `css/style.css`; potřebuje `class/Section.php` od v2.7.11 a `includes/functions.php` od v2.7.10 (pokud je server nemá, nahrát s nimi). Se starším `Section.php` fatální chyba; se starým výpisem parcel odkaz potichu ukáže všech 800 tisíc parcel. Bez nového CSS odkazy fungují, jen se zalomí.
   - **Zaokrouhlení výměr (v2.8.4):** `admin_owners/owners_detail.php` a `includes/functions.php` (`area_value()`). S novým detailem a starým `functions.php` skončí detail vlastníka fatální chybou.
   - **Minimální délka textového filtru obce (v2.9.0):** `class/Section.php` a `admin_owners/owners_functions.php` (`Section::setFilterMinLength()`). S novým `owners_functions.php` a starým `Section.php` skončí výpis i export vlastníků fatální chybou.
     - Ostatní soubory verze nahrát spolu s nimi: `admin_parcels_main.php`, `admin_municipalities/municipalities_section.php` (zástupný text hledání; se starým `Section.php` zůstane starý text, s novým `Section.php` a starým modulem pole zástupný text nemá), `css/style.css` (barva upozornění u filtru), `ajax_municipalities.php` a `js/app.js` (našeptávač počítá znaky, ne bajty; na ostatních souborech nezávisí).
   - Samostatně lze nahrát:
     - `includes/functions.php` s pořadím vlastníků parcely (na server s v2.7.10+),
     - `admin_owners/owners_functions.php` s výchozím řazením vlastníků (na server s v2.7.11+), spolu s migrací 2 (pořadí nerozhoduje),
     - `admin_municipalities/municipalities_section.php` s poddotazem počtu parcel (potřebuje `Section.php` a `Database.php` od v2.7.7).
   - Migraci 3 spustit až s kódem od v2.8.2 (se starším řazením by mohla přeskupit vlastníky na stranách).
2. Ověřit na 7.4 (kapitola 4).
3. Připravit PHP 8.5 vedle 7.4 (rozšíření, `php.ini`), přepnout web (Apache / PHP-FPM) i cron na 8.5.
4. Ověřit na 8.5 (kapitola 4).
5. Návrat: přepnout web i cron zpět na PHP 7.4. Kód zůstává.

DB migrace z kapitoly 5 se dají spustit před nahráním kódu i po něm, v pořadí z přehledu (výjimka: migrace 3 viz výše).

## 4. Ověření

Na serveru:

- Projít Přehled, Vlastníky (filtr obce, hledání IČO, řazení, další strana), detail a úpravu vlastníka (jen zobrazit), Parcely, Obce.
  - Z Obcí odkaz „vlastníci“ (popisek „Obec: název ×“, sloupec „V obci“) a „parcely“ (počet stejný jako sloupec „Parcel“, × vrátí celý výpis).
  - Stáhnout export CSV.
  - Na stránkách nesmí být chyba, v patičce je verze aplikace.
- Spustit `php cron/nightly_report.php` pod stejným PHP jako cron. Musí skončit „Hotovo za …“; „už je v historii zapsaný“ při druhém běhu za den je v pořádku.
- Projít `logs/sql_error.log`, `logs/db_error.log` a error log PHP / Apache.

Testy proti serveru (z vývojářského počítače, jen čtou):

```bash
APP_URL=https://<server> vendor/bin/phpunit --testsuite smoke
```

Smoke testy počítají s testovacími daty, na produkci část selže. Zelené mají být na kopii s vygenerovanými daty nebo v Dockeru. Před nasazením lokálně všechny testy a PHPStan na obou verzích (README).

## 5. Přehled DB migrací

Každá změna databáze je SQL soubor v [`sql/migrations/`](sql/migrations/), který se spouští ručně.

- Tabulka je seznam všeho, co je při nasazení potřeba spustit, v pořadí spuštění (= pořadí podle čísla v názvu).
- Soubory jsou na sobě nezávislé, spouštějí se přesto v pořadí.
- `001` se do v2.8.1 jmenoval `2026-11-26_ownerships_owner_id.sql`.
- Doba je z Dockeru (500 tis. vlastníků, 1,2 mil. vztahů), na produkci řádově sekundy.

| # | Soubor | Verze | Co dělá | Povinná? | Kdy vůči nahrání souborů | Návrat | Doba |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | [`001_ownerships_owner_id.sql`](sql/migrations/001_ownerships_owner_id.sql) | 2.7.9 (`ANALYZE` od 2.8.2) | Index `idx_ownerships_owner (owner_id)` pro výpis vlastníků; `ANALYZE TABLE ownerships`. | Ne. Bez ní stejný výsledek, jen pomalejší. | Kdykoli, před i po. | `SET SESSION sql_mode = ''; SET SESSION lock_wait_timeout = 10; ALTER TABLE ownerships DROP INDEX IF EXISTS idx_ownerships_owner;` | asi 1,5 s (~20 MB) |
| 2 | [`002_owners_name.sql`](sql/migrations/002_owners_name.sql) | 2.8.2 | Index `idx_owners_name (last_name, first_name, id)` pro výchozí řazení vlastníků; histogramy sloupců `type`, `ico`, `municipality_id`, `do_not_contact`. Před spuštěním kontrola serveru (5.2). | Ne. Bez ní stejný výsledek, jen pomalejší. Spouštět vždy celý soubor (index bez histogramů zhorší některé filtry). | Kdykoli, před i po. | `SET SESSION sql_mode = ''; SET SESSION lock_wait_timeout = 10; ALTER TABLE owners DROP INDEX IF EXISTS idx_owners_name;` Statistiky mohou zůstat (5.2). | asi 1,5 s (~17 MB) |
| 3 | [`003_owners_municipality.sql`](sql/migrations/003_owners_municipality.sql) | 2.8.3 | Index `idx_owners_municipality (municipality_id, last_name, first_name, id)` pro filtr obce ve výpisu vlastníků a noční report; `ANALYZE TABLE owners`. | Ne. Bez ní stejný výsledek, jen pomalejší. | Kdykoli s kódem od v2.8.2, před i po (se starším kódem viz kapitola 3). | `SET SESSION sql_mode = ''; SET SESSION lock_wait_timeout = 10; ALTER TABLE owners DROP INDEX IF EXISTS idx_owners_municipality;` Statistiky není třeba vracet. | asi 1 s (~19 MB) |

### Společné pro všechny migrace

- **Idempotentní.** Opakované spuštění nic nezmění (poznámka `Note 1061: Duplicate key name`, návratový kód 0). Lze spustit znovu kdykoli.
- **`sql_mode`.** Soubor nastaví pro svou relaci `sql_mode = ''` jako aplikace. Se striktním režimem by `ALTER TABLE owners` skončil chybou `1067 Invalid default value for 'duedate'`. Proto návratové příkazy začínají stejnými `SET SESSION`.
- **Online.** `ALGORITHM=INPLACE, LOCK=NONE`: čtení i zápis běží dál. Bez podpory online varianty příkaz skončí chybou a nic nezmění.
- **Metadata lock.** `ALTER` potřebuje krátký výhradní lock a čeká na dlouhé dotazy (noční report ve 2:15, exporty). Po 10 s (`lock_wait_timeout`) skončí chybou `Lock wait timeout exceeded`, stačí spustit znovu. Spouštět mimo noční report a ideálně mimo špičku.
- **`ANALYZE TABLE` je součást souboru**, aby index a statistiky vznikly vždy spolu.
  - **ověřit** na verzi produkčního serveru: starší MariaDB/MySQL může při `ANALYZE` čekat na běžící dotazy („Waiting for table flush“). I `ANALYZE` proto mimo noční report a dlouhé exporty.
  - **ověřit** `use_stat_tables` před `001` i `003` (kontrola v 5.2). S `PREFERABLY` nebo `COMPLEMENTARY` prostý `ANALYZE` projde celou tabulku a u `owners` přepíše histogramy z `002`.
- **Práva.** Uživatel potřebuje `ALTER` (resp. `INDEX`). Aplikační uživatel ho mít nemusí, pak spustit jako správce. Na zápis histogramů (`ANALYZE … PERSISTENT FOR`) stačí práva k databázi aplikace.
- **Data se nemění** (`CHECKSUM TABLE` před a po stejný). Při návratu kódu na starší verzi se index odebírat nemusí.

Spuštění na produkci, v pořadí z tabulky (název databáze a uživatele podle serveru):

```bash
mysql -u <admin> -p <databáze> --show-warnings < sql/migrations/001_ownerships_owner_id.sql
mysql -u <admin> -p <databáze> --show-warnings < sql/migrations/002_owners_name.sql
mysql -u <admin> -p <databáze> --show-warnings < sql/migrations/003_owners_municipality.sql
```

Lokálně (Docker) se migrace samy neprovedou. Spusťte je na existující databázi i po každém `docker compose down -v`:

```bash
for f in sql/migrations/*.sql; do docker compose exec -T db mariadb -ucrm -pcrm legacy_crm --show-warnings < "$f"; done
```

### 5.1 Index `ownerships.owner_id` (výpis vlastníků)

Výpis vlastníků načítá počty parcel vlastníků stránky jedním dotazem. Bez indexu každý takový dotaz projde celou tabulku `ownerships` (1,2 mil. řádků), s indexem přečte asi 120 řádků.

- Ověření: `SHOW INDEX FROM ownerships WHERE Key_name = 'idx_ownerships_owner';` vrátí jeden řádek. `EXPLAIN select count(*) from ownerships where owner_id in (1, 2)` ukazuje `key = idx_ownerships_owner`, `type = range`.
- Kde soubor proběhl před doplněním `ANALYZE TABLE`, stačí ho spustit znovu (nebo jen `ANALYZE TABLE ownerships;`).

### 5.2 Index `owners (last_name, first_name, id)` (výchozí řazení vlastníků)

Výpis vlastníků se řadí `o.last_name, o.first_name, o.id`. S indexem čte první strany přímo z indexu místo řazení všech 500 tis. vlastníků. Pořadí na indexu nezávisí, index jen zrychluje.

- **Histogramy jsou nutné.** Bez nich by index zhoršil hledání IČO, filtr podle id obce (před migrací 3) a kombinaci typu se zákazem oslovení.
  - Po velkém importu nebo hromadné změně vlastníků spusťte soubor znovu. Zastaralé histogramy nemění výsledky, jen můžou zhoršit plán.
- **Návrat:** příkaz `DROP INDEX` z tabulky. Histogramy (`mysql.column_stats`, `mysql.table_stats`) mohou zůstat, nebo je správce odebere:

  ```sql
  DELETE FROM mysql.column_stats WHERE db_name = '<databáze>' AND table_name = 'owners';
  DELETE FROM mysql.table_stats WHERE db_name = '<databáze>' AND table_name = 'owners';
  FLUSH TABLES owners;
  ```

Kontrola serveru před spuštěním (**ověřit**, histogramy jsou ověřené jen v Dockeru, MariaDB 10.11.19):

```sql
select @@version, @@use_stat_tables, @@optimizer_use_condition_selectivity, @@histogram_size;
```

- Očekává se 10.11.x, `use_stat_tables` jiné než `NEVER`, `optimizer_use_condition_selectivity` 4 nebo 5, `histogram_size` větší než 0. Výchozí v Dockeru: `PREFERABLY_FOR_QUERIES`, `4`, `254`.
- Při jiných hodnotách migraci spustit a hned ověřit plány níže.
- `use_stat_tables` rozhoduje i o `ANALYZE` v `001` a `003` (viz *Společné pro všechny migrace*).

Ověření (jako správce):

- `SHOW INDEX FROM owners WHERE Key_name = 'idx_owners_name';` vrátí 3 řádky.
- `SELECT column_name FROM mysql.column_stats WHERE db_name = '<databáze>' AND table_name = 'owners';` vrátí 4 sloupce (`type`, `ico`, `municipality_id`, `do_not_contact`).
- `EXPLAIN select o.* from owners o order by o.last_name, o.first_name, o.id limit 0, 50` ukazuje `type = index`, `key = idx_owners_name`, bez `Using filesort`.
- `EXPLAIN select o.* from owners o where o.ico = '01256748' order by o.last_name, o.first_name, o.id limit 0, 50` ukazuje `type = ALL` a `Using filesort`. Stejně s `o.municipality_id = 1` místo IČO, dokud neproběhla migrace 3 (po ní `type = ref`, `key = idx_owners_municipality`).
  - Pokud dotaz s IČO (nebo před migrací 3 s id obce) ukazuje `type = index` a `key = idx_owners_name`, optimalizátor histogramy nepoužívá. Index odebrat (návrat z tabulky), dokud se nastavení nevyřeší. Kód funguje i bez indexu.

### 5.3 Index `owners (municipality_id, last_name, first_name, id)` (obec vlastníka)

Filtr podle obce ve výpisu vlastníků bez indexu prochází všech 500 tis. vlastníků. Složený index čte jen vlastníky obce, rovnou v pořadí stránky. Výchozí řazení je jednoznačné, takže ho index nemění. Histogramy z `002` se znovu nesbírají a migrace na nich nezávisí.

- **Řazení podle „Založeno“ není jednoznačné.** S filtrem obce mu migrace mění plán, takže vlastníci se stejným `created_at` se mohou přeskupit mezi stranami.
  - **ověřit:** `select created_at, count(*) c from owners group by created_at having c > 1 limit 5`. Prázdný výsledek = pořadí se nezmění. Jinak se mění jen rozdělení na strany, ne nalezení vlastníci (oprava je položka v TODO).
- **Textový filtr „Obec“ také zrychlí**, kromě velmi širokých textů: text od 2 znaků, který najde přes zhruba 40 % všech vlastníků (např. dvě písmena nebo `%`), je s indexem pomalejší než bez něj. Jedno písmeno se od v2.9.0 ignoruje.
  - **ověřit:** `EXPLAIN select count(*) from owners o left join municipalities m on m.id = o.municipality_id where m.name like '%Lhota%'` ukazuje `m`: `ALL`, `o`: `ref` `idx_owners_municipality`. Pro obvyklá zadání filtru zjistit počet nalezených vlastníků.
- **ověřit** (jen pro odhad zisku): `select municipality_id, count(*) c from owners group by municipality_id order by c desc limit 5`.
- **ověřit** dobu na produkci (disk, zátěž). Běží online.
- **Návrat:** příkaz `DROP INDEX` z tabulky. Histogramy z `002` nechat (filtr podle id obce je bez indexu znovu potřebuje). Pokud `ANALYZE` při jiném `use_stat_tables` zapsal statistiky všech sloupců, mohou zůstat, nebo je smazat a spustit znovu `002` (postup v 5.2).

Ověření (jako správce):

- `SHOW INDEX FROM owners WHERE Key_name = 'idx_owners_municipality';` vrátí 4 řádky.
- `EXPLAIN select o.* from owners o where o.municipality_id = 1 order by o.last_name, o.first_name, o.id limit 0, 50` ukazuje `type = ref`, `key = idx_owners_municipality`, bez `Using filesort`.
- `EXPLAIN select count(*) from owners o where o.municipality_id = 1` ukazuje `type = ref`, `Using index`.
- Výpis bez filtru má dál `key = idx_owners_name` (5.2).
- Pokud filtr podle id obce ukazuje `type = ALL` nebo `key = idx_owners_name`, spustit soubor znovu (nebo jen `ANALYZE TABLE owners;`).
