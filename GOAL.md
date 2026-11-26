# Jak naložit s celým CRM

Tato aplikace je výsek CRM a pilot navrženého postupu ([GUIDE.md](GUIDE.md)). Případové studie jsou v příloze.

## Výchozí stav

- Asi 540 tis. řádků, 32 modulů, vlastní framework, žádné testy, tým 2–3 vývojáři.
- Nasazení nahráním změněných souborů bez buildu, server se upgraduje nezávisle na kódu.
- Nálezy pilotu (SQL injection, CSRF, XSS, N+1 dotazy) se v CRM nejspíš opakují.

## Proč postupná náhrada, ne přepis

- Přepis by na dlouho zastavil vývoj pro byznys nebo zdvojil práci (každou změnu dělat dvakrát).
- Bez testů nikdo neví, co přesně starý kód dělá. Přepis by ztratil pravidla, která nejsou nikde zapsaná.
- Velká náhrada naráz je riskantní: u jednoho takového programu se zahodila většina práce (Patterns of Legacy Displacement, příloha).
- Postupná náhrada (strangler fig) přináší hodnotu průběžně, po malých vratných krocích.
- **Vývoj pro byznys nestojí.** Modernizace má stálý, předem domluvený podíl kapacity.

## Fáze

Fáze 3 může začít hned po fázi 1, fáze 4 až po fázích 2 a 3. Fáze 4 běží dlouhodobě.

1. **Stabilizace**
   - Charakterizační testy (Feathers, příloha): smoke testy stránek, Playwright hlavních toků.
   - PHPStan s baseline a rostoucí úrovní (Slack, příloha). CI obojí spouští při každém pushi.
   - Hotovo: každý modul má smoke testy a CI je zelené.
2. **PHP 8.5 v celém CRM**
   - Modul po modulu, kód běží zároveň na 7.4 i 8.5 a chování se nemění.
   - Rector jen s pravidly pro odstraněné a zastaralé zápisy. Plné sady pro 8.x zavádějí syntaxi, kterou 7.4 nenačte, proto až po konci 7.4 (Faugeron, příloha).
   - Opravy chyb zvlášť, ne ve stejné změně jako migrace. Kritické bezpečnostní díry hned.
   - Hotovo: server běží na 8.5, uplynula doba pro návrat na 7.4 a podpora 7.4 končí.
3. **Nasazení z gitu**
   - CI sestaví verzi (Composer, build) a nasadí ji. Návrat na předchozí verzi jedním krokem.
   - DB změny jako číslované idempotentní migrace, jako v pilotu.
   - Hotovo: nikdo už nenahrává soubory ručně.
4. **Postupná náhrada v Symfony**
   - Symfony a balíčky potřebují PHP 8.x a build, proto až teď.
   - Nový front controller spustí Symfony, co neobslouží, předá starému kódu (Symfony docs, příloha).
   - Moduly se přesouvají v pořadí podle další kapitoly.
   - Hotovo pro modul: běží v Symfony a starý kód je smazaný.

## Co se zastaralým kódem

Moduly rozdělit podle používání a míry změn:

- **Používání:** aplikace zapisuje požadavky do `access_log` (`log_access()`), v CRM ověřit.
- **Míra změn:** historie gitu, případně evidence chyb, pokud existuje.
- **V pilotu** se smazaly nevolané funkce (ověřeno prohledáním kódu).

| Kategorie | Co s ní |
| --- | --- |
| Nepoužívané | Smazat, práh domluvit s byznysem. |
| Stabilní, málo měněné | Jen udržovat kompatibilní, nepřepisovat. |
| Často měněné nebo kritické | Přesouvat do Symfony přednostně. |

- **První přesun:** malý modul s nízkým rizikem, aby se ověřil postup. Teprve pak často měněné a kritické.
- **Vlastní framework zmrazit:** žádné nové funkce, od fáze 4 nové věci už jen v Symfony.
- **Přibalené knihovny** (např. `plugin/`) nahradit balíčky z Composeru, až bude build.
- **Uvnitř modulu malé kroky** (Shopify, příloha): oddělit za rozhraní, dvojí zápis, přepnout čtení, smazat staré.

## Tým a AI

- **AI agenti jsou nástroj.** Pilot běžel ve smyčce: jeden agent implementuje a spustí testy, druhý nezávisle reviduje, nálezy jdou zpět, pak verze a tag.
- **Člověk rozhoduje** o rozsahu, pořadí modulů a o tom, co je otázka pro byznys.
- **Testy a review jsou povinná pojistka**, AI funguje lépe s expertem (Thoughtworks, příloha).
- Hodí se na opakovanou práci: testy stránek, kompatibilita s PHP 8, escapování, N+1 dotazy.

## Rizika a měření pokroku

- **Tichá změna chování mimo testy:** porovnávat HTML stránek mezi verzemi PHP.
- **Dvě struktury dlouho vedle sebe, byznys vytlačí modernizaci:** pravidla výše a měření.
- **Rozdíly serveru:** seznam nastavení a ověření po nasazení (v pilotu `MIGRATION.md`).
- **Měření:** moduly s testy, na 8.5 a v Symfony, úroveň PHPStanu a baseline, chyby po nasazení.

## Příloha: případové studie

- **[Symfony docs](https://symfony.com/doc/current/migration.html): migrace existující aplikace na Symfony.** Oficiálně popisují přístup Strangler Fig. `public/index.php` spustí Symfony, a když je odpověď 404, „LegacyBridge“ spustí starý skript. Varianta „Legacy Route Loader“ registruje staré skripty jako routy Symfony a spouští je v controlleru, takže i staré části může převzít zabezpečení Symfony.
  - Pro nás: cílová podoba fáze 4.
- **[Shopify Engineering](https://shopify.engineering/refactoring-legacy-code-strangler-fig-pattern), Adrianna Chang, 2020.** Z modelu `Shop` (přes 3000 řádků) vyjmuli nastavení obchodu v 7 krocích: nové rozhraní, přesměrovat volání, nová tabulka, dvojí zápis v transakci, doplnění dat, přepnout čtení, přestat zapisovat do starého sloupce a smazat ho. Bez výpadku byl zásadní požadavek.
  - Pro nás: postup uvnitř modulu.
- **[Slack Engineering](https://slack.engineering/hacklang-at-slack-a-better-php/), Scott Sandler, 2020.** Backend od 2014 na PHP 5, 2016 HHVM, pak Hack. Začali v „partial mode“ (netypované hodnoty jako „any“), typy přidávali postupně po souborech a pak přepínali na strict. Článek sám dodává, že dnes už postupná migrace z PHP na Hack nejde, protože Hack opustil kompatibilitu s PHP.
  - Pro nás: princip postupného zpřísňování, nástrojem je PHPStan s baseline.
- **[Loïc Faugeron](https://gnugat.github.io/2025/11/19/xl-6-php-8.html), 2025, „eXtreme Legacy“.** Aplikace BisouLand z roku 2005 z PHP 5.6 na 8.5. Rector postupně po verzích (sady PHP_56 → PHP_70 …), změny našel u 7.3, 8.1 a 8.4.
  - Pro nás: Rector po verzích, plné sady až po konci 7.4.
- **[Tomáš Votruba](https://www.slideshare.net/slideshow/instant-upgrades-with-rector-php-day-verona-italy-2019-05/145575940), PHP Day 2019.** Rector převedl projekt z Nette na Symfony jedním příkazem, 54 357 řádků, 151 rout. Slajdy tvrdí „jakýkoli legacy projekt za 1 měsíc“. Je to tvrzení autora nástroje, ne nezávislé.
  - Pro nás: automatizace přesunu je možná, ověřit na pilotním modulu.
- **[Thoughtworks](https://www.thoughtworks.com/insights/blog/legacy-modernization/legacy-modernization-in-the-age-of-ai), Subhrajit Roy a Nick Hill, 2026.** Dvoučlenný tým s Claude Code nejdřív pochopil starý dashboard v Angularu 2.2, pak ho přepsal do Reactu s pojistkami (čistý kód, testy, přístupnost). Odhad byl nejméně 6 měsíců pro 6 lidí, do produkce šel za 6 týdnů se 3 lidmi, asi 20 % odhadu. AI funguje výrazně lépe ve dvojici s expertem a s výslovnými pojistkami (testy, lintery).
  - Pro nás: AI s expertem a pojistkami. Šlo o jeden dashboard; pro CRM s 32 moduly to není argument pro velký přepis.
- **Ian Cartwright, Rob Horn, James Lewis, 2024, [Patterns of Legacy Displacement](https://martinfowler.com/articles/patterns-legacy-displacement/) (web Martina Fowlera).** Čtyři činnosti: definovat výsledky, rozdělit problém, dodávat po částech, změnit organizaci. Vzory mimo jiné Branch by Abstraction, Legacy Mimic, Divert the Flow, Feature Parity. U jednoho programu s náhradou „big bang“ se většina práce zahodila.
  - Pro nás: žádný velký přepis, dodávky po modulech.
- **Michael Feathers, *Working Effectively with Legacy Code*.** Charakterizační testy zachytí, co kód dělá dnes (ne co by měl), ještě před změnou.
  - Pro nás: úkol 0 v pilotu, fáze 1.
