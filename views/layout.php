<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$nav = match ($user['role'] ?? '') {
    'MARKETING' => ['/' => 'Tugas saya', '/applications' => 'Pengajuan saya', '/applications/new' => 'Pengajuan baru'],
    'ATASAN' => ['/' => 'Menunggu approval', '/applications' => 'Pengajuan tim'],
    'ADMIN_BO' => ['/' => 'Antrean backoffice', '/applications' => 'Semua pengajuan', '/po' => 'Purchase order'],
    'DEALER' => ['/po' => 'Purchase order'],
    default => [],
};
$flash = flash();
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Berkas Kredit') ?> · Berkas Kredit JKL</title>
<link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="topbar">
  <a class="brand" href="/">Berkas Kredit JKL</a>
  <nav class="nav" aria-label="Menu utama">
    <?php foreach ($nav as $href => $label): ?>
      <a href="<?= e($href) ?>"<?= $path === $href ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php if ($user): ?>
  <div class="who">
    <span><strong><?= e($user['name']) ?></strong>, <?= e(ROLE_LABELS[$user['role']]) ?></span>
    <form method="post" action="/logout"><?= csrf_field() ?><button class="link-btn" type="submit">Keluar</button></form>
  </div>
  <?php endif; ?>
</header>
<main id="main">
  <?php if ($flash): ?>
    <p class="flash<?= $flash['kind'] === 'error' ? ' error' : '' ?>" role="<?= $flash['kind'] === 'error' ? 'alert' : 'status' ?>"><?= e($flash['message']) ?></p>
  <?php endif; ?>
  <?= $content ?>
</main>
</body>
</html>
