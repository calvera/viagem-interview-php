<?php

/*
 * Common bootstrap for all suites. It loads no application code on purpose:
 * includes/functions.php does not parse on PHP 8 yet (function match()), so
 * only the unit tests load it and the smoke suite runs on any PHP version.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('Europe/Prague');
