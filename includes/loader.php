<?php

require_once ROOT . '/includes/functions.php';
require_once ROOT . '/class/Database.php';
require_once ROOT . '/class/Section.php';

if (PHP_SAPI != 'cli')
{
  session_start();
  if (!isset($_SESSION['user'])) $_SESSION['user'] = APP_USER;
}

global $db;
$db = new Database();
if (!$db->connect())
{
  if (PHP_SAPI == 'cli')
  {
    echo "Nepodařilo se připojit k databázi.\n";
    exit(1);
  }
  header('HTTP/1.1 503 Service Unavailable');
  echo '<h1>Databáze není dostupná</h1><p>Zkuste to prosím za chvíli znovu.</p>';
  exit;
}

if (PHP_SAPI == 'cli')
{
  $request_page = 'cli:' . basename($_SERVER['SCRIPT_NAME']);
}
else
{
  $request_page = $_SERVER['REQUEST_URI'];
}

log_access($request_page);
