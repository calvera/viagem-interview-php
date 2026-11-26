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
      'municipalities' => array('/index.php?url=municipalities', 'Obce', 'f_municipality='),
      'municipalities huge page shows last page' => array('/index.php?url=municipalities&page=99999999999999999999', 'Obce', ', strana 125 z 125'),
      'municipalities by region' => array('/index.php?url=municipalities&f_region=Z%C3%A1padn%C3%AD%20kraj', 'Obce', '<td class="col_string">Západní kraj</td>'),
      'array url is ignored' => array('/index.php?url%5B%5D=x', 'Přehled', 'Poslední poznámky'),
      'array search is ignored' => array('/index.php?url=owners&search%5B%5D=x', 'Vlastníci pozemků', 'class="pagination"'),
      'array sort is ignored' => array('/index.php?url=municipalities&sort%5B%5D=x', 'Obce', 'f_municipality='),
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
   *
   * @dataProvider inMunicipalityProvider
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

  private function assertNoPhpError($body)
  {
    $text = strip_tags($body);
    foreach (self::PHP_ERRORS as $error)
    {
      $this->assertStringNotContainsString($error, $text);
    }
  }
}
