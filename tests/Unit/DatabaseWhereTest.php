<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/class/Database.php';

/**
 * Database::getWhere() builds the WHERE clause and bind parameters without a DB connection.
 */
class DatabaseWhereTest extends TestCase
{
  private $db;

  protected function setUp(): void
  {
    $this->db = new Database();
  }

  public function testEmptyConditionsGiveEmptyString()
  {
    $this->assertSame('', $this->db->getWhere(array()));
    $this->assertSame(array(), $this->db->bind_params);
  }

  public function testConditionBecomesParameter()
  {
    $this->assertSame(' where o.type = :o_type', $this->db->getWhere(array("o.type = 'F'")));
    $this->assertSame(array(':o_type' => 'F'), $this->db->bind_params);
  }

  public function testConditionsAreJoinedWithAnd()
  {
    $sql = $this->db->getWhere(array("o.type = 'F'", "o.do_not_contact = '0'"));

    $this->assertSame(' where o.type = :o_type and o.do_not_contact = :o_do_not_contact', $sql);
    $this->assertSame(array(':o_type' => 'F', ':o_do_not_contact' => '0'), $this->db->bind_params);
  }

  public function testCustomOperator()
  {
    $this->assertSame(' where id = :id or code = :code', $this->db->getWhere(array('id = 1', 'code = 2'), true, 'or'));
  }

  public function testWithoutWhereKeyword()
  {
    $this->assertSame(' and o.type = :o_type', $this->db->getWhere(array("o.type = 'P'"), false));
  }

  /**
   * @dataProvider operatorProvider
   */
  public function testOperators($condition, $expected)
  {
    $this->assertSame($expected, $this->db->getWhere(array($condition)));
    $this->assertSame(array(':o_id' => '10'), $this->db->bind_params);
  }

  public function operatorProvider()
  {
    return array(
      '>=' => array('o.id >= 10', ' where o.id >= :o_id'),
      '<=' => array('o.id <= 10', ' where o.id <= :o_id'),
      '<>' => array('o.id <> 10', ' where o.id <> :o_id'),
      '!=' => array('o.id != 10', ' where o.id != :o_id'),
      '<' => array('o.id < 10', ' where o.id < :o_id'),
      '>' => array('o.id > 10', ' where o.id > :o_id'),
    );
  }

  /**
   * @dataProvider unchangedProvider
   */
  public function testConditionIsLeftUnchanged($condition)
  {
    $this->assertSame(' where ' . $condition, $this->db->getWhere(array($condition)));
    $this->assertSame(array(), $this->db->bind_params);
  }

  public function unchangedProvider()
  {
    return array(
      'like' => array("m.name like '%Lhota%'"),
      'like with operator' => array("m.name like '%a=b%'"),
      'parentheses' => array("(o.last_name like '%Nov%' or o.first_name like '%Nov%')"),
      'parentheses with operator' => array('(o.id = 1 or o.id = 2)'),
      'no operator' => array('o.ico is null'),
    );
  }

  public function testParameterNameCollision()
  {
    $sql = $this->db->getWhere(array('id = 1', 'id = 2', 'id = 3'));

    $this->assertSame(' where id = :id and id = :id_2 and id = :id_3', $sql);
    $this->assertSame(array(':id' => '1', ':id_2' => '2', ':id_3' => '3'), $this->db->bind_params);
  }

  public function testCollisionWithAlreadyBoundParameter()
  {
    $this->db->bindParam('o_type', 'F');

    $this->assertSame(' where o.type = :o_type_2', $this->db->getWhere(array("o.type = 'P'")));
    $this->assertSame(array(':o_type' => 'F', ':o_type_2' => 'P'), $this->db->bind_params);
  }

  /**
   * @dataProvider quotedValueProvider
   */
  public function testQuotedValueLosesOnlyOuterApostrophes($value, $expected)
  {
    $this->db->getWhere(array('o.last_name = ' . $value));

    $this->assertSame(array(':o_last_name' => $expected), $this->db->bind_params);
  }

  public function quotedValueProvider()
  {
    return array(
      'inner apostrophe' => array("'O'Brien'", "O'Brien"),
      'empty' => array("''", ''),
      'trailing apostrophe' => array("'F''", "F'"),
      'only apostrophe' => array("'''", "'"),
    );
  }
}
