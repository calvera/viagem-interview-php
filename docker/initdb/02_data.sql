SET NAMES utf8mb4;
SET SESSION sql_mode = '';

-- Obce: 10 predpon x 25 korenu x 25 pripon = 6 250 unikatnich nazvu
INSERT INTO municipalities (id, code, name, district, region)
SELECT
  seq,
  CAST(500003 + seq * 37 AS CHAR),
  CONCAT(
    ELT(1 + (seq - 1) % 10, '', 'Horní ', 'Dolní ', 'Nová ', 'Stará ', 'Velká ', 'Malá ', 'Zadní ', 'Přední ', 'Dlouhá '),
    ELT(1 + FLOOR((seq - 1) / 10) % 25,
      'Lhota', 'Ves', 'Bystřice', 'Lipnice', 'Březnice', 'Habrovka', 'Javornice', 'Olešnice', 'Dubenka', 'Jedlinka',
      'Vrbice', 'Topolnice', 'Bukovina', 'Lomnice', 'Kamenice', 'Lučnice', 'Ostružná', 'Hrabová', 'Sosnovka', 'Jasenice',
      'Klenovka', 'Borovnice', 'Tisovka', 'Jilmová', 'Smrčina'),
    ELT(1 + FLOOR((seq - 1) / 250) % 25,
      '', ' u Brodu', ' nad Lesem', ' pod Horou', ' u Kamene', ' nad Potokem', ' u Hájku', ' na Výsluní', ' v Údolí', ' pod Skalou',
      ' u Rybníka', ' nad Řekou', ' u Mlýna', ' na Kopci', ' za Vodou', ' u Lípy', ' pod Lesem', ' nad Lukami', ' u Dvora', ' v Polích',
      ' u Kostela', ' na Rovni', ' pod Vrchem', ' u Studánky', ' za Humny')
  ),
  ELT(1 + (seq * 7) % 20,
    'Bělokamen', 'Zálesí', 'Podhorany', 'Vranov nad Struhou', 'Kostelany', 'Mlýnsko', 'Rybnicko', 'Habřiny', 'Lučany', 'Ořechov nad Lukou',
    'Sadovsko', 'Borovansko', 'Jesenicko nad Tichou', 'Hradčany u Lesa', 'Doubravsko', 'Podlesí', 'Kamenec', 'Výšiny', 'Vřesovsko', 'Rovensko'),
  ELT(1 + FLOOR(((seq * 7) % 20) / 3),
    'Severní kraj', 'Jižní kraj', 'Západní kraj', 'Východní kraj', 'Horský kraj', 'Polabský kraj', 'Podhorský kraj')
FROM seq_1_to_6250;

-- Vlastnici: 500 000, kazdy 12. je pravnicka osoba
INSERT INTO owners (id, type, title, first_name, last_name, birth_date, ico, street, city, zip, municipality_id,
  phone, email, do_not_contact, duedate, note, created_at)
SELECT
  s.seq,
  IF(s.seq % 12 = 0, 'P', 'F'),
  IF(s.seq % 12 <> 0 AND s.seq % 9 = 0, ELT(1 + s.seq % 5, 'Ing.', 'Mgr.', 'MUDr.', 'JUDr.', 'Bc.'), NULL),
  IF(s.seq % 12 = 0, '',
    IF(s.seq % 2 = 0,
      ELT(1 + FLOOR(s.seq / 2) % 30,
        'Jan', 'Petr', 'Josef', 'Pavel', 'Martin', 'Jaroslav', 'Tomáš', 'Miroslav', 'Zdeněk', 'František',
        'Václav', 'Karel', 'Milan', 'Jiří', 'Lukáš', 'Ondřej', 'Vladimír', 'Stanislav', 'Roman', 'Marek',
        'Radek', 'Libor', 'Aleš', 'Bohumil', 'Ladislav', 'Vlastimil', 'Oldřich', 'Rudolf', 'Miloš', 'Antonín'),
      ELT(1 + FLOOR(s.seq / 2) % 30,
        'Jana', 'Marie', 'Eva', 'Hana', 'Anna', 'Lenka', 'Kateřina', 'Lucie', 'Věra', 'Alena',
        'Petra', 'Veronika', 'Jaroslava', 'Martina', 'Jitka', 'Helena', 'Ludmila', 'Zdeňka', 'Ivana', 'Monika',
        'Zuzana', 'Jarmila', 'Markéta', 'Marcela', 'Dana', 'Simona', 'Božena', 'Vlasta', 'Olga', 'Irena'))),
  IF(s.seq % 12 = 0,
    CONCAT(
      ELT(1 + FLOOR(s.seq / 12) % 12, 'Agro', 'Statek', 'Farma', 'Lesní správa', 'Zemědělská společnost', 'Pozemkový spolek',
        'Rybníkářství', 'Sady', 'Vinařství', 'Pastviny', 'Polnosti', 'Realitní správa'),
      ' ',
      ELT(1 + FLOOR(s.seq / 144) % 25,
        'Lhota', 'Ves', 'Bystřice', 'Lipnice', 'Březnice', 'Habrovka', 'Javornice', 'Olešnice', 'Dubenka', 'Jedlinka',
        'Vrbice', 'Topolnice', 'Bukovina', 'Lomnice', 'Kamenice', 'Lučnice', 'Ostružná', 'Hrabová', 'Sosnovka', 'Jasenice',
        'Klenovka', 'Borovnice', 'Tisovka', 'Jilmová', 'Smrčina'),
      ' ',
      ELT(1 + s.seq % 5, 's.r.o.', 'a.s.', 'spol. s r.o.', 'družstvo', 'v.o.s.')),
    IF(s.seq % 2 = 0,
      ELT(1 + FLOOR(s.seq / 7) % 40,
        'Svoboda', 'Novotný', 'Dvořák', 'Černý', 'Procházka', 'Veselý', 'Horák', 'Němec', 'Pokorný', 'Marek',
        'Pospíšil', 'Hájek', 'Jelínek', 'Král', 'Růžička', 'Beneš', 'Fiala', 'Sedláček', 'Zeman', 'Kolář',
        'Navrátil', 'Čermák', 'Urban', 'Vaněk', 'Blažek', 'Kříž', 'Kovář', 'Bartoš', 'Vlček', 'Polák',
        'Musil', 'Kopecký', 'Šimek', 'Konečný', 'Malý', 'Holub', 'Štěpánek', 'Kratochvíl', 'Bláha', 'Říha'),
      ELT(1 + FLOOR(s.seq / 7) % 40,
        'Svobodová', 'Novotná', 'Dvořáková', 'Černá', 'Procházková', 'Veselá', 'Horáková', 'Němcová', 'Pokorná', 'Marková',
        'Pospíšilová', 'Hájková', 'Jelínková', 'Králová', 'Růžičková', 'Benešová', 'Fialová', 'Sedláčková', 'Zemanová', 'Kolářová',
        'Navrátilová', 'Čermáková', 'Urbanová', 'Vaňková', 'Blažková', 'Křížová', 'Kovářová', 'Bartošová', 'Vlčková', 'Poláková',
        'Musilová', 'Kopecká', 'Šimková', 'Konečná', 'Malá', 'Holubová', 'Štěpánková', 'Kratochvílová', 'Bláhová', 'Říhová'))),
  IF(s.seq % 12 = 0, NULL, DATE_ADD('1932-01-01', INTERVAL (s.seq * 7919) % 26000 DAY)),
  IF(s.seq % 12 = 0, LPAD((s.seq * 104729) % 99999999, 8, '0'), NULL),
  CONCAT(
    ELT(1 + s.seq % 20, 'Polní', 'Lesní', 'Školní', 'Zahradní', 'Krátká', 'Dlouhá', 'U Rybníka', 'Nádražní', 'Luční', 'Družstevní',
      'Za Mlýnem', 'K Lesu', 'Sadová', 'Májová', 'Okružní', 'Spojovací', 'Na Výsluní', 'Lipová', 'Jabloňová', 'Podhorní'),
    ' ', 1 + (s.seq * 13) % 280),
  m.name,
  LPAD(10000 + (s.seq * 31) % 89999, 5, '0'),
  IF(s.seq % 4 = 0, 1 + (s.seq * 4111) % 1250, 1 + (s.seq * 4111) % 6250),
  IF(s.seq % 4 = 0, CONCAT('+420 999 ', LPAD((s.seq * 7) % 1000, 3, '0'), ' ', LPAD((s.seq * 11) % 1000, 3, '0')), NULL),
  IF(s.seq % 5 = 0, CONCAT('vlastnik', s.seq, '@example.com'), NULL),
  IF(CRC32(CONCAT('n', s.seq)) % 29 = 0, 1, 0),
  CASE
    WHEN CRC32(CONCAT('d', s.seq)) % 5 IN (0, 1) THEN '0000-00-00'
    ELSE DATE_ADD('2025-06-01', INTERVAL CRC32(CONCAT('t', s.seq)) % 700 DAY)
  END,
  IF(s.seq % 17 = 0, ELT(1 + s.seq % 8,
    'Kontakt přes obecní úřad.', 'Nereaguje na dopisy.', 'Zájem o prodej, ozvat se na jaře.', 'Spoluvlastník žije v zahraničí.',
    'Preferuje e-mail.', 'Probíhá dědické řízení.', 'Volat po 17. hodině.', 'Na LV je poznámka o exekuci, ověřit.'), NULL),
  DATE_ADD('2012-03-01 08:00:00', INTERVAL s.seq * 13 * 60 + (s.seq * 7) % 60 SECOND)
FROM seq_1_to_500000 s
LEFT JOIN municipalities m ON m.id = IF(s.seq % 4 = 0, 1 + (s.seq * 4111) % 1250, 1 + (s.seq * 4111) % 6250);

-- Parcely: 800 000
INSERT INTO parcels (id, municipality_id, cadastral_area, cadastral_code, parcel_number, area_m2, land_type)
SELECT
  s.seq,
  m.id,
  IF(s.seq % 4 = 0, CONCAT(m.name, ' – ', ELT(1 + s.seq % 4, 'Zálesí', 'Podhájí', 'Zahrádky', 'Paseky')), m.name),
  CAST(600000 + m.id * 4 + IF(s.seq % 4 = 0, 1 + s.seq % 3, 0) AS CHAR),
  IF(s.seq % 6 = 0,
    CONCAT('st. ', 1 + s.seq % 900),
    CONCAT(1 + (s.seq * 7) % 4000, IF(s.seq % 2 = 0, CONCAT('/', 1 + s.seq % 60), ''))),
  IF(s.seq % 6 = 0, 40 + (s.seq * 7919) % 900, 150 + (s.seq * 7919) % 45000),
  IF(s.seq % 6 = 0, 'zastavěná plocha a nádvoří',
    ELT(1 + FLOOR(s.seq / 7) % 9, 'orná půda', 'trvalý travní porost', 'lesní pozemek', 'zahrada', 'ostatní plocha',
      'vodní plocha', 'ovocný sad', 'vinice', 'orná půda'))
FROM seq_1_to_800000 s
LEFT JOIN municipalities m ON m.id = 1 + (s.seq * 2017) % 6250;

-- Vlastnictvi: 1 200 000; vlastnici s id delitelnym 101 nemaji zadnou parcelu,
-- 3 z 5 parcel vlastnika lezi v obci jeho bydliste (1853 = inverze 2017 mod 6250)
INSERT INTO ownerships (id, owner_id, parcel_id, share_num, share_den, lv_number, created_at)
SELECT
  y.seq,
  y.owner_id,
  IF(y.seq % 5 < 3,
    IF(((y.owner_muni - 1) * 1853) % 6250 = 0, 6250, ((y.owner_muni - 1) * 1853) % 6250) + 6250 * ((y.seq * 7) % 128),
    1 + (y.seq * 3331) % 800000),
  1,
  ELT(1 + y.seq % 6, 1, 2, 2, 3, 4, 6),
  1 + (y.seq * 13) % 2500,
  DATE_ADD('2012-03-01 08:00:00', INTERVAL y.seq * 5 MINUTE)
FROM (
  SELECT z.seq, z.owner_id,
    IF(z.owner_id % 4 = 0, 1 + (z.owner_id * 4111) % 1250, 1 + (z.owner_id * 4111) % 6250) AS owner_muni
  FROM (
    SELECT x.seq, IF(x.oid % 101 = 0, x.oid + 1, x.oid) AS owner_id
    FROM (SELECT seq, 1 + (seq * 7919) % 500000 AS oid FROM seq_1_to_1200000) x
  ) z
) y;

-- Poznamky: 60 000
INSERT INTO owner_notes (owner_id, note, created_by, created_at)
SELECT
  1 + (seq * 7) % 500000,
  ELT(1 + seq % 10,
    'Odeslán dopis s nabídkou výkupu.', 'Telefonicky nedostupný.', 'Požaduje vyšší cenu.', 'Souhlasí se schůzkou.',
    'Vrácena obálka – neznámý adresát.', 'Předáno právnímu oddělení.', 'Ozve se sám po konzultaci s rodinou.',
    'Nemá zájem, neoslovovat dalších 12 měsíců.', 'Zaslán návrh kupní smlouvy.', 'Doplnit kontaktní údaje.'),
  ELT(1 + seq % 4, 'operator.a', 'operator.b', 'kancelar', 'import'),
  DATE_ADD('2016-01-04 09:00:00', INTERVAL seq * 47 MINUTE)
FROM seq_1_to_60000;
