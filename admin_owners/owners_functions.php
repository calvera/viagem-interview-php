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

  $section->setSortBy("concat(o.last_name, ' ', o.first_name)", 'asc');

  return $section;
}

/**
 * Loads the municipality name, parcel count and (with the municipality filter) the parcels
 * for all owners on the page in one query each, instead of per row.
 */
function owners_list_prepare($rows, $section)
{
  $owner_ids = array_column($rows, 'id');
  $names = municipality_names(array_column($rows, 'municipality_id'));
  $counts = owners_parcel_counts($owner_ids);
  $parcels = $section->hasFilter('municipality') ? owners_parcels($owner_ids) : array();

  foreach ($rows as $i => $r)
  {
    $id = (int)$r['id'];
    $municipality_id = (int)$r['municipality_id'];
    $rows[$i]['_municipality_name'] = isset($names[$municipality_id]) ? $names[$municipality_id] : null;
    $rows[$i]['parcel_count'] = isset($counts[$id]) ? $counts[$id] : '0';
    $rows[$i]['_parcels'] = isset($parcels[$id]) ? $parcels[$id] : array();
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

  if ($section->hasFilter('municipality'))
  {
    $in = parcels_in_municipality($r['_parcels'], $section->getFilterValue('municipality'));
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
