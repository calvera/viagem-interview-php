<?php

switch (URL2)
{
  case 'detail':
    require ROOT . '/admin_owners/owners_detail.php';
    break;

  case 'edit':
    require ROOT . '/admin_owners/owners_edit.php';
    break;

  case 'export':
    require ROOT . '/admin_owners/owners_export.php';
    break;

  default:
    require ROOT . '/admin_owners/owners_section.php';
}
