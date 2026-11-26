# Evidence vlastníků

Interní webová aplikace pro evidenci vlastníků pozemků: vlastníci, jejich parcely a podíly, poznámky k oslovování, export do CSV a noční report. Je postavená na vlastním PHP frameworku (PHP 7.4, MariaDB 10.11) a je výsekem většího interního CRM.

Všechna data jsou syntetická, generují se při prvním startu databáze.

## Funkce

- **Přehled** – počty záznamů, poslední poznámky, historie a poslední noční report.
- **Vlastníci** – výpis s filtrem podle obce, typu a zákazu oslovení, hledání podle jména nebo IČO, detail s parcelami a podíly, poznámky, úprava údajů, export do CSV.
- **Parcely** – výpis s filtrem podle obce a druhu pozemku, hledání podle čísla parcely nebo kódu katastrálního území.
- **Obce** – výpis s filtrem podle okresu a kraje, odkaz na vlastníky v obci.
- **Noční report** – souhrnné statistiky do `reports/`, záznam do `report_runs` a odeslání na reportovací službu.

Aplikace nemá přihlášení, uživatel je pevně nastavený v `includes/config.php`.

Známé problémy jsou v [TODO.md](TODO.md), změny v [CHANGELOG.md](CHANGELOG.md). Kód zatím na PHP 8 nefunguje (viz TODO).

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

Testy jsou záchranná síť pro migraci na PHP 8.5: zachycují dnešní chování na PHP 7.4 a po migraci musí stejně projít i na 8.5. Jen čtou, data v DB nemění. Vedlejší účinky jsou stejné jako při běžném provozu: každý požadavek na stránku zapíše řádek do `access_log`, export přidá řádek do `logs/export.log` a pomalé dotazy se zapíšou do `logs/slow_query.log` (logy git ignoruje).

Testy počítají s čerstvě vygenerovanou databází (konkrétní vlastníci, IČO 01256748, poznámka s ID 60000 u vlastníka 420001). Pokud se data změnila, vraťte je příkazem `docker compose down -v` a novým `docker compose up -d`.

Kontrola „žádná PHP chyba“ hledá na stránce výpis fatální chyby a chyby syntaxe (`Fatal error`, `Parse error`, `Uncaught`). Varování a upozornění aplikace sama potlačuje (`error_reporting(E_ERROR | E_PARSE)` v `includes/config.php`), takže je kontrola neuvidí, i když hledá i `Warning:`, `Notice:` a `Deprecated:`.

| Sada | Co ověřuje |
| --- | --- |
| `unit` (PHPUnit, `tests/Unit`) | Pomocné funkce z `includes/functions.php` (formátování data, výměry, podílu, jména vlastníka, IČO, escapování…) a skládání SQL v `Database::getWhere()`. Bez databáze. |
| `smoke` (PHPUnit, `tests/Smoke`) | HTTP požadavky na běžící aplikaci (`APP_URL`): každá stránka vrátí 200, správný nadpis a žádnou fatální chybu ani chybu syntaxe; vlastníci s filtry, hledáním, řazením a stránkováním, detail, úprava (jen GET), export CSV, parcely, obce, našeptávač (JSON), 404. Bez `APP_URL` se přeskočí. |
| Playwright (`e2e/`) | Totéž v prohlížeči (Chromium) a navíc JavaScript a klikání: menu, filtry, řazení, stránkování, našeptávač obcí, potvrzovací dialog mazání poznámky (vždy se zruší), stažení CSV. Po každém testu ověří, že v konzoli prohlížeče není chyba a na stránce není výpis fatální chyby ani chyby syntaxe. Test mazání poznámky navíc požadavek `delete_note=` v prohlížeči zablokuje, takže poznámku nesmaže, ani kdyby potvrzovací dialog nefungoval. |

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

Na PHP 8.5 testy projdou až po migraci (úkol 1 v [TODO.md](TODO.md)). Do té doby jsou červené, protože `includes/functions.php` na PHP 8 nejde načíst (funkce `match()`).

Skripty `composer test` a `composer test:smoke` spouští PHPUnit pod PHP hostitele (v image `php74` Composer není). Pro unit testy na PHP 7.4 proto použijte příkaz s `docker compose run` výše.

Smoke sada záměrně nenačítá kód aplikace (`tests/bootstrap.php` načte jen autoload), takže ji lze pustit z PHP libovolné verze proti kterékoli z obou aplikací. Adresy pro Playwright lze změnit proměnnými `PHP74_URL` a `PHP85_URL`.

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
