# Pravidla projektu

Všechny dokumenty v repozitáři (`README.md`, `TODO.md`, `CHANGELOG.md`, `MIGRATION.md`, `CLAUDE.md`) se píší česky.

## Postup u pracovních položek

Každá pracovní položka (jeden úkol z `TODO.md` nebo jedna ucelená změna) projde touto smyčkou:

1. **Stavba**: práci dostane agent `opus-builder`. Ten:
   - provede změny v kódu a spustí testy,
   - odškrtne nebo upraví položku v `TODO.md`,
   - přidá záznam pod `## [Nevydáno]` v `CHANGELOG.md`,
   - aktualizuje `MIGRATION.md`, pokud se změna týká migrace nebo serveru.
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
