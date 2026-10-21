<?php
declare(strict_types=1);

const REQUIRED_DOCS = ['KTP', 'KK', 'BUKTI_TANDA_JADI'];
const UPLOAD_TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];

/**
 * Validate the application form. Returns [errors, clean]:
 * errors maps a field name to one message; clean holds typed values.
 */
function validate_application(array $in): array
{
    $err = [];
    $s = fn(string $k): string => trim((string) ($in[$k] ?? ''));

    $c = [
        'nik' => $s('nik'),
        'full_name' => $s('full_name'),
        'birth_date' => $s('birth_date'),
        'marital_status' => $s('marital_status'),
        'phone' => preg_replace('/[\s-]/', '', $s('phone')),
        'address' => $s('address'),
        'spouse_nik' => $s('spouse_nik'),
        'spouse_name' => $s('spouse_name'),
        'dealer_id' => (int) $s('dealer_id'),
        'spk_no' => $s('spk_no'),
        'tanda_jadi' => parse_rupiah($s('tanda_jadi')) ?? 0,
        'brand' => $s('brand'),
        'model' => $s('model'),
        'type' => $s('type'),
        'color' => $s('color'),
        'year' => (int) $s('year'),
        'otr_price' => parse_rupiah($s('otr_price')),
        'down_payment' => parse_rupiah($s('down_payment')),
        'tenor_months' => (int) $s('tenor_months'),
        'insurance_type' => $s('insurance_type'),
    ];

    if (!preg_match('/^\d{16}$/', $c['nik'])) {
        $err['nik'] = 'NIK harus 16 digit angka.';
    }
    if ($c['full_name'] === '' || mb_strlen($c['full_name']) > 100) {
        $err['full_name'] = 'Isi nama lengkap sesuai KTP, maksimal 100 karakter.';
    }

    $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $c['birth_date']);
    $minAge = (int) param('MIN_AGE');
    if (!$birth || $birth->format('Y-m-d') !== $c['birth_date']) {
        $err['birth_date'] = 'Isi tanggal lahir dengan format yang benar.';
    } elseif ($birth->diff(new DateTimeImmutable('today'))->y < $minAge) {
        $err['birth_date'] = "Konsumen harus berusia minimal $minAge tahun.";
    }

    if (!isset(MARITAL_LABELS[$c['marital_status']])) {
        $err['marital_status'] = 'Pilih status perkawinan.';
    } elseif ($c['marital_status'] === 'KAWIN') {
        if (!preg_match('/^\d{16}$/', $c['spouse_nik'])) {
            $err['spouse_nik'] = 'NIK pasangan harus 16 digit angka.';
        } elseif ($c['spouse_nik'] === $c['nik']) {
            $err['spouse_nik'] = 'NIK pasangan tidak boleh sama dengan NIK konsumen.';
        }
        if ($c['spouse_name'] === '') {
            $err['spouse_name'] = 'Isi nama pasangan sesuai KTP.';
        }
    }

    if (!preg_match('/^(\+62|62|0)8\d{7,11}$/', $c['phone'])) {
        $err['phone'] = 'Isi nomor HP yang diawali 08 atau +628.';
    }
    if ($c['address'] === '') {
        $err['address'] = 'Isi alamat sesuai KTP.';
    }

    if (!q('SELECT 1 FROM dealers WHERE id = ?', [$c['dealer_id']])->fetchColumn()) {
        $err['dealer_id'] = 'Pilih dealer.';
    }
    if ($c['spk_no'] === '') {
        $err['spk_no'] = 'Isi nomor SPK dari dealer.';
    }
    foreach (['brand' => 'merek', 'model' => 'model', 'type' => 'tipe', 'color' => 'warna'] as $field => $label) {
        if ($c[$field] === '') {
            $err[$field] = "Isi $label kendaraan.";
        }
    }
    $thisYear = (int) date('Y');
    if ($c['year'] < $thisYear - 15 || $c['year'] > $thisYear + 1) {
        $err['year'] = 'Tahun kendaraan maksimal 15 tahun ke belakang.';
    }

    $minDpPct = (int) param('MIN_DP_PCT');
    if (!$c['otr_price'] || $c['otr_price'] <= 0) {
        $err['otr_price'] = 'Isi harga OTR kendaraan.';
    } elseif ($c['down_payment'] === null) {
        $err['down_payment'] = 'Isi uang muka (DP).';
    } else {
        $minDp = intdiv($c['otr_price'] * $minDpPct + 99, 100);
        if ($c['down_payment'] < $minDp) {
            $err['down_payment'] = "DP minimal $minDpPct% dari OTR, yaitu " . rupiah($minDp) . '.';
        } elseif ($c['down_payment'] >= $c['otr_price']) {
            $err['down_payment'] = 'DP harus lebih kecil dari harga OTR.';
        }
        if ($c['tanda_jadi'] > $c['down_payment']) {
            $err['tanda_jadi'] = 'Tanda jadi tidak boleh melebihi DP.';
        }
    }

    $tenors = array_map('intval', explode(',', param('TENORS')));
    if (!in_array($c['tenor_months'], $tenors, true)) {
        $err['tenor_months'] = 'Pilih tenor yang tersedia.';
    }
    if (!in_array($c['insurance_type'], ['ALL_RISK', 'TLO'], true)) {
        $err['insurance_type'] = 'Pilih jenis asuransi.';
    }

    return [$err, $c];
}

/** Check one uploaded file. Returns an error message, or null when it is acceptable. */
function validate_upload(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return 'Pilih file yang akan diunggah.';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'File gagal diunggah. Coba lagi.';
    }
    $maxBytes = (int) param('MAX_UPLOAD_MB') * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        return 'Ukuran file maksimal ' . param('MAX_UPLOAD_MB') . ' MB.';
    }
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(UPLOAD_TYPES[$ext]) || UPLOAD_TYPES[$ext] !== $mime) {
        return 'Gunakan file JPG, PNG atau PDF.';
    }
    return null;
}
