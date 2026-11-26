<?php

/*
 * Common bootstrap for all suites. It loads no application code on purpose:
 * only the unit tests load includes/functions.php, so the smoke suite does not
 * depend on the application code and runs on any PHP version.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('Europe/Prague');
