# Otevřené otázky

Nejasnosti, které musí rozhodnout zadavatel. Po rozhodnutí se u otázky doplní výsledek a odkaz na položku v `TODO.md` nebo na verzi.

## 1. Co znamená filtr „Obec“ a odkaz „vlastníci“ u obce?

**Stav:** otevřená, blokuje položku *Filtr obce ve výpisu vlastníků* v `TODO.md` (sekce *Výkon*).

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

**Stav:** otevřená, blokuje položku *Výchozí řazení vlastníků* v `TODO.md` (sekce *Výkon*).

Výpis vlastníků se ve výchozím stavu řadí podle `concat(o.last_name, ' ', o.first_name)` (`admin_owners/owners_functions.php`). Na výraz nejde použít index, takže každá strana výpisu přečte a seřadí všech 500 tisíc vlastníků (`EXPLAIN`: `ALL`, `Using filesort`).

Řazení navíc není jednoznačné: 500 000 vlastníků má jen 1 070 různých jmen (asi 470 vlastníků na jedno jméno). Pořadí vlastníků se stejným jménem databáze neurčuje a závisí na plánu dotazu. Proto každá změna řazení nebo indexu změní, kteří vlastníci jsou na které straně (jména na stranách zůstanou, vlastníci se stejným jménem se přeskupí). Při stránkování se navíc může stát, že se vlastník se stejným jménem objeví na dvou stranách nebo na žádné.

**Varianty:**

- a) Ponechat jako dnes.
- b) Řadit podle `last_name, first_name, id` s indexem `(last_name, first_name, id)`. Databáze pak může stranu výpisu číst z indexu bez řazení všech vlastníků (ověřit `EXPLAIN` po přidání indexu) a pořadí je jednoznačné. Index je další SQL migrace (500 tisíc řádků, online).
- c) Jen doplnit `id` jako rozhodující kritérium (`concat(…), id`). Pořadí je jednoznačné, ale výpis dál řadí všech 500 tisíc vlastníků.

**Doporučení:** b). Na testovacích datech řadí `concat` i dvojice sloupců všech 1 070 jmen stejně (ověřeno porovnáním seznamů). Před změnou je potřeba totéž ověřit na produkčních datech: liší se např. u prázdného `first_name` (`NULL` udělá z celého `concat` `NULL`, takový vlastník je na začátku) nebo u příjmení s mezerou.
