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
