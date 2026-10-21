<?php
$title = 'Tugas saya';
$heading = match ($user['role']) {
    'MARKETING' => ['Tugas Anda hari ini', 'Draf yang belum dikirim, pengajuan yang ditolak, dan kontrak yang menunggu tanda tangan.'],
    'ATASAN' => ['Menunggu approval Anda', 'Pengajuan dari tim Anda yang sudah lengkap dan siap diputuskan.'],
    'ADMIN_BO' => ['Antrean backoffice', 'Pengajuan yang disetujui perlu kontrak dan PO; yang sudah ditandatangani siap dicairkan.'],
};
$next = [
    'DRAFT' => 'Lengkapi dan kirim',
    'REJECTED' => 'Baca alasan, revisi',
    'DOCS_PRINTED' => 'Minta tanda tangan',
    'SUBMITTED' => 'Putuskan',
    'APPROVED' => 'Buat kontrak dan PO',
    'SIGNED' => 'Cairkan dana',
];
?>
<div class="page-head">
  <div><h1><?= e($heading[0]) ?></h1><p><?= e($heading[1]) ?></p></div>
  <?php if ($user['role'] === 'MARKETING'): ?><a class="btn" href="/applications/new">Buat pengajuan baru</a><?php endif; ?>
</div>

<?php if (!$tasks): ?>
  <p class="empty">Tidak ada yang menunggu Anda. Pengajuan baru akan muncul di sini.</p>
<?php else: ?>
<table class="ledger">
  <thead><tr><th>Nomor</th><th>Konsumen</th><th>Dealer</th><th class="num">Harga OTR</th><th>Status</th><th>Langkah berikutnya</th></tr></thead>
  <tbody>
  <?php foreach ($tasks as $t): ?>
    <tr>
      <td><a href="/applications/<?= (int) $t['id'] ?>"><?= e($t['app_no']) ?></a></td>
      <td><?= e($t['full_name']) ?></td>
      <td><?= e($t['dealer_name']) ?></td>
      <td class="num"><?= rupiah($t['otr_price']) ?></td>
      <td><?= stamp($t['status']) ?></td>
      <td><a href="/applications/<?= (int) $t['id'] ?>"><?= e($next[$t['status']] ?? 'Buka') ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<h2 style="margin-top:48px">Semua pengajuan menurut status</h2>
<div class="status-strip">
  <?php foreach ($counts as $status => $count): ?>
    <a href="/applications?status=<?= e($status) ?>"><span class="count"><?= (int) $count ?></span><span class="label"><?= e(STATUS_LABELS[$status]) ?></span></a>
  <?php endforeach; ?>
</div>
