<?php
// includes/header.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/security.php';
send_html_security_headers();

$site_name = $site_name ?? 'Banten-Exploiter';
$logo_url  = $logo_url  ?? '/assets/logo.png';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<base href="/">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?= e($title ?? 'Home') ?> — <?= e($site_name) ?></title>
<meta name="description" content="<?= e($meta_desc ?? 'Global Cyber Vandalism Mirror Database') ?>">
<meta name="keywords" content="<?= e($meta_kw ?? 'Banten-Exploiter, deface, defacer, cyber vandalism, mirror database, hacked, hacker') ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($site_name) ?>">
<meta property="og:title" content="<?= e($title ?? 'Home') ?> — <?= e($site_name) ?>">
<meta property="og:description" content="<?= e($meta_desc ?? 'Global Cyber Vandalism Mirror Database') ?>">
<meta property="og:image" content="<?= e($logo_url) ?>">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="<?= e($title ?? 'Home') ?> — <?= e($site_name) ?>">
<meta name="twitter:description" content="<?= e($meta_desc ?? 'Global Cyber Vandalism Mirror Database') ?>">
<link rel="icon" type="image/png" href="<?= e($logo_url) ?>">
<link rel="stylesheet" href="/assets/css/style.css?v=5">
</head>
<body>
<div class="container">

  <header class="site-header">
    <a class="logo-link" href="/index.php" title="<?= e($site_name) ?>">
      <img src="<?= e($logo_url) ?>" alt="<?= e($site_name) ?>" class="logo">
    </a>
    <form action="/search.php" method="get" class="searchs">
      <input type="text" name="q" placeholder="Search" required>
    </form>
  </header>

  <nav class="mainnav">
    <ul>
      <li class="H"><a href="/index.php">Home</a></li>
      <li class="A"><a href="/archive.php">Archive</a></li>
      <li class="S"><a href="/special.php">Special</a></li>
      <li class="O"><a href="/onhold.php">Onhold</a></li>
      <li class="AR"><a href="/rank_attacker.php">Attacker Rank</a></li>
      <li class="TR"><a href="/rank_team.php">Team Rank</a></li>
      <li class="ST"><a href="/stats.php">Stats</a></li>
      <li class="NO"><a href="/notify.php">Notify</a></li>
    </ul>
    <div class="toogle">
      <span></span><span></span><span></span>
    </div>
  </nav>

  <main class="content">