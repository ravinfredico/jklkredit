<?php
declare(strict_types=1);

/**
 * Create the database and load demo data.
 *   php bin/setup.php            -> SQLite at storage/database.sqlite
 *   DB_DSN=mysql:... php bin/setup.php  -> MySQL (uses database/schema.mysql.sql)
 * Demo applications are moved through the real workflow functions, so their
 * status history is exactly what the app would record.
 */
require dirname(__DIR__) . '/src/bootstrap.php';

$dsn = getenv('DB_DSN') ?: 'sqlite:' . ROOT . '/storage/database.sqlite';
if (str_starts_with($dsn, 'sqlite:')) {
    @unlink(substr($dsn, 7));
}
foreach (glob(storage_path('uploads/*'), GLOB_ONLYDIR) ?: [] as $dir) {
    array_map('unlink', glob("$dir/*") ?: []);
    @rmdir($dir);
}

$schema = file_get_contents(ROOT . '/database/' . (str_starts_with($dsn, 'mysql') ? 'schema.mysql.sql' : 'schema.sqlite.sql'));
foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $schema)))) as $stmt) {
    db()->exec($stmt);
}

foreach (['MIN_DP_PCT' => '20', 'FLAT_RATE_BP' => '500', 'TENORS' => '12,24,36,48,60', 'MIN_AGE' => '21', 'MAX_UPLOAD_MB' => '5'] as $k => $v) {
    q('INSERT INTO parameters (name, value) VALUES (?, ?)', [$k, $v]);
}

$dealers = [
    ['DLR-SNT', 'Sunter Motor', 'Jakarta Utara', 'BCA', '0123456789'],
    ['DLR-BSD', 'BSD Auto Prima', 'Tangerang Selatan', 'BCA', '0987654321'],
    ['DLR-CKR', 'Cikarang Mobilindo', 'Bekasi', 'Mandiri', '1230004567'],
];
foreach ($dealers as $d) {
    q('INSERT INTO dealers (code, name, city, bank_name, bank_account) VALUES (?, ?, ?, ?, ?)', $d);
}

$hash = password_hash('rahasia123', PASSWORD_DEFAULT);
$addUser = function (string $username, string $name, string $role, ?int $sup = null, ?int $dealer = null) use ($hash): array {
    q('INSERT INTO users (username, name, password_hash, role, supervisor_id, dealer_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$username, $name, $hash, $role, $sup, $dealer, now()]);
    return q('SELECT * FROM users WHERE id = ?', [db()->lastInsertId()])->fetch();
};
$budi = $addUser('budi', 'Budi Santoso', 'ATASAN');
$rina = $addUser('rina', 'Rina Wijaya', 'MARKETING', (int) $budi['id']);
$dimas = $addUser('dimas', 'Dimas Pratama', 'MARKETING', (int) $budi['id']);
$sari = $addUser('sari', 'Sari Lestari', 'ADMIN_BO');
$addUser('dealer.sunter', 'Sunter Motor', 'DEALER', null, 1);

/** A small placeholder image standing in for a scanned document. */
function sample_scan(string $label): string
{
    $img = imagecreatetruecolor(640, 400);
    imagefill($img, 0, 0, imagecolorallocate($img, 238, 242, 238));
    $ink = imagecolorallocate($img, 28, 43, 58);
    imagerectangle($img, 10, 10, 629, 389, $ink);
    imagestring($img, 5, 30, 30, "CONTOH $label (DATA DEMO)", $ink);
    $path = tempnam(sys_get_temp_dir(), 'scan') . '.png';
    imagepng($img, $path);
    return $path;
}
function attach(int $appId, array $user, string $type): void
{
    store_document($appId, $user, $type, sample_scan($type), strtolower($type) . '.png', false);
}

$people = [
    // marketing, nik, name, birth, marital, spouse, phone, address, dealer, brand, model, type, color, otr, dp, tenor, target
    [$rina, '3171045508900002', 'Andi Kurniawan', '1990-08-15', 'KAWIN', ['3171046203920001', 'Maya Kurniawan'], '081234567801', 'Jl. Danau Sunter Utara No. 12, Jakarta Utara', 1, 'Toyota', 'Avanza', '1.5 G CVT', 'Putih', 300000000, 75000000, 36, 'DISBURSED'],
    [$rina, '3674031212880003', 'Siti Rahmawati', '1988-12-12', 'BELUM_KAWIN', null, '081298765402', 'Jl. Pahlawan Seribu, BSD, Tangerang Selatan', 2, 'Honda', 'HR-V', '1.5 E CVT', 'Abu-abu', 395000000, 100000000, 48, 'SUBMITTED'],
    [$dimas, '3216020505950004', 'Rudi Hartono', '1995-05-05', 'KAWIN', ['3216024407960002', 'Dewi Hartono'], '085611223303', 'Perum Cikarang Baru Blok C3, Bekasi', 3, 'Mitsubishi', 'Xpander', 'Ultimate CVT', 'Hitam', 330000000, 80000000, 36, 'SUBMITTED'],
    [$rina, '3171012003910005', 'Fajar Nugroho', '1991-03-20', 'BELUM_KAWIN', null, '081377788804', 'Jl. Yos Sudarso No. 88, Jakarta Utara', 1, 'Suzuki', 'Ertiga', 'GX AT', 'Merah', 275000000, 60000000, 24, 'APPROVED'],
    [$dimas, '3603151707930006', 'Lina Marlina', '1993-07-17', 'CERAI', null, '082122334405', 'Jl. Raya Serpong No. 5, Tangerang', 2, 'Daihatsu', 'Terios', 'R AT', 'Silver', 280000000, 70000000, 36, 'SIGNED'],
    [$rina, '3171060101970007', 'Yoga Prasetyo', '1997-01-01', 'BELUM_KAWIN', null, '081355566606', 'Jl. Kelapa Gading Boulevard, Jakarta Utara', 1, 'Toyota', 'Raize', '1.0 GR Sport', 'Kuning', 290000000, 58000000, 36, 'REJECTED'],
    [$rina, '3171070909940008', 'Putri Ayuningtyas', '1994-09-09', 'BELUM_KAWIN', null, '081266677707', 'Jl. Boulevard Artha Gading, Jakarta Utara', 1, 'Honda', 'Brio', 'RS CVT', 'Oranye', 245000000, 55000000, 24, 'DOCS_PRINTED'],
];

foreach ($people as [$mkt, $nik, $name, $birth, $marital, $spouse, $phone, $addr, $dealer, $brand, $model, $type, $color, $otr, $dp, $tenor, $target]) {
    $input = [
        'nik' => $nik, 'full_name' => $name, 'birth_date' => $birth, 'marital_status' => $marital,
        'spouse_nik' => $spouse[0] ?? '', 'spouse_name' => $spouse[1] ?? '', 'phone' => $phone, 'address' => $addr,
        'dealer_id' => $dealer, 'spk_no' => 'SPK/' . date('Y') . '/' . substr($nik, -4), 'tanda_jadi' => 5000000,
        'brand' => $brand, 'model' => $model, 'type' => $type, 'color' => $color, 'year' => date('Y'),
        'otr_price' => $otr, 'down_payment' => $dp, 'tenor_months' => $tenor, 'insurance_type' => 'ALL_RISK',
    ];
    [$errors, $clean] = validate_application($input);
    if ($errors) {
        throw new RuntimeException("Seed data for $name is invalid: " . json_encode($errors));
    }
    $id = save_application($mkt, $clean);
    foreach (REQUIRED_DOCS as $doc) {
        attach($id, $mkt, $doc);
    }
    $steps = [
        'SUBMITTED' => [['submit', $mkt, []]],
        'APPROVED' => [['submit', $mkt, []], ['approve', $budi, ['note' => 'Data dan dokumen lengkap.']]],
        'REJECTED' => [['submit', $mkt, []], ['reject', $budi, ['note' => 'Slip gaji tidak dilampirkan dan penghasilan belum terverifikasi. Lengkapi lalu kirim ulang.']]],
        'DOCS_PRINTED' => [['submit', $mkt, []], ['approve', $budi, []], ['generate_docs', $sari, []]],
        'SIGNED' => [['submit', $mkt, []], ['approve', $budi, []], ['generate_docs', $sari, []], ['sign', $mkt, []]],
        'DISBURSED' => [['submit', $mkt, []], ['approve', $budi, ['note' => 'DP 25%, kapasitas bayar memadai.']], ['generate_docs', $sari, []], ['sign', $mkt, []], ['disburse', $sari, ['disbursement_ref' => 'TRF-' . date('ymd') . '-00' . $id]]],
    ][$target] ?? [];
    foreach ($steps as [$action, $actor, $in]) {
        if ($action === 'sign') {
            attach($id, $mkt, 'KONTRAK_SIGNED');
            $action = 'mark_signed';
        }
        transition($id, $actor, $action, $in);
    }
}

echo "Database siap: " . q('SELECT COUNT(*) FROM loan_applications')->fetchColumn() . " pengajuan demo.\n";
echo "Login dengan rina / budi / sari / dimas / dealer.sunter, password rahasia123\n";
