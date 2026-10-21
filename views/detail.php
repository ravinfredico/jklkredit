<?php
$title = $app['app_no'];
$docsByType = [];
foreach ($docs as $d) {
    $docsByType[$d['doc_type']] = $d;
}
$isOwner = (int) $app['marketing_id'] === (int) $user['id'];
$expectedDocs = array_merge(REQUIRED_DOCS, in_array($app['status'], ['DOCS_PRINTED', 'SIGNED', 'DISBURSED'], true) ? ['KONTRAK', 'PO', 'KONTRAK_SIGNED'] : []);
?>
<div class="page-head">
  <div>
    <h1><?= e($app['full_name']) ?></h1>
    <p><?= e($app['app_no']) ?>, dibuat oleh <?= e($app['marketing_name']) ?> pada <?= e(tanggal($app['created_at'])) ?></p>
  </div>
  <?= str_replace('class="stamp', 'class="stamp big', stamp($app['status'])) ?>
</div>

<?php if ($app['status'] === 'REJECTED' && $app['rejection_reason']): ?>
  <div class="rejected-note"><strong>Alasan penolakan:</strong> <?= e($app['rejection_reason']) ?></div>
<?php endif; ?>

<div class="detail">
  <div>
    <h2>Konsumen</h2>
    <dl class="facts">
      <div class="row"><dt>NIK</dt><dd class="num"><?= e($app['nik']) ?></dd></div>
      <div class="row"><dt>Tanggal lahir</dt><dd><?= e(tanggal($app['birth_date'], false)) ?></dd></div>
      <div class="row"><dt>Status perkawinan</dt><dd><?= e(MARITAL_LABELS[$app['marital_status']]) ?></dd></div>
      <?php if ($app['spouse_name']): ?>
        <div class="row"><dt>Pasangan</dt><dd><?= e($app['spouse_name']) ?>, NIK <?= e($app['spouse_nik']) ?></dd></div>
      <?php endif; ?>
      <div class="row"><dt>Nomor HP</dt><dd><?= e($app['phone']) ?></dd></div>
      <div class="row"><dt>Alamat</dt><dd><?= e($app['address']) ?></dd></div>
    </dl>

    <h2>Kendaraan</h2>
    <dl class="facts">
      <div class="row"><dt>Unit</dt><dd><?= e("{$app['brand']} {$app['model']} {$app['type']}") ?></dd></div>
      <div class="row"><dt>Warna dan tahun</dt><dd><?= e("{$app['color']}, {$app['year']}") ?></dd></div>
      <div class="row"><dt>Dealer</dt><dd><?= e($app['dealer_name']) ?></dd></div>
      <div class="row"><dt>Nomor SPK</dt><dd><?= e($app['spk_no']) ?></dd></div>
    </dl>

    <h2>Pinjaman</h2>
    <dl class="facts">
      <div class="row"><dt>Harga OTR</dt><dd class="num"><?= rupiah($app['otr_price']) ?></dd></div>
      <div class="row"><dt>Uang muka (DP)</dt><dd class="num"><?= rupiah($app['down_payment']) ?>, tanda jadi <?= rupiah($app['tanda_jadi']) ?></dd></div>
      <div class="row"><dt>Pokok pembiayaan</dt><dd class="num"><?= rupiah($app['principal']) ?></dd></div>
      <div class="row"><dt>Bunga</dt><dd class="num"><?= rupiah($app['total_interest']) ?> (flat <?= e(pct_from_bp($app['flat_rate_bp'])) ?> per tahun)</dd></div>
      <div class="row"><dt>Angsuran</dt><dd class="num"><strong><?= rupiah($app['installment']) ?></strong> per bulan, <?= (int) $app['tenor_months'] ?> bulan</dd></div>
      <div class="row"><dt>Asuransi</dt><dd><?= $app['insurance_type'] === 'ALL_RISK' ? 'All risk' : 'Total loss only (TLO)' ?></dd></div>
      <?php if ($app['disbursement_ref']): ?>
        <div class="row"><dt>Referensi transfer</dt><dd><?= e($app['disbursement_ref']) ?></dd></div>
      <?php endif; ?>
    </dl>
  </div>

  <div class="side">
    <section aria-labelledby="h-actions">
      <h2 id="h-actions">Langkah berikutnya</h2>
      <?php if (!$actions && !($app['status'] === 'DRAFT' && $isOwner)): ?>
        <p class="blocked"><?= $app['status'] === 'DISBURSED' ? 'Selesai. Dana sudah dicairkan ke dealer.' : 'Tidak ada aksi untuk Anda pada status ini.' ?></p>
      <?php endif; ?>
      <?php if ($app['status'] === 'DRAFT' && $isOwner): ?>
        <p style="margin-bottom:12px"><a href="/applications/<?= (int) $app['id'] ?>/edit">Ubah data draf</a></p>
      <?php endif; ?>
      <?php foreach ($actions as $action => $a): ?>
        <form class="action-form" method="post" action="/applications/<?= (int) $app['id'] ?>/action/<?= e($action) ?>">
          <?= csrf_field() ?>
          <?php if ($action === 'approve'): ?>
            <label for="note-approve" class="hint">Catatan (opsional)</label>
            <textarea id="note-approve" name="note"></textarea>
          <?php elseif ($action === 'reject'): ?>
            <label for="note-reject" class="hint">Alasan penolakan (wajib)</label>
            <textarea id="note-reject" name="note" required></textarea>
          <?php elseif ($action === 'disburse'): ?>
            <div class="field"><label for="ref">Nomor referensi transfer</label><input id="ref" name="disbursement_ref" required></div>
            <p class="hint">Ke <?= e($app['bank_name'] . ' ' . $app['bank_account']) ?>, sejumlah <?= rupiah((int) $app['otr_price'] - (int) $app['down_payment']) ?></p>
          <?php endif; ?>
          <button class="btn<?= $action === 'reject' ? ' danger' : '' ?>" type="submit"<?= $a['blocked'] ? ' disabled aria-describedby="why-' . e($action) . '"' : '' ?>><?= e($a['label']) ?></button>
          <?php if ($a['blocked']): ?><p class="blocked" id="why-<?= e($action) ?>"><?= e($a['blocked']) ?></p><?php endif; ?>
        </form>
      <?php endforeach; ?>
    </section>

    <section aria-labelledby="h-docs">
      <h2 id="h-docs">Dokumen</h2>
      <ul class="docs">
        <?php foreach ($expectedDocs as $type): ?>
          <li>
            <span><?= e(DOC_LABELS[$type]) ?></span>
            <?php if (isset($docsByType[$type])): ?>
              <a href="/documents/<?= (int) $docsByType[$type]['id'] ?>" target="_blank" rel="noopener">Buka</a>
            <?php else: ?>
              <span class="missing">Belum ada</span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($uploadable): ?>
        <form class="upload" method="post" action="/applications/<?= (int) $app['id'] ?>/documents" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <label class="hint" for="doc_type">Unggah dokumen (JPG, PNG atau PDF, maksimal <?= e(param('MAX_UPLOAD_MB')) ?> MB)</label>
          <select id="doc_type" name="doc_type">
            <?php foreach ($uploadable as $t): ?><option value="<?= e($t) ?>"><?= e(DOC_LABELS[$t]) ?></option><?php endforeach; ?>
          </select>
          <input type="file" name="file" accept=".jpg,.jpeg,.png,.pdf" required aria-label="File dokumen">
          <button class="btn secondary" type="submit">Unggah</button>
        </form>
      <?php endif; ?>
    </section>

    <section aria-labelledby="h-history">
      <h2 id="h-history">Riwayat status</h2>
      <ol class="timeline">
        <?php foreach ($history as $h): ?>
          <li>
            <div class="what"><?= e(STATUS_LABELS[$h['to_status']]) ?></div>
            <div class="when"><?= e(tanggal($h['created_at'])) ?>, <?= e($h['actor_name']) ?> (<?= e(ROLE_LABELS[$h['actor_role']]) ?>)</div>
            <?php if ($h['note']): ?><div class="note"><?= e($h['note']) ?></div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>
</div>
