# Changelog

Formát vychází z [Keep a Changelog](https://keepachangelog.com/cs/1.1.0/). Starší záznamy jsou doplněné zpětně z historie gitu. Verze aplikace (`APP_VERSION` v `includes/config.php`) se dříve k jednotlivým změnám nezapisovala; od 2.7.4 má každá verze vlastní záznam a git tag.

## [Nevydáno]

## [2.8.4] – 2026-11-26

### Opraveno

- `format_area()` a výměra podílu v detailu vlastníka zaokrouhlovaly hodnotu těsně pod polovinou na PHP 8.5 jinak než na 7.4: součet podílů 1/3 + 1/12 + 1/12 z 1 m² (v plovoucí čárce `0.49999999999999994`) dal na 7.4 „1 m²“, na 8.5 „0 m²“, součet podílů přesně 118 450 m² na 7.4 „11,85 ha“, na 8.5 „11,84 ha“. Příčina: PHP 8.4 v `round()` a `number_format()` zrušilo „pre-rounding“ na 15 platných číslic (od 1 výše) a přepsalo zaokrouhlení na celé číslo (pod 1 dříve `floor(x + 0.5)`), UPGRADING 8.4, GH-12143. Hodnota se před zaokrouhlením předzaokrouhlí na 15 platných číslic stejnými kroky jako v 7.4 (`area_value()` v `includes/functions.php`, použitá ve `format_area()` a v `admin_owners/owners_detail.php`), výstup je na obou verzích stejný jako dosud na 7.4 (ověřeno na 4,2 mil. hodnot: součty podílů do 1e8 m², hodnoty x,5 a jejich sousedé v plovoucí čárce do 1e9). Pod 1 m² se pár hodnot těsně pod 0,5 nově zaokrouhlí nahoru i na 7.4 (reálně jen výměra podílu malé parcely). Týká se sloupce „V obci“ (textový filtr i filtr podle id obce) a detailu vlastníka; jen na přesné hranici zaokrouhlení, testovací data ji nezasáhnou (HTML stránek beze změny). Unit testy hraničních hodnot pro m² i ha.

## [2.8.3] – 2026-11-26

### Přidáno

- SQL migrace `sql/migrations/003_owners_municipality.sql`: index `idx_owners_municipality (municipality_id, last_name, first_name, id)` (idempotentní, online) a `ANALYZE TABLE owners`. Výpis vlastníků s filtrem podle id obce čte jen vlastníky obce a stranu rovnou v pořadí (`ref` bez filesortu místo průchodu všemi 500 000 vlastníky), `count(*)` a export CSV s id obce také. Zrychlí i textový filtr „Obec“ (`count(*)` „Lhota“ 825 stránek místo 1 mil.) a přehled obcí v nočním reportu (čte index místo tabulky). Dotaz na řádky textového filtru ale teď řadí všechny nalezené: u „Lhota“ (20 000 vlastníků) čte na 1. až 9. straně víc stránek než dříve (61 tis. místo 6 až 56 tis.), celá stránka s `count(*)` je přesto mnohem levnější (1. strana asi 16×); velmi široký filtr je s indexem pomalejší: podle doby `ANALYZE` zhruba od 40 % všech vlastníků, změřeno 2,2× až 2,6× u jednoho písmena („e“, „a“: 80 až 93 % vlastníků). Kód se nemění a funguje i bez migrace se stejným výsledkem; HTML stránek ve výchozím řazení je s indexem i bez něj stejné (řazení podle „Založeno“ je stejné jen při jedinečném `created_at`, `MIGRATION.md`, 5.3, **ověřit**). `MIGRATION.md`, 5.3.

## [2.8.2] – 2026-11-26

### Změněno

- Migrace v `sql/migrations/` mají pořadové číslo, pořadí podle názvu je pořadí spuštění: `2026-11-26_ownerships_owner_id.sql` je teď `001_ownerships_owner_id.sql`.
- Výchozí řazení výpisu vlastníků (a exportu CSV) je `o.last_name, o.first_name, o.id` místo `concat(o.last_name, ' ', o.first_name)` (`setSortBy()` v `admin_owners/owners_functions.php`, rozhodnutí z otázky 5 v `OPENQUESTIONS.md`, varianta b). S vyplněným hledáním se řadí `o.id + 0` místo `o.id` (stejné pořadí), aby hledání zůstalo u dosavadního plánu: s indexem by vzácné jméno četlo skoro celý index. Pořadí je jednoznačné: vlastníci se stejným jménem jsou seřazení podle id, na stranách jsou proto jiní vlastníci než dříve. Posloupnost jmen se na celých testovacích datech nezměnila (všech 1 070 jmen ve stejném pořadí). Opakovaná načtení jsou bajtově shodná, PHP 7.4 i 8.5 stejně; výpisy parcel a obcí beze změny.

### Přidáno

- SQL migrace `sql/migrations/002_owners_name.sql`: index `idx_owners_name (last_name, first_name, id)` (idempotentní, online) a `ANALYZE TABLE owners` s histogramy sloupců `type`, `ico`, `municipality_id`, `do_not_contact`. První strany výpisu se čtou z indexu (bez filtru 50 řádků místo 500 000 a filesortu); s histogramy zůstane filtr podle id obce, hledání IČO a kombinace typu a zákazu oslovení na dalších stranách u průchodu tabulkou místo čtení celého indexu. Kód funguje i bez migrace se stejným výsledkem.
- `ANALYZE TABLE ownerships` na konci migrace `001_ownerships_owner_id.sql` (statistiky pro optimalizátor, opakované spuštění neškodí).
- Obě migrace nastaví pro svou relaci `sql_mode = ''` jako aplikace: se striktním režimem a `NO_ZERO_DATE` skončil `ALTER TABLE owners` chybou 1067 (výchozí hodnoty s nulovým datem).
- `MIGRATION.md`: přehled DB migrací (soubor, verze, co dělá, povinnost, kdy vůči nahrání souborů, návrat, doba) a společné zásady; kapitola 5 přestavěná podle něj.
- Smoke test: vlastníci se stejným jménem na 1. a 5000. straně výpisu jsou ve vzestupném pořadí id.

## [2.8.1] – 2026-11-26

### Opraveno

- Výpis parcel: pořadí vlastníků se stejným příjmením v buňce „Vlastníci“ a s ním pořadí čísel v buňce „LV“ bylo neurčené, dvě načtení stejné adresy se mohla lišit (parcela 19020, tři vlastníci „Kratochvíl“). `parcels_owners()` v `includes/functions.php` řadí `o.last_name, o.first_name, o.id, os.id`, docblock popisuje i shody. Opakovaná načtení jsou bajtově shodná, PHP 7.4 i 8.5 stejně; mění se jen pořadí uvnitř shod (např. parcela 35: LV `1589, 883` místo `883, 1589`). Plán dotazu beze změny.

### Přidáno

- Smoke test pořadí vlastníků a LV u parcel 19020 a 35.
- `TODO.md`: neurčené pořadí řádků se shodným klíčem v detailu vlastníka (`owner_parcels()`) a při řazení výpisů podle sloupce (*Chyby v logice*).

## [2.8.0] – 2026-11-26

### Přidáno

- Výpis obcí: vedle odkazu „vlastníci“ odkaz „parcely“ na výpis parcel obce podle id (`f_municipality_id=<id>`). Výpis parcel má skrytý filtr `p.municipality_id = {value}` (`Section::addHiddenFilter()`, stejně jako výpis vlastníků): nad formulářem popisek „Obec: <b>název</b> ×“, × filtr zruší a vrátí výpis na 1. stranu, druh pozemku a hledání ho zachovají, neplatné id se ignoruje. Počet parcel se rovná sloupci „Parcel“ ve výpisu obcí. Dotazy používají index `idx_parcels_municipality`, stránka má 6 SQL dotazů. Textové pole „Obec“ dál hledá podřetězec.
- Odkazy „vlastníci“ a „parcely“ ve výpisu obcí vypadají jako malá tlačítka (nová třída `a.button.small` v `css/style.css`, oba odkazy v jednom řádku), zůstávají odkazy `<a>`.
- Smoke testy odkazu „parcely“, výpisu parcel podle id obce (počet, popisek, zachování druhu pozemku, neexistující id) a ignorování neplatného id; Playwright test odkazu z obcí na parcely (popisek, počet rovný sloupci „Parcel“, řádky jen z obce, zachování při dalším filtru, ×).

## [2.7.11] – 2026-11-26

### Změněno

- Odkaz „vlastníci“ ve výpisu obcí filtruje podle id obce (`f_municipality_id=<id>`), ne podle podřetězce názvu: „Lhota“ ukáže 240 vlastníků s obcí Lhota místo 20 000 (dříve i Horní, Dolní… Lhota). Jde o obec vlastníka jako dosud (`o.municipality_id`). Zda má odkaz místo toho ukazovat vlastníky parcel v obci, zůstává otevřené (`OPENQUESTIONS.md`, otázka 1). Ve formuláři filtrů je před prvním polem popisek „Obec: <b>název</b> ×“; × filtr zruší a vrátí výpis na 1. stranu, ostatní filtry a hledání ho zachovají. Sloupec „V obci“ s tímto filtrem počítá parcely a výměru podílů v SQL podle `p.municipality_id` (`owners_parcels_in_municipality()` v `includes/functions.php`). Export CSV s `f_municipality_id` obsahuje vlastníky obce. Textové pole „Obec“ dál hledá podřetězec a staré odkazy s `f_municipality=<název>` fungují jako dřív.

### Přidáno

- `Section::addHiddenFilter()`: filtr bez pole ve formuláři, nastavený odkazem. Bere jen číslice, hodnota je parametr SQL, popisek se načte jedním dotazem s parametrem a escapuje, hodnota zůstane ve formuláři jako skryté pole.
- Unit testy skrytého filtru (jen číslice, parametr, escapování popisku, odkaz ×, skryté pole, beze změny formuláře bez hodnoty); smoke testy odkazu z výpisu obcí, počtu vlastníků podle id, popisku, hodnot „V obci“, ignorování neplatného id a exportu; Playwright test odkazu z obcí na vlastníky (adresa, popisek, prázdné textové pole, „V obci“, zachování při dalším filtru, ×).

## [2.7.10] – 2026-11-26

### Změněno

- Výpis parcel: vlastníci všech parcel stránky se načtou jedním dotazem `in (…)` místo dotazu na každý řádek (`parcels_list_prepare()`, nová `parcels_owners()` v `includes/functions.php`). Stránka má 5 SQL dotazů místo 54.
- Výpis obcí: počet parcel je korelovaný poddotaz v hlavním dotazu výpisu místo dotazu na každý řádek; MariaDB ho vyhodnotí jen pro řádky stránky. Stránka má 5 SQL dotazů místo 55. Porovnání s dávkovým dotazem a s `left join … group by` (přečtené řádky) je v README.
- HTML obou výpisů je stejné jako dříve (porovnáno na PHP 7.4 i 8.5), u parcel až na pořadí vlastníků se stejným příjmením, které bylo neurčené už dříve. Nový index není potřeba.

### Přidáno

- Smoke testy vlastníků a LV konkrétní parcely, parcely bez vlastníka a počtu parcel konkrétní obce; unit test `parcels_owners()` při chybě SQL.
- `TODO.md`: neurčené pořadí vlastníků se stejným příjmením ve výpisu parcel (*Chyby v logice*).

## [2.7.9] – 2026-11-26

### Změněno

- Výpis vlastníků: název obce, počet parcel a s filtrem obce i parcely pro sloupec „V obci“ se načtou jednou za stránku dotazem `in (…)` místo dotazu na každý řádek (`Section::setRowsPrepareFunction()`, `owners_list_prepare()`). Stránka má 5 SQL dotazů místo 103, s filtrem obce 6 místo 153. „V obci“ se dál filtruje v PHP stejným `mb_stripos`, HTML výpisu je stejné jako dříve (ověřeno porovnáním na PHP 7.4 i 8.5).
- `owners_parcel_counts()`, `owners_parcels()` a `parcels_in_municipality()` nahradily `owner_parcel_count()` a `owner_parcels_in_municipality()`; přibyly `municipality_names()` a `sql_int_list()`.

### Přidáno

- SQL migrace `sql/migrations/2026-11-26_ownerships_owner_id.sql`: index `idx_ownerships_owner` na `ownerships.owner_id` (idempotentní, online). Postup nasazení v `MIGRATION.md`. Kód funguje i bez indexu, jen pomaleji.
- Smoke testy obce, počtu parcel (i vlastník bez parcel, šedý řádek) a hodnot „V obci“ s filtrem obce; unit testy `Section::setRowsPrepareFunction()` (jednou se všemi řádky před funkcí řádku, prázdná strana bez volání), `parcels_in_municipality()`, `sql_int_list()` a dávkových dotazů při chybě SQL.
- `OPENQUESTIONS.md`: otázka 5, výchozí řazení výpisu vlastníků.

## [2.7.8] – 2026-11-26

### Bezpečnost

- Export CSV: text z databáze začínající `=`, `+`, `-`, `@`, tabulátorem nebo CR dostane na začátek `'`, takže ho tabulkový procesor nevyhodnotí jako vzorec (`csv_safe()`). Telefonní čísla (`+420 777 123 456`) a hlavička exportu se nemění. Výjimka pro telefon platí pro všechny sloupce: hodnoty jako `-1-1` zůstanou beze změny a tabulkový procesor je spočítá (bez písmen nejde o funkci ani odkaz).
- Export CSV zapisuje řádky bez escape znaku (`csv_line()`, `fputcsv(…, '')`): uvozovka za zpětným lomítkem se zdvojí. Dříve se hodnota jako `x\";=2+3;\"` v tabulkovém procesoru rozdělila do více buněk a vzorec `=2+3` obešel ošetření. Výstup se mění jen u hodnot se zpětným lomítkem.
- Odstraněný nepoužívaný kód, který skládal SQL z neošetřených hodnot: `Database::delete()`, `Database::prepareData()`, `Database::escape()` a `db_escape()`.

### Opraveno

- Hodnoty `NULL` z databáze se do vestavěných funkcí předávají přetypované (`(string)`, `(float)`): druh pozemku do `mb_ucFirst()` (výpis parcel, filtr druhu, detail vlastníka, noční report), číslo LV a druh pozemku jako klíč pole, název obce do `mb_stripos()` (sloupec „V obci“), `number_format()` ve sloupcích typu `int`, na přehledu a ve `format_area()`. `get_municipality_name()` a `owner_parcel_count()` pro chybějící řádek vrátí `null` bez varování „array offset on null“. Na PHP 8.1+ dříve hlášení Deprecated (v PHP 9 chyba), výstup je stejný jako dosud.
- Desetinná čísla v nočním reportu („Doba výpočtu“, „Hotovo za …“) a v `logs/slow_query.log` mají na PHP 7.4 i 8.x tečku nezávisle na locale serveru (`setlocale(LC_NUMERIC, 'C')` v `includes/config.php`). Dříve na PHP 7.4 s nainstalovaným `cs_CZ.UTF-8` čárku.
- Obří číslo strany (`page=99999999999999999999`) už nedá chybu SQL a prázdný výpis.
- `format_share()` se jmenovatelem 0 vrací `0/0` (resp. `num/0`) místo „celý“.

### Změněno

- Číslo strany za koncem výpisu ukáže poslední stranu místo „Nebyly nalezeny žádné …“.

### Přidáno

- Unit testy `csv_safe()`, `csv_line()`, `get_municipality_name()` / `owner_parcel_count()` bez řádku, `format_share()` se jmenovatelem 0, `format_area(null)` a sloupce typu `int` s `NULL` v `Section`; smoke test obří hodnoty `page`.

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
