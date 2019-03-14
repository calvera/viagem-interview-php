<?php
require_once ROOT . '/plugin/minify/Minify.php';
?><!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo h($page_title) ?> | <?php echo APP_NAME ?></title>
<style><?php echo Minify::file(ROOT . '/css/style.css') ?></style>
</head>
<body>

<div id="header">
  <a href="index.php" class="logo"><?php echo APP_NAME ?></a>
  <ul id="menu">
<?php foreach ($menu as $key => $title) { ?>
    <li<?php if ($key == URL1) echo ' class="active"' ?>><a href="index.php?url=<?php echo $key ?>"><?php echo $title ?></a></li>
<?php } ?>
  </ul>
  <span class="user">Přihlášen: <b><?php echo h($_SESSION['user']) ?></b></span>
</div>

<div id="main_content">
