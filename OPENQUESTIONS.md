# Otevřené otázky

Rozhodnutí pro zadavatele. Po rozhodnutí se u otázky doplní výsledek a odkaz na položku v `TODO.md` nebo na verzi.

## 1. Co znamená filtr „Obec“ a odkaz „vlastníci“ u obce?

**Stav:** rozhodnuto 26. 11. 2026, varianta a).

Výpis vlastníků filtruje podle obce, kde vlastník bydlí nebo sídlí. U obcí se ale ukazuje počet parcel v obci a u vlastníků jejich parcely v obci. Výpis tak míchá dva významy.

Příklad Lhota: 240 vlastníků v ní bydlí, 319 v ní má parcelu, 239 obojí. 80 vlastníků s parcelou ve Lhotě, kteří bydlí jinde, se ve výpisu pro Lhotu neobjeví.

**Varianty:**

- a) Obec, kde vlastník bydlí nebo sídlí (jako dnes).
- b) Vlastníci, kteří mají v obci parcelu. Sedí k počtu parcel u obcí.

**Doporučení:** b).

**Výsledek:** varianta a). Odkaz „vlastníci“ u obce vybírá přesně jednu obec (v2.7.11). Textové pole „Obec“ dál hledá část názvu („Lhota“ najde i Horní, Dolní… Lhota). Viz `TODO.md`, *Filtr obce ve výpisu vlastníků: textové pole*.

## 2. Je termín oslovení „dnes“ po splatnosti?

**Stav:** otevřená.

Výpis vlastníků ukazuje dnešní termín jako po splatnosti (červeně), noční report ne. K 26. 11. 2026 se to týká 439 vlastníků.

**Varianty:**

- a) Dnešní termín po splatnosti není (jako report).
- b) Dnešní termín po splatnosti je (jako výpis).

**Doporučení:** a).

## 3. Přihlášení uživatelů

**Stav:** otevřená.

Aplikace nemá přihlášení, všichni pracují pod jedním pevným uživatelem. U poznámek a v logu proto není skutečný autor a data může měnit kdokoli s přístupem k síti.

**Otázky:**

- Kdo jsou uživatelé a odkud se berou (vlastní seznam, firemní adresář, jednotné přihlášení)?
- Jsou potřeba role (např. jen čtení, obchodník, správce)?
- Chrání přístup dnes něco před aplikací (VPN, heslo na serveru)? Pokud ano, stačí převzít jméno uživatele odtud.

## 4. Kontrola formuláře úpravy vlastníka

**Stav:** otevřená.

**Otázky:**

- Pole, které ve formuláři úplně chybí (jen u ručně upraveného požadavku), se dnes uloží jako prázdné. Má to být chyba, nebo se má ponechat původní hodnota?
- Jaká pravidla mají platit pro jednotlivá pole (povinná pole, formát telefonu, jen česká PSČ, rozsah termínu oslovení)? Dnes se kontroluje jen příjmení / název, e-mail, PSČ (5 číslic) a platné datum.
- Délka polí se nekontroluje. Příliš dlouhý text databáze potichu zkrátí a uživatel se to nedozví. Má to být chyba?
- Má se formulář kontrolovat i v prohlížeči (rychlejší odezva)? Kontrola na serveru zůstane v každém případě.

## 5. Výchozí řazení výpisu vlastníků

**Stav:** rozhodnuto 26. 11. 2026, varianta b).

**Otázka:** Smí se změnit, kteří vlastníci se stejným jménem jsou na které straně výpisu?

Výpis je pomalý, protože pro každou stranu řadí všech 500 tisíc vlastníků. Navíc 500 tisíc vlastníků má jen asi 1 000 různých jmen a pořadí vlastníků se stejným jménem není dané. Při listování se tak vlastník může objevit na dvou stranách nebo na žádné. Každá změna řazení je přeskupí.

**Varianty:**

- a) Ponechat jako dnes.
- b) Řadit podle příjmení, jména a pořadového čísla vlastníka. Pořadí je pevné a výpis rychlý.
- c) Jen doplnit pořadové číslo. Pořadí je pevné, výpis zůstane pomalý.

**Doporučení:** b).

**Výsledek:** varianta b), provedeno ve v2.8.2 (`TODO.md`, *Výchozí řazení vlastníků*). Pořadí jmen se na testovacích datech nezměnilo, vlastníci se stejným jménem jsou na stranách jinak než dříve. Na produkci se pořadí může mírně lišit ve dvou případech: u firem, jejichž název začíná příjmením osoby, a u vlastníků bez vyplněného křestního jména.

## 6. Hlídání vazeb v databázi

**Stav:** otevřená.

**Otázka:** Má databáze sama hlídat, že záznamy na sebe správně odkazují (vlastník → obec, parcela → obec, poznámka → vlastník, vlastnictví → vlastník a parcela)? Dnes to hlídá jen aplikace.

Co to znamená:

- Zápisy z jiných částí CRM nebo importů, které dnes projdou s neúplnými údaji, by skončily chybou. Je potřeba je projít.
- Před zavedením je potřeba ověřit, že produkční data jsou v pořádku (testovací jsou).

**Varianty:**

- a) Nechat jak je, hlídá jen aplikace.
- b) Databáze hlídá vazby: smazat vlastníka, parcelu nebo obec, na které něco odkazuje, nepůjde.
- c) Jako b), ale se smazáním vlastníka nebo parcely zmizí i jeho poznámky a vlastnické vztahy.

**Doporučení:** b). Chrání data a nic nemaže potichu. Mazání závislých záznamů jen tam, kde to zadavatel výslovně chce.

**Související:** Může mít vlastník k jedné parcele víc vlastnických vztahů (např. na různých LV), nebo nejvýš jeden?

## 7. Minimální délka textového filtru „Obec“ ve výpisu vlastníků

**Stav:** rozhodnuto 26. 11. 2026, varianta b).

**Otázka:** Má textový filtr „Obec“ vyžadovat aspoň několik znaků?

Po zrychlení výpisu (v2.8.3) je běžné hledání obce několikanásobně rychlejší. Filtr s jedním písmenem ale najde skoro všechny vlastníky a je víc než 2× pomalejší než dříve. Uživateli takový filtr k ničemu není.

**Varianty:**

- a) Nechat jak je.
- b) Minimální délka (např. 2 znaky): kratší text se ignoruje a u pole je upozornění. Našeptávač obcí už hledá od 2 znaků.
- c) Kratší text hledá jen obce, jejichž název tímto textem začíná.

**Doporučení:** a), dokud se na produkci neukáže, že uživatelé jedno písmeno opravdu zadávají. Jinak b).

**Výsledek:** varianta b), minimálně 2 znaky jako u našeptávače, platí pro výpis i export. Vydáno ve v2.9.0 (`TODO.md`, *Ostatní*, *Textový filtr „Obec“ ve výpisu vlastníků: minimální délka 2 znaky*).
