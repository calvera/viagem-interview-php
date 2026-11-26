<?php

/*
 * Constants PHPStan cannot discover: ROOT is defined in includes/config.php,
 * which also connects to the database, so it cannot be loaded here.
 */

define('ROOT', dirname(__DIR__));
