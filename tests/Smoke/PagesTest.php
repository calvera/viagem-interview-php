<?php

use PHPUnit\Framework\TestCase;

/**
 * HTTP smoke tests against a running application (env APP_URL, e.g. http://localhost:8074).
 * Read only: every request is a GET that changes no data (apart from the access log).
 */
class PagesTest extends TestCase
{
  const PHP_ERRORS = array('Fatal error:', 'Parse error:', 'Warning:', 'Deprecated:', 'Notice:', 'Uncaught ');

  protected function setUp(): void
  {
    if (!getenv('APP_URL')) $this->markTestSkipped('APP_URL is not set.');
  }

  /**
   * @dataProvider pageProvider
   */
  public function testPage($path, $heading, $text)
  {
    $response = $this->get($path);

    $this->assertSame(200, $response['status']);
    $this->assertStringContainsString('text/html', $response['type']);
    $this->assertNoPhpError($response['body']);
    $this->assertMatchesRegularExpression('~<h1>\s*' . preg_quote($heading, '~') . '~u', $response['body']);
    $this->assertStringContainsString($text, $response['body']);
  }

  public function pageProvider()
  {
    return array(
      'dashboard' => array('/', 'Přehled', 'Poslední poznámky'),
      'owners' => array('/index.php?url=owners', 'Vlastníci pozemků', 'class="pagination"'),
      'owners by municipality' => array('/index.php?url=owners&f_municipality=Lhota', 'Vlastníci pozemků', '<th style="width: 10%">V obci</th>'),
      'owners search name' => array('/index.php?url=owners&search=Svobodov%C3%A1', 'Vlastníci pozemků', ' Svobodová</a>'),
      'owners search ico' => array('/index.php?url=owners&search=01256748', 'Vlastníci pozemků', 'Statek Lhota spol. s r.o.'),
      'owners sorted' => array('/index.php?url=owners&sort=o.created_at&dir=desc', 'Vlastníci pozemků', 'Založeno ▼'),
      'owners page 2' => array('/index.php?url=owners&page=2', 'Vlastníci pozemků', ', strana 2 z '),
      'owner person' => array('/index.php?url=owners/detail&id=1', 'Jana Svobodová', 'Vlastník je fyzická osoba'),
      'owner company' => array('/index.php?url=owners/detail&id=12', 'Statek Lhota spol. s r.o.', '<tr><th>IČO</th><td>01256748</td></tr>'),
      'owner with note' => array('/index.php?url=owners/detail&id=420001', 'Jana Svobodová', 'Odeslán dopis s nabídkou výkupu.'),
      'owner missing' => array('/index.php?url=owners/detail&id=999999999', 'Vlastník nenalezen', 'Vlastník s ID 999999999 v evidenci není.'),
      'owner edit' => array('/index.php?url=owners/edit&id=1', 'Úprava vlastníka', 'name="last_name" value="Svobodová"'),
      'owner edit missing' => array('/index.php?url=owners/edit&id=999999999', 'Vlastník nenalezen', 'Zpět na výpis vlastníků'),
      'parcels' => array('/index.php?url=parcels', 'Parcely', 'class="pagination"'),
      'parcels by land type' => array('/index.php?url=parcels&f_land_type=orn%C3%A1%20p%C5%AFda', 'Parcely', '<td class="col_string">Orná půda</td>'),
      'municipalities' => array('/index.php?url=municipalities', 'Obce', 'f_municipality_id='),
      'municipalities huge page shows last page' => array('/index.php?url=municipalities&page=99999999999999999999', 'Obce', ', strana 125 z 125'),
      'municipalities by region' => array('/index.php?url=municipalities&f_region=Z%C3%A1padn%C3%AD%20kraj', 'Obce', '<td class="col_string">Západní kraj</td>'),
      'array url is ignored' => array('/index.php?url%5B%5D=x', 'Přehled', 'Poslední poznámky'),
      'array search is ignored' => array('/index.php?url=owners&search%5B%5D=x', 'Vlastníci pozemků', 'class="pagination"'),
      'array sort is ignored' => array('/index.php?url=municipalities&sort%5B%5D=x', 'Obce', 'f_municipality_id='),
    );
  }

  public function testUnknownModuleReturns404()
  {
    $response = $this->get('/index.php?url=nope');

    $this->assertSame(404, $response['status']);
    $this->assertNoPhpError($response['body']);
    $this->assertStringContainsString('<h1>Stránka nenalezena</h1>', $response['body']);
  }

  public function testExportCsv()
  {
    $response = $this->get('/index.php?url=owners/export&f_type=P');

    $this->assertSame(200, $response['status']);
    $this->assertSame('text/csv; charset=utf-8', $response['type']);
    $this->assertNoPhpError($response['body']);

    $lines = explode("\n", $response['body']);
    $this->assertStringStartsWith("\xEF\xBB\xBF\"Export vlastníků\";", $lines[0]);
    $this->assertSame('ID;Typ;Titul;Jméno;"Příjmení / název";"Datum narození";IČO;Ulice;PSČ;Město;"Obec (KN)";Telefon;E-mail;"Termín oslovení";Založeno', $lines[1]);
    $this->assertStringContainsString(';"Právnická osoba";', $lines[2]);
  }

  public function testExportSkipsDoNotContactOwners()
  {
    $response = $this->get('/index.php?url=owners/export&f_type=P&f_dnc=1');

    $this->assertSame(200, $response['status']);
    $this->assertNoPhpError($response['body']);

    $lines = explode("\n", $response['body']);
    $this->assertCount(5, $lines);
    $this->assertSame('', $lines[2]);
    $this->assertMatchesRegularExpression('~^"Vynecháno [1-9]\d* vlastníků se zákazem oslovení\."$~u', $lines[3]);
  }

  public function testExportAllIncludesDoNotContactOwners()
  {
    $response = $this->get('/index.php?url=owners/export&f_type=P&f_dnc=1&all=1');

    $this->assertSame(200, $response['status']);
    $this->assertNoPhpError($response['body']);

    $lines = explode("\n", $response['body']);
    $this->assertStringContainsString(';"Právnická osoba";', $lines[2]);
    $this->assertStringNotContainsString('Vynecháno', $response['body']);
  }

  public function testAutocompleteReturnsJson()
  {
    $response = $this->get('/ajax_municipalities.php?q=Lh');

    $this->assertSame(200, $response['status']);
    $this->assertSame('application/json; charset=utf-8', $response['type']);

    $items = json_decode($response['body'], true);
    $this->assertIsArray($items);
    $this->assertCount(15, $items);
    $this->assertSame(array('id' => '1', 'code' => '500040', 'value' => 'Lhota', 'label' => 'Lhota (okres Habřiny)'), $items[0]);
  }

  public function testAutocompleteNeedsTwoCharacters()
  {
    $response = $this->get('/ajax_municipalities.php?q=L');

    $this->assertSame(200, $response['status']);
    $this->assertSame('[]', $response['body']);
  }

  public function testAutocompleteIgnoresArray()
  {
    $response = $this->get('/ajax_municipalities.php?q%5B%5D=Lh');

    $this->assertSame(200, $response['status']);
    $this->assertSame('[]', $response['body']);
  }

  /**
   * @dataProvider injectionProvider
   */
  public function testInjectionInFilterOrSearchFindsNothing($path)
  {
    $response = $this->get($path);

    $this->assertSame(200, $response['status']);
    $this->assertNoPhpError($response['body']);
    $this->assertStringContainsString('Nalezeno <b>0</b>', $response['body']);
  }

  public function injectionProvider()
  {
    // Before the fix each of these matched every row ("or 'a' like 'a" / "or 1=1").
    $always_true = rawurlencode("zzzqq%' or 'a' like 'a");

    return array(
      'municipalities search' => array('/index.php?url=municipalities&search=' . $always_true),
      'municipalities district' => array('/index.php?url=municipalities&f_district=' . rawurlencode("x' or 1=1 or district <> 'y")),
      'parcels municipality' => array('/index.php?url=parcels&f_municipality=' . $always_true),
      'parcels apostrophe' => array('/index.php?url=parcels&search=x%27'),
      'owners type' => array('/index.php?url=owners&f_type=' . rawurlencode("x' or '1'='1")),
    );
  }

  public function testSearchIsEscapedInHtml()
  {
    $response = $this->get('/index.php?url=municipalities&search=%3Cscript%3Ex%3C%2Fscript%3E');

    $this->assertSame(200, $response['status']);
    $this->assertStringNotContainsString('<script>x</script>', $response['body']);
    $this->assertStringContainsString('name="search" value="&lt;script&gt;x&lt;/script&gt;"', $response['body']);
    $this->assertStringContainsString('„<b>&lt;script&gt;x&lt;/script&gt;</b>“', $response['body']);
  }

  public function testFilterIsEscapedInHtml()
  {
    $response = $this->get('/index.php?url=parcels&f_municipality=%22%3E%3Cscript%3Ex%3C%2Fscript%3E');

    $this->assertSame(200, $response['status']);
    $this->assertStringNotContainsString('<script>x</script>', $response['body']);
    $this->assertStringContainsString('name="f_municipality" value="&quot;&gt;&lt;script&gt;x&lt;/script&gt;"', $response['body']);
  }

  /**
   * @dataProvider autocompleteInjectionProvider
   */
  public function testAutocompleteInjectionReturnsEmptyJson($q)
  {
    $response = $this->get('/ajax_municipalities.php?q=' . rawurlencode($q));

    $this->assertSame(200, $response['status']);
    $this->assertSame('[]', $response['body']);
  }

  public function autocompleteInjectionProvider()
  {
    return array(
      'apostrophe' => array("Lh'"),
      'always true' => array("zz%' or 'a' like 'a"),
    );
  }

  /**
   * Municipality, parcel count and "V obci" (parcels in municipalities whose name contains the filter, with share area)
   * for owners on a page that fits all matches, so the order does not matter. Values from v2.7.8.
   * With the filter by id (link from the municipalities list), "V obci" counts the parcels in that municipality;
   * values from the database (`ownerships` joined to `parcels where municipality_id = ?`).
   *
   * @dataProvider inMunicipalityProvider
   * @dataProvider inMunicipalityByIdProvider
   */
  public function testInMunicipalityColumn($path, $owner_id, $municipality, $parcels, $in_municipality)
  {
    $response = $this->get($path);

    $this->assertSame(200, $response['status']);
    $this->assertNoPhpError($response['body']);
    $this->assertMatchesRegularExpression('~<tr><td class="col_html"><a href="index.php\?url=owners/detail&amp;id=' . $owner_id . '">'
      . '(?:(?!</tr>).)*<td class="col_html">' . preg_quote($municipality, '~') . '</td><td class="col_int">' . $parcels . '</td>'
      . '<td class="col_html">' . preg_quote($in_municipality, '~') . '</td>~u',
      $response['body']);
  }

  public function inMunicipalityProvider()
  {
    $lhota = '/index.php?url=owners&f_municipality=Lhota&search=Agro+Bukovina';
    $kopec = '/index.php?url=owners&f_municipality=Doln%C3%AD+Lhota+na+Kopci';

    return array(
      'all parcels in municipality, ha' => array($lhota, 476928, 'Přední Lhota u Kamene', 3, '3 <small>(1,01 ha)</small>'),
      'all parcels in municipality, m²' => array($lhota, 422928, 'Přední Lhota pod Horou', 2, '2 <small>(1 518 m²)</small>'),
      'some parcels elsewhere' => array($lhota, 224928, 'Přední Lhota u Brodu', 4, '2 <small>(1,74 ha)</small>'),
      'no parcel in municipality' => array($kopec, 435982, 'Dolní Lhota na Kopci', 2, '<span class="muted">–</span>'),
    );
  }

  public function inMunicipalityByIdProvider()
  {
    $lhota = '/index.php?url=owners&f_municipality_id=1&search=';

    return array(
      'by id, m²' => array($lhota . 'Agro', 180000, 'Lhota', 2, '2 <small>(5 188 m²)</small>'),
      'by id, ha' => array($lhota . 'Agro', 360000, 'Lhota', 2, '2 <small>(1,09 ha)</small>'),
      'by id, area 12 650 m² rounds up' => array($lhota . 'Dvo%C5%99%C3%A1k', 102500, 'Lhota', 3, '3 <small>(1,27 ha)</small>'),
      'by id, parcels only elsewhere' => array('/index.php?url=owners&f_municipality_id=2018&search=Kol%C3%A1%C5%99ov%C3%A1', 2097, 'Zadní Ves v Údolí', 3, '<span class="muted">–</span>'),
    );
  }

  /**
   * Links "vlastníci" and "parcely" in the municipalities list filter by municipality id (Lhota, code 500040, has id 1)
   * and look like small buttons.
   */
  public function testMunicipalityOwnersLinkById()
  {
    $response = $this->get('/index.php?url=municipalities&search=500040');

    $this->assertSame(200, $response['status']);
    $this->assertStringContainsString('<span class="buttons"><a href="index.php?url=owners&amp;f_municipality_id=1" class="button small">vlastníci</a> '
      . '<a href="index.php?url=parcels&amp;f_municipality_id=1" class="button small">parcely</a></span>', $response['body']);
    $this->assertStringNotContainsString('f_municipality=', $response['body']);
  }

  /**
   * Owners by municipality id: exactly the owners of that municipality (`select count(*) from owners where
   * municipality_id = ?`; "Lhota" as a name substring finds 20 000), with the label, the remove link, the value
   * as a hidden input and an empty text field.
   *
   * @dataProvider ownersByIdProvider
   */
  public function testOwnersByMunicipalityId($id, $name, $count)
  {
    $response = $this->get('/index.php?url=owners&f_municipality_id=' . $id);

    $this->assertSame(200, $response['status']);
    $this->assertNoPhpError($response['body']);
    $this->assertStringContainsString('Nalezeno <b>' . $count . '</b> vlastníků', $response['body']);
    $this->assertStringContainsString('<span class="hidden_filter">Obec: <b>' . $name . '</b> <a href="index.php?url=owners" title="zrušit">×</a></span> '
      . '<input type="hidden" name="f_municipality_id" value="' . $id . '">', $response['body']);
    $this->assertStringContainsString('name="f_municipality" value=""', $response['body']);
    $this->assertStringContainsString('<th style="width: 10%">V obci</th>', $response['body']);
  }

  public function ownersByIdProvider()
  {
    return array(
      'Lhota' => array(1, 'Lhota', 240),
      'Zadní Ves v Údolí' => array(2018, 'Zadní Ves v Údolí', 80),
      'Velká Lhota na Rovni' => array(5256, 'Velká Lhota na Rovni', 80),
    );
  }

  /**
   * An id that is not just digits is ignored: the full list without the label and the "V obci" column.
   *
   * @dataProvider invalidIdProvider
   */
  public function testInvalidMunicipalityIdIsIgnored($value)
  {
    $response = $this->get('/index.php?url=owners&f_municipality_id=' . $value);

    $this->assertSame(200, $response['status']);
    $this->assertNoPhpError($response['body']);
    $this->assertStringContainsString('Nalezeno <b>500 000</b> vlastníků', $response['body']);
    $this->assertStringNotContainsString('hidden_filter', $response['body']);
    $this->assertStringNotContainsString('V obci', $response['body']);
  }

  /**
   * Default sort of the owners list: last name, first name and owner id, so owners with the same name are in
   * ascending id order. Page 1 has only "Agro Borovnice v.o.s." (139 owners), page 5000 only "Marie Marková",
   * with and without the title (the title is not part of the sort). Ids from the database:
   * `select id from owners order by last_name, first_name, id limit <offset>, 50`.
   *
   * @dataProvider defaultSortTiesProvider
   */
  public function testOwnersWithSameNameAreInIdOrder($path, $name, $first_id, $step)
  {
    $response = $this->get($path);

    $this->assertSame(200, $response['status']);
    $this->assertNoPhpError($response['body']);
    preg_match_all('~<td class="col_html"><a href="index.php\?url=owners/detail&amp;id=(\d+)">((?:[^<]+ )?' . preg_quote($name, '~') . ')</a>~u', $response['body'], $m);

    $this->assertSame(range($first_id, $first_id + 49 * $step, $step), array_map('intval', $m[1]));
  }

  public function defaultSortTiesProvider()
  {
    return array(
      'page 1' => array('/index.php?url=owners', 'Agro Borovnice v.o.s.', 3024, 3600),
      'page 5000' => array('/index.php?url=owners&page=5000', 'Marie Marková', 207543, 840),
    );
  }

  public function invalidIdProvider()
  {
    return array(
      'text' => array('abc'),
      'injection' => array(rawurlencode('1 or 1=1')),
      'negative' => array('-1'),
      'array' => array('1&f_municipality_id%5B%5D=1'),
    );
  }

  public function testExportByMunicipalityId()
  {
    $response = $this->get('/index.php?url=owners/export&f_municipality_id=1&all=1');

    $this->assertSame(200, $response['status']);
    $this->assertNoPhpError($response['body']);

    $lines = array_slice(explode("\n", trim($response['body'])), 2);
    $this->assertCount(240, $lines);
    foreach ($lines as $line) $this->assertSame('Lhota', str_getcsv($line, ';', '"', '')[10]);
  }

  /**
   * Without the municipality filter: municipality and parcel count; an owner without parcels has 0 and a grey row.
   * Values from v2.7.8.
   *
   * @dataProvider ownerRowProvider
   */
  public function testOwnerRowMunicipalityAndParcels($path, $owner_id, $row_start, $municipality, $parcels)
  {
    $response = $this->get($path);

    $this->assertSame(200, $response['status']);
    $this->assertNoPhpError($response['body']);
    $this->assertStringNotContainsString('V obci', $response['body']);
    $this->assertMatchesRegularExpression('~' . preg_quote($row_start, '~') . '<td class="col_html"><a href="index.php\?url=owners/detail&amp;id=' . $owner_id . '">'
      . '(?:(?!</tr>).)*<td class="col_html">' . preg_quote($municipality, '~') . '</td><td class="col_int">' . $parcels . '</td>'
      . '<td class="col_html"><span~u',
      $response['body']);
  }

  public function ownerRowProvider()
  {
    return array(
      'owner with parcels' => array('/index.php?url=owners&search=01256748', 12, '<tr>', 'Dolní Dubenka nad Lesem', 3),
      'owner without parcels' => array('/index.php?url=owners&search=86750415', 305424, '<tr class="no_parcels">', 'Stará Javornice nad Lesem', 0),
    );
  }

  /**
   * Owners and LV numbers of a parcel on the default first page (sorted by id): a wrong mapping of the batch-loaded
   * owners to parcels would show other owners. Parcel 15 has owners with different last names, so their order is fixed.
   * Values from the database (`ownerships where parcel_id = 15`), same as in v2.7.9.
   */
  public function testParcelOwners()
  {
    $owner = function ($id, $name, $share) {
      return '<a href="index.php?url=owners/detail&amp;id=' . $id . '">' . $name . '</a> <small>(' . $share . ')</small>';
    };
    $owners = implode('<br>', array(
      $owner(12705, 'Markéta Benešová', 'celý'),
      $owner(132487, 'Hana Horáková', '1/4'),
      $owner(412705, 'Jaroslava Kratochvílová', '1/2'),
      $owner(312705, 'Ing. Markéta Šimková', '1/4'),
      $owner(332487, 'MUDr. Martina Zemanová', 'celý'),
    ));

    $response = $this->get('/index.php?url=parcels');

    $this->assertSame(200, $response['status']);
    $this->assertNoPhpError($response['body']);
    $this->assertStringContainsString('<tr><td class="col_string">Velká Lhota na Rovni</td><td class="col_string">Velká Lhota na Rovni</td>'
      . '<td class="col_string">106</td><td class="col_string">2209, 2423</td><td class="col_string">Lesní pozemek</td>'
      . '<td class="col_area">2,89 ha</td><td class="col_html">' . $owners . '</td></tr>', $response['body']);
  }

  /**
   * Owners with the same last name are ordered by first name and owner id, and the LV numbers follow the owners.
   * Parcel 19020 has three owners "Kratochvíl" (two "Václav"), parcel 35 two owners "Černá" with different LVs,
   * so the tie decides the order of the LV numbers. Order from the database:
   * `ownerships where parcel_id = ? order by last_name, first_name, owner id, ownership id`.
   *
   * @dataProvider parcelOwnerTiesProvider
   */
  public function testParcelOwnersWithSameLastName($path, $row_start, $lv, $owners)
  {
    $links = array();
    foreach ($owners as $o)
    {
      $links[] = '<a href="index.php?url=owners/detail&amp;id=' . $o[0] . '">' . $o[1] . '</a> <small>(' . $o[2] . ')</small>';
    }

    $response = $this->get($path);

    $this->assertSame(200, $response['status']);
    $this->assertNoPhpError($response['body']);
    $this->assertStringContainsString($row_start . '<td class="col_string">' . $lv . '</td>', $response['body']);
    $this->assertStringContainsString('<td class="col_html">' . implode('<br>', $links) . '</td></tr>', $response['body']);
  }

  public function parcelOwnerTiesProvider()
  {
    return array(
      'parcel 19020' => array(
        '/index.php?url=parcels&search=st.%20121',
        '<tr><td class="col_string">Jedlinka pod Horou</td><td class="col_string">Jedlinka pod Horou – Zálesí</td><td class="col_string">st. 121</td>',
        '554, 1538',
        array(
          array(24940, 'Radek Dvořák', '1/6'),
          array(444940, 'Radek Dvořák', '1/3'),
          array(204940, 'Radek Kratochvíl', '1/6'),
          array(64940, 'Václav Kratochvíl', '1/3'),
          array(484940, 'Václav Kratochvíl', '1/2'),
          array(144940, 'Radek Kříž', '1/6'),
          array(4940, 'Václav Kříž', '1/3'),
          array(184940, 'Václav Navrátil', '1/3'),
          array(104940, 'Pastviny Lipnice s.r.o.', '1/2'),
          array(464940, 'Pastviny Lipnice s.r.o.', '1/6'),
          array(124940, 'Václav Pokorný', '1/3'),
          array(164940, 'Pozemkový spolek Klenovka s.r.o.', '1/2'),
          array(382232, 'Vladimír Procházka', '1/6'),
          array(84940, 'Radek Růžička', '1/6'),
          array(44940, 'Statek Bukovina s.r.o.', '1/2'),
          array(224940, 'Statek Bukovina s.r.o.', '1/2'),
        ),
      ),
      'parcel 35' => array(
        '/index.php?url=parcels',
        '<tr><td class="col_string">Velká Jedlinka na Výsluní</td><td class="col_string">Velká Jedlinka na Výsluní</td><td class="col_string">246</td>',
        '1589, 883',
        array(
          array(442145, 'Eva Černá', '1/4'),
          array(307467, 'MUDr. Martina Černá', '1/2'),
          array(7467, 'Martina Kovářová', '1/4'),
          array(342145, 'Jaroslava Kratochvílová', 'celý'),
          array(242145, 'Ing. Markéta Šimková', '1/2'),
        ),
      ),
    );
  }

  /**
   * Parcel 1 (first row of the default page) has no owner.
   */
  public function testParcelWithoutOwner()
  {
    $response = $this->get('/index.php?url=parcels');

    $this->assertStringContainsString('<tbody><tr><td class="col_string">Zadní Ves v Údolí</td><td class="col_string">Zadní Ves v Údolí</td>'
      . '<td class="col_string">8</td><td class="col_string"></td><td class="col_string">Orná půda</td><td class="col_area">8 069 m²</td>'
      . '<td class="col_html"><span class="muted">bez vlastníka</span></td></tr>', $response['body']);
  }

  /**
   * Parcel count of a municipality, equal to `select count(*) from parcels where municipality_id = ?` (128 for
   * every municipality in the generated data; a wrong correlation of the subquery gives 0).
   *
   * @dataProvider municipalityParcelsProvider
   */
  public function testMunicipalityParcelCount($code, $name, $district, $region, $parcels)
  {
    $response = $this->get('/index.php?url=municipalities&search=' . $code);

    $this->assertSame(200, $response['status']);
    $this->assertNoPhpError($response['body']);
    $this->assertStringContainsString('Nalezeno <b>1</b> obcí', $response['body']);
    $this->assertStringContainsString('<tr><td class="col_string">' . $code . '</td><td class="col_string">' . $name . '</td>'
      . '<td class="col_string">' . $district . '</td><td class="col_string">' . $region . '</td><td class="col_int">' . $parcels . '</td>',
      $response['body']);
  }

  public function municipalityParcelsProvider()
  {
    return array(
      'Zadní Ves v Údolí (id 2018)' => array('574669', 'Zadní Ves v Údolí', 'Rybnicko', 'Západní kraj', 128),
      'Velká Lhota na Rovni (id 5256)' => array('694475', 'Velká Lhota na Rovni', 'Jesenicko nad Tichou', 'Horský kraj', 128),
    );
  }

  /**
   * Parcels by municipality id (link "parcely" from the municipalities list): the count equals `select count(*)
   * from parcels where municipality_id = ?` (and the "Parcel" column, 128 for every municipality), with the label,
   * the remove link keeping the other filters, the value as a hidden input and an empty text field.
   *
   * @dataProvider parcelsByIdProvider
   */
  public function testParcelsByMunicipalityId($query, $id, $name, $remove, $count)
  {
    $response = $this->get('/index.php?url=parcels&f_municipality_id=' . $id . $query);

    $this->assertSame(200, $response['status']);
    $this->assertNoPhpError($response['body']);
    $this->assertStringContainsString('Nalezeno <b>' . $count . '</b> parcel', $response['body']);
    $this->assertStringContainsString('<span class="hidden_filter">Obec: <b>' . $name . '</b> <a href="' . $remove . '" title="zrušit">×</a></span> '
      . '<input type="hidden" name="f_municipality_id" value="' . $id . '">', $response['body']);
    $this->assertStringContainsString('name="f_municipality" value=""', $response['body']);
    if ($count) $this->assertSame(array($name), array_values(array_unique($this->firstColumn($response['body']))));
  }

  public function parcelsByIdProvider()
  {
    return array(
      'Lhota' => array('', 1, 'Lhota', 'index.php?url=parcels', 128),
      'Zadní Ves v Údolí' => array('', 2018, 'Zadní Ves v Údolí', 'index.php?url=parcels', 128),
      'Velká Lhota na Rovni, page 3' => array('&page=3', 5256, 'Velká Lhota na Rovni', 'index.php?url=parcels', 128),
      'Lhota, arable land' => array('&f_land_type=orn%C3%A1%20p%C5%AFda', 1, 'Lhota', 'index.php?url=parcels&amp;f_land_type=orn%C3%A1+p%C5%AFda', 18),
      'nonexistent id' => array('', 999999, '999999', 'index.php?url=parcels', 0),
    );
  }

  /**
   * An id that is not just digits is ignored in the parcels list: all parcels, no label.
   *
   * @dataProvider invalidIdProvider
   */
  public function testInvalidMunicipalityIdIsIgnoredInParcels($value)
  {
    $response = $this->get('/index.php?url=parcels&f_municipality_id=' . $value);

    $this->assertSame(200, $response['status']);
    $this->assertNoPhpError($response['body']);
    $this->assertStringContainsString('Nalezeno <b>800 000</b> parcel', $response['body']);
    $this->assertStringNotContainsString('hidden_filter', $response['body']);
  }

  /**
   * Notes are deleted only by POST; a GET with delete_note is ignored.
   * A nonexistent note id, so nothing is deleted even if this check fails.
   */
  public function testGetDeleteNoteDeletesNothing()
  {
    $response = $this->get('/index.php?url=owners/detail&id=420001&delete_note=999999999');

    $this->assertSame(200, $response['status']);
    $this->assertNoPhpError($response['body']);
    $this->assertStringContainsString('name="note_id" value="60000"', $response['body']);
    $this->assertStringContainsString('name="csrf_token" value="', $response['body']);
    $this->assertStringNotContainsString('delete_note=', $response['body']);
  }

  /**
   * GET the path and return status, content type and body.
   */
  private function get($path)
  {
    $ch = curl_init(rtrim(getenv('APP_URL'), '/') . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $body = curl_exec($ch);
    $this->assertIsString($body, curl_error($ch));

    return array(
      'status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
      'type' => (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
      'body' => $body,
    );
  }

  /**
   * Text of the first cell of every row in the list table.
   */
  private function firstColumn($body)
  {
    preg_match_all('~<tr><td class="[^"]*">([^<]*)</td>~u', $body, $m);
    $this->assertNotEmpty($m[1]);

    return $m[1];
  }

  private function assertNoPhpError($body)
  {
    $text = strip_tags($body);
    foreach (self::PHP_ERRORS as $error)
    {
      $this->assertStringNotContainsString($error, $text);
    }
  }
}
