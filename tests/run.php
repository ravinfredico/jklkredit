<?php
declare(strict_types=1);

/**
 * Workflow tests on a throwaway SQLite database.
 * Run: php tests/run.php
 */
$db = sys_get_temp_dir() . '/jkl-test-' . getmypid() . '.sqlite';
// TEST_DB_DSN (+ DB_USER) runs the suite against an EMPTY MySQL or PostgreSQL database instead.
putenv('DB_DSN=' . (getenv('TEST_DB_DSN') ?: "sqlite:$db"));
ob_start();
require dirname(__DIR__) . '/bin/setup.php';
ob_end_clean();

$passed = 0;
$failed = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  ok   ' : '  FAIL ') . $name . ($ok || $detail === '' ? '' : "  ($detail)") . "\n";
}
function refused(callable $fn, string $class = DomainError::class): ?string
{
    try {
        $fn();
    } catch (Throwable $e) {
        return $e instanceof $class ? $e->getMessage() : get_class($e) . ': ' . $e->getMessage();
    }
    return null;
}
function u(string $username): array
{
    return q('SELECT * FROM users WHERE username = ?', [$username])->fetch();
}
function app_by_status(string $status): array
{
    return find_application((int) q('SELECT id FROM loan_applications WHERE status = ? ORDER BY id LIMIT 1', [$status])->fetchColumn());
}

echo "Kalkulasi angsuran\n";
$c = calc_installment(300_000_000, 75_000_000, 36, 500);
check('contoh slide: OTR 300 jt, DP 25%, 5% flat, 36 bln = Rp7.187.500', $c['installment'] === 7_187_500, json_encode($c));
check('bunga total Rp33.750.000', $c['total_interest'] === 33_750_000);
$c2 = calc_installment(330_000_000, 80_000_000, 36, 500);
check('angsuran dibulatkan ke atas ke Rp100', $c2['installment'] === 7_986_200, (string) $c2['installment']);
check('DP >= OTR ditolak', refused(fn() => calc_installment(100, 100, 12, 500), InvalidArgumentException::class) !== null);

echo "Validasi form\n";
$valid = [
    'nik' => '3171045508900099', 'full_name' => 'Uji Coba', 'birth_date' => '1990-01-01', 'marital_status' => 'BELUM_KAWIN',
    'phone' => '081234567890', 'address' => 'Jl. Uji 1', 'dealer_id' => 1, 'spk_no' => 'SPK/1', 'tanda_jadi' => '5.000.000',
    'brand' => 'Toyota', 'model' => 'Avanza', 'type' => 'G', 'color' => 'Putih', 'year' => date('Y'),
    'otr_price' => 'Rp300.000.000', 'down_payment' => '75.000.000', 'tenor_months' => 36, 'insurance_type' => 'TLO',
];
[$err, $clean] = validate_application($valid);
check('input valid lolos, format rupiah diterima', $err === [] && $clean['otr_price'] === 300_000_000, json_encode($err));
[$err] = validate_application(['nik' => '123'] + $valid);
check('NIK bukan 16 digit ditolak', isset($err['nik']));
[$err] = validate_application(['down_payment' => '10000000'] + $valid);
check('DP di bawah 20% ditolak', isset($err['down_payment']));
[$err] = validate_application(['marital_status' => 'KAWIN'] + $valid);
check('status kawin tanpa data pasangan ditolak', isset($err['spouse_nik'], $err['spouse_name']));
[$err] = validate_application(['birth_date' => date('Y-m-d', strtotime('-18 years'))] + $valid);
check('usia di bawah 21 ditolak', isset($err['birth_date']));
[$err] = validate_application(['tenor_months' => 30] + $valid);
check('tenor di luar daftar ditolak', isset($err['tenor_months']));
[$err] = validate_application(['tanda_jadi' => '90000000'] + $valid);
check('tanda jadi melebihi DP ditolak', isset($err['tanda_jadi']));

echo "Workflow dan hak akses\n";
$rina = u('rina');
$dimas = u('dimas');
$budi = u('budi');
$sari = u('sari');

$draftId = save_application($rina, $clean);
check('draf baru tersimpan dengan angsuran dari server', (int) find_application($draftId)['installment'] === 7_187_500);
check('submit tanpa dokumen ditolak', refused(fn() => transition($draftId, $rina, 'submit')) !== null);
foreach (REQUIRED_DOCS as $t) {
    attach_sample($draftId, $rina, $t);
}
check('Marketing lain tidak bisa submit draf orang lain', refused(fn() => transition($draftId, $dimas, 'submit')) !== null);
transition($draftId, $rina, 'submit');
check('submit dengan dokumen lengkap berhasil', find_application($draftId)['status'] === 'SUBMITTED');
check('Marketing tidak bisa approve', refused(fn() => transition($draftId, $rina, 'approve')) !== null);
check('Admin BO tidak bisa approve', refused(fn() => transition($draftId, $sari, 'approve')) !== null);
check('reject tanpa alasan ditolak', refused(fn() => transition($draftId, $budi, 'reject')) !== null);

q("INSERT INTO users (username, name, password_hash, role, created_at) VALUES ('atasan2', 'Atasan Lain', 'x', 'ATASAN', ?)", [now()]);
check('atasan dari tim lain tidak bisa approve', refused(fn() => transition($draftId, u('atasan2'), 'approve')) !== null);

transition($draftId, $budi, 'approve');
check('atasan tim bisa approve', find_application($draftId)['status'] === 'APPROVED');
check('approve dua kali ditolak (status sudah berubah)', refused(fn() => transition($draftId, $budi, 'approve')) !== null);

$stale = find_application($draftId);
q('UPDATE loan_applications SET version = version + 1 WHERE id = ?', [$draftId]);
check('lock optimistis: versi berubah membuat update gagal', refused(function () use ($draftId, $sari, $stale) {
    in_transaction(function () use ($draftId, $stale) {
        $n = q('UPDATE loan_applications SET status = ? WHERE id = ? AND status = ? AND version = ?',
            ['DOCS_PRINTED', $draftId, 'APPROVED', $stale['version']])->rowCount();
        if ($n !== 1) {
            throw new ConflictError('stale');
        }
    });
}, ConflictError::class) !== null);

transition($draftId, $sari, 'generate_docs');
check('kontrak dan PO dibuat', has_document($draftId, 'KONTRAK') && has_document($draftId, 'PO')
    && (bool) q('SELECT 1 FROM purchase_orders WHERE application_id = ?', [$draftId])->fetchColumn());
check('PDF kontrak benar-benar PDF', str_starts_with(document_bytes((int)
    q("SELECT id FROM application_documents WHERE application_id = ? AND doc_type = 'KONTRAK'", [$draftId])->fetchColumn()
), '%PDF'));
check('tandai ditandatangani tanpa upload ditolak', refused(fn() => transition($draftId, $rina, 'mark_signed')) !== null);
attach_sample($draftId, $rina, 'KONTRAK_SIGNED');
transition($draftId, $rina, 'mark_signed');

$usedRef = app_by_status('DISBURSED')['disbursement_ref'];
check('nomor transfer yang sudah dipakai ditolak', refused(fn() => transition($draftId, $sari, 'disburse', ['disbursement_ref' => $usedRef])) !== null);
check('pencairan tanpa nomor transfer ditolak', refused(fn() => transition($draftId, $sari, 'disburse')) !== null);
transition($draftId, $sari, 'disburse', ['disbursement_ref' => 'TRF-TEST-0001']);
$done = find_application($draftId);
check('dana dicairkan dan tercatat', $done['status'] === 'DISBURSED' && $done['disbursement_ref'] === 'TRF-TEST-0001');

$h = array_column(history($draftId), 'to_status');
check('riwayat lengkap DRAFT sampai DISBURSED', $h === ['DRAFT', 'SUBMITTED', 'APPROVED', 'DOCS_PRINTED', 'SIGNED', 'DISBURSED'], implode(',', $h));

echo "Visibilitas\n";
check('Marketing hanya melihat pengajuannya sendiri', !can_view_application($dimas, $done) && can_view_application($rina, $done));
check('Atasan melihat pengajuan timnya', can_view_application($budi, $done) && !can_view_application(u('atasan2'), $done));
check('Dealer tidak membuka detail pengajuan', !can_view_application(u('dealer.sunter'), $done));

$rejected = app_by_status('REJECTED');
transition((int) $rejected['id'], $rina, 'revise');
check('pengajuan ditolak bisa direvisi kembali ke draf', find_application((int) $rejected['id'])['status'] === 'DRAFT');

@unlink($db);
echo "\n$passed lulus, $failed gagal\n";
exit($failed ? 1 : 0);
