<?php $title = 'Pengajuan'; ?>
<div class="page-head">
  <div><h1><?= $user['role'] === 'MARKETING' ? 'Pengajuan saya' : ($user['role'] === 'ATASAN' ? 'Pengajuan tim' : 'Semua pengajuan') ?></h1>
  <p><?= count($rows) ?> pengajuan<?= $status ? ' dengan status ' . e(strtolower(STATUS_LABELS[$status] ?? '')) : '' ?>.</p></div>
  <?php if ($user['role'] === 'MARKETING'): ?><a class="btn" href="/applications/new">Buat pengajuan baru</a><?php endif; ?>
</div>
<nav class="filters" aria-label="Saring menurut status">
  <a href="/applications"<?= !$status ? ' aria-current="true"' : '' ?>>Semua</a>
  <?php foreach (STATUS_LABELS as $code => $label): ?>
    <a href="/applications?status=<?= e($code) ?>"<?= $status === $code ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>
<?php if (!$rows): ?>
  <p class="empty">Belum ada pengajuan dengan status ini.</p>
<?php else: ?>
<table class="ledger">
  <thead><tr><th>Nomor</th><th>Konsumen</th><th>Dealer</th><?php if ($user['role'] !== 'MARKETING'): ?><th>Marketing</th><?php endif; ?><th class="num">Angsuran</th><th>Status</th><th>Diubah</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><a href="/applications/<?= (int) $r['id'] ?>"><?= e($r['app_no']) ?></a></td>
      <td><?= e($r['full_name']) ?></td>
      <td><?= e($r['dealer_name']) ?></td>
      <?php if ($user['role'] !== 'MARKETING'): ?><td><?= e($r['marketing_name']) ?></td><?php endif; ?>
      <td class="num"><?= rupiah($r['installment']) ?><div class="sub"><?= (int) $r['tenor_months'] ?> bulan</div></td>
      <td><?= stamp($r['status']) ?></td>
      <td class="sub"><?= e(tanggal($r['updated_at'])) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
