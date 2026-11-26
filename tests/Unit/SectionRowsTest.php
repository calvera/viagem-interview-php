<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/class/Database.php';
require_once dirname(__DIR__, 2) . '/class/Section.php';

if (!defined('LIST_LIMIT')) define('LIST_LIMIT', 50);

/**
 * Database returning fixed rows instead of querying (no DB connection).
 */
class FixedRowsDatabase extends Database
{
  public $rows = array();

  function query($sql)
  {
    $rows = $this->rows;
    return new class($rows) {
      private $rows;

      function __construct($rows) { $this->rows = $rows; }

      function fetch_assoc() { return array_shift($this->rows); }
    };
  }
}

/**
 * Section::getRows(): the rows prepare function runs once with all rows, before the row function.
 */
class SectionRowsTest extends TestCase
{
  public static $calls = array();

  private $db;

  protected function setUp(): void
  {
    global $db;

    $db = new FixedRowsDatabase();
    $this->db = $db;
    $_GET = array();
    self::$calls = array();
  }

  protected function tearDown(): void
  {
    $_GET = array();
  }

  private function section()
  {
    $section = new Section('test');
    $section->setTable('t');
    $section->setRowsPrepareFunction(function ($rows, $section) {
      SectionRowsTest::$calls[] = 'prepare:' . implode(',', array_column($rows, 'id'));
      foreach ($rows as $i => $r) $rows[$i]['prepared'] = 'p' . $r['id'];
      return $rows;
    });
    $section->setRowShowFunction(function ($r, $section) {
      SectionRowsTest::$calls[] = 'row:' . $r['id'] . ':' . $r['prepared'];
      $r['shown'] = true;
      return $r;
    });

    return $section;
  }

  public function testPrepareRunsOnceWithAllRowsBeforeRowFunction()
  {
    $this->db->rows = array(array('id' => '1'), array('id' => '2'), array('id' => '3'));

    $rows = $this->section()->getRows();

    $this->assertSame(array('prepare:1,2,3', 'row:1:p1', 'row:2:p2', 'row:3:p3'), self::$calls);
    $this->assertSame(array('id' => '2', 'prepared' => 'p2', 'shown' => true), $rows[1]);
  }

  public function testPrepareIsSkippedForEmptyPage()
  {
    $this->db->rows = array();

    $this->assertSame(array(), $this->section()->getRows());
    $this->assertSame(array(), self::$calls);
  }

  public function testRowFunctionWithoutPrepare()
  {
    $this->db->rows = array(array('id' => '1'));
    $section = new Section('test');
    $section->setTable('t');
    $section->setRowShowFunction(function ($r, $section) {
      $r['shown'] = true;
      return $r;
    });

    $this->assertSame(array(array('id' => '1', 'shown' => true)), $section->getRows());
  }
}
