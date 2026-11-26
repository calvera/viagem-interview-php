# Pravidla projektu

Všechny dokumenty v repozitáři (`README.md`, `TODO.md`, `OPENQUESTIONS.md`, `CHANGELOG.md`, `MIGRATION.md`, `GUIDE.md`, `CLAUDE.md`) se píší česky.

## Postup u pracovních položek

Každá pracovní položka (jeden úkol z `TODO.md` nebo jedna ucelená změna) projde touto smyčkou:

1. **Stavba**: agent `opus-builder`:
   - změní kód a spustí testy,
   - odškrtne nebo upraví položku v `TODO.md`,
   - přidá záznam pod `## [Nevydáno]` v `CHANGELOG.md`,
   - aktualizuje `MIGRATION.md`, pokud se změna týká migrace nebo serveru,
   - aktualizuje `GUIDE.md`: stav kroku a stručně co a proč.
2. **Review**: agent Fable (`sonnet-checker` s `model: fable`), jen čte diff a kód. Nespouští testy (to dělá builder) a nic nemění.
3. **Opakování**: nálezy review jdou zpět stejnému builderovi (SendMessage), pak další review. Položka je hotová až po čistém review.
4. **Vydání**: po čistém review dostane položka vlastní verzi:
   - zvýšit `version` v `composer.json` (semver: oprava chyby nebo kompatibility → patch, nová funkce → minor, nekompatibilní změna → major) a nastavit `time` na datum vydání (`YYYY-MM-DD`),
   - nastavit stejnou verzi do `APP_VERSION` v `includes/config.php`,
   - v `TODO.md` doplnit k odškrtnuté položce verzi a datum: `(vx.y.z, YYYY-MM-DD)`,
   - v `CHANGELOG.md` přejmenovat `## [Nevydáno]` na `## [x.y.z] – YYYY-MM-DD` a nad něj založit nový prázdný `## [Nevydáno]`,
   - commitnout položku a commit otagovat `vx.y.z`.

## Styl dokumentů

- Stručně: krátké věty, žádné opakování, odrážky a pododrážky místo dlouhých odstavců.
- Technické detaily jen ty nejdůležitější.
- `CHANGELOG.md`: jen co se změnilo, ne jak.
- `OPENQUESTIONS.md`: jen otázky srozumitelné laikovi, bez technických detailů.
- `TODO.md`: každá položka má netechnický popis (**Problém** nebo **Cíl**) a **Řešení**.

## MIGRATION.md

Popisuje, co se při migraci na PHP 8.5 udělalo a co je potřeba přenastavit na produkčním serveru (rozšíření, php.ini, locale, cron, databáze, pořadí nasazení, návrat zpět, ověření). Položka, která mění něco důležitého pro server nebo migraci, ho aktualizuje v kroku Stavba a review kontroluje, že je aktuální.

Každá změna databáze (index, sloupec, cizí klíč, úprava dat):

- je samostatný idempotentní SQL soubor v `sql/migrations/` s dalším pořadovým číslem v názvu (např. `003_popis.sql`), takže pořadí podle názvu je pořadí spuštění,
- se zapíše do přehledu DB migrací v `MIGRATION.md`: soubor, verze, co dělá, zda je povinná, kdy ji spustit vůči nahrání souborů, jak ji vrátit.

Přehled je seznam všeho, co je potřeba při nasazení spustit, v pořadí spuštění.

## GUIDE.md

Popis řešení k odevzdání technické úlohy pro hodnotitele, ne pro provoz. Stručně říká, co se udělalo a proč, a odkazuje na podrobné dokumenty. Položka ho aktualizuje v kroku Stavba a review kontroluje, že odpovídá skutečnosti.

## OPENQUESTIONS.md

Otázky pro zadavatele (rozhodnutí o produktu, která nejsou opravou chyby, např. přihlášení nebo pravidla validace) patří sem, ne do `TODO.md`. Dokud nepřijde odpověď, neřeší se. Po rozhodnutí se u otázky doplní výsledek a odkaz na položku v `TODO.md` nebo na verzi.
