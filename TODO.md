# TODO

## Zadání (technická úloha Viagem)

Omezení ze zadání:

- Nasazuje se nahráním změněných souborů, bez build kroku. Composer jen pro vývojové nástroje.
- Server se upgraduje nezávisle na kódu, kód proto musí běžet na PHP 7.4 i 8.5.
- Testy nejsou, tým má 2–3 vývojáře a vývoj pro byznys nesmí stát.

0. [x] **Testy jako záchranná síť pro migraci** (v2.7.4, 2026-11-26)
   - Cíl: zachytit dnešní chování aplikace, aby šlo po migraci ověřit, že se nic nerozbilo. Testy data v DB nemění.
   - Řešení: testy napsané a zelené na PHP 7.4, po migraci se stejné spustí na 8.5.
     - [x] PHPUnit: konfigurace, unit testy pomocných funkcí a skládání SQL.
     - [x] PHPUnit: smoke testy všech stránek přes HTTP (stránka se načte, správný nadpis, žádná PHP chyba).
     - [x] Playwright: konfigurace pro obě verze PHP, hlídá chyby v konzoli i v těle stránky.
     - [x] Playwright: navigace, výpisy, filtry, detail a formulář vlastníka.
     - [x] Playwright: našeptávač obcí, potvrzení mazání poznámky, stažení CSV exportu.
     - [x] Ověření a dokumentace: vše zelené na 7.4 (na 8.5 tehdy očekávaně červené), README sekce „Testy“.
1. [x] **Převést aplikaci na PHP 8.5** (v2.7.5, 2026-11-26)
   - Cíl: aplikace běží na PHP 8.5 a dál i na 7.4.
   - Řešení: opravy v sekci *Kompatibilita s PHP 8*.
     - [x] Všechny testy (úkol 0) zelené na 7.4 i 8.5.
     - [x] Noční report ověřený v Dockeru na obou verzích.
     - [x] Statická analýza (PHPStan) pro 7.4 i 8.5.
2. [x] **Zrychlit výpis vlastníků** (v2.7.9, 2026-11-26)
   - Cíl: výpis vlastníků se načítá pomalu.
   - Řešení: viz sekce *Výkon*.
     - [x] Nejdřív zjistit příčinu: stránka dělala přes 100 dotazů, pro každý řádek zvlášť. Dotazy na počet parcel (s filtrem obce i na parcely vlastníka) procházely celou tabulku vlastnických vztahů.
     - [x] Opravit: data pro celou stránku se načtou několika dotazy a přibyl index. Výchozí řazení zůstalo, jeho změnu rozhodl zadavatel (otázka 5, položka *Výchozí řazení vlastníků*).
     - [x] Index dodat jako SQL migraci s návodem v `MIGRATION.md` (nasazení nemá migrační krok).
     - [x] Počet SQL dotazů a `EXPLAIN` před a po zapsat do README.
3. [x] **Popsat, jak naložit s celým CRM** (v2.9.1, 2026-11-27)
   - Cíl: nejvýše 2 strany o postupu s celým CRM.
   - Řešení: [GOAL.md](GOAL.md). Postupná náhrada po stabilizaci, tato aplikace jako pilot, případové studie v příloze.

Odevzdání:

- [ ] Repozitář se smysluplnou historií commitů (jeden commit na jednu změnu).
- [ ] README: jak aplikaci spustit a proč jsem se rozhodl, jak jsem se rozhodl.

## Známé problémy

Z revize kódu (listopad 2026). Položky označené ✔ jsou ověřené syntaktickou kontrolou na PHP 8, ostatní vycházejí ze čtení kódu a před opravou se reprodukují.

### Kompatibilita s PHP 8

Všechny opravy zachovávají chování z PHP 7.4.

- [x] ✔ **Vlastní funkce `match()`**
  - Problém: od PHP 8 je to rezervované slovo, aplikace se vůbec nespustí.
  - Řešení: funkce přejmenovaná.
- [x] ✔ **Vnořený ternární operátor v detailu vlastníka**
  - Problém: na PHP 8 fatální chyba, detail se nezobrazí.
  - Řešení: doplněné závorky podle vyhodnocení v 7.4. Náhrada funkcí `owner_name()` by změnila jméno osoby s titulem bez křestního jména, proto ne.
- [x] ✔ **Zastaralé zápisy** (`case` se středníkem, `(integer)` / `(boolean)`, backtick)
  - Problém: PHP 8 hlásí varování, v dalších verzích to bude chyba.
  - Řešení: nahrazené současnými zápisy.
- [x] **Funkce, které v PHP 8 nejsou nebo se chovají jinak**
  - Problém: chyba nebo varování; noční report navíc vždy hlásil nedostupný server.
  - Řešení: nahrazené nebo odstraněné.
    - `get_magic_quotes_gpc()`, `strftime()`, kontrola curl handle (objekt místo resource).
    - ✔ `curl_close()` (od 8.5 varování, od 8.0 nic nedělá): odstraněno.
- [x] **`count()` na `null`** (neexistující vlastník, vlastník bez parcel)
  - Problém: na PHP 8 detail vlastníka spadne.
  - Řešení: funkce vrací prázdné pole, detail s chybějícím vlastníkem počítá (viz *Chyby v logice*).
- [x] **Nedeklarované vlastnosti tříd**
  - Problém: PHP 8.2 hlásí varování, v PHP 9 to bude chyba.
  - Řešení: vlastnosti deklarované.
- [x] ✔ **Přibalená knihovna Minify**
  - Problém: starý zápis přístupu ke znaku řetězce, na 8.5 se nenačte hlavička stránky.
  - Řešení: jediná úprava knihovny, nový zápis.
- [x] ✔ **Vlastní `mb_ucFirst()` / `mb_lcFirst()`**
  - Problém: PHP 8.4 má stejné funkce vestavěné, aplikace spadne.
  - Řešení: vlastní se deklarují jen tam, kde vestavěné chybí.
- [x] ✔ **Parametr s výchozí hodnotou `null` bez typu `?`**
  - Problém: PHP 8.4 hlásí varování, v PHP 9 to bude chyba.
  - Řešení: typ doplněný.
- [x] ✔ **Čísla z databáze jako čísla místo textu**
  - Problém: od PHP 8.1 by přestalo fungovat porovnání příznaku „neoslovovat“.
  - Řešení: databáze vrací text jako na 7.4.
- [x] ✔ **Prázdný termín oslovení**
  - Problém: na 8.x by vlastník bez termínu měl termín „30. 11. -0001“.
  - Řešení: porovnání upravené, výsledek jako na 7.4.
- [x] **Prázdné hodnoty z databáze do vestavěných funkcí** (v2.7.8, 2026-11-26)
  - Problém: PHP 8.1 hlásí varování, v PHP 9 to bude chyba. Týká se prázdného druhu pozemku, obce nebo výměry. Testovací data je nemají, testy je proto nezachytily.
  - Řešení: hodnoty se převedou na text nebo číslo, výstup je stejný jako dosud. Ověřeno dočasnými řádky s prázdnými hodnotami.
- [x] **Chybějící parametry požadavku do vestavěných funkcí**
  - Problém: PHP 8.1 hlásí varování, v PHP 9 to bude chyba. Týká se filtrů, hledání, řazení, formulářů a našeptávače bez zadaného textu.
  - Řešení: opraveno při migraci, chybějící hodnota se bere jako prázdný text.
- [x] ✔ **Chyba SQL jako výjimka**
  - Problém: PHP 8 při chybě SQL vyhodí výjimku, aplikace s tím nepočítá. Noční report při druhém běhu za den spadl.
  - Řešení: nastavené chování jako na 7.4 (chyba vrátí `false`).
- [x] ✔ **CSV export: parametr `escape`**
  - Problém: PHP 8.4 hlásí varování u každého řádku, v PHP 9 to bude chyba.
  - Řešení: předává se výslovně. Po opravě vzorců v CSV (*Bezpečnost*) prázdný.
- [x] ✔ **Konstanta pro nastavení připojení k MySQL**
  - Problém: PHP 8.5 hlásí varování, náhrada v 7.4 neexistuje.
  - Řešení: použije se ta, která je k dispozici.
- [x] ✔ **Pole místo hodnoty v parametru adresy** (v2.7.6, 2026-11-26)
  - Problém: podvržená adresa shodí stránku na PHP 8, jinde se z pole stane text „Array“. Aplikace takové odkazy netvoří.
  - Řešení: pole se ignoruje, jako by parametr chyběl (viz *Chyby v logice*).
- [x] **České locale na serveru** (v2.7.8, 2026-11-26)
  - Problém: PHP 7.4 s českým locale píše desetinná čísla s čárkou (noční report, log pomalých dotazů), PHP 8 vždy s tečkou.
  - Řešení: pro čísla se nastaví locale „C“, obě verze píšou tečku. Podrobnosti v `MIGRATION.md`.

### Bezpečnost

- [x] **SQL injection ve filtrech, hledání, našeptávači a mazání poznámky** (v2.7.7, 2026-11-26)
  - Problém: podvržený text ve filtru nebo hledání změnil dotaz do databáze (např. vrátil všechny obce). Ověřeno.
  - Řešení: hodnoty se předávají jako parametry dotazu. Výsledky běžného hledání se nemění.
- [x] **Mazání poznámky odkazem** (v2.7.7, 2026-11-26)
  - Problém: poznámku šlo smazat podvrženým odkazem, i cizímu vlastníkovi. Při chybě hláška tvrdila, že se smazala.
  - Řešení: maže se formulářem s ochranným tokenem (CSRF) a jen u zobrazeného vlastníka. Token mají všechny formuláře. Hláška o smazání jen při skutečném smazání.
- [x] **Hodnota odeslaná jako seznam ve formuláři úpravy vlastníka** (v2.7.7, 2026-11-26)
  - Problém: podvržený formulář s hodnotou odeslanou jako seznam uložil prázdný termín nebo poznámku bez chyby.
  - Řešení: je to chyba validace, nic se neuloží.
- [x] **XSS: neescapované výstupy** (v2.7.7, 2026-11-26)
  - Problém: text z filtru, hledání nebo poznámky se vložil do stránky jako HTML a mohl spustit skript. Stejně popisky našeptávače. Ověřeno.
  - Řešení: výstupy se escapují, našeptávač vkládá jen text.
- [x] **CSV export: vzorce** (v2.7.8, 2026-11-26)
  - Problém: hodnota začínající `=`, `+`, `-` nebo `@` se po otevření v Excelu vyhodnotí jako vzorec. Z review navíc: uvozovka za zpětným lomítkem rozdělila hodnotu do buněk a vzorec prošel.
  - Řešení: takové hodnoty dostanou na začátek `'`, telefonní čísla zůstanou beze změny. Uvozovky se vždy zdvojí.
  - Známé omezení: číselné hodnoty jako `-1-1` zůstanou bez `'` a spočítají se. Bez písmen ale nejde zapsat funkci ani odkaz, proto to není nebezpečné.
- [x] **Nepoužívaný kód s neošetřeným SQL** (v2.7.8, 2026-11-26)
  - Problém: funkce, které skládají SQL z neošetřených hodnot. Nic je nevolá, ale někdo by je mohl použít.
  - Řešení: odstraněné (ověřeno prohledáním celého repozitáře).

### Výkon

- [x] **Výpis vlastníků: zbytečné dotazy** (v2.7.9, 2026-11-26)
  - Problém: pro každý řádek se zvlášť načítal název obce, počet parcel a parcely v obci, přes 100 dotazů na stránku.
  - Řešení: načítají se jednou za stránku, 5–6 dotazů. Výpis vypadá stejně.
- [x] **Odkaz „vlastníci“ u obce** (v2.7.11, 2026-11-26)
  - Problém: odkaz posílal název obce a filtr hledal část názvu, takže „Lhota“ našla i Horní, Dolní… Lhota (20 000 vlastníků místo 240).
  - Řešení: odkaz vybírá přesně jednu obec (rozhodnutí z 26. 11. 2026, otázka 1). Ve filtrech je popisek „Obec: název ×“. Sloupec „V obci“ se počítá podle obce parcely v databázi. Textové pole „Obec“ se nemění.
- [ ] **Filtr obce ve výpisu vlastníků: textové pole**
  - Problém: textové pole „Obec“ hledá část názvu, takže „Lhota“ najde i Horní, Dolní… Lhota a výpis je pomalý. Význam filtru rozhodnut (otázka 1, varianta a): obec vlastníka, jako dosud.
  - Řešení: po krocích, zatím částečně.
    - Hotovo: parcely pro „V obci“ jedním dotazem za stránku (úkol 2).
    - Hotovo: odkaz u obce podle id a „V obci“ v databázi (položka výše).
    - Hotovo: index na obec vlastníka (položka níže) běžné hledání zrychlil.
    - Hotovo: jedno písmeno se ignoruje, minimální délka 2 znaky (otázka 7, položka v *Ostatní*).
    - Zbývá: hledání části názvu prochází všechny obce. Text od 2 znaků, který najde přes asi 40 % vlastníků, je s indexem pomalejší než bez něj (`MIGRATION.md`, 5.3).
- [x] **Výpisy parcel a obcí: zbytečné dotazy** (v2.7.10, 2026-11-26)
  - Problém: vlastníci každé parcely a počet parcel každé obce se načítali zvlášť, přes 50 dotazů na stránku.
  - Řešení: 5 dotazů na stránku, bez nového indexu. Výpisy vypadají stejně.
- [x] **Index na vlastnické vztahy podle vlastníka** (v2.7.9, 2026-11-26)
  - Problém: chyběl, každý dotaz na parcely vlastníka procházel 1,2 mil. řádků.
  - Řešení: index jako SQL migrace 001 (`MIGRATION.md`). Index na obec vlastníka tehdy záměrně ne, protože by přeskupil vlastníky se stejným jménem na stranách.
- [x] **Výchozí řazení vlastníků** (v2.8.2, 2026-11-26)
  - Problém: řazení podle jména nemohlo použít index, každá strana řadila všech 500 tis. vlastníků. Pořadí vlastníků se stejným jménem bylo náhodné.
  - Řešení: rozhodnuto otázkou 5 (varianta b). Řadí se podle příjmení, jména a id s indexem (SQL migrace 002, `MIGRATION.md`, 5.2).
    - Pořadí jmen se na testovacích datech nezměnilo. Vlastníci se stejným jménem jsou teď seřazení podle id, na stranách jsou proto jiní než dříve.
    - První strany výpisu jsou výrazně rychlejší, hledání zůstává jako dříve.
    - Filtr „neoslovovat“ na vzdálenějších stranách čte víc dat. Doba je stejná nebo nižší, se studenou cache databáze může být horší.
- [x] **Index na obec vlastníka** (v2.8.3, 2026-11-26)
  - Problém: poslední vazba bez indexu, filtr podle obce procházel všechny vlastníky.
  - Řešení: index jako SQL migrace 003 (`MIGRATION.md`, 5.3), kód se nemění.
    - Filtr podle obce a noční report jsou výrazně rychlejší, běžné textové hledání obce také.
    - Velmi široký textový filtr (jedno písmeno) je pomalejší, řeší minimální délka (otázka 7).
    - Výstup všech stránek je před a po stejný.
    - K ověření na produkci: řazení podle „Založeno“ s filtrem obce (bod (b) v *Chyby v logice*, `MIGRATION.md`, 5.3). Cizí klíče řeší otázka 6.
- [ ] **Výpis parcel: název obce**
  - Problém: na vzdálených stranách výpisu parcel databáze zbytečně hledá obec i pro přeskočené řádky (asi 5× pomalejší).
  - Řešení: název obce načítat jen pro řádky stránky. Ověřit výsledky s filtrem i bez něj, výpis má vypadat stejně. Cenu přeskakování samotného to neřeší.
    - Prozkoumaný postup: název obce poddotazem místo joinu, filtr „Obec“ přes id obcí, jejichž název odpovídá textu. Změřeno na vzdálené straně asi 0,5 s → 0,1 s.

### Chyby v logice

- [x] **Export: méně řádků, než odpovídá filtru** (v2.7.6, 2026-11-26)
  - Problém: limit 20 000 řádků se počítal i z vynechaných vlastníků se zákazem oslovení.
  - Řešení: vynechávají se už v databázi, limit počítá jen exportované řádky.
- [x] **Vlastník bez parcel**
  - Problém: funkce vrátila „nic“ místo prázdného seznamu.
  - Řešení: opraveno při migraci na PHP 8, na 7.4 se chování nemění.
- [x] **Zkracování textu s diakritikou** (v2.7.6, 2026-11-26)
  - Problém: krátká poznámka s diakritikou dostala na přehledu „…“, i když se nic neuřízlo.
  - Řešení: délka se počítá ve znacích.
- [x] **Prázdné datum bez času** (v2.7.6, 2026-11-26)
  - Problém: ukázalo se jako „30. 11. -0001 00:00“.
  - Řešení: bere se jako prázdné.
- [x] **Apostrofy ve jménu** (v2.7.6, 2026-11-26)
  - Problém: z „O'Brien“ se při hledání stalo „OBrien“.
  - Řešení: odstraňují se jen okrajové apostrofy.
- [x] **Podíl se jmenovatelem 0** (v2.7.6, 2026-11-26)
  - Problém: na PHP 8 detail vlastníka a výpis s filtrem obce spadnou, na 7.4 ukázaly „inf ha“. Testovací data takový podíl nemají.
  - Řešení: podíl přispívá nulou, „Výměra podílu“ ukazuje „–“. Kontrola dat na produkci je doporučená (`MIGRATION.md`).
- [x] **Pole v parametrech jako text „Array“** (v2.7.6, 2026-11-26)
  - Problém: podvržený požadavek hledal nebo uložil text „Array“ (např. jako titul vlastníka).
  - Řešení: pole se ignoruje, jako by parametr chyběl.
- [x] **Ručně odeslaný termín `0.5`** (v2.7.6, 2026-11-26)
  - Problém: po chybě validace se ve formuláři ukázal prázdný.
  - Řešení: prázdné pole jen pro nulové datum z databáze.
- [x] **Obří číslo strany** (v2.7.8, 2026-11-26)
  - Problém: ručně zadaná strana mimo rozsah skončila chybou SQL a prázdným výpisem.
  - Řešení: strana se omezí na rozsah 1 až poslední strana.
- [x] **Podíl 0/0 jako „celý“** (v2.7.8, 2026-11-26)
  - Problém: podíl ukazoval „celý“, přitom výměra vedle „–“.
  - Řešení: ukazuje `0/0`.
- [x] **Výpis parcel: pořadí vlastníků se stejným příjmením** (v2.8.1, 2026-11-26)
  - Problém: při každém načtení stránky mohlo být jiné (i pořadí čísel LV).
  - Řešení: řadí se podle příjmení, jména a id, pořadí je pokaždé stejné.
- [ ] **Neurčené pořadí řádků i jinde** (zatím se neprojevilo)
  - Problém: kde se řadí podle hodnoty, kterou má víc řádků, databáze pořadí nezaručuje.
    - (a) Detail vlastníka: parcely se stejným číslem ve stejném katastrálním území.
    - (b) Řazení výpisů podle sloupce (např. katastrální území, výměra, okres): řádek se může mezi stranami přeskočit nebo ukázat dvakrát.
  - Řešení: přidat id jako poslední klíč řazení. Mění pořadí na stranách, proto samostatně.
  - K ověření na produkci: jestli je čas „Založeno“ u vlastníků jedinečný. Od migrace 003 se s filtrem obce může pořadí podle „Založeno“ změnit (`MIGRATION.md`, 5.3).
- [x] **Zaokrouhlení výměry na PHP 7.4 a 8.5** (v2.8.4, 2026-11-26)
  - Problém: hodnota těsně pod polovinou se na 8.5 zaokrouhlila jinak než na 7.4 (např. „0 m²“ místo „1 m²“, „11,84 ha“ místo „11,85 ha“). Jen na přesné hranici, testovací data ji nezasáhnou.
  - Řešení: aplikace napodobuje zaokrouhlení ze 7.4, výstup je na obou verzích stejný. Pod 1 m² se pár hodnot nově zaokrouhlí nahoru i na 7.4.

### Ostatní

- [x] **Přidat testy**
  - Problém: aplikace neměla žádné testy.
  - Řešení: viz úkol 0.
- [x] **Výpis obcí: odkaz „parcely“** (v2.8.0, 2026-11-26)
  - Cíl: požadavek zadavatele, z obce přejít na její parcely.
  - Řešení: vedle „vlastníci“ odkaz „parcely“ (přesně jedna obec, popisek „Obec: název ×“). Oba odkazy jako malá tlačítka.
- [x] **Textový filtr „Obec“ ve výpisu vlastníků: minimální délka 2 znaky** (v2.9.0, 2026-11-27)
  - Cíl: rozhodnutí z 26. 11. 2026 (otázka 7, varianta b), jednopísmenný filtr nic nefiltruje a je pomalý.
  - Řešení: kratší text se ignoruje (výpis i export), u pole je „zadejte aspoň 2 znaky“. Našeptávač počítá mez nově také ve znacích, ne v bajtech. Výpis parcel se nemění.
- [x] **Zástupný text pole „Hledat“** (v2.9.0, 2026-11-27)
  - Problém: všechny výpisy ukazovaly „jméno, příjmení nebo IČO“, i parcely a obce.
  - Řešení: každý výpis má vlastní text. Vlastníci beze změny, parcely „číslo parcely nebo kód k. ú.“, obce „název nebo kód obce“.
