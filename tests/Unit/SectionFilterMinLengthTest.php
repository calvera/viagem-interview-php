<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/class/Database.php';
require_once dirname(__DIR__, 2) . '/class/Section.php';

if (!defined('LIST_LIMIT')) define('LIST_LIMIT', 50);

/**
 * Section::setFilterMinLength(): a shorter text filter is ignored but stays in the field with a note,
 * and the search placeholder from setSearch() (no DB connection).
 */
class SectionFilterMinLengthTest extends TestCase
{
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

  private function section($value)
  {
    $_GET = array('url' => 'owners', 'f_municipality' => $value);
    $section = new Section('owners');
    $section->addFilter('municipality', 'Obec', 'autocomplete', "m.name like concat('%', {value}, '%')");
    $section->setFilterMinLength('municipality', 2);

    return $section;
  }

  public static function shortValues()
  {
    return array(
      'one letter' => array('a', 'a'),
      'one letter with spaces' => array('  a ', 'a'),
      'one multibyte letter' => array('Ž', 'Ž'),
    );
  }

  /**
   * @dataProvider shortValues
   */
  public function testShortValueIsIgnoredAndShownWithNote($value, $shown)
  {
    $section = $this->section($value);

    $this->assertFalse($section->hasFilter('municipality'));
    $this->assertSame('', $section->getWhere());
    $this->assertSame(array(), $this->db->bind_params);

    $html = $section->showFilters();
    $this->assertStringContainsString('name="f_municipality" value="' . $shown . '" data-autocomplete="municipality" autocomplete="off"> <small class="filter_note">zadejte aspoň 2 znaky</small></label>', $html);
  }

  public static function longValues()
  {
    return array(
      'two letters' => array('Lh'),
      'two multibyte letters' => array('Žď'),
      'name' => array('Lhota'),
    );
  }

  /**
   * @dataProvider longValues
   */
  public function testValueOfMinLengthFilters($value)
  {
    $section = $this->section($value);

    $this->assertTrue($section->hasFilter('municipality'));
    $this->assertSame(" where m.name like concat('%', :f_municipality, '%')", $section->getWhere());
    $this->assertSame(array(':f_municipality' => $value), $this->db->bind_params);
    $this->assertStringNotContainsString('filter_note', $section->showFilters());
  }

  public function testEmptyValueHasNoNote()
  {
    $section = $this->section('');

    $this->assertFalse($section->hasFilter('municipality'));
    $this->assertStringNotContainsString('filter_note', $section->showFilters());
  }

  public function testSearchWithoutPlaceholder()
  {
    $section = new Section('owners');
    $section->setSearch(array('o.last_name'), '');

    $this->assertStringContainsString('<input type="text" name="search" value=""></label>', $section->showFilters());
  }

  public function testSearchPlaceholderIsEscaped()
  {
    $section = new Section('parcels');
    $section->setSearch(array('p.parcel_number'), '', 'číslo "parcely"');

    $this->assertStringContainsString('<input type="text" name="search" value="" placeholder="číslo &quot;parcely&quot;"></label>', $section->showFilters());
  }
}
