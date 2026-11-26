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
      'municipalities by region' => array('/index.php?url=municipalities&f_region=Z%C3%A1padn%C3%AD%20kraj', 'Obce', '<td class="col_string">Západní kraj</td>'),
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
