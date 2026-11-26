<?php

function owners_build_section(?Section $section = null, $for_export = false)
{
  if (!$section) $section = new Section('owners');

  $section->setItemName('vlastníků');
  $section->setTable('owners o');
  $section->setTableId('id');
  $section->setDetailUrl('index.php?url=owners/detail&amp;id=');
  $section->setExportUrl('index.php?url=owners/export');

  $section->addSelect('o.*');

  $section->addHiddenFilter('municipality_id', 'Obec', 'o.municipality_id = {value}', 'select name from municipalities where id = {value}');
  $section->addFilter('municipality', 'Obec', 'autocomplete', "m.name like concat('%', {value}, '%')");
  $section->addFilter('type', 'Typ', 'select', 'o.type = {value}', array('F' => 'Fyzická osoba', 'P' => 'Právnická osoba'));
  $section->addFilter('dnc', 'Neoslovovat', 'select', 'o.do_not_contact = {value}', array('1' => 'ano', '0' => 'ne'));

  if ($section->hasFilter('municipality') || $for_export)
  {
    $section->addJoin('left join municipalities m on m.id = o.municipality_id');
    if ($for_export) $section->addSelect('m.name as municipality_name');
  }

  $search = get_param('search');
  $ico = regex_match('/^\s*(\d{8})\s*$/', $search, 1);
  if ($ico !== false)
  {
    $section->addSQLWhereString("o.ico = '" . $ico . "'");
    $search = '';
  }
  $section->setSearch(array('o.last_name', 'o.first_name'), $search);

  // Columns instead of concat(): the page is read from index idx_owners_name (last_name, first_name, id),
  // id makes the order unique (OPENQUESTIONS.md, question 5). Changing this sort breaks the use of the index.
  // With a search text the optimizer cannot estimate like '%…%' and would read almost the whole index for
  // a rare name; "o.id + 0" gives the same order but cannot come from the index, so the search keeps the
  // scan with filesort (no index hint: it would be an SQL error before the migration has run).
  // Section appends the direction only after the last column, so the default stays 'asc'.
  $id = $section->search_value !== '' ? 'o.id + 0' : 'o.id';
  $section->setSortBy('o.last_name, o.first_name, ' . $id, 'asc');

  return $section;
}

/**
 * True when the list shows the "V obci" column: with the municipality filter by id or by name.
 */
function owners_has_municipality_filter($section)
{
  return $section->hasFilter('municipality_id') || $section->hasFilter('municipality');
}

/**
 * Loads the municipality name, parcel count and (with the municipality filter) the parcels
 * for all owners on the page in one query each, instead of per row.
 * With the filter by id, "V obci" (count and share area) comes from SQL by p.municipality_id;
 * with the filter by name only, the parcels are matched by name substring in owners_list_row().
 */
function owners_list_prepare($rows, $section)
{
  $owner_ids = array_column($rows, 'id');
  $names = municipality_names(array_column($rows, 'municipality_id'));
  $counts = owners_parcel_counts($owner_ids);
  $in = $section->hasFilter('municipality_id') ? owners_parcels_in_municipality($owner_ids, $section->getFilterValue('municipality_id')) : null;
  $parcels = $in === null && $section->hasFilter('municipality') ? owners_parcels($owner_ids) : array();

  foreach ($rows as $i => $r)
  {
    $id = (int)$r['id'];
    $municipality_id = (int)$r['municipality_id'];
    $rows[$i]['_municipality_name'] = isset($names[$municipality_id]) ? $names[$municipality_id] : null;
    $rows[$i]['parcel_count'] = isset($counts[$id]) ? $counts[$id] : '0';
    $rows[$i]['_parcels'] = isset($parcels[$id]) ? $parcels[$id] : array();
    if ($in !== null) $rows[$i]['_in_municipality'] = isset($in[$id]) ? $in[$id] : array('cnt' => 0, 'area' => 0);
  }

  return $rows;
}

function owners_list_row($r, $section)
{
  $html = '<a href="index.php?url=owners/detail&amp;id=' . $r['id'] . '">' . h(owner_name($r)) . '</a>';
  if ($r['do_not_contact'] === '1') $html .= ' <span class="badge badge_red">neoslovovat</span>';
  if (strlen((string)$r['note'])) $html .= ' <span class="badge" title="' . h($r['note']) . '">pozn.</span>';
  $r['owner_html'] = $html;

  $r['type_label'] = owner_type_label($r['type']);
  $r['birth_ico'] = $r['type'] == 'P' ? 'IČO ' . $r['ico'] : format_date($r['birth_date']);

  $r['municipality_html'] = h($r['_municipality_name']);

  if (owners_has_municipality_filter($section))
  {
    $in = isset($r['_in_municipality']) ? $r['_in_municipality'] : parcels_in_municipality($r['_parcels'], $section->getFilterValue('municipality'));
    if ($in['cnt'] > 0) $r['in_municipality'] = $in['cnt'] . ' <small>(' . format_area($in['area']) . ')</small>';
    else $r['in_municipality'] = '<span class="muted">–</span>';
  }

  if ((int)$r['duedate'] == 0)
  {
    $r['duedate_html'] = '<span class="muted">nenastaven</span>';
  }
  else
  {
    $ts = strtotime($r['duedate']);
    $r['duedate_html'] = '<span class="' . ($ts < time() ? 'overdue' : 'planned') . '">' . date('j. n. Y', $ts) . '</span>';
  }

  if (!$r['parcel_count']) $r['_class'] = 'no_parcels';

  return $r;
}
