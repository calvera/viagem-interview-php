<?php

define('DEBUG', false);

if (DEBUG) error_reporting(E_ALL);
else error_reporting(E_ERROR | E_PARSE);

ini_set('display_errors', 1);
date_default_timezone_set('Europe/Prague');
setlocale(LC_ALL, 'cs_CZ.UTF-8', 'cs_CZ', 'czech');
// Decimal point in float to string like PHP 8 (PHP 7.4 uses the locale's comma).
setlocale(LC_NUMERIC, 'C');

define('ROOT', dirname(dirname(__FILE__)));

define('DB_HOST', getenv('DB_HOST') ? getenv('DB_HOST') : 'db');
define('DB_NAME', 'legacy_crm');
define('DB_USER', 'crm');
define('DB_PASS', 'crm');

define('APP_NAME', 'Evidence vlastníků');
define('APP_VERSION', '2.8.4');
define('APP_USER', 'operator.a');

define('LIST_LIMIT', 50);
define('EXPORT_LIMIT', 20000);

define('LOG_DIR', ROOT . '/logs');
define('REPORT_DIR', ROOT . '/reports');

define('REPORT_PING_URL', 'http://reporting.invalid/api/ping');
define('REPORT_UPLOAD_URL', 'http://reporting.invalid/api/upload');

require_once ROOT . '/includes/loader.php';
