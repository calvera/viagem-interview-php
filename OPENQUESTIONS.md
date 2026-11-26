# Otevřené otázky

Nejasnosti, které musí rozhodnout zadavatel. Po rozhodnutí se u otázky doplní výsledek a odkaz na položku v `TODO.md` nebo na verzi.

## 1. Co znamená filtr „Obec“ a odkaz „vlastníci“ u obce?

**Stav:** rozhodnuto 26. 11. 2026: varianta a). Odkaz „vlastníci“ u obce filtruje podle id obce (přesně jedna obec, v2.7.11) a ukazuje vlastníky s bydlištěm nebo sídlem v obci (`o.municipality_id = id`), jako dosud. Textové pole „Obec“ s našeptávačem dál hledá podřetězec názvu („Lhota“ najde i Horní, Dolní… Lhota).

Filtr „Obec“ ve výpisu vlastníků dnes vybírá vlastníky podle jejich obce (`o.municipality_id`, v detailu „Obec (KN)“). Výpis obcí ale vedle odkazu „vlastníci“ ukazuje počet parcel v obci a sloupec „V obci“ ve výpisu vlastníků počítá parcely. Výpis tak míchá dva významy.

Data pro obec Lhota:

| | počet |
|---|---|
| vlastníci s obcí Lhota | 240 |
| vlastníci, kteří mají ve Lhotě parcelu | 319 |
| obojí | 239 |

79 vlastníků s parcelou ve Lhotě, kteří bydlí jinde, se ve výpisu filtrovaném na Lhotu neobjeví.

**Varianty:**

- a) Obec vlastníka (jako dnes).
- b) Vlastníci parcel v obci (`exists … p.municipality_id = id`). Sedí ke sloupcům „Parcel“ u obcí a „V obci“ u vlastníků.

**Doporučení:** b).

**Výsledek:** varianta a), obec vlastníka. Vlastníci s parcelou v obci, kteří bydlí jinde, se přes odkaz u obce nezobrazí. Viz `TODO.md`, *Výkon*, *Filtr obce ve výpisu vlastníků: textové pole*.

## 2. Je termín oslovení „dnes“ po splatnosti?

**Stav:** otevřená.

Výpis vlastníků (`admin_owners/owners_functions.php:66`) porovnává `strtotime(duedate) < time()`. Termín je jen datum (půlnoc), takže dnešní termín je od 0:00 červený („overdue“). Noční report (`cron/nightly_report.php:93`) počítá `duedate < curdate()`, takže u něj dnešní termín po splatnosti není. K 26. 11. 2026 se to týká 439 vlastníků.

**Varianty:**

- a) Dnešní termín po splatnosti není (jako report): výpis porovná jen datum.
- b) Dnešní termín po splatnosti je: report počítá `duedate <= curdate()`.

**Doporučení:** a).

## 3. Přihlášení uživatelů

**Stav:** otevřená.

Aplikace nemá přihlášení, uživatel je natvrdo `operator.a` (`APP_USER` v `includes/config.php`). Kdo je zapsaný u poznámek a v logu, proto neodpovídá skutečnosti a kdokoli s přístupem k síti může data měnit.

**Otázky:**

- Kdo jsou uživatelé a odkud se berou (vlastní tabulka, firemní adresář / LDAP, SSO)?
- Jsou potřeba role (např. jen čtení, obchodník, správce)?
- Řeší přístup dnes něco před aplikací (VPN, HTTP autentizace na serveru)? Pokud ano, stačí převzít jméno uživatele odtud.

## 4. Validace vstupu ve formuláři úpravy vlastníka

**Stav:** otevřená.

Pole, které v odeslaném formuláři chybí úplně, se uloží jako prázdné (`title`, `street`… `''`, `note` `NULL`, `duedate` jako `0000-00-00`, tj. termín „nenastaven“: sloupec je `DATE NOT NULL DEFAULT '0000-00-00'` a při `sql_mode = ''` se `NULL` uloží jako výchozí hodnota; `admin_owners/owners_edit.php:25-35`). Stane se to jen u ručně upraveného požadavku s platným CSRF tokenem.

**Otázky:**

- Má chybějící pole být chybou validace, nebo se má ponechat hodnota z databáze?
- Jaká pravidla mají platit pro jednotlivá pole (povinná pole, formát telefonu, PSČ jen česká, rozsah termínu oslovení)? Dnes se kontroluje jen příjmení / název, e-mail, PSČ (5 číslic) a platné datum.
- **Délka polí se nekontroluje nikde.** Sloupce v `owners` mají omezenou délku (`title` 30, `first_name` 60, `last_name` 160, `street` a `city` 120, `zip` 6, `phone` 20, `email` 120 znaků). Databáze běží se `sql_mode = ''`, takže delší hodnotu potichu ořízne a uloží; uživatel se o tom nedozví. Má delší hodnota být chybou validace?
- **Kontrola v prohlížeči neexistuje.** Formulář má jen `type="date"` u termínu oslovení; žádné `required`, `maxlength`, `type="email"` ani JavaScript. Chceme kontrolu i v prohlížeči (rychlejší odezva pro uživatele), nebo stačí server? Kontrola na serveru zůstává v každém případě.

## 5. Výchozí řazení výpisu vlastníků

**Stav:** rozhodnuto 2026-11-26: varianta b). Provede položka *Výchozí řazení vlastníků* v `TODO.md` (sekce *Výkon*).

**Otázka:** Smí se pořadí vlastníků se stejným jménem na stranách výpisu změnit a kterou variantu zvolit?

Výpis vlastníků se ve výchozím stavu řadí podle `concat(o.last_name, ' ', o.first_name)` (`admin_owners/owners_functions.php`). Na výraz nejde použít index, takže každá strana výpisu přečte a seřadí všech 500 tisíc vlastníků (`EXPLAIN`: `ALL`, `Using filesort`).

Řazení navíc není jednoznačné: 500 000 vlastníků má jen 1 070 různých jmen (asi 470 vlastníků na jedno jméno). Pořadí vlastníků se stejným jménem databáze neurčuje a závisí na plánu dotazu. Proto každá změna řazení nebo indexu změní, kteří vlastníci jsou na které straně (jména na stranách zůstanou, vlastníci se stejným jménem se přeskupí). Při stránkování se navíc může stát, že se vlastník se stejným jménem objeví na dvou stranách nebo na žádné.

**Varianty:**

- a) Ponechat jako dnes.
- b) Řadit podle `last_name, first_name, id` s indexem `(last_name, first_name, id)`. Databáze pak může stranu výpisu číst z indexu bez řazení všech vlastníků (ověřit `EXPLAIN` po přidání indexu) a pořadí je jednoznačné. Index je další SQL migrace (500 tisíc řádků, online).
- c) Jen doplnit `id` jako rozhodující kritérium (`concat(…), id`). Pořadí je jednoznačné, ale výpis dál řadí všech 500 tisíc vlastníků.

**Doporučení:** b). Na testovacích datech řadí `concat` i dvojice sloupců všech 1 070 jmen stejně (ověřeno porovnáním seznamů). Před změnou je potřeba totéž ověřit na produkčních datech: liší se např. u prázdného `first_name` (`NULL` udělá z celého `concat` `NULL`, takový vlastník je na začátku) nebo u příjmení s mezerou.

**Souvislosti:** Kvůli přeskupování vlastníků se stejným jménem se zatím nepřidal index na `owners.municipality_id` (`TODO.md`, položka o indexu `ownerships.owner_id`, v2.7.9). Po zavedení jednoznačného řazení už index pořadí nezmění, takže se dá přidat (položka *Index na `owners.municipality_id`* v `TODO.md`; cizí klíče viz otázka 6). Na testovacích datech mají všechny právnické osoby (41 666) prázdné `first_name` a název v `last_name`, často s mezerou. U nich se `concat` a řazení podle dvou sloupců mohou lišit (např. firma „Nový Agro“ a osoba „Nový Adam“), proto se pořadí porovná na celých datech, nejen na seznamu jmen.

**Výsledek:** varianta b), řadit podle `last_name, first_name, id` s indexem `(last_name, first_name, id)`. Viz položka *Výchozí řazení vlastníků* v `TODO.md`. Provedeno ve v2.8.2.: výpis řadí `o.last_name, o.first_name, o.id`, index `idx_owners_name` je migrace `sql/migrations/002_owners_name.sql` (s histogramy filtrovaných sloupců, `MIGRATION.md`, 5.2). Porovnání na celých testovacích datech: posloupnost všech 1 070 jmen je stejná jako s `concat()`; na produkci se může lišit jen u osoby, jejíž příjmení je prvním slovem názvu firmy (např. „Nový Zdeněk“ s firmou „Nový Agro“: `concat` dá firmu první, sloupce osobu), a u `first_name = NULL`. Vlastníci se stejným jménem jsou seřazení podle id, na stranách jsou proto jiní vlastníci než dříve. První strany výpisu (bez filtru, s typem, zákazem oslovení, textovým filtrem obce) se čtou z indexu; hledání zůstává u dosavadního plánu; filtrované první strany čtou až asi 4× víc stránek, ale jsou mnohem rychlejší, a filtr „neoslovovat“ na 5. až 60. straně čte až 21× víc stránek (doba podle `ANALYZE` stejná nebo nižší, s chladným buffer poolem může být horší).

## 6. Cizí klíče mezi tabulkami

**Stav:** otevřená.

**Otázka:** Má databáze hlídat vazby mezi tabulkami cizími klíči? Pokud ano, co se má stát při smazání vlastníka, parcely nebo obce, na které něco odkazuje?

V databázi dnes nejsou žádné cizí klíče. Vazby hlídá jen aplikace:

| Vazba | Typ | Index |
|---|---|---|
| `parcels.municipality_id` → `municipalities` | N:1 | ano |
| `owners.municipality_id` → `municipalities` | N:1 | ne, položka v `TODO.md` |
| `owner_notes.owner_id` → `owners` | N:1 | ano |
| `ownerships.owner_id` → `owners` | M:N | ano (v2.7.9) |
| `ownerships.parcel_id` → `parcels` | M:N | ano |

Testovací data jsou čistá: žádný řádek neodkazuje na neexistující záznam, žádné `municipality_id = 0` a žádné duplicitní dvojice `(owner_id, parcel_id)`. Cizí klíče by tedy šly přidat. Rizika:

- **Zápisy, které dnes projdou, by skončily chybou.** Sloupce mají `DEFAULT 0` a připojení běží se `sql_mode = ''`. Zápis bez hodnoty dnes potichu uloží 0, s cizím klíčem skončí chybou. Aplikace sama zapisuje jen do `owners` (úprava vlastníka) a maže jen poznámky. Do stejné databáze ale můžou zapisovat jiné moduly CRM nebo importy, a ty je potřeba projít.
- **Produkční data.** Před přidáním je potřeba na produkci ověřit, že žádné řádky neodkazují na neexistující záznam. Jinak se cizí klíč nepřidá.
- **Index na `owners.municipality_id`.** InnoDB k cizímu klíči sám založí index, pokud chybí. Proto až po položce *Výchozí řazení vlastníků* (otázka 5).

**Varianty:**

- a) Cizí klíče nepřidávat, vazby dál hlídá jen aplikace.
- b) Cizí klíče s `ON DELETE RESTRICT`: smazat vlastníka, parcelu nebo obec, na které něco odkazuje, nepůjde.
- c) Jako b), ale `ON DELETE CASCADE` u závislých záznamů (`owner_notes`, `ownerships`): se smazáním vlastníka nebo parcely zmizí i jeho poznámky a vlastnické vztahy.

**Doporučení:** b). Chrání data a nic nemaže potichu. `CASCADE` jen tam, kde to zadavatel výslovně chce.

**Související:** Má mít vlastník k jedné parcele nejvýš jeden vlastnický vztah (unikátní klíč `(owner_id, parcel_id)` v `ownerships`), nebo jich může mít víc, třeba na různých LV?
