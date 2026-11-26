# TODO

## Zadání (technická úloha Viagem)

Omezení ze zadání: nasazuje se nahráním změněných souborů bez build kroku a server se upgraduje nezávisle na kódu. Kód proto musí běžet zároveň na PHP 7.4 i 8.5 a nesmí za běhu potřebovat Composer ani jiný build (Composer slouží jen pro vývojové nástroje). Testy žádné nejsou, tým má 2–3 vývojáře a vývoj pro byznys nesmí stát.

1. [ ] **Převést aplikaci na PHP 8.5** při zachování kompatibility s 7.4 – viz sekce *Kompatibilita s PHP 8*.
   - [ ] Ověřit všechny stránky a noční report v Dockeru na obou verzích (`php74`, `php85`).
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

### Ostatní

- [ ] Přidat testy (zatím žádné nejsou).
