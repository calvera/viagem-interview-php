# Changelog

Formát vychází z [Keep a Changelog](https://keepachangelog.com/cs/1.1.0/). Starší záznamy jsou doplněné zpětně z historie gitu. Verze aplikace (`APP_VERSION` v `includes/config.php`) se dříve k jednotlivým změnám nezapisovala; od 2.7.4 má každá verze vlastní záznam a git tag.

## [Nevydáno]

## [2.7.7] – 2026-11-26

### Bezpečnost

- SQL injection: hodnoty filtrů a hledání ve výpisech (`Section`) a parametr `q` našeptávače obcí se do SQL předávají jako parametry, ne jako text. Definice filtrů v modulech používají `{value}` bez apostrofů (`'o.type = {value}'`, `"m.name like concat('%', {value}, '%')"`); `Database::getWhere()` nechá už navázaný parametr beze změny.
- CSRF: formuláře přidání a smazání poznámky a úpravy vlastníka posílají token ze session (`csrf_token()`, `csrf_field()`, `csrf_check()`). Bez platného tokenu se nic nezmění, stránka se načte znovu s chybou a s hodnotami z databáze (úprava vlastníka nepřevezme odeslané hodnoty, takže je jedno kliknutí na „Uložit“ neuloží).
- Záznam do `access_log` (`log_access()`) předává adresu, IP a uživatele jako parametry místo `addslashes`.
- Smazání poznámky maže jen poznámku zobrazeného vlastníka (`id` i `owner_id` jako parametry).
- XSS: hodnoty filtrů a hledaný výraz ve výpisech, text poznámek v detailu vlastníka a na přehledu, kód katastrálního území v detailu vlastníka se escapují; našeptávač vkládá popisky jako text (`textContent`).

### Opraveno

- Smazání poznámky hlásí „Poznámka byla smazána.“ jen tehdy, když se poznámka opravdu smazala, jinak chybu (dříve např. `delete_note[]=x`).
- Úprava vlastníka: pole odeslané jako seznam (`duedate[]=x`, `note[]=x`…) je chyba validace, dříve se uložil prázdný termín nebo poznámka.

### Změněno

- Poznámka se maže formulářem (POST) s tlačítkem „smazat“ místo odkazu `?delete_note=`; potvrzovací dialog zůstává. Odkaz `?delete_note=` už nic nemaže.
- Test Playwright mazání poznámky klikne na tlačítko ve formuláři a blokuje každý POST, ne jen odkaz `delete_note=`.

### Přidáno

- TODO: nové otevřené položky – chybějící pole v POST úpravy vlastníka se uloží prázdná, CSV formula injection v exportu, nepoužívané `Database::delete()` / `prepareData()` / `escape()`, obří `page` dá chybu SQL.
- Unit testy: CSRF funkce, `post_has_array()`, parametry filtrů a hledání v `Section` (hodnota s apostrofem je parametr, ne SQL), escapování filtrů a hledání, `getWhere()` s navázaným parametrem. Smoke testy (jen GET): hodnoty jako `x' or '1'='1` ve filtrech, hledání a našeptávači nic nenajdou, `<script>` v hledání a filtru je escapovaný, GET `?delete_note=` nic nesmaže. Playwright: našeptávač nevloží HTML z popisku.

## [2.7.6] – 2026-11-26

### Opraveno

- `trim_text()` porovnává délku ve znacích, ne v bajtech: krátká poznámka s diakritikou na přehledu už nedostane „…“, když se nic neuřízlo.
- `format_datetime('0000-00-00')` vrací prázdnou hodnotu jako `format_date()` místo `30. 11. -0001 00:00`.
- `Database::getWhere()` odstraní z hodnoty v apostrofech jen okrajové apostrofy, vnitřní zůstanou (`'O'Brien'` → `O'Brien`).
- Vlastnický podíl se jmenovatelem 0 (`share_den = 0`) už na PHP 8 neshodí detail vlastníka ani výpis s filtrem obce (`DivisionByZeroError`) a na 7.4 neukazuje „inf ha“ / „nan m²“. Nová funkce `share_area()`: do součtů přispívá nulou, „Výměra podílu“ ukazuje „–“.
- Parametr GET/POST jako pole (`search[]=x`, `title[]=x`…) `get_param()` / `post_param()` ignorují a vrátí výchozí hodnotu: nehledá se ani neukládá text „Array“ a `?url[]=x`, `?sort[]=x`, `ajax_municipalities.php?q[]=x` a POST `duedate[]=x` na PHP 8 nespadnou na `TypeError`.
- Úprava vlastníka po chybě validace zobrazí odeslaný termín oslovení (např. `0.5`), prázdné pole jen pro nulové datum z databáze.
- Export: vlastníci se zákazem oslovení se vynechávají už v SQL, takže limit 20 000 počítá jen exportované řádky. Počet v řádku „Vynecháno N vlastníků se zákazem oslovení“ zahrnuje všechny vlastníky odpovídající filtru, ne jen ty mezi prvními 20 000.

### Přidáno

- `GUIDE.md`: stručný popis řešení k odevzdání (postup, co máme, rozhodnutí); pravidlo v `CLAUDE.md`, že ho každá položka udržuje aktuální.
- Unit testy `share_area()`, `get_param()` a `post_param()` s polem; smoke testy pro pole v parametrech `url`, `search`, `sort` a `q` našeptávače a pro vynechání vlastníků se zákazem oslovení v exportu (s `all` i bez něj); unit testy okrajových případů apostrofů v `getWhere()`.
- TODO: nové otevřené položky – pole ve formuláři úpravy uloží prázdný termín / poznámku, `delete_note[]` hlásí smazání, `format_share('0', '0')` vrací „celý“.

### Změněno

- Unit testy, které zachycovaly chybné chování `trim_text()`, `format_datetime('0000-00-00')` a apostrofů v `getWhere()`, ověřují správné chování.

## [2.7.5] – 2026-11-26

### Opraveno

- Aplikace běží na PHP 8.5 a dál i na PHP 7.4 (jeden kód pro obě verze, bez build kroku). Podrobnosti a postup přechodu serveru v [MIGRATION.md](MIGRATION.md).
- Syntaxe, kterou PHP 8 nenačte: funkce `match()` přejmenovaná na `regex_match()`, vnořený ternární operátor v detailu vlastníka uzávorkovaný podle dosavadního vyhodnocení, přístup ke znaku řetězce `$s{$i}` v přibalené knihovně `plugin/minify/`, vlastní `mb_ucFirst()` / `mb_lcFirst()` kolidující s vestavěnými funkcemi PHP 8.4.
- PDO: chyby SQL vracejí `false` jako v 7.4 (`ERRMODE_SILENT`), místo aby na PHP 8 shodily stránku nebo noční report; hodnoty z databáze jsou řetězce jako v 7.4 (`ATTR_STRINGIFY_FETCHES`), takže funguje porovnání `do_not_contact === '1'` (štítek „neoslovovat“, zaškrtávátko v úpravě, vynechání v exportu).
- Vlastník bez termínu oslovení (`0000-00-00`) by se na PHP 8 nezobrazoval jako „nenastaven“ (výpis, úprava, export).
- Detail neexistujícího vlastníka a vlastníka bez parcel by na PHP 8 spadl na `count()`; `owner_parcels()` vrací prázdné pole místo `null`.
- Noční report by na PHP 8 vždy hlásil „curl není k dispozici“ (`is_resource()` na objektu `CurlHandle`) a nepingl reportovací službu; teď hlásí skutečný výsledek jako na 7.4.
- Deprecated konstrukce PHP 8.x: `case …;`, přetypování `(integer)` / `(boolean)`, operátor backtick, `strftime()`, `get_magic_quotes_gpc()`, `curl_close()`, dynamické vlastnosti tříd, implicitně nullable parametr, `fputcsv()` bez `$escape`, `PDO::MYSQL_ATTR_INIT_COMMAND`, `null` do parametrů vestavěných funkcí.

### Přidáno

- PHPStan (úroveň 2) pro PHP 7.4 (`phpstan.neon`) i 8.5 (`phpstan-php85.neon`), baseline jen pro proměnné z includovaných šablon.
- `MIGRATION.md`: co se při migraci změnilo a proč, co nastavit na serveru, pořadí nasazení, návrat a ověření.

### Změněno

- `CLAUDE.md` přeložený do češtiny; nové pravidlo udržovat aktuální `MIGRATION.md`.
- README: testy jsou zelené na PHP 7.4 i 8.5, příkazy pro PHPStan, rozhodnutí při migraci.
- TODO: nové otevřené položky z revize migrace – dělení nulou při `share_den = 0` (na PHP 8 pád), `null` z databáze do vestavěných funkcí, pole v parametrech GET/POST, `duedate` `0.5` ve formuláři úpravy.

## [2.7.4] – 2026-11-26

### Přidáno

- `TODO.md` se seznamem známých problémů (kompatibilita s PHP 8, bezpečnost, výkon).
- `CHANGELOG.md`.
- Composer s vývojovými nástroji (PHPStan 2.3, PHPUnit 9.6), platforma nastavená na PHP 7.4, aby nástroje běžely na 7.4 i 8.x.
- `CLAUDE.md` s pravidly pro práci AI agentů (každou změnu dělá worker, kontroluje ji read-only review, dokud není čisté).
- Skilly pro AI agenty v `.agents/skills/` (`analyse-with-phpstan`, `php-best-practices`), propojené symlinky do `.claude/skills/` a `.junie/skills/`, verze zamčené v `skills-lock.json`.
- Testy jako záchranná síť pro migraci na PHP 8.5 (jen čtou, data v DB nemění):
  - PHPUnit: `phpunit.xml` se sadami `unit` a `smoke`, skripty `composer test` a `composer test:smoke`.
  - Unit testy pomocných funkcí z `includes/functions.php` a skládání SQL v `Database::getWhere()`.
  - Smoke testy přes HTTP (`APP_URL`): všechny stránky, filtry, hledání, řazení, stránkování, detail, úprava (jen GET), export CSV, našeptávač, 404; na stránce nesmí být výpis fatální chyby ani chyby syntaxe.
  - Playwright v `e2e/` (projekty `php74` a `php85`): navigace, výpisy, našeptávač, potvrzovací dialog mazání poznámky, stažení CSV; hlídá chyby v konzoli prohlížeče a výpis fatální chyby nebo chyby syntaxe na stránce (varování aplikace potlačuje přes `error_reporting`); test mazání poznámky blokuje požadavek `delete_note=`.

### Změněno

- README: přehled funkcí a odkazy na TODO a changelog.
- `CLAUDE.md`: review jen čte kód, testy nespouští (to dělá worker); release každé položky TODO s verzí, datem a git tagem.
- `.gitignore`: ignoruje `/vendor/` a výstupy Playwrightu (`e2e/node_modules/`, `e2e/test-results/`, `e2e/playwright-report/`).

## 2026-10-27

### Přidáno

- Lokální prostředí v Dockeru: PHP 7.4 a 8.5, MariaDB 10.11, generátor syntetických dat.
- README.

## 2021-09-02

### Přidáno

- Export vlastníků do CSV.
- Našeptávač obcí.
- Úprava údajů vlastníka.
- Výpis parcel.
- Noční report (`cron/nightly_report.php`).

## 2019-03-14

### Přidáno

- Evidence vlastníků: výpis, detail a poznámky.
