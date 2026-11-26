# Changelog

Formát vychází z [Keep a Changelog](https://keepachangelog.com/cs/1.1.0/). Záznamy před 2.7.4 jsou doplněné zpětně z historie gitu; od 2.7.4 má každá verze vlastní záznam a git tag.

## [Nevydáno]

## [2.9.1] – 2026-11-27

### Změněno

- Dokumenty zestručněné. Položky TODO jsou rozdělené na popis a řešení, OPENQUESTIONS jsou srozumitelné bez technických znalostí.
- `CLAUDE.md`: pravidla stylu dokumentů.

### Přidáno

- `GOAL.md`: jak postupovat s celým CRM, případové studie v příloze.

## [2.9.0] – 2026-11-27

### Změněno

- Textový filtr „Obec“ ve výpisu vlastníků platí až od 2 znaků, stejně jako našeptávač (`OPENQUESTIONS.md`, otázka 7). Kratší text se ignoruje a u pole je upozornění.
- Pole „Hledat“ ve výpisech parcel a obcí má zástupný text podle toho, co opravdu hledá.

### Opraveno

- Našeptávač obcí počítá minimální délku ve znacích, ne v bajtech (samotné „Č“ už nehledá).

## [2.8.4] – 2026-11-26

### Opraveno

- Výměry ploch (sloupec „V obci“, detail vlastníka) se na PHP 8.5 zaokrouhlují stejně jako na 7.4. Rozdíl byl jen na přesné hranici zaokrouhlení.

### Přidáno

- Testy hraničních hodnot pro m² i ha.

## [2.8.3] – 2026-11-26

### Přidáno

- SQL migrace `sql/migrations/003_owners_municipality.sql`: index na obec vlastníka. Zrychlí filtr podle obce, export a noční report; velmi široký textový filtr obce je s ním pomalejší. Kód funguje i bez migrace. `MIGRATION.md`, 5.3.

## [2.8.2] – 2026-11-26

### Změněno

- SQL migrace mají v názvu pořadové číslo: `2026-11-26_ownerships_owner_id.sql` je teď `001_ownerships_owner_id.sql`.
- Výchozí řazení výpisu vlastníků a exportu je jednoznačné: příjmení, jméno, id (`OPENQUESTIONS.md`, otázka 5). Vlastníci se stejným jménem jsou na stranách jinak než dříve.

### Přidáno

- SQL migrace `sql/migrations/002_owners_name.sql`: index pro výchozí řazení vlastníků a statistiky pro optimalizátor. Kód funguje i bez migrace.
- Migrace `001_ownerships_owner_id.sql` navíc aktualizuje statistiky tabulky.
- Migrace fungují i na serveru se striktním `sql_mode`.
- `MIGRATION.md`: přehled DB migrací.
- Smoke test pořadí vlastníků se stejným jménem.

## [2.8.1] – 2026-11-26

### Opraveno

- Výpis parcel: pořadí vlastníků se stejným příjmením (a čísel LV) je jednoznačné, opakovaná načtení se neliší.

### Přidáno

- Smoke test pořadí vlastníků a LV.
- `TODO.md`: neurčené pořadí řádků v detailu vlastníka a při řazení podle sloupce.

## [2.8.0] – 2026-11-26

### Přidáno

- Výpis obcí: odkaz „parcely“ na výpis parcel obce. Nad formulářem je popisek „Obec: název ×“, × filtr zruší.
- Odkazy „vlastníci“ a „parcely“ ve výpisu obcí vypadají jako malá tlačítka.
- Smoke a Playwright testy odkazu „parcely“.

## [2.7.11] – 2026-11-26

### Změněno

- Odkaz „vlastníci“ ve výpisu obcí vybírá vlastníky přesně té obce, ne všech obcí s podobným názvem („Lhota“ 240 vlastníků místo 20 000). Nad formulářem je popisek „Obec: název ×“. Textové pole „Obec“ a staré odkazy fungují jako dřív. Význam odkazu byl tehdy otevřený (`OPENQUESTIONS.md`, otázka 1).

### Přidáno

- Skrytý filtr ve výpisech, nastavený odkazem.
- Unit, smoke a Playwright testy odkazu a filtru.

## [2.7.10] – 2026-11-26

### Změněno

- Výpis parcel a výpis obcí jsou rychlejší: 5 SQL dotazů na stránku místo 54, resp. 55. Výstup je stejný.

### Přidáno

- Smoke a unit testy výpisu parcel a obcí.
- `TODO.md`: neurčené pořadí vlastníků se stejným příjmením ve výpisu parcel.

## [2.7.9] – 2026-11-26

### Změněno

- Výpis vlastníků je rychlejší: 5 SQL dotazů na stránku místo 103, s filtrem obce 6 místo 153. Výstup je stejný.

### Přidáno

- SQL migrace `sql/migrations/2026-11-26_ownerships_owner_id.sql` (od 2.8.2 `001_ownerships_owner_id.sql`): index vazby vlastník–parcela. Kód funguje i bez něj, jen pomaleji. Postup v `MIGRATION.md`.
- Smoke a unit testy výpisu vlastníků.
- `OPENQUESTIONS.md`: otázka 5, výchozí řazení výpisu vlastníků.

## [2.7.8] – 2026-11-26

### Bezpečnost

- Export CSV: text, který by tabulkový procesor vyhodnotil jako vzorec, se ošetří. Telefonní čísla se nemění.
- Export CSV: hodnota se zpětným lomítkem a uvozovkou už nerozdělí buňku.
- Odstraněný nepoužívaný kód, který skládal SQL z neošetřených hodnot.

### Opraveno

- Hodnoty `NULL` z databáze už na PHP 8.1+ nehlásí Deprecated. Výstup je stejný.
- Desetinná čísla v nočním reportu a v logu pomalých dotazů mají vždy tečku, bez ohledu na locale serveru.
- Obří číslo strany už nedá chybu SQL.
- Podíl se jmenovatelem 0 se už nezobrazuje jako „celý“.

### Změněno

- Číslo strany za koncem výpisu ukáže poslední stranu.

### Přidáno

- Unit a smoke testy těchto oprav.

## [2.7.7] – 2026-11-26

### Bezpečnost

- SQL injection: hodnoty filtrů, hledání a našeptávače jdou do SQL jako parametry.
- CSRF: formuláře poznámek a úpravy vlastníka mají token.
- Záznam do `access_log` používá parametry SQL.
- Smazání poznámky maže jen poznámku zobrazeného vlastníka.
- XSS: filtry, hledání, poznámky a kód k. ú. se escapují, našeptávač vkládá text.

### Opraveno

- Smazání poznámky hlásí úspěch, jen když se opravdu smazala.
- Úprava vlastníka: pole odeslané jako seznam je chyba validace.

### Změněno

- Poznámka se maže formulářem (POST), ne odkazem. Potvrzovací dialog zůstává.

### Přidáno

- `TODO.md`: nové otevřené položky z bezpečnostní revize.
- Unit, smoke a Playwright testy oprav.

## [2.7.6] – 2026-11-26

### Opraveno

- Zkrácení textu s diakritikou na přehledu už nepřidá „…“ zbytečně.
- Nulové datum (`0000-00-00`) se u data a času zobrazí prázdně.
- Hodnota s apostrofem uvnitř (`O'Brien`) se ve filtrech nezkomolí.
- Podíl se jmenovatelem 0 už na PHP 8 neshodí detail vlastníka ani výpis (na 7.4 ukazoval „inf ha“).
- Parametr GET/POST odeslaný jako pole se ignoruje: nehledá se text „Array“ a PHP 8 nespadne.
- Úprava vlastníka po chybě validace zobrazí odeslaný termín oslovení.
- Export: limit 20 000 řádků počítá jen exportované vlastníky; počet vynechaných je přesný.

### Přidáno

- `GUIDE.md`: popis řešení k odevzdání.
- Unit a smoke testy oprav.
- `TODO.md`: nové otevřené položky.

### Změněno

- Testy, které zachycovaly chybné chování, ověřují správné.

## [2.7.5] – 2026-11-26

### Opraveno

- Aplikace běží na PHP 8.5 i dál na PHP 7.4 (jeden kód, bez build kroku). Podrobnosti a postup přechodu serveru v [MIGRATION.md](MIGRATION.md):
  - syntaxe, kterou PHP 8 nenačte,
  - chování databázové vrstvy jako na 7.4 (chyby SQL, typy hodnot),
  - prázdný termín oslovení, detail vlastníka bez parcel a noční report fungují na PHP 8 jako na 7.4,
  - odstraněné konstrukce hlášené jako Deprecated.

### Přidáno

- PHPStan (úroveň 2) pro PHP 7.4 i 8.5.
- `MIGRATION.md`: co se změnilo, co nastavit na serveru, nasazení, návrat a ověření.

### Změněno

- `CLAUDE.md` v češtině, nové pravidlo udržovat `MIGRATION.md`.
- README: testy na PHP 7.4 i 8.5, PHPStan, rozhodnutí při migraci.
- `TODO.md`: nové otevřené položky z revize migrace.

## [2.7.4] – 2026-11-26

### Přidáno

- `TODO.md` se známými problémy (PHP 8, bezpečnost, výkon).
- `CHANGELOG.md`.
- Composer s vývojovými nástroji (PHPStan, PHPUnit 9.6).
- `CLAUDE.md` s pravidly pro práci AI agentů a skilly v `.agents/skills/`.
- Testy jako záchranná síť pro migraci (jen čtou, data nemění):
  - unit testy pomocných funkcí,
  - smoke testy všech stránek přes HTTP,
  - Playwright testy v prohlížeči pro PHP 7.4 i 8.5.

### Změněno

- README: přehled funkcí, odkazy na TODO a changelog.
- `CLAUDE.md`: review jen čte kód; každá položka má verzi, datum a git tag.
- `.gitignore`: vendor a výstupy Playwrightu.

## 2026-10-27

### Přidáno

- Lokální prostředí v Dockeru: PHP 7.4 a 8.5, MariaDB 10.11, generátor syntetických dat.
- README.

## 2021-09-02

### Přidáno

- Export vlastníků do CSV.
- Našeptávač obcí.
- Úprava údajů vlastníka.
- Výpis parcel.
- Noční report (`cron/nightly_report.php`).

## 2019-03-14

### Přidáno

- Evidence vlastníků: výpis, detail a poznámky.
