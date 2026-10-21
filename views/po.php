<?php $title = 'Purchase order'; ?>
<div class="page-head">
  <div><h1>Purchase order</h1><p>PO dibuat otomatis setelah pengajuan disetujui. Konfirmasi saat Anda menerimanya.</p></div>
</div>
<?php if (!$rows): ?>
  <p class="empty">Belum ada PO untuk dealer Anda.</p>
<?php else: ?>
<table class="ledger">
  <thead><tr><th>Nomor PO</th><th>Konsumen</th><th>Unit</th><th class="num">Dibayar ke dealer</th><th>Status pengajuan</th><th>Diterima dealer</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?php if ($r['doc_id']): ?><a href="/documents/<?= (int) $r['doc_id'] ?>" target="_blank" rel="noopener"><?= e($r['po_no']) ?></a><?php else: ?><?= e($r['po_no']) ?><?php endif; ?></td>
      <td><?= e($r['full_name']) ?></td>
      <td><?= e("{$r['brand']} {$r['model']} {$r['type']}") ?></td>
      <td class="num"><?= rupiah($r['amount']) ?></td>
      <td><?= stamp($r['status']) ?></td>
      <td>
        <?php if ($r['acknowledged_at']): ?>
          <span class="sub"><?= e(tanggal($r['acknowledged_at'])) ?></span>
        <?php elseif ($user['role'] === 'DEALER'): ?>
          <form method="post" action="/po/<?= (int) $r['id'] ?>/ack"><?= csrf_field() ?><button class="btn secondary" type="submit">Konfirmasi diterima</button></form>
        <?php else: ?>
          <span class="sub">Belum dikonfirmasi</span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
