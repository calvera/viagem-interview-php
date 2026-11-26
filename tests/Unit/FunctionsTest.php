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
    );
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
