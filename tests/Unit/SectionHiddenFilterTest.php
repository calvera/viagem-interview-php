<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/class/Database.php';
require_once dirname(__DIR__, 2) . '/class/Section.php';

if (!defined('LIST_LIMIT')) define('LIST_LIMIT', 50);

/**
 * Database that records each query with its bound parameters and returns a fixed label (no DB connection).
 */
class LabelDatabase extends Database
{
  public $label = 'Lhota';
  public $queries = array();

  function query($sql)
  {
    $this->queries[] = array($sql, $this->bind_params);
    $this->bind_params = array();

    $row = $this->label === null ? false : array('name' => $this->label);
    return new class($row) {
      private $row;

      function __construct($row) { $this->row = $row; }

      function fetch_assoc() { return $this->row; }
    };
  }
}

/**
 * Section::addHiddenFilter(): a filter set by a link (f_municipality_id), digits only, bound as a parameter,
 * shown as an escaped label with a remove link and kept as a hidden input.
 */
class SectionHiddenFilterTest extends TestCase
{
  private $db;

  protected function setUp(): void
  {
    global $db;

    $db = new LabelDatabase();
    $this->db = $db;
    $_GET = array();
  }

  protected function tearDown(): void
  {
    $_GET = array();
  }

  private function section()
  {
    $section = new Section('owners');
    $section->addHiddenFilter('municipality_id', 'Obec', 'o.municipality_id = {value}', 'select name from municipalities where id = {value}');
    $section->addFilter('type', 'Typ', 'select', 'o.type = {value}', array('F' => 'Fyzická osoba'));
    $section->total = 0;

    return $section;
  }

  public function testValueIsBound()
  {
    $_GET = array('url' => 'owners', 'f_municipality_id' => '12');
    $section = $this->section();

    $this->assertTrue($section->hasFilter('municipality_id'));
    $this->assertSame(' where o.municipality_id = :f_municipality_id', $section->getWhere());
    $this->assertSame(array(':f_municipality_id' => '12'), $this->db->bind_params);
  }

  /**
   * @dataProvider invalidProvider
   */
  public function testOnlyDigitsAreAccepted($value)
  {
    $_GET = array('url' => 'owners', 'f_municipality_id' => $value);
    $section = $this->section();

    $this->assertFalse($section->hasFilter('municipality_id'));
    $this->assertSame('', $section->getWhere());
    $this->assertSame(array(), $this->db->bind_params);
    $this->assertStringNotContainsString('hidden_filter', $section->showFilters());
    $this->assertSame(array(), $this->db->queries);
  }

  public function invalidProvider()
  {
    return array(
      'empty' => array(''),
      'text' => array('abc'),
      'injection' => array("1 or 1=1"),
      'negative' => array('-1'),
      'decimal' => array('1.5'),
      'leading space' => array(' 1'),
      'trailing newline' => array("1\n"),
      'array' => array(array('1')),
    );
  }

  public function testLabelIsLoadedByBoundQuery()
  {
    $_GET = array('url' => 'owners', 'f_municipality_id' => '12');
    $this->section()->showFilters();

    $this->assertSame(array(array('select name from municipalities where id = :f_municipality_id', array(':f_municipality_id' => '12'))), $this->db->queries);
  }

  public function testLabelIsEscaped()
  {
    $_GET = array('url' => 'owners', 'f_municipality_id' => '12');
    $this->db->label = '<script>x</script> & "Lhota"';

    $html = $this->section()->showFilters();

    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringContainsString('<span class="hidden_filter">Obec: <b>&lt;script&gt;x&lt;/script&gt; &amp; &quot;Lhota&quot;</b> ', $html);
  }

  public function testMissingLabelShowsValue()
  {
    $_GET = array('url' => 'owners', 'f_municipality_id' => '999999');
    $this->db->label = null;

    $this->assertStringContainsString('Obec: <b>999999</b>', $this->section()->showFilters());
  }

  public function testRemoveLinkDropsThisParameterAndPage()
  {
    $_GET = array('url' => 'owners', 'f_municipality_id' => '12', 'f_type' => 'F', 'search' => 'Nová', 'page' => '2');

    $html = $this->section()->showFilters();

    $this->assertStringContainsString('<a href="index.php?url=owners&amp;f_type=F&amp;search=Nov%C3%A1" title="zrušit">×</a></span>', $html);
  }

  public function testValueIsKeptAsHiddenInput()
  {
    $_GET = array('url' => 'owners', 'f_municipality_id' => '12');

    $html = $this->section()->showFilters();

    $this->assertStringContainsString('<input type="hidden" name="f_municipality_id" value="12">', $html);
    $this->assertLessThan(strpos($html, '<button type="submit">'), strpos($html, 'name="f_municipality_id"'));
  }

  public function testWithoutValueFormIsUnchanged()
  {
    $_GET = array('url' => 'owners');
    $with = $this->section()->showFilters();

    $section = new Section('owners');
    $section->addFilter('type', 'Typ', 'select', 'o.type = {value}', array('F' => 'Fyzická osoba'));
    $without = $section->showFilters();

    $this->assertSame($without, $with);
  }
}
