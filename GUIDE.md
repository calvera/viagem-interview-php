# Průvodce řešením

Stručně, co se udělalo a proč. Podrobnosti jsou v odkazovaných dokumentech.

## Zadání a omezení

1. Převést aplikaci na PHP 8.5.
2. Zrychlit výpis vlastníků.
3. Popsat, jak naložit s celým CRM.

Rozhodnutí určují dvě omezení ze zadání:

- **Nasazuje se nahráním souborů, server se upgraduje nezávisle na kódu.** Kód proto běží zároveň na PHP 7.4 i 8.5, bez build kroku a bez Composeru za běhu (Composer jen pro vývojové nástroje).
- **Neexistují testy a vývoj nesmí stát.** Každá změna je malá, ověřitelná a vratná.

## Jak jsme postupovali

1. **Revize kódu.** Nálezy v [TODO.md](TODO.md): PHP 8, bezpečnost, výkon, chyby v logice.
2. **Dokumentace a plán.** README, TODO, CHANGELOG doplněný zpětně z gitu.
3. **Vývojové nástroje.** PHPStan a PHPUnit 9.6 přes Composer, běží na 7.4 i 8.x.
4. **Pravidla práce.** Implementace → nezávislé review → oprava → vydání (viz *Jak se pracovalo*).
5. **Testy (v2.7.4).** Záchranná síť před první změnou kódu, zelená na 7.4.
6. **Migrace na PHP 8.5 (v2.7.5).** Stejné chování na 7.4 i 8.5, nasazení v [MIGRATION.md](MIGRATION.md).
7. **Chyby v logice (v2.7.6).** Opravy až po migraci, aby migrace chování neměnila.
8. **Bezpečnost (v2.7.7).** SQL injection, CSRF, XSS, mazání poznámky. Bez nové vrstvy, malé změny.
9. **Zbývající opravy (v2.7.8).** Hodnoty `NULL` z databáze, locale, vzorce v CSV, obří číslo strany.
10. **Zrychlení výpisů (v2.7.9–v2.8.3, v2.9.0).** Viz kapitola 2.
    - Mezi tím v2.8.4: stejné zaokrouhlení výměr na 7.4 i 8.5 (kompatibilita).
11. **Jak naložit s celým CRM (v2.9.1).** Viz kapitola 3 a [GOAL.md](GOAL.md).

Každá chyba se před opravou nejdřív prokázala testem nebo reprodukcí. Každá položka má vlastní commit, verzi a git tag ([CHANGELOG.md](CHANGELOG.md)).

## Co máme

| | Stav |
| --- | --- |
| Aplikace na PHP 7.4 i 8.5 | hotovo, připraveno k nasazení |
| Testy | 175 unit, 83 smoke, 21 Playwright; zelené na 7.4 i 8.5 |
| Statická analýza | PHPStan level 2 bez chyb pro 7.4 i 8.5 |
| Lokální prostředí | Docker: PHP 7.4, PHP 8.5, MariaDB se syntetickými daty |
| Nasazení | [MIGRATION.md](MIGRATION.md): server, pořadí, návrat, ověření, tři DB migrace |
| Chyby v logice a bezpečnost | opravené až na pořadí řádků se shodným klíčem ([TODO.md](TODO.md)); přihlášení je otázka pro zadavatele |
| Zrychlení výpisu vlastníků | hotovo: 5 SQL dotazů na stránku místo 103 a tři indexy |
| Známé problémy | [TODO.md](TODO.md), mimo jiné název obce ve výpisu parcel a textový filtr obce |
| Otevřené otázky | [OPENQUESTIONS.md](OPENQUESTIONS.md) |
| Jak naložit s celým CRM | [GOAL.md](GOAL.md): postupná náhrada, tato aplikace jako pilot |

## 0. Testy před migrací

Bez testů nejde ověřit, že migrace nic nerozbila. Testy proto vznikly první a zachycují chování na 7.4. Stejný výsledek na 8.5 znamená stejné chování.

- **Unit (PHPUnit 9.6):** pomocné funkce bez databáze. 9.6 je poslední verze pro PHP 7.4.
- **Smoke (přes HTTP):** všechny stránky vrátí správný obsah bez fatální chyby.
- **Playwright:** totéž v prohlížeči včetně JavaScriptu, pro obě verze PHP.

Testy jen čtou a data nemění. Chyby, které odhalily, se při migraci neopravovaly, aby neměnila chování.

## 1. Migrace na PHP 8.5

Jeden kód, který se na 7.4 i 8.5 chová stejně. Server se tak dá přepnout na 8.5 kdykoli a stejně snadno vrátit.

Nejdřív syntaxe, kterou PHP 8 nenačte. Patří sem i vnořený ternární operátor: je jen uzávorkovaný podle 7.4, protože jednodušší oprava by změnila zobrazení jména.

Důležitější byly tiché změny chování, které by testy bez cíleného hledání nemusely chytit:

- databázová vrstva vrací jiné typy a místo `false` vyhazuje výjimky,
- jinak se porovnává řetězec s číslem (prázdný termín oslovení),
- jinak se zaokrouhlují výměry (našlo se až po migraci, opraveno ve v2.8.4).

U každé se zachovalo chování z 7.4. Ověření: testy a PHPStan zelené na obou verzích, HTML vybraných stránek shodné.

**Stav: kód je připravený k nasazení**, i na server ještě na 7.4. Před přepnutím serveru na 8.5 je potřeba ověřit rozšíření, `php.ini`, proměnné prostředí a práva k zápisu ([MIGRATION.md](MIGRATION.md), kapitola 2).

## 2. Zrychlení výpisu vlastníků

Důkazem je počet SQL dotazů v patičce a `EXPLAIN`, dobu stránky neměříme. Čísla a plány jsou v README.

- **Příčina:** N+1 dotazy (na každý řádek zvlášť). Dotazy na počet parcel (s filtrem obce i na parcely vlastníka) navíc kvůli chybějícímu indexu procházely celou tabulku vlastnictví.
- **Oprava:** dávkové načtení jednou za stránku a index jako SQL migrace. 103 → 5 dotazů na stránku, HTML výpisu bajtově shodné.
- **Výpisy parcel a obcí:** stejná příčina, 54 → 5 a 55 → 5 dotazů.
- **Odkazy z výpisu obcí:** „vlastníci“ vybírá obec podle id místo podobných názvů (rozhodnutí zadavatele), nově i „parcely“.
- **Výchozí řazení vlastníků:** dřív neurčené pořadí uvnitř stejného jména, takže každý index by přeskupil strany. Zadavatel rozhodl o jednoznačném řazení (otázka 5), pak přibyl index a první strany se čtou z něj.
- **Index na obec vlastníka:** možný až po jednoznačném řazení. Filtr podle obce čte jen vlastníky obce.
- **Minimální délka textového filtru obce:** 2 znaky (otázka 7). Jedno písmeno najde skoro všechny vlastníky a s indexem je pomalejší.
- **Chování se nemění:** HTML se před a po každé změně porovnává na obou verzích PHP.
- **Body k ověření na produkci** jsou v [MIGRATION.md](MIGRATION.md), kapitola 5.

## 3. Jak naložit s celým CRM

Návrh je v [GOAL.md](GOAL.md): hlavní text nejvýše 2 strany, případové studie v příloze.

- **Žádný velký přepis, čtyři fáze:** stabilizace (testy, PHPStan, CI), PHP 8.5 v celém CRM, nasazení z gitu s buildem, postupná náhrada modulů v Symfony vedle starého kódu (strangler fig).
- **Zastaralý kód podle používání a míry změn:** nepoužívaný smazat, stabilní jen udržovat, přesouvat nejdřív malý modul s nízkým rizikem, pak často měněné a kritické. Vlastní framework zmrazit.
- **Tato aplikace je pilot:** testy před první změnou, kód na 7.4 i 8.5, smyčka AI agentů s review.

## Jak se pracovalo

Zadání AI nástroje povoluje. Práce běžela s Claude Code v pevné smyčce:

1. Položku z TODO implementuje jeden agent a spustí testy.
2. Jiný agent ji nezávisle zreviduje, jen čtením kódu.
3. Nálezy jdou zpět k implementaci, dokud review není čisté.
4. Pak verze, commit a tag.

O rozsahu (co opravit, co jen zapsat do TODO) rozhodoval vývojář. Pravidla jsou v [CLAUDE.md](CLAUDE.md).

## Dokumenty

- [README.md](README.md): spuštění aplikace a testů, podrobnosti zrychlení.
- [GOAL.md](GOAL.md): jak postupovat s celým CRM.
- [MIGRATION.md](MIGRATION.md): migrace na PHP 8.5, nastavení serveru, DB migrace.
- [TODO.md](TODO.md): stav úkolů a známé problémy.
- [OPENQUESTIONS.md](OPENQUESTIONS.md): otázky pro zadavatele.
- [CHANGELOG.md](CHANGELOG.md): změny po verzích.
