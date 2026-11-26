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
10. **Zrychlení výpisu vlastníků.** Nejdřív příčina z počtu SQL dotazů a `EXPLAIN` (bez měření doby stránky): N+1 dotazy, které kvůli chybějícímu indexu procházely celou tabulku `ownerships` 50× (s filtrem obce 100×) na stránku. Oprava: dávkové načtení jednou za stránku a index `ownerships.owner_id` jako SQL migrace. 103 → 5 dotazů, s filtrem obce 153 → 6; HTML výpisu bajtově shodné. Řazení a `count(*)` beze změny (viz sekce 2). Potom stejné N+1 ve výpisu parcel (vlastníci parcely) a obcí (počet parcel): 54 → 5 a 55 → 5 dotazů, bez nového indexu. Nakonec odkaz „vlastníci“ z výpisu obcí: podle rozhodnutí zadavatele filtruje podle id obce místo podřetězce názvu („Lhota“ 240 vlastníků místo 20 000). Potom odkaz „parcely“ z výpisu obcí a jednoznačné pořadí vlastníků se stejným příjmením ve výpisu parcel. Nakonec výchozí řazení výpisu vlastníků podle rozhodnutí zadavatele: podle sloupců a id s indexem, první strany se čtou z indexu místo řazení 500 tisíc vlastníků; přehled DB migrací v MIGRATION.md. Potom index na obec vlastníka, který čekal na jednoznačné řazení: filtr podle id obce čte jen vlastníky obce v pořadí stránky místo průchodu všemi, bez změny kódu.
11. **Jak naložit s celým CRM.** Čeká.

Každá dokončená položka má vlastní commit, verzi v `composer.json` a git tag. Změny po verzích jsou v [CHANGELOG.md](CHANGELOG.md).

## Co máme

| | Stav |
| --- | --- |
| Aplikace na PHP 7.4 i 8.5 | hotovo (v2.7.5), připraveno k nasazení; před přepnutím serveru na 8.5 ověřit nastavení PHP na serveru; ošetřené i hodnoty `NULL` z databáze a locale |
| Testy | 137 unit, 74 smoke, 20 Playwright; zelené na PHP 7.4 i 8.5 |
| Statická analýza | PHPStan level 2, bez chyb pro 7.4 i 8.5 |
| Lokální prostředí | Docker: PHP 7.4 (`:8074`), PHP 8.5 (`:8085`), MariaDB se syntetickými daty |
| Postup nasazení | [MIGRATION.md](MIGRATION.md): nastavení serveru, pořadí, návrat zpět, ověření, přehled DB migrací (tři indexy pro výpis vlastníků) |
| Chyby v logice | opravené (TODO, *Chyby v logice*), včetně pádů na PHP 8 při `share_den = 0` a poli v parametrech, obřího čísla strany a `format_share()` se jmenovatelem 0 |
| Bezpečnost | opravené SQL injection, CSRF, mazání poznámky (POST, jen u vlastníka), XSS, vzorce v CSV exportu (včetně uvozovek za zpětným lomítkem), odstraněný nepoužívaný kód s neošetřeným SQL; přihlášení je otevřená otázka pro zadavatele ([OPENQUESTIONS.md](OPENQUESTIONS.md)) |
| Známé problémy | [TODO.md](TODO.md): výkon (sekce *Výkon*: textový filtr obce), neurčené pořadí řádků se shodným klíčem řazení v detailu vlastníka a při řazení výpisů podle sloupce (*Chyby v logice*, zatím bez projevu) |
| Otevřené otázky pro zadavatele | [OPENQUESTIONS.md](OPENQUESTIONS.md): význam filtru obce, termín oslovení, přihlášení uživatelů, pravidla validace vstupu; výchozí řazení vlastníků rozhodnuto a provedeno |
| Zrychlení výpisu vlastníků | hotovo: 5 SQL dotazů místo 103 (s filtrem obce 6 místo 153), index jako SQL migrace; výchozí řazení podle sloupců a id s indexem (první strany z indexu, filtrované první strany čtou až asi 4× víc stránek, ale jsou mnohem rychlejší; „neoslovovat“ na 5. až 60. straně čte až 21× víc stránek); index na obec vlastníka (filtr podle id obce `ref` místo průchodu tabulkou, asi 25× méně stránek na 1. straně); výpis parcel 5 místo 54, obcí 5 místo 55 |
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

Dobu stránky neměříme, že je výpis pomalý, je zřejmé. Důkazem je počet SQL dotazů v patičce a `EXPLAIN`. Čísla a plány před a po jsou v README (*Zrychlení výpisu vlastníků*).

- **Příčina.** Patička ukázala 103 dotazů na stránku, s filtrem obce 153: pro každý z 50 řádků název obce a počet parcel, s filtrem navíc všechny parcely vlastníka. Na `ownerships.owner_id` chyběl index, takže dotaz na počet i na parcely prošel celou tabulku (1,2 mil. řádků, `ALL`).
- **Oprava N+1.** `Section` dostala funkci volanou jednou se všemi řádky stránky. Výpis vlastníků v ní načte názvy obcí, počty parcel a parcely pro „V obci“ dotazy `in (…)`: 5 dotazů, s filtrem obce 6. Sloupec „V obci“ se dál počítá v PHP stejným porovnáním, protože `LIKE` s českou kolací by mohl najít jiné obce.
- **Index.** `ownerships.owner_id` jako idempotentní SQL soubor, protože nasazení nemá migrační krok. Online, bez zamčení tabulky; kód funguje i bez něj. Postup, cena metadata locku a návrat v [MIGRATION.md](MIGRATION.md).
- **Chování se nesmí změnit.** HTML výpisu se před a po změně porovnalo na obou verzích PHP pro 17 kombinací filtrů, hledání, řazení a stran: bajtově shodné.
- **Co se zpočátku záměrně neměnilo.** Výchozí řazení podle `concat(příjmení, jméno)`: 500 tisíc vlastníků má jen 1 070 různých jmen a pořadí uvnitř jména je neurčené, takže každá změna řazení nebo indexu přeskupí vlastníky na stranách. To bylo rozhodnutí pro zadavatele ([OPENQUESTIONS.md](OPENQUESTIONS.md), otázka 5), provedené později (poslední bod). Ze stejného důvodu se zatím nepřidal index na obec vlastníka (ověřeno: změní 1. stranu „Lhota“); přidaný je až po jednoznačném řazení (poslední bod). `count(*)` je jeden průchod indexem a výpis ukazuje přesný počet. Filtr obce je samostatná položka v TODO.
- **Výpis parcel a obcí.** Stejná příčina, ale jednotlivé dotazy byly levné (`ref` na index), takže vadil jen jejich počet. Parcely načtou vlastníky celé stránky jedním dotazem (join by parcelu rozmnožil podle vlastníků a rozbil stránkování). Obce mají počet parcel jako poddotaz v hlavním dotazu: `ANALYZE` ukázal, že se vyhodnotí jen pro 50 řádků stránky a čte stejně jako dávka, kdežto `left join … group by` čte 800 tisíc řádků a rozbije počet obcí. HTML shodné, u parcel až na pořadí vlastníků se stejným příjmením, které bylo neurčené už dříve (opraveno samostatně, viz další bod).
- **Pořadí vlastníků se stejným příjmením (výpis parcel).** Vlastníci parcely se řadili jen podle příjmení, takže vlastníci se stejným příjmením (a s nimi čísla LV) měli neurčené pořadí, které se mezi načteními mohlo lišit. Řadí se navíc podle jména, id vlastníka a id vztahu, pořadí je jednoznačné. Plán dotazu stejný. Opakovaná načtení 13 adres výpisu jsou bajtově shodná, 7.4 i 8.5 stejně, a po seřazení uvnitř buněk se rovnají stavu před změnou. Podobné shody v detailu vlastníka a při řazení podle sloupce se zatím neprojevily, jsou zapsané v TODO. Podrobnosti v README (*Pořadí vlastníků se stejným příjmením*).
- **Odkaz „vlastníci“ podle id obce.** Odkaz u obce posílal název a filtr hledal podřetězec, takže „Lhota“ našla i Horní, Dolní… Lhota: 20 000 vlastníků místo 240. Zadavatel rozhodl (26. 11. 2026), že odkaz vybírá přesně jednu obec podle id. Nový obecný skrytý filtr v `Section` (jen číslice, parametr SQL, popisek „Obec: název ×“, skryté pole ve formuláři); „V obci“ se s ním počítá v SQL podle id obce, ne podle názvu. Význam zůstává obec vlastníka jako dosud; zda má jít o vlastníky parcel v obci, je dál otevřená otázka 1 v [OPENQUESTIONS.md](OPENQUESTIONS.md). Textové pole „Obec“ se nemění. Ověřeno proti SQL (počty, „V obci“), HTML ostatních stránek bajtově shodné, ve výpisu obcí se změnil jen odkaz. Dotaz dál prochází všech 500 tisíc vlastníků (`ALL`); index na obec vlastníka se ze stejného důvodu jako výše tehdy nepřidal (přidaný později, poslední bod). Podrobnosti v README (*Odkaz „vlastníci“ podle id obce*).
- **Odkaz „parcely“ u obce (požadavek zadavatele).** Vedle „vlastníci“ nový odkaz na parcely obce podle id. Výpis parcel použil stejný skrytý filtr ze `Section` (`p.municipality_id`), takže nevznikl nový kód filtru; dotazy jdou přes existující index `idx_parcels_municipality`, stránka má 6 SQL dotazů. Oba odkazy mají vzhled malého tlačítka (existující styl `a.button` v menší variantě), zůstávají odkazy. Ověřeno proti SQL a sloupci „Parcel“; HTML výpisů parcel bez filtru, vlastníků a obcí (kromě buňky s odkazy) je na 7.4 i 8.5 stejné jako před změnou. Podrobnosti v README (*Odkaz „parcely“ podle id obce*).
- **Výchozí řazení vlastníků (rozhodnutí zadavatele, otázka 5).** Řadí se `příjmení, jméno, id` místo `concat(příjmení, jméno)` a přibyl index na tyto tři sloupce. Pořadí je jednoznačné, vlastníci se stejným jménem jsou podle id. Před změnou se porovnalo pořadí celé tabulky: posloupnost všech 1 070 jmen je stejná, liší se jen pořadí uvnitř jména, a to zadavatel přijal. Výsledek je s indexem i bez něj stejný, index jen zrychluje: první strany výpisu (bez filtru, s typem, zákazem oslovení, textovým filtrem obce) se čtou z indexu, bez filtru 50 řádků místo seřazení 500 tisíc. `EXPLAIN` / `ANALYZE` ukázal i to, kde nepomůže: vzdálené strany s velkým OFFSET a filtry na sloupce bez indexu dál procházejí tabulku. Bez statistik by index filtr podle id obce a hledání IČO zhoršil (optimalizátor nezná selektivitu a čte celý index), proto migrace sbírá histogramy těchto sloupců; `ANALYZE TABLE` je přímo v souboru migrace, aby se nedal zapomenout a index a statistiky vznikly spolu. Hledání jména by s indexem četlo u vzácného jména skoro celý index (`like '%…%'` optimalizátor neodhadne, 1,2 mil. stránek místo 4 tis.); s vyplněným hledáním se proto řadí podle výrazu se stejným pořadím, který z indexu číst nejde, a hledání má plán jako dříve. Hint pro ignorování indexu by bez migrace byl chyba SQL. Filtrované první strany (typ F, „neoslovovat“) čtou z indexu až asi 4× víc stránek z buffer poolu než dřívější průchod tabulkou, ale podle `ANALYZE` jsou mnohem rychlejší. „Neoslovovat“ na 5. až 60. straně čte mnohonásobně víc (až 21×), doba je do 30. strany nižší a dál zhruba stejná, od 70. strany se plán vrací k průchodu tabulkou; s chladným buffer poolem by tyto strany mohly být pomalejší. Ověřeno: posloupnost jmen na 20 stránkách výpisu a 4 exportech stejná jako dříve, opakovaná načtení a 7.4 proti 8.5 bajtově shodná, parcely a obce beze změny. MIGRATION.md má nově přehled všech DB migrací (soubor, verze, povinnost, kdy vůči nahrání souborů, návrat, doba), podle kterého se nasazuje. Podrobnosti v README (*Výchozí řazení vlastníků*).
- **Index na obec vlastníka.** Poslední vazba bez indexu. Dřív by změna plánu přeskupila vlastníky se stejným jménem na stranách, po jednoznačném výchozím řazení už pořadí výpisu a exportu na plánu nezávisí. Řazení podle sloupce „Založeno“ jednoznačné není (jen podle času, bez id): na testovacích datech jsou časy jedinečné, na produkci je to bod k ověření v MIGRATION.md a oprava je samostatná položka v TODO. Index začíná obcí a pokračuje sloupci řazení, takže stranu s filtrem podle id obce čte rovnou v pořadí: 1. strana 156 stránek z buffer poolu místo asi 4 tisíc, bez řazení; prostý index na obec by stranu řadil (876 stránek) a u velké obce na produkci by řadil tisíce vlastníků na každé straně. Kód se nemění, je to další SQL migrace se statistikami; histogramy z předchozí migrace se znovu sbírat nemusí (popisují data, ne indexy). Zrychlil i textový filtr „Obec“ (počet „Lhota“ 825 stránek místo milionu), jen samotný dotaz na řádky „Lhota“ (20 000 vlastníků) čte na 1. až 9. straně víc stránek, protože se teď na každé straně řadí všichni nalezení; celá stránka s počtem je přesto mnohem levnější (1. strana asi 16×), proto je to vyhodnoceno jako zlepšení. Dotaz na řádky ale roste s počtem nalezených (asi 3 stránky na vlastníka), takže velmi široký filtr je s indexem pomalejší než bez něj: podle doby `ANALYZE` zhruba od 40 % všech vlastníků, u jednoho písmena (80 až 93 % vlastníků) změřeno 2,2× až 2,6×; běžné části názvu obce jsou levnější. Na produkci je to bod k ověření. Ostatní případy beze změny. Ověřeno: HTML 41 adres před a po bajtově shodné na 7.4 i 8.5, opakovaná načtení také. Podrobnosti v README (*Index na obec vlastníka*) a MIGRATION.md (5.3).

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
