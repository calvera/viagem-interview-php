# Evidence vlastníků

Interní webová aplikace pro evidenci vlastníků pozemků: vlastníci, jejich parcely a podíly, poznámky k oslovování, export do CSV a noční report. Je postavená na vlastním PHP frameworku (PHP 7.4, MariaDB 10.11) a je výsekem většího interního CRM.

Všechna data jsou syntetická, generují se při prvním startu databáze.

## Funkce

- **Přehled** – počty záznamů, poslední poznámky, historie a poslední noční report.
- **Vlastníci** – výpis s filtrem podle obce, typu a zákazu oslovení, hledání podle jména nebo IČO, detail s parcelami a podíly, poznámky, úprava údajů, export do CSV.
- **Parcely** – výpis s filtrem podle obce a druhu pozemku, hledání podle čísla parcely nebo kódu katastrálního území.
- **Obce** – výpis s filtrem podle okresu a kraje, odkaz na vlastníky v obci.
- **Noční report** – souhrnné statistiky do `reports/`, záznam do `report_runs` a odeslání na reportovací službu.

Aplikace nemá přihlášení, uživatel je pevně nastavený v `includes/config.php`.

Známé problémy jsou v [TODO.md](TODO.md), změny v [CHANGELOG.md](CHANGELOG.md). Kód zatím na PHP 8 nefunguje (viz TODO).

## Spuštění

Potřebujete Docker (Docker Desktop) a volné porty 8074, 8085 a 33061.

```bash
docker compose up -d
```

První spuštění trvá déle: stáhnou se image, sestaví se PHP kontejnery a databáze si vygeneruje testovací data (6 250 obcí, 500 000 vlastníků, 800 000 parcel, 1 200 000 vlastnických vztahů). Příkaz počká, až bude databáze připravená. Samotné generování dat trvá podle stroje zhruba 0,5 až 2 minuty, stažení a sestavení image při prvním startu několik minut navíc.

| Služba | Adresa |
| --- | --- |
| Aplikace na PHP 7.4 | http://localhost:8074 |
| Aplikace na PHP 8.5 | http://localhost:8085 |
| MariaDB | `localhost:33061`, databáze `legacy_crm`, uživatel `crm` / heslo `crm` |

Obě PHP služby používají stejný kód (adresář projektu je do kontejnerů připojený), takže úpravy se projeví bez restartu.

Noční report (v provozu ho spouští cron):

```bash
docker compose exec php74 php cron/nightly_report.php
docker compose exec php85 php cron/nightly_report.php
```

Report se uloží do `reports/`, logy aplikace jsou v `logs/`.

Zastavení: `docker compose down`. Smazání včetně databáze: `docker compose down -v` (při dalším startu se data vygenerují znovu).

## Struktura

- `index.php` → `includes/config.php` → `includes/loader.php` (načte `includes/functions.php`, `class/Database.php`, `class/Section.php`, připojí DB)
- `admin_*_main.php` – moduly (Přehled, Vlastníci, Parcely, Obce), adresa `index.php?url=<modul>/<akce>`
- `admin_owners/` – výpis, detail, úprava a export vlastníků
- `class/Section.php` – obecný výpis s filtry, hledáním, řazením a stránkováním
- `ajax_municipalities.php` – našeptávač obcí (`?q=`)
- `cron/nightly_report.php` – noční report
- `layout/`, `css/`, `js/` – hlavička a patička stránky, styly, klientské skripty (potvrzení mazání, našeptávač)
- `plugin/minify/` – přibalená knihovna třetí strany pro minifikaci CSS a JS
- `docker/` – Dockerfile pro PHP 7.4 a 8.5, schéma a generátor dat
