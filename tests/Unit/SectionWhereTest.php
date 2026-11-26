<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/class/Database.php';
require_once dirname(__DIR__, 2) . '/class/Section.php';

if (!defined('LIST_LIMIT')) define('LIST_LIMIT', 50);

/**
 * Section filters and search are bound as parameters, never put into the SQL text (no DB connection).
 */
class SectionWhereTest extends TestCase
{
  const INJECTION = "x' or '1'='1";

  private $db;

  protected function setUp(): void
  {
    global $db;

    $db = new Database();
    $this->db = $db;
    $_GET = array();
  }

  protected function tearDown(): void
  {
    $_GET = array();
  }

  public function testEqualsFilterIsBound()
  {
    $_GET = array('f_type' => self::INJECTION);
    $section = new Section('owners');
    $section->addFilter('type', 'Typ', 'select', 'o.type = {value}');

    $this->assertSame(' where o.type = :f_type', $section->getWhere());
    $this->assertSame(array(':f_type' => self::INJECTION), $this->db->bind_params);
  }

  public function testLikeFilterIsBound()
  {
    $_GET = array('f_municipality' => self::INJECTION);
    $section = new Section('owners');
    $section->addFilter('municipality', 'Obec', 'autocomplete', "m.name like concat('%', {value}, '%')");

    $this->assertSame(" where m.name like concat('%', :f_municipality, '%')", $section->getWhere());
    $this->assertSame(array(':f_municipality' => self::INJECTION), $this->db->bind_params);
  }

  public function testOperatorInValueStaysInParameter()
  {
    $_GET = array('f_district' => "x' or 1=1 or district <> 'y");
    $section = new Section('municipalities');
    $section->addFilter('district', 'Okres', 'select', 'district = {value}');

    $this->assertSame(' where district = :f_district', $section->getWhere());
    $this->assertSame(array(':f_district' => "x' or 1=1 or district <> 'y"), $this->db->bind_params);
  }

  public function testEmptyFilterIsSkipped()
  {
    $section = new Section('owners');
    $section->addFilter('type', 'Typ', 'select', 'o.type = {value}');

    $this->assertSame('', $section->getWhere());
    $this->assertSame(array(), $this->db->bind_params);
  }

  public function testSearchIsBoundWithWildcards()
  {
    $section = new Section('owners');
    $section->setSearch(array('o.last_name', 'o.first_name'), self::INJECTION);

    $this->assertSame(' where (o.last_name like :search or o.first_name like :search_2)', $section->getWhere());
    $this->assertSame(array(':search' => '%' . self::INJECTION . '%', ':search_2' => '%' . self::INJECTION . '%'), $this->db->bind_params);
  }

  public function testCodeConditionAndFilterTogether()
  {
    $_GET = array('f_type' => 'P');
    $section = new Section('owners');
    $section->addSQLWhereString("o.do_not_contact <> '1'");
    $section->addFilter('type', 'Typ', 'select', 'o.type = {value}');

    $this->assertSame(' where o.do_not_contact <> :o_do_not_contact and o.type = :f_type', $section->getWhere());
    $this->assertSame(array(':f_type' => 'P', ':o_do_not_contact' => '1'), $this->db->bind_params);
  }

  public function testFiltersAndSearchAreEscapedInHtml()
  {
    $_GET = array('url' => 'owners', 'f_municipality' => '"><script>x</script>');
    $section = new Section('owners');
    $section->addFilter('municipality', 'Obec', 'autocomplete', "m.name like concat('%', {value}, '%')");
    $section->setSearch(array('o.last_name'), '<script>y</script>');
    $section->total = 0;

    $html = $section->showFilters() . $section->showInfo();

    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringContainsString('value="&quot;&gt;&lt;script&gt;x&lt;/script&gt;"', $html);
    $this->assertStringContainsString('value="&lt;script&gt;y&lt;/script&gt;"', $html);
    $this->assertStringContainsString('„<b>&lt;script&gt;y&lt;/script&gt;</b>“', $html);
  }

  public function testIntCellFormatsNumberAndNull()
  {
    $section = new Section('owners');
    $col = array('type' => 'int');

    $this->assertSame('1 234', $section->formatCell('parcel_count', $col, array('parcel_count' => '1234')));
    $this->assertSame('0', $section->formatCell('parcel_count', $col, array('parcel_count' => null)));
  }
}
