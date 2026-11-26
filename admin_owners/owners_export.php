<?php

require_once ROOT . '/admin_owners/owners_functions.php';

$section = owners_build_section(null, true);
$section->setLimit(EXPORT_LIMIT);

// Do-not-contact owners are left out in SQL, so the limit counts only exported rows.
$skipped = 0;
if (!get_param('all'))
{
  $dnc_section = owners_build_section(null, true);
  $dnc_section->addSQLWhereString("o.do_not_contact = '1'");
  $skipped = $dnc_section->getCount();

  $section->addSQLWhereString("o.do_not_contact <> '1'");
}

$rows = $section->getRows(false);
if (!$rows) $rows = array();

$filename = 'vlastnici_' . date('Ymd_Hi') . '.csv';

while (ob_get_level()) ob_end_clean();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'w');

fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, array('Export vlastníků', date('d.m.Y H:i'), $_SESSION['user']), ';', '"', '\\');

$head = array('ID', 'Typ', 'Titul', 'Jméno', 'Příjmení / název', 'Datum narození', 'IČO', 'Ulice', 'PSČ', 'Město',
  'Obec (KN)', 'Telefon', 'E-mail', 'Termín oslovení', 'Založeno');
fputcsv($out, $head, ';', '"', '\\');

$exported = 0;

foreach ($rows as $r)
{
  if ((int)$r['duedate'] == 0) $duedate = '';
  else $duedate = date('d.m.Y', strtotime($r['duedate']));

  $line = array(
    $r['id'],
    owner_type_label($r['type']),
    $r['title'],
    $r['first_name'],
    $r['last_name'],
    $r['birth_date'] ? date('d.m.Y', strtotime($r['birth_date'])) : '',
    $r['ico'],
    $r['street'],
    $r['zip'],
    $r['city'],
    $r['municipality_name'],
    $r['phone'],
    $r['email'],
    $duedate,
    date('d.m.Y H:i', strtotime($r['created_at'])),
  );

  fputcsv($out, $line, ';', '"', '\\');
  $exported++;
}

if ($skipped)
{
  fputcsv($out, array(), ';', '"', '\\');
  fputcsv($out, array('Vynecháno ' . $skipped . ' vlastníků se zákazem oslovení.'), ';', '"', '\\');
}

fclose($out);

app_log('export.log', $_SESSION['user'] . ' exportoval ' . $exported . ' vlastníků (' . $_SERVER['QUERY_STRING'] . ')');

exit;
