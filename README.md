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

Stručný popis řešení k odevzdání je v [GUIDE.md](GUIDE.md). Známé problémy jsou v [TODO.md](TODO.md), změny v [CHANGELOG.md](CHANGELOG.md), přechod serveru na PHP 8.5 v [MIGRATION.md](MIGRATION.md).

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
| `unit` (PHPUnit, `tests/Unit`) | Pomocné funkce z `includes/functions.php` (formátování data, výměry, podílu, jména vlastníka, IČO, escapování, parametry GET/POST, CSRF token…), skládání SQL v `Database::getWhere()` a parametry filtrů a hledání v `Section` (hodnota nikdy není v textu SQL). Bez databáze. |
| `smoke` (PHPUnit, `tests/Smoke`) | HTTP požadavky na běžící aplikaci (`APP_URL`): každá stránka vrátí 200, správný nadpis a žádnou fatální chybu ani chybu syntaxe; vlastníci s filtry, hledáním, řazením a stránkováním, detail, úprava (jen GET), export CSV, parcely, obce, našeptávač (JSON), 404, parametry jako pole (`url[]`, `search[]`, `sort[]`, `q[]`), vynechání vlastníků se zákazem oslovení v exportu, hodnoty jako `x' or '1'='1` ve filtrech, hledání a našeptávači nic nenajdou, `<script>` v hledání a filtru je escapovaný, GET `?delete_note=` nic nesmaže. Bez `APP_URL` se přeskočí. |
| Playwright (`e2e/`) | Totéž v prohlížeči (Chromium) a navíc JavaScript a klikání: menu, filtry, řazení, stránkování, našeptávač obcí (popisek se vloží jako text, ne HTML), potvrzovací dialog mazání poznámky (vždy se zruší), stažení CSV. Po každém testu ověří, že v konzoli prohlížeče není chyba a na stránce není výpis fatální chyby ani chyby syntaxe. Test mazání poznámky navíc v prohlížeči zablokuje každý POST, takže poznámku nesmaže, ani kdyby potvrzovací dialog nefungoval. |

Nejdřív spusťte aplikaci (`docker compose up -d`, viz výše). Výpis vlastníků je pomalý (jednotky až desítky sekund na stránku), smoke sada proto trvá asi minutu a Playwright asi dvě.

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

Všechny sady jsou zelené na PHP 7.4 i 8.5 (unit 96, smoke 37, Playwright 19 testů na každou verzi).

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
