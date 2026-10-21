<?php
$title = $app ? 'Ubah draf ' . $app['app_no'] : 'Pengajuan baru';
$dealers = q('SELECT id, name, city FROM dealers ORDER BY name')->fetchAll();
$dealerOpts = [];
foreach ($dealers as $d) {
    $dealerOpts[$d['id']] = "{$d['name']}, {$d['city']}";
}
$tenors = [];
foreach (explode(',', param('TENORS')) as $t) {
    $tenors[(int) $t] = "$t bulan";
}
$money = ['inputmode' => 'numeric', 'autocomplete' => 'off'];
$action = $app ? "/applications/{$app['id']}" : '/applications';
?>
<div class="page-head">
  <div><h1><?= e($title) ?></h1>
  <p>Isi sesuai dokumen konsumen. Angsuran dihitung ulang oleh sistem saat disimpan.</p></div>
</div>
<?php if ($errors): ?>
  <p class="flash error" role="alert">Ada <?= count($errors) ?> isian yang perlu diperbaiki. Lihat pesan merah di bawah.</p>
<?php endif; ?>

<form class="form-layout" method="post" action="<?= e($action) ?>" novalidate>
  <div>
    <?= csrf_field() ?>
    <fieldset>
      <legend>Data konsumen</legend>
      <div class="grid">
        <?= field('full_name', 'Nama lengkap sesuai KTP', $old, $errors, 'text', ['autocomplete' => 'name']) ?>
        <?= field('nik', 'NIK', $old, $errors, 'text', ['inputmode' => 'numeric', 'maxlength' => '16']) ?>
        <?= field('birth_date', 'Tanggal lahir', $old, $errors, 'date') ?>
        <?= select_field('marital_status', 'Status perkawinan', MARITAL_LABELS, $old, $errors) ?>
        <?= field('phone', 'Nomor HP', $old, $errors, 'tel', ['autocomplete' => 'tel']) ?>
        <div class="wide"><?= field('address', 'Alamat sesuai KTP', $old, $errors) ?></div>
      </div>
    </fieldset>

    <fieldset id="spouse-fields"<?= ($old['marital_status'] ?? '') === 'KAWIN' ? '' : ' hidden' ?>>
      <legend>Data pasangan <span>wajib jika status kawin</span></legend>
      <div class="grid">
        <?= field('spouse_name', 'Nama pasangan', $old, $errors) ?>
        <?= field('spouse_nik', 'NIK pasangan', $old, $errors, 'text', ['inputmode' => 'numeric', 'maxlength' => '16']) ?>
      </div>
    </fieldset>

    <fieldset>
      <legend>Data kendaraan</legend>
      <div class="grid">
        <?= select_field('dealer_id', 'Dealer', $dealerOpts, $old, $errors) ?>
        <?= field('spk_no', 'Nomor SPK', $old, $errors) ?>
        <?= field('brand', 'Merek', $old, $errors) ?>
        <?= field('model', 'Model', $old, $errors) ?>
        <?= field('type', 'Tipe', $old, $errors) ?>
        <?= field('color', 'Warna', $old, $errors) ?>
        <?= field('year', 'Tahun', $old, $errors, 'text', ['inputmode' => 'numeric', 'maxlength' => '4']) ?>
      </div>
    </fieldset>

    <fieldset>
      <legend>Data pinjaman</legend>
      <div class="grid">
        <?= field('otr_price', 'Harga OTR (Rp)', $old, $errors, 'text', $money) ?>
        <?= field('down_payment', 'Uang muka / DP (Rp)', $old, $errors, 'text', $money + ['aria-describedby' => 'dp-hint']) ?>
        <?= field('tanda_jadi', 'Tanda jadi yang sudah dibayar (Rp)', $old, $errors, 'text', $money) ?>
        <?= select_field('tenor_months', 'Tenor', $tenors, $old, $errors) ?>
        <?= select_field('insurance_type', 'Asuransi', ['ALL_RISK' => 'All risk', 'TLO' => 'Total loss only (TLO)'], $old, $errors) ?>
      </div>
      <p class="hint" id="dp-hint">DP minimal <?= e(param('MIN_DP_PCT')) ?>% dari harga OTR.</p>
    </fieldset>

    <div class="form-actions">
      <button class="btn" type="submit"><?= $app ? 'Simpan perubahan' : 'Simpan draf' ?></button>
      <a class="btn secondary" href="<?= $app ? '/applications/' . (int) $app['id'] : '/' ?>">Batal</a>
    </div>
  </div>

  <aside class="sim" aria-live="polite">
    <h2>Simulasi angsuran</h2>
    <dl>
      <div class="line"><dt>Pokok pembiayaan</dt><dd id="s-principal">–</dd></div>
      <div class="line"><dt>Bunga flat <span id="s-rate"><?= e(pct_from_bp(param('FLAT_RATE_BP'))) ?></span> per tahun</dt><dd id="s-interest">–</dd></div>
      <div class="line"><dt>Total dibayar</dt><dd id="s-total">–</dd></div>
    </dl>
    <div class="result"><div class="amount" id="s-installment">–</div><div class="per" id="s-per">Isi OTR, DP dan tenor</div></div>
    <p class="note">Rate diambil dari parameter sistem dan tidak bisa diubah Marketing.</p>
  </aside>
</form>

<script>
(() => {
  const $ = (id) => document.getElementById(id);
  const marital = $('f-marital_status');
  marital.addEventListener('change', () => { $('spouse-fields').hidden = marital.value !== 'KAWIN'; });

  let timer;
  const update = () => {
    clearTimeout(timer);
    timer = setTimeout(async () => {
      const params = new URLSearchParams({ otr: $('f-otr_price').value, dp: $('f-down_payment').value, tenor: $('f-tenor_months').value });
      const res = await fetch('/api/simulate?' + params);
      const data = await res.json();
      if (!data.ok) {
        ['s-principal', 's-interest', 's-total', 's-installment'].forEach((id) => $(id).textContent = '–');
        $('s-per').textContent = 'Isi OTR, DP dan tenor';
        return;
      }
      $('s-principal').textContent = data.principal;
      $('s-interest').textContent = data.total_interest;
      $('s-total').textContent = data.total_payable;
      $('s-installment').textContent = data.installment;
      $('s-per').textContent = 'per bulan selama ' + $('f-tenor_months').value + ' bulan';
    }, 150);
  };
  ['f-otr_price', 'f-down_payment', 'f-tenor_months'].forEach((id) => $(id).addEventListener('input', update));
  update();
})();
</script>
