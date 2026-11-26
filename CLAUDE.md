# Pravidla projektu

Všechny dokumenty v repozitáři (`README.md`, `TODO.md`, `OPENQUESTIONS.md`, `CHANGELOG.md`, `MIGRATION.md`, `GUIDE.md`, `CLAUDE.md`) se píší česky.

## Postup u pracovních položek

Každá pracovní položka (jeden úkol z `TODO.md` nebo jedna ucelená změna) projde touto smyčkou:

1. **Stavba**: práci dostane agent `opus-builder`. Ten:
   - provede změny v kódu a spustí testy,
   - odškrtne nebo upraví položku v `TODO.md`,
   - přidá záznam pod `## [Nevydáno]` v `CHANGELOG.md`,
   - aktualizuje `MIGRATION.md`, pokud se změna týká migrace nebo serveru,
   - aktualizuje `GUIDE.md`: stav kroku a stručně co a proč.
2. **Review**: read-only review agentem Fable (`sonnet-checker` s `model: fable`). Jen čte diff a kód: nespouští testy a nic nemění. Testy spouští builder.
3. **Opakování**: pokud review není čisté, jeho nálezy jdou zpět stejnému builderovi (SendMessage) a následuje další review. Položka je hotová, až když je review čisté.
4. **Vydání**: po čistém review dostane položka vlastní verzi:
   - zvýšit `version` v `composer.json` (semver: oprava chyby nebo kompatibility → patch, nová funkce → minor, nekompatibilní změna → major) a nastavit `time` na datum vydání (`YYYY-MM-DD`),
   - nastavit stejnou verzi do `APP_VERSION` v `includes/config.php`,
   - v `TODO.md` doplnit k odškrtnuté položce verzi a datum: `(vx.y.z, YYYY-MM-DD)`,
   - v `CHANGELOG.md` přejmenovat `## [Nevydáno]` na `## [x.y.z] – YYYY-MM-DD` a nad něj založit nový prázdný `## [Nevydáno]`,
   - commitnout položku a commit otagovat `vx.y.z`.

## MIGRATION.md

`MIGRATION.md` popisuje, co jsme při migraci na PHP 8.5 udělali, a co je potřeba přenastavit na produkčním serveru (rozšíření, php.ini, locale, cron, změny v databázi, pořadí nasazení, návrat zpět, ověření). Každá položka, která mění něco důležitého pro server nebo migraci, ho aktualizuje v kroku Stavba a review kontroluje, že je aktuální.

Každá změna databáze (index, sloupec, cizí klíč, úprava dat) je samostatný idempotentní SQL soubor v `sql/migrations/` a zapíše se do přehledu DB migrací v `MIGRATION.md`: soubor, verze, co dělá, zda je povinná, kdy ji spustit vůči nahrání souborů, jak ji vrátit. Přehled je seznam všeho, co je potřeba při nasazení spustit, v pořadí spuštění. Soubor má v názvu další pořadové číslo (např. `003_popis.sql`), takže pořadí podle názvu je pořadí spuštění.

## GUIDE.md

`GUIDE.md` je popis řešení k odevzdání technické úlohy. Stručně a jasně říká, co se udělalo a proč, a odkazuje na podrobné dokumenty. Píše se pro hodnotitele úlohy, ne pro provoz. Každá položka ho aktualizuje v kroku Stavba a review kontroluje, že odpovídá skutečnosti.

## OPENQUESTIONS.md

Otevřené otázky pro zadavatele (rozhodnutí o produktu, která nejsou opravou chyby, např. přihlášení nebo pravidla validace) patří do `OPENQUESTIONS.md`, ne do `TODO.md`. Dokud nepřijde odpověď, neřeší se. Po rozhodnutí se u otázky doplní výsledek a odkaz na položku v `TODO.md` nebo na verzi.
