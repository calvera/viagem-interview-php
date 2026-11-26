<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/functions.php';

/**
 * Pure helper functions from includes/functions.php.
 */
class FunctionsTest extends TestCase
{
  protected function tearDown(): void
  {
    $_GET = array();
    $_POST = array();
  }

  public function testHEscapesSpecialCharacters()
  {
    $this->assertSame('&lt;a href=&quot;x&quot;&gt;Tom &amp; Jerry&#039;s&lt;/a&gt;', h('<a href="x">Tom & Jerry\'s</a>'));
  }

  public function testHReturnsEmptyStringForNull()
  {
    $this->assertSame('', h(null));
  }

  /**
   * @dataProvider formatDateProvider
   */
  public function testFormatDate($date, $expected)
  {
    $this->assertSame($expected, format_date($date));
  }

  public function formatDateProvider()
  {
    return array(
      'date' => array('2021-05-15', '15. 5. 2021'),
      'datetime' => array('2021-05-15 17:00:00', '15. 5. 2021'),
      'null' => array(null, '–'),
      'empty' => array('', '–'),
      'zero date' => array('0000-00-00', '–'),
      'zero datetime' => array('0000-00-00 00:00:00', '–'),
    );
  }

  public function testFormatDateCustomEmptyText()
  {
    $this->assertSame('nenastaven', format_date('0000-00-00', 'nenastaven'));
  }

  /**
   * @dataProvider formatDatetimeProvider
   */
  public function testFormatDatetime($date, $expected)
  {
    $this->assertSame($expected, format_datetime($date));
  }

  public function formatDatetimeProvider()
  {
    return array(
      'datetime' => array('2021-05-15 17:05:00', '15. 5. 2021 17:05'),
      'null' => array(null, '–'),
      'zero datetime' => array('0000-00-00 00:00:00', '–'),
      'zero date' => array('0000-00-00', '–'),
    );
  }

  public function testFormatDatetimeCustomEmptyText()
  {
    $this->assertSame('nikdy', format_datetime(null, 'nikdy'));
  }

  /**
   * @dataProvider formatAreaProvider
   */
  public function testFormatArea($m2, $expected)
  {
    $this->assertSame($expected, format_area($m2));
  }

  public function formatAreaProvider()
  {
    return array(
      'zero' => array(0, '0 m²'),
      'small' => array(512, '512 m²'),
      'below hectare' => array(9999, '9 999 m²'),
      'one hectare' => array(10000, '1,00 ha'),
      'hectares' => array(12345, '1,23 ha'),
      'string from DB' => array('2500', '2 500 m²'),
      'null from DB' => array(null, '0 m²'),
    );
  }

  /**
   * @dataProvider formatShareProvider
   */
  public function testFormatShare($num, $den, $expected)
  {
    $this->assertSame($expected, format_share($num, $den));
  }

  public function formatShareProvider()
  {
    return array(
      'whole' => array(1, 1, 'celý'),
      'whole as strings' => array('2', '2', 'celý'),
      'half' => array(1, 2, '1/2'),
      'not reduced' => array('2', '4', '2/4'),
      'invalid zero denominator' => array('0', '0', '0/0'),
      'invalid denominator only' => array('3', '0', '3/0'),
    );
  }

  /**
   * @dataProvider csvSafeProvider
   */
  public function testCsvSafe($value, $expected)
  {
    $this->assertSame($expected, csv_safe($value));
  }

  public function csvSafeProvider()
  {
    return array(
      'plain text' => array('Novák', 'Novák'),
      'null' => array(null, null),
      'empty' => array('', ''),
      'formula' => array('=HYPERLINK("http://x")', '\'=HYPERLINK("http://x")'),
      'plus' => array('+1+cmd', '\'+1+cmd'),
      'minus' => array('-2+3', '\'-2+3'),
      'at' => array('@SUM(A1)', '\'@SUM(A1)'),
      'tab' => array("\t=1", "'\t=1"),
      'carriage return' => array("\r=1", "'\r=1"),
      'phone' => array('+420 777 123 456', '+420 777 123 456'),
      'phone with brackets' => array('+420 (777) 123-456', '+420 (777) 123-456'),
      'sign inside' => array('Novák-Svoboda', 'Novák-Svoboda'),
    );
  }

  /**
   * @dataProvider csvLineProvider
   */
  public function testCsvLine($fields, $expected)
  {
    $out = fopen('php://memory', 'w+');
    csv_line($out, $fields);
    rewind($out);

    $this->assertSame($expected, stream_get_contents($out));
  }

  public function csvLineProvider()
  {
    return array(
      'plain' => array(array('1', 'Novák', ''), "1;Novák;\n"),
      'separator and quote' => array(array('a;b', 'say "hi"'), "\"a;b\";\"say \"\"hi\"\"\"\n"),
      // With the escape character \ the quote after a backslash was not doubled and a spreadsheet split the cell.
      'quote after backslash' => array(array('x\\";=2+3;\\"'), "\"x\\\"\";=2+3;\\\"\"\"\n"),
    );
  }

  /**
   * Missing municipality or failed query: null without a notice "array offset on null".
   */
  public function testLookupsWithoutRowReturnNull()
  {
    global $db;

    $db = new class {
      public function queryOneResult($sql)
      {
        return null;
      }
    };

    $this->assertNull(get_municipality_name(999999));
  }

  /**
   * Failed query: the batch lookups of the owners list return an empty map.
   */
  public function testBatchLookupsWithFailedQueryReturnEmpty()
  {
    global $db;

    $db = new class {
      public function query($sql)
      {
        return false;
      }
    };

    $this->assertSame(array(), municipality_names(array(1, 2)));
    $this->assertSame(array(), owners_parcel_counts(array(1, 2)));
    $this->assertSame(array(), owners_parcels(array(1, 2)));
  }

  public function testSqlIntListCastsAndDeduplicates()
  {
    $this->assertSame('1, 0, 5', sql_int_list(array('1', null, '5', 1, "0 or 1=1")));
  }

  /**
   * "V obci": substring match on the municipality name, case-insensitive, with share area.
   */
  public function testParcelsInMunicipality()
  {
    $parcels = array(
      array('municipality_name' => 'Horní Lhota', 'area_m2' => '1000', 'share_num' => '1', 'share_den' => '2'),
      array('municipality_name' => 'LHOTA', 'area_m2' => '300', 'share_num' => '1', 'share_den' => '1'),
      array('municipality_name' => 'Habřiny', 'area_m2' => '5000', 'share_num' => '1', 'share_den' => '1'),
      array('municipality_name' => null, 'area_m2' => null, 'share_num' => '1', 'share_den' => '1'),
      array('municipality_name' => 'Lhota', 'area_m2' => '200', 'share_num' => '1', 'share_den' => '0'),
    );

    $this->assertSame(array('cnt' => 3, 'area' => 800), parcels_in_municipality($parcels, 'lhota'));
    $this->assertSame(array('cnt' => 0, 'area' => 0), parcels_in_municipality(array(), 'Lhota'));
  }

  public function testTrimTextKeepsShortText()
  {
    $this->assertSame('Krátký text', trim_text('Krátký text'));
  }

  public function testTrimTextShortensLongText()
  {
    $this->assertSame('abcde…', trim_text('abcdefghij', 5));
  }

  public function testTrimTextCountsCharactersNotBytes()
  {
    $this->assertSame('žluťoučký', trim_text('žluťoučký', 10));
    $this->assertSame('žluť…', trim_text('žluťoučký', 4));
  }

  /**
   * @dataProvider shareAreaProvider
   */
  public function testShareArea($area, $num, $den, $expected)
  {
    $this->assertSame($expected, share_area($area, $num, $den));
  }

  public function shareAreaProvider()
  {
    return array(
      'whole' => array('2500', '1', '1', 2500),
      'half' => array('2500', '1', '2', 1250),
      'third' => array(300, 1, 3, 100),
      'zero denominator' => array('2500', '1', '0', null),
      'zero denominator and area' => array('0', '1', '0', null),
    );
  }

  public function testGetParamReturnsStringValue()
  {
    $_GET = array('search' => 'Novák');

    $this->assertSame('Novák', get_param('search'));
    $this->assertSame('x', get_param('missing', 'x'));
  }

  public function testGetParamIgnoresArray()
  {
    $_GET = array('search' => array('x'), 'url' => array('owners'));

    $this->assertNull(get_param('search'));
    $this->assertSame('', get_param('url', ''));
  }

  public function testPostParamIgnoresArray()
  {
    $_POST = array('title' => array('x'), 'last_name' => 'Novák');

    $this->assertSame('', post_param('title', ''));
    $this->assertSame('Novák', post_param('last_name'));
  }

  public function testPostHasArray()
  {
    $_POST = array('title' => 'Ing.', 'note' => array('x'));

    $this->assertTrue(post_has_array(array('title', 'note')));
    $this->assertFalse(post_has_array(array('title', 'missing')));
  }

  public function testCsrfTokenIsStableInSession()
  {
    $_SESSION = array();
    $token = csrf_token();

    $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    $this->assertSame($token, csrf_token());
    $this->assertSame('<input type="hidden" name="csrf_token" value="' . $token . '">', csrf_field());
  }

  public function testCsrfCheckAcceptsSessionToken()
  {
    $_SESSION = array();
    $_POST = array('csrf_token' => csrf_token());

    $this->assertTrue(csrf_check());
  }

  /**
   * @dataProvider csrfInvalidProvider
   */
  public function testCsrfCheckRejectsInvalidToken($post)
  {
    $_SESSION = array();
    csrf_token();
    $_POST = $post;

    $this->assertFalse(csrf_check());
  }

  public function csrfInvalidProvider()
  {
    return array(
      'missing' => array(array()),
      'empty' => array(array('csrf_token' => '')),
      'wrong' => array(array('csrf_token' => str_repeat('0', 64))),
      'array' => array(array('csrf_token' => array('x'))),
    );
  }

  public function testCsrfCheckFailsWithoutSessionToken()
  {
    $_SESSION = array();
    $_POST = array('csrf_token' => '');

    $this->assertFalse(csrf_check());
  }

  /**
   * @dataProvider ownerTypeLabelProvider
   */
  public function testOwnerTypeLabel($type, $lower, $expected)
  {
    $this->assertSame($expected, owner_type_label($type, $lower));
  }

  public function ownerTypeLabelProvider()
  {
    return array(
      'person' => array('F', false, 'Fyzická osoba'),
      'company' => array('P', false, 'Právnická osoba'),
      'unknown' => array('X', false, 'Neurčeno'),
      'null' => array(null, false, 'Neurčeno'),
      'person lower' => array('F', true, 'fyzická osoba'),
      'company lower' => array('P', true, 'právnická osoba'),
    );
  }

  /**
   * @dataProvider ownerNameProvider
   */
  public function testOwnerName($owner, $expected)
  {
    $this->assertSame($expected, owner_name($owner));
  }

  public function ownerNameProvider()
  {
    return array(
      'person with title' => array(array('type' => 'F', 'title' => 'Ing.', 'first_name' => 'Jan', 'last_name' => 'Novák'), 'Ing. Jan Novák'),
      'person without title' => array(array('type' => 'F', 'title' => null, 'first_name' => 'Jana', 'last_name' => 'Svobodová'), 'Jana Svobodová'),
      'person without first name' => array(array('type' => 'F', 'title' => '', 'first_name' => '', 'last_name' => 'Svoboda'), 'Svoboda'),
      'company' => array(array('type' => 'P', 'title' => null, 'first_name' => null, 'last_name' => 'Statek Lhota spol. s r.o.'), 'Statek Lhota spol. s r.o.'),
    );
  }

  public function testMbUcFirst()
  {
    $this->assertSame('Žluťoučký kůň', mb_ucFirst('žluťoučký kůň'));
    $this->assertSame('Orná půda', mb_ucFirst('orná půda'));
    $this->assertSame('', mb_ucFirst(''));
  }

  public function testMbLcFirst()
  {
    $this->assertSame('čERVENÝ', mb_lcFirst('ČERVENÝ'));
    $this->assertSame('fyzická osoba', mb_lcFirst('Fyzická osoba'));
  }

  /**
   * @dataProvider isIcoProvider
   */
  public function testIsIco($value, $expected)
  {
    $this->assertSame($expected, is_ico($value));
  }

  public function isIcoProvider()
  {
    return array(
      'eight digits' => array('01256748', true),
      'surrounding spaces' => array('  01256748 ', true),
      'seven digits' => array('1256748', false),
      'nine digits' => array('012567481', false),
      'letter' => array('0125674a', false),
      'space inside' => array('0125 6748', false),
      'empty' => array('', false),
    );
  }

  public function testSelectOptionsMarksSelectedValue()
  {
    $html = select_options(array('F' => 'Fyzická osoba', 'P' => 'Právnická osoba'), 'P');

    $this->assertSame('<option value="F">Fyzická osoba</option><option value="P" selected>Právnická osoba</option>', $html);
  }

  public function testSelectOptionsComparesAsStrings()
  {
    $html = select_options(array('1' => 'ano', '0' => 'ne'), 0);

    $this->assertSame('<option value="1">ano</option><option value="0" selected>ne</option>', $html);
  }

  public function testSelectOptionsWithoutSelection()
  {
    $this->assertSame('<option value="0">ne</option>', select_options(array('0' => 'ne')));
  }

  public function testSelectOptionsEscapes()
  {
    $html = select_options(array('a"b' => '<b>&</b>'));

    $this->assertSame('<option value="a&quot;b">&lt;b&gt;&amp;&lt;/b&gt;</option>', $html);
  }
}
