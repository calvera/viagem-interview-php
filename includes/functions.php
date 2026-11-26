<?php

function h($s)
{
  return @htmlspecialchars($s === null ? '' : $s, ENT_QUOTES, 'UTF-8');
}

/**
 * GET parameter, or $default when missing or an array (no parameter of the app is an array).
 */
function get_param($name, $default = null)
{
  if (isset($_GET[$name]) && !is_array($_GET[$name])) return $_GET[$name];
  return $default;
}

/**
 * POST parameter, or $default when missing or an array.
 */
function post_param($name, $default = null)
{
  if (isset($_POST[$name]) && !is_array($_POST[$name])) return $_POST[$name];
  return $default;
}

/**
 * True when any of the POST fields was sent as an array (a forged request, the forms send strings).
 */
function post_has_array($names)
{
  foreach ($names as $name)
  {
    if (isset($_POST[$name]) && is_array($_POST[$name])) return true;
  }
  return false;
}

/**
 * CSRF token of the session, created on first use.
 */
function csrf_token()
{
  if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf_token'];
}

/**
 * Hidden form field with the CSRF token.
 */
function csrf_field()
{
  return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * True when the POSTed CSRF token matches the session token.
 */
function csrf_check()
{
  if (empty($_SESSION['csrf_token'])) return false;
  return hash_equals($_SESSION['csrf_token'], (string)post_param('csrf_token'));
}

function redirect($url)
{
  header('Location: ' . $url);
  exit;
}

function url($params = array(), $keep_current = false)
{
  $query = $keep_current ? $_GET : array();
  foreach ($params as $key => $val)
  {
    if ($val === null) unset($query[$key]);
    else $query[$key] = $val;
  }
  if (!count($query)) return 'index.php';
  return 'index.php?' . h(http_build_query($query));
}

function regex_match($pattern, $subject, $group = 0)
{
  if (!preg_match($pattern, (string)$subject, $m)) return false;
  return isset($m[$group]) ? $m[$group] : false;
}

// PHP 8.4+ has mb_ucfirst() and mb_lcfirst() built in (function names are case-insensitive).
if (!function_exists('mb_ucfirst'))
{
  function mb_ucFirst($s)
  {
    return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');
  }
}

if (!function_exists('mb_lcfirst'))
{
  function mb_lcFirst($s)
  {
    return mb_strtolower(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');
  }
}

function is_ico($s)
{
  return regex_match('/^\d{8}$/', trim($s)) !== false;
}

function format_date($date, $empty = '–')
{
  if (!$date || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') return $empty;
  return date('j. n. Y', strtotime($date));
}

function format_datetime($date, $empty = '–')
{
  if (!$date || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') return $empty;
  return date('j. n. Y H:i', strtotime($date));
}

function format_area($m2)
{
  if ($m2 >= 10000) return number_format($m2 / 10000, 2, ',', ' ') . ' ha';
  return number_format((float)$m2, 0, ',', ' ') . ' m²';
}

/**
 * Share as "num/den", "celý" for a whole share (an invalid denominator 0 is never whole).
 */
function format_share($num, $den)
{
  if ($den != 0 && $num == $den) return 'celý';
  return $num . '/' . $den;
}

/**
 * Area of an ownership share, null for an invalid share with denominator 0.
 */
function share_area($area, $num, $den)
{
  if (!$den) return null;
  return $area * $num / $den;
}

function trim_text($s, $length = 80)
{
  if (mb_strlen($s, 'UTF-8') <= $length) return $s;
  return mb_substr($s, 0, $length, 'UTF-8') . '…';
}

/**
 * CSV cell that a spreadsheet will not evaluate as a formula: text starting with = + - @, tab or CR
 * gets a leading apostrophe. Phone-like numbers (+420 777 123 456) stay unchanged.
 */
function csv_safe($value)
{
  if (!preg_match('/^[=+\-@\t\r]/', (string)$value)) return $value;
  if (preg_match('/^\+?[0-9 ()-]+$/', $value)) return $value;
  return "'" . $value;
}

/**
 * Write one CSV line separated by ";". No escape character (RFC 4180, like spreadsheets):
 * every quote is doubled, also after a backslash.
 */
function csv_line($out, $fields)
{
  fputcsv($out, $fields, ';', '"', '');
}

function owner_type_label($type, $lower = false)
{
  switch ($type)
  {
    case 'F':
      $label = 'Fyzická osoba';
      break;
    case 'P':
      $label = 'Právnická osoba';
      break;
    default:
      $label = 'Neurčeno';
  }

  if ($lower) $label = mb_lcFirst($label);

  return $label;
}

function owner_name($r)
{
  if ($r['type'] == 'P') return $r['last_name'];
  return trim($r['title'] . ' ' . $r['first_name'] . ' ' . $r['last_name']);
}

function get_municipality_name($id)
{
  global $db;

  $r = $db->queryOneResult("select name from municipalities where id = " . intval($id));
  return $r ? $r['name'] : null;
}

/**
 * Comma separated integer ids for "in (...)".
 */
function sql_int_list($ids)
{
  return implode(', ', array_unique(array_map('intval', $ids)));
}

/**
 * Municipality names by id in one query; missing municipalities are left out.
 */
function municipality_names($ids)
{
  global $db;

  $names = array();
  $res = $db->query("select id, name from municipalities where id in (" . sql_int_list($ids) . ")");
  if (!$res) return $names;

  while ($r = $res->fetch_assoc()) $names[$r['id']] = $r['name'];

  return $names;
}

/**
 * Parcel (ownership) counts by owner id in one query; owners without parcels are left out.
 */
function owners_parcel_counts($owner_ids)
{
  global $db;

  $counts = array();
  $res = $db->query("select owner_id, count(*) as cnt from ownerships
    where owner_id in (" . sql_int_list($owner_ids) . ") group by owner_id");
  if (!$res) return $counts;

  while ($r = $res->fetch_assoc()) $counts[$r['owner_id']] = $r['cnt'];

  return $counts;
}

/**
 * Parcels of the owners with share and municipality name, grouped by owner id, in one query.
 * Each owner's parcels are in the same order as in owner_parcels().
 */
function owners_parcels($owner_ids)
{
  global $db;

  $list = array();
  $res = $db->query("select os.owner_id, os.share_num, os.share_den, p.area_m2, m.name as municipality_name
    from ownerships os
    left join parcels p on p.id = os.parcel_id
    left join municipalities m on m.id = p.municipality_id
    where os.owner_id in (" . sql_int_list($owner_ids) . ")
    order by os.owner_id, m.name, p.cadastral_area, p.parcel_number");
  if (!$res) return $list;

  while ($r = $res->fetch_assoc()) $list[$r['owner_id']][] = $r;

  return $list;
}

/**
 * Owners of the parcels with share and LV number, grouped by parcel id, in one query.
 * Each parcel's owners are ordered by last name, as in the parcels list before.
 */
function parcels_owners($parcel_ids)
{
  global $db;

  $list = array();
  $res = $db->query("select os.parcel_id, o.id, o.type, o.title, o.first_name, o.last_name, os.share_num, os.share_den, os.lv_number
    from ownerships os
    left join owners o on o.id = os.owner_id
    where os.parcel_id in (" . sql_int_list($parcel_ids) . ")
    order by os.parcel_id, o.last_name");
  if (!$res) return $list;

  while ($r = $res->fetch_assoc()) $list[$r['parcel_id']][] = $r;

  return $list;
}

/**
 * Count and share area of the parcels whose municipality name contains $municipality (case-insensitive).
 */
function parcels_in_municipality($parcels, $municipality)
{
  $result = array('cnt' => 0, 'area' => 0);

  foreach ($parcels as $p)
  {
    if (mb_stripos((string)$p['municipality_name'], $municipality, 0, 'UTF-8') === false) continue;
    $result['cnt']++;
    $result['area'] += share_area($p['area_m2'], $p['share_num'], $p['share_den']);
  }

  return $result;
}

function owner_parcels($owner_id)
{
  global $db;

  $list = array();

  $res = $db->query("select os.id as ownership_id, os.share_num, os.share_den, os.lv_number,
      p.id, p.cadastral_area, p.cadastral_code, p.parcel_number, p.area_m2, p.land_type,
      m.name as municipality_name
    from ownerships os
    left join parcels p on p.id = os.parcel_id
    left join municipalities m on m.id = p.municipality_id
    where os.owner_id = " . intval($owner_id) . "
    order by m.name, p.cadastral_area, p.parcel_number");

  if (!$res) return $list;

  while ($r = $res->fetch_assoc())
  {
    $list[] = $r;
  }

  return $list;
}

function app_log($file, $msg)
{
  $line = date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL;
  @file_put_contents(LOG_DIR . '/' . $file, $line, FILE_APPEND);
}

function log_access($page)
{
  global $db;

  $user = isset($_SESSION['user']) ? $_SESSION['user'] : '';
  $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';

  $db->bindParams(array('page' => substr($page, 0, 255), 'ip' => $ip, 'user_name' => $user));
  $db->query("insert into access_log (page, ip, user_name, created_at) values (:page, :ip, :user_name, now())");
}

function flash_set($msg, $type = 'ok')
{
  $_SESSION['flash'][] = array('msg' => $msg, 'type' => $type);
}

function flash_show()
{
  if (empty($_SESSION['flash'])) return '';

  $html = '';
  foreach ($_SESSION['flash'] as $f)
  {
    $html .= '<div class="flash flash_' . $f['type'] . '">' . h($f['msg']) . '</div>';
  }
  unset($_SESSION['flash']);

  return $html;
}

function select_options($options, $selected = null)
{
  $html = '';
  foreach ($options as $key => $title)
  {
    $html .= '<option value="' . h($key) . '"' . ((string)$key === (string)$selected ? ' selected' : '') . '>' . h($title) . '</option>';
  }
  return $html;
}
