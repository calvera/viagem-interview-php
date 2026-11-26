# Průvodce řešením

Stručný přehled, co se v repozitáři udělalo a proč. Podrobnosti jsou v odkazovaných dokumentech.

## Zadání a omezení

1. Převést aplikaci na PHP 8.5.
2. Zrychlit výpis vlastníků.
3. Popsat, jak naložit s celým CRM.

Rozhodnutí určují hlavně dvě omezení ze zadání:

- **Nasazuje se nahráním změněných souborů a server se upgraduje nezávisle na kódu.** Kód proto musí běžet zároveň na PHP 7.4 i 8.5 a nesmí potřebovat build ani Composer za běhu. Composer slouží jen vývojovým nástrojům.
- **Neexistují testy a vývoj pro byznys nesmí stát.** Každá změna musí být malá, ověřitelná a vratná.

## Jak jsme postupovali

1. **Revize kódu.** Přečíst celou aplikaci a sepsat nálezy do [TODO.md](TODO.md): kompatibilita s PHP 8, bezpečnost, výkon, chyby v logice. Problémy s PHP 8 ověřené `php -l`.
2. **Dokumentace a plán.** README s popisem funkcí, TODO rozdělené na úkoly ze zadání a známé problémy, CHANGELOG doplněný zpětně z historie gitu.
3. **Vývojové nástroje.** Composer jen pro vývoj (PHPStan, PHPUnit 9.6), platforma nastavená na PHP 7.4, aby nástroje běžely na obou verzích. Aplikace Composer nepotřebuje.
4. **Pravidla práce.** Smyčka implementace → nezávislé review → oprava → vydání (viz *Jak se pracovalo*), verze a git tag pro každou položku.
5. **Testy (v2.7.4).** Záchranná síť ještě před první změnou kódu: unit, smoke a Playwright, zelené na PHP 7.4. Chyby, které testy odhalily, jsou zapsané v TODO.
6. **Migrace na PHP 8.5 (v2.7.5).** Kód upravený tak, aby běžel na 7.4 i 8.5 se stejným chováním. Testy zelené na obou verzích, popis nasazení v [MIGRATION.md](MIGRATION.md). Nálezy z review, které se netýkají kompatibility, jen zapsané do TODO.
7. **Oprava logických chyb.** Chyby z revize a z testů (*Chyby v logice* v TODO), každá nejdřív prokázaná testem nebo reprodukcí: `trim_text`, `format_datetime('0000-00-00')`, apostrofy v `getWhere`, dělení nulou při `share_den = 0` (na PHP 8 pád), pole v parametrech GET/POST (text „Array“, na PHP 8 pád), `duedate` ve formuláři po chybě validace, limit exportu. Opravy až po migraci, aby migrace chování neměnila; malé změny, stejné chování na 7.4 i 8.5.
8. **Oprava bezpečnostních chyb.** Položky z *Bezpečnost* v TODO, každá nejdřív prokázaná neškodnou reprodukcí (hledání, které vrátí všechny řádky, chyba SQL v logu, dočasná poznámka s HTML) nebo testem. Hodnoty filtrů, hledání a našeptávače jdou do SQL jako parametry; poznámka se maže formulářem POST jen u svého vlastníka; všechny formuláře mají CSRF token ze session; pole ve formuláři úpravy je chyba validace; výstupy z uživatelských dat se escapují. Malé změny bez nové vrstvy (tři krátké funkce pro CSRF), stejné výsledky pro běžné hodnoty, stejně na 7.4 i 8.5. Přihlášení zůstává otevřené: kdo a jak se přihlašuje, je rozhodnutí o produktu.
9. **Zbývající opravy (PHP 8, bezpečnost, logika).** Poslední otevřené položky ze tří sekcí TODO, každá opět nejdřív prokázaná: dočasné řádky s `NULL` v databázi a běh s `E_ALL` (hlášení Deprecated na 8.5), noční report v dočasném kontejneru PHP 7.4 s vygenerovaným `cs_CZ.UTF-8` (čárka místo tečky), dočasný vlastník se vzorcem v exportu CSV, obří číslo strany (chyba SQL), unit testy. Opravy jsou přetypování hodnot z databáze se stejným výstupem, `LC_NUMERIC = C` (stejný formát čísel na 7.4 i 8.5 bez ohledu na locale serveru), apostrof před vzorcem v CSV (telefonní čísla beze změny) a CSV bez escape znaku, aby uvozovka za zpětným lomítkem nerozdělila buňku, odstranění nepoužívaného kódu s neošetřeným SQL, omezení strany na poslední a `format_share()` se jmenovatelem 0. Databáze je po ověření stejná jako předtím (kontrolní součty tabulek).
10. **Zrychlení výpisu vlastníků.** Čeká.
11. **Jak naložit s celým CRM.** Čeká.

Každá dokončená položka má vlastní commit, verzi v `composer.json` a git tag. Změny po verzích jsou v [CHANGELOG.md](CHANGELOG.md).

## Co máme

| | Stav |
| --- | --- |
| Aplikace na PHP 7.4 i 8.5 | hotovo (v2.7.5), připraveno k nasazení; před přepnutím serveru na 8.5 ověřit nastavení PHP na serveru; ošetřené i hodnoty `NULL` z databáze a locale |
| Testy | 116 unit, 38 smoke, 19 Playwright; zelené na PHP 7.4 i 8.5 |
| Statická analýza | PHPStan level 2, bez chyb pro 7.4 i 8.5 |
| Lokální prostředí | Docker: PHP 7.4 (`:8074`), PHP 8.5 (`:8085`), MariaDB se syntetickými daty |
| Postup nasazení | [MIGRATION.md](MIGRATION.md): nastavení serveru, pořadí, návrat zpět, ověření |
| Chyby v logice | opravené (TODO, *Chyby v logice*), včetně pádů na PHP 8 při `share_den = 0` a poli v parametrech, obřího čísla strany a `format_share()` se jmenovatelem 0 |
| Bezpečnost | opravené SQL injection, CSRF, mazání poznámky (POST, jen u vlastníka), XSS, vzorce v CSV exportu (včetně uvozovek za zpětným lomítkem), odstraněný nepoužívaný kód s neošetřeným SQL; přihlášení je otevřená otázka pro zadavatele ([OPENQUESTIONS.md](OPENQUESTIONS.md)) |
| Známé problémy | [TODO.md](TODO.md): výkon (sekce *Výkon*) |
| Otevřené otázky pro zadavatele | [OPENQUESTIONS.md](OPENQUESTIONS.md): přihlášení uživatelů, pravidla validace vstupu |
| Zrychlení výpisu vlastníků | čeká |
| Popis, jak naložit s celým CRM | čeká |

## 0. Testy před migrací

Bez testů nejde ověřit, že migrace nic nerozbila. Testy proto vznikly jako první a zachycují dnešní chování na PHP 7.4. Po migraci stejná sada běží i na 8.5: stejný výsledek na obou verzích znamená, že se chování nezměnilo.

- **Unit (PHPUnit 9.6):** pomocné funkce a skládání SQL, bez databáze. PHPUnit 9.6 je poslední verze, která běží na 7.4.
- **Smoke (PHPUnit přes HTTP):** všechny stránky vrátí 200, správný obsah a žádnou fatální chybu.
- **Playwright:** totéž v prohlížeči a navíc JavaScript (našeptávač, potvrzení mazání, stažení CSV). Jeden běh otestuje obě verze PHP.

Testy jen čtou, data nemění. Test mazání poznámky sám blokuje každý POST, takže nic nesmaže, ani když selže JavaScript aplikace.

Testy při psaní odhalily tři chyby (`trim_text`, `format_datetime`, apostrofy v `getWhere`). Během migrace se neopravovaly, aby neměnila chování; opravené jsou až v kroku *Oprava logických chyb* a testy teď ověřují správné chování.

## 1. Migrace na PHP 8.5

Jeden kód pro obě verze, který se na 7.4 i 8.5 chová stejně. Opravy chyb v logice (krok 7) mění chování na obou verzích stejně. Server se tak dá přepnout na 8.5 kdykoli a stejně snadno vrátit zpět.

Kromě zjevných chyb (syntaxe, kterou PHP 8 nenačte) byly nejdůležitější změny, které PHP 8 provede potichu a testy by je bez cíleného hledání nemusely chytit:

- **PDO od 8.1 vrací čísla místo řetězců.** Kontroly jako `do_not_contact === '1'` by přestaly platit. Nastavením `ATTR_STRINGIFY_FETCHES` vrací PDO řetězce jako na 7.4.
- **PDO od 8.0 vyhazuje výjimky.** Aplikace počítá s tím, že chybný dotaz vrátí `false`. Výchozí režim ze 7.4 je nastavený výslovně.
- **Porovnání řetězce s číslem.** `'0000-00-00' == 0` je na 7.4 pravda, na 8 ne. Na tom stojí prázdný termín oslovení.
- **Vnořený ternární operátor** je uzávorkovaný tak, jak ho vyhodnocovalo 7.4. Jednodušší úprava by změnila zobrazení jména u části vlastníků.

Ověření: všechny testy zelené na obou verzích, PHPStan bez chyb pro 7.4 i 8.5, HTML vybraných stránek na obou verzích shodné.

**Stav: kód je připravený k nasazení.** Dá se nahrát na produkční server ještě na PHP 7.4, protože kód běží na 7.4 i 8.5 stejně; opravy chyb v logice mění chování na obou verzích stejně. Před přepnutím serveru na PHP 8.5 je potřeba ověřit nastavení PHP na serveru:

- rozšíření PHP (`php -m` pro web i CLI),
- `php.ini`: `default_charset = UTF-8`, nastavení odstraněná v PHP 8,
- proměnná `DB_HOST` pro web (Apache nebo FPM) i pro cron,
- práva k zápisu do `logs/`, `reports/` a adresáře se session.

Locale `cs_CZ.UTF-8` už ověřovat není potřeba: formát desetinných čísel na něm od kroku 9 nezávisí.

Kontrola dat na řádky s `share_den = 0` je jen doporučená: po opravě logických chyb už na PHP 8 nic neshodí.

Postup nasazení, kontrolní příkazy a návrat zpět popisuje [MIGRATION.md](MIGRATION.md).

Nálezy, které se netýkají kompatibility (bezpečnost, ošetření vstupů, další chyby), jsou zapsané v TODO a v migraci se neopravovaly, aby změna zůstala malá.

## 2. Zrychlení výpisu vlastníků

Čeká. Postup: nejdřív zjistit příčinu (počet SQL dotazů, `EXPLAIN`), pak opravit a porovnat dotazy před a po. Dobu stránky neměříme, že je výpis pomalý, je zřejmé.

## 3. Jak naložit s celým CRM

Čeká.

## Jak se pracovalo

Zadání AI nástroje povoluje. Práce běžela s Claude Code v pevné smyčce:

1. Každou položku z TODO implementuje jeden agent a spustí testy.
2. Jiný agent ji nezávisle zreviduje, jen čtením kódu.
3. Nálezy jdou zpět k implementaci, dokud review není čisté.
4. Teprve pak verze, commit a tag.

Rozhodnutí o rozsahu (co opravit, co jen zapsat do TODO) dělal vývojář. Pravidla smyčky jsou v [CLAUDE.md](CLAUDE.md).

## Dokumenty

- [README.md](README.md): spuštění aplikace a testů.
- [MIGRATION.md](MIGRATION.md): migrace na PHP 8.5 a nastavení serveru.
- [TODO.md](TODO.md): zadání, stav úkolů a známé problémy.
- [OPENQUESTIONS.md](OPENQUESTIONS.md): otevřené otázky pro zadavatele.
- [CHANGELOG.md](CHANGELOG.md): změny po verzích.
