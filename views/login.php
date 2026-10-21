<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk · Berkas Kredit JKL</title>
<link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<main class="login">
  <h1>Berkas Kredit JKL</h1>
  <p>Pengajuan kredit kendaraan, dari draf sampai dana cair, dalam satu alur.</p>
  <?php if ($error): ?><p class="flash error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="/login">
    <?= csrf_field() ?>
    <div class="field"><label for="u">Username</label><input id="u" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus></div>
    <div class="field"><label for="p">Password</label><input id="p" name="password" type="password" autocomplete="current-password" required></div>
    <div class="form-actions"><button class="btn" type="submit">Masuk</button></div>
  </form>
  <p class="demo">Akun demo, semua dengan password <strong>rahasia123</strong>: rina (Marketing), dimas (Marketing), budi (Atasan Marketing), sari (Admin Backoffice), dealer.sunter (Dealer).</p>
</main>
</body>
</html>
