<?php

class QueryResult
{
  var $stmt;
  var $num_rows;

  function __construct($stmt)
  {
    $this->stmt = $stmt;
    $this->num_rows = $stmt->rowCount();
  }

  function fetch_assoc()
  {
    return $this->stmt->fetch(PDO::FETCH_ASSOC);
  }

  function fetch_row()
  {
    return $this->stmt->fetch(PDO::FETCH_NUM);
  }

  function fetch_all()
  {
    return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}

class Database
{
  var $pdo;
  var $bind_params = array();
  var $query_count = 0;
  var $connected;
  var $sql;
  var $last_id;
  var $affected_rows;
  var $last_error;

  function connect()
  {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    // PHP 8.5 deprecates PDO::MYSQL_ATTR_INIT_COMMAND, its replacement does not exist in 7.4.
    $init_command = defined('Pdo\Mysql::ATTR_INIT_COMMAND') ? constant('Pdo\Mysql::ATTR_INIT_COMMAND') : constant('PDO::MYSQL_ATTR_INIT_COMMAND');

    $options = array(
      $init_command => "SET NAMES utf8mb4 COLLATE utf8mb4_czech_ci, SESSION sql_mode = ''",
      PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
      PDO::ATTR_EMULATE_PREPARES => true,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_STRINGIFY_FETCHES => true,
    );

    try
    {
      $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    catch (PDOException $e)
    {
      app_log('db_error.log', 'Connect: ' . $e->getMessage());
      $this->connected = false;
      return false;
    }

    $this->connected = true;
    return true;
  }

  function isConnected()
  {
    return $this->connected;
  }

  function bindParam($id, $val)
  {
    $orig_id = $id;
    $i = 2;

    while (isset($this->bind_params[':' . $id]))
    {
      $id = $orig_id . '_' . $i;
      $i++;
    }

    $this->bind_params[':' . $id] = $val;

    return $id;
  }

  function bindParams($arr)
  {
    if (!is_array($arr)) return false;
    foreach ($arr as $key => $val) $this->bindParam($key, $val);
    return true;
  }

  function unbindParams()
  {
    $this->bind_params = array();
  }

  function query($sql)
  {
    $this->sql = $sql;
    $this->query_count++;
    $start = microtime(true);

    $stmt = $this->pdo->prepare($sql);
    if (!$stmt)
    {
      $this->error($sql, $this->pdo->errorInfo());
      $this->bind_params = array();
      return false;
    }

    $params = $this->bind_params;
    $this->bind_params = array();

    if (!$stmt->execute($params))
    {
      $this->error($sql, $stmt->errorInfo());
      return false;
    }

    $time = microtime(true) - $start;
    if ($time > 2)
    {
      app_log('slow_query.log', round($time, 3) . 's ' . preg_replace('/\s+/', ' ', $sql));
    }

    if (stripos(ltrim($sql), 'insert') === 0)
    {
      $this->last_id = $this->pdo->lastInsertId();
    }

    $this->affected_rows = $stmt->rowCount();

    return new QueryResult($stmt);
  }

  function error($sql, $info)
  {
    $this->last_error = $info[2];
    app_log('sql_error.log', $info[2] . ' | ' . preg_replace('/\s+/', ' ', $sql));

    if (DEBUG)
    {
      echo '<pre class="sql_error">' . h($info[2]) . "\n" . h($sql) . '</pre>';
    }
  }

  function queryResult($sql)
  {
    $result = array();
    $res = $this->query($sql);
    if (!$res) return $result;

    while ($r = $res->fetch_assoc())
    {
      $result[] = $r;
    }
    return $result;
  }

  function queryOneResult($sql)
  {
    $res = $this->query($sql);
    if (!$res) return null;

    $r = $res->fetch_assoc();
    if (!$r) return null;

    return $r;
  }

  function queryValue($sql)
  {
    $r = $this->queryOneResult($sql);
    if (!$r) return null;
    return reset($r);
  }

  function getById($id, $table, $id_name = 'id')
  {
    $bind = $this->bindParam($id_name, intval($id));
    return $this->queryOneResult("select * from " . $table . " where " . $id_name . " = :" . $bind . " limit 1");
  }

  function getCount($table, $where = array(), $join = '')
  {
    $sql = "select count(*) as pocet from " . $table . " " . $join . " " . $this->getWhere($where);
    $r = $this->queryOneResult($sql);
    return $r['pocet'];
  }

  function getWhere($where, $show_where = true, $operator = 'and')
  {
    if (!is_array($where) || !count($where)) return '';

    $exit = $show_where ? ' where' : ' and';
    $operators = array('<>', '!=', '>=', '<=', '=', '<', '>');

    $i = 1;
    foreach ($where as $val)
    {
      $val_operator = '';
      foreach ($operators as $op)
      {
        if (strpos($val, $op) !== false)
        {
          $val_operator = $op;
          break;
        }
      }

      if ($val_operator && strpos($val, '(') === false && stripos($val, ' like ') === false)
      {
        $valarr = explode($val_operator, $val, 2);
        $val_id = trim($valarr[0]);
        $val_val = trim($valarr[1]);

        // Already bound (e.g. a Section filter): keep the placeholder as it is.
        if (!preg_match('/^:\w+$/', $val_val))
        {
          if (substr($val_val, 0, 1) == "'") $val_val = preg_replace("/^'|'$/", '', $val_val);

          $bind = $this->bindParam(str_replace('.', '_', $val_id), $val_val);
          $val = $val_id . ' ' . $val_operator . ' :' . $bind;
        }
      }

      if ($i != 1) $exit .= ' ' . $operator;
      $exit .= ' ' . $val;

      $i++;
    }

    return $exit;
  }

  static function escape($val)
  {
    return db_escape($val);
  }

  function prepareData($data)
  {
    $prepared = array();
    foreach ($data as $column => $value)
    {
      $prepared[$column] = "'" . $this->escape(trim($value)) . "'";
    }
    return $prepared;
  }

  function insert($data, $table)
  {
    $cols = array();
    $values = array();

    foreach ($data as $key => $val)
    {
      $cols[] = '`' . $key . '`';
      $values[] = ':' . $this->bindParam($key, $val);
    }

    $res = $this->query("insert into " . $table . " (" . implode(', ', $cols) . ") values (" . implode(', ', $values) . ")");
    if (!$res) return false;

    return $this->last_id;
  }

  function update($data, $table, $id_name)
  {
    $set = array();
    foreach ($data as $key => $val)
    {
      if ($key == $id_name) continue;
      $set[] = '`' . $key . '` = :' . $this->bindParam($key, $val);
    }

    $bind = $this->bindParam($id_name, $data[$id_name]);

    return $this->query("update " . $table . " set " . implode(', ', $set) . " where `" . $id_name . "` = :" . $bind);
  }

  function delete($table, $id_name, $id)
  {
    return $this->query("delete from " . $table . " where " . $id_name . " = " . $id . " limit 1");
  }

  function getId()
  {
    return $this->last_id;
  }

  function getAffectedRows()
  {
    return $this->affected_rows;
  }

  function getLastError()
  {
    return $this->last_error;
  }
}
