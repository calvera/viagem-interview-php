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
