<?php
declare(strict_types=1);

/**
 * Documents are stored in the database (base64), never in the public web root,
 * and are only served through /documents/{id} after an access check.
 * Keeping them in the database also makes them survive on serverless hosts.
 */
function documents(int $appId): array
{
    return q('SELECT d.id, d.application_id, d.doc_type, d.original_name, d.mime, d.uploaded_at, u.name AS uploader
              FROM application_documents d JOIN users u ON u.id = d.uploaded_by
              WHERE d.application_id = ? ORDER BY d.id', [$appId])->fetchAll();
}

function document_bytes(int $docId): string
{
    return base64_decode((string) q('SELECT content_b64 FROM application_documents WHERE id = ?', [$docId])->fetchColumn());
}

function has_document(int $appId, string $type): bool
{
    return (bool) q('SELECT 1 FROM application_documents WHERE application_id = ? AND doc_type = ?', [$appId, $type])->fetchColumn();
}

function missing_documents(int $appId): array
{
    return array_values(array_filter(REQUIRED_DOCS, fn($t) => !has_document($appId, $t)));
}

/** Which document types this user may upload right now. */
function uploadable_types(array $user, array $app): array
{
    if ($user['role'] !== 'MARKETING' || (int) $app['marketing_id'] !== (int) $user['id']) {
        return [];
    }
    return match ($app['status']) {
        'DRAFT' => REQUIRED_DOCS,
        'DOCS_PRINTED' => ['KONTRAK_SIGNED'],
        default => [],
    };
}

function store_document(int $appId, array $user, string $type, string $sourcePath, string $originalName, bool $moveUpload = true): void
{
    if ($moveUpload && !is_uploaded_file($sourcePath)) {
        throw new DomainError('File gagal diunggah. Coba lagi.');
    }
    $bytes = file_get_contents($sourcePath);
    if ($bytes === false) {
        throw new DomainError('File gagal disimpan. Coba lagi.');
    }
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $mime = UPLOAD_TYPES[$ext] ?? 'application/pdf';
    // One current file per type: replacing an upload removes the old one.
    q('DELETE FROM application_documents WHERE application_id = ? AND doc_type = ?', [$appId, $type]);
    q('INSERT INTO application_documents (application_id, doc_type, original_name, mime, content_b64, uploaded_by, uploaded_at)
       VALUES (?, ?, ?, ?, ?, ?, ?)', [$appId, $type, mb_substr($originalName, 0, 150), $mime, base64_encode($bytes), $user['id'], now()]);
}

/** FPDF core fonts are Windows-1252. */
function pdf_text(string $s): string
{
    return iconv('UTF-8', 'windows-1252//TRANSLIT', $s) ?: $s;
}

final class JklPdf extends FPDF
{
    public string $docTitle = '';
    public string $docNo = '';

    public function Header(): void
    {
        $this->SetFillColor(35, 71, 181);
        $this->Rect(0, 0, 210, 4, 'F');
        $this->SetFont('Helvetica', 'B', 15);
        $this->SetTextColor(28, 43, 58);
        $this->SetXY(20, 14);
        $this->Cell(100, 8, 'PT. JKL', 0, 0);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(79, 95, 102);
        $this->Cell(70, 8, pdf_text($this->docNo), 0, 1, 'R');
        $this->SetX(20);
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetTextColor(35, 71, 181);
        $this->Cell(170, 8, pdf_text($this->docTitle), 0, 1);
        $this->SetDrawColor(28, 43, 58);
        $this->SetLineWidth(0.5);
        $this->Line(20, 32, 190, 32);
        $this->Ln(8);
    }

    public function Footer(): void
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(79, 95, 102);
        $this->Cell(0, 6, pdf_text('Dibuat otomatis oleh sistem dari data pengajuan yang sudah disetujui. Halaman ' . $this->PageNo()), 0, 0, 'C');
    }

    public function row(string $label, string $value): void
    {
        $this->SetX(20);
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(79, 95, 102);
        $this->Cell(55, 7, pdf_text($label), 0, 0);
        $this->SetTextColor(28, 43, 58);
        $this->SetFont('Helvetica', 'B', 10);
        $this->MultiCell(115, 7, pdf_text($value), 0, 'L');
    }

    public function section(string $title): void
    {
        $this->Ln(3);
        $this->SetX(20);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(28, 43, 58);
        $this->Cell(170, 8, pdf_text($title), 'B', 1);
        $this->Ln(2);
    }
}

/** Build the contract and PO from the approved data and record them. */
function generate_contract_and_po(array $app, array $user): void
{
    $poNo = sprintf('PO-%s-%04d', date('Ym'), $app['id']);
    $contractNo = sprintf('KTR-%s-%04d', date('Ym'), $app['id']);
    $poAmount = (int) $app['otr_price'] - (int) $app['down_payment'];
    $vehicle = "{$app['brand']} {$app['model']} {$app['type']}, {$app['color']}, {$app['year']}";

    $k = new JklPdf('P', 'mm', 'A4');
    $k->docTitle = 'Perjanjian Pembiayaan Kendaraan Bermotor';
    $k->docNo = "No. $contractNo";
    $k->SetMargins(20, 40, 20);
    $k->AddPage();
    $k->section('Para pihak');
    $k->row('Kreditur', 'PT. JKL');
    $k->row('Debitur', $app['full_name']);
    $k->row('NIK', $app['nik']);
    $k->row('Alamat', $app['address']);
    if ($app['spouse_name']) {
        $k->row('Pasangan (persetujuan)', "{$app['spouse_name']} (NIK {$app['spouse_nik']})");
    }
    $k->section('Objek pembiayaan');
    $k->row('Kendaraan', $vehicle);
    $k->row('Dealer', "{$app['dealer_name']} ({$app['dealer_code']})");
    $k->row('Nomor SPK', $app['spk_no']);
    $k->section('Ketentuan pembiayaan');
    $k->row('Harga OTR', rupiah($app['otr_price']));
    $k->row('Uang muka (DP)', rupiah($app['down_payment']));
    $k->row('Pokok pembiayaan', rupiah($app['principal']));
    $k->row('Bunga flat per tahun', pct_from_bp($app['flat_rate_bp']));
    $k->row('Total bunga', rupiah($app['total_interest']));
    $k->row('Tenor', "{$app['tenor_months']} bulan");
    $k->row('Angsuran per bulan', rupiah($app['installment']));
    $k->row('Asuransi', $app['insurance_type'] === 'ALL_RISK' ? 'All risk' : 'Total loss only (TLO)');
    $k->section('Tanda tangan');
    $k->Ln(16);
    $k->SetX(20);
    $k->SetFont('Helvetica', '', 10);
    $k->Cell(85, 6, pdf_text('Debitur: ' . $app['full_name']), 'T', 0);
    $k->Cell(85, 6, pdf_text('Untuk PT. JKL'), 'T', 1, 'R');

    $p = new JklPdf('P', 'mm', 'A4');
    $p->docTitle = 'Purchase Order Kendaraan';
    $p->docNo = "No. $poNo";
    $p->SetMargins(20, 40, 20);
    $p->AddPage();
    $p->section('Kepada dealer');
    $p->row('Dealer', "{$app['dealer_name']} ({$app['dealer_code']})");
    $p->row('Rekening pencairan', "{$app['bank_name']} {$app['bank_account']}");
    $p->section('Unit yang dipesan');
    $p->row('Kendaraan', $vehicle);
    $p->row('Atas nama', $app['full_name']);
    $p->row('Nomor SPK', $app['spk_no']);
    $p->row('Nomor pengajuan', $app['app_no']);
    $p->section('Nilai');
    $p->row('Harga OTR', rupiah($app['otr_price']));
    $p->row('DP dibayar konsumen ke dealer', rupiah($app['down_payment']));
    $p->row('Dibayar PT. JKL ke dealer', rupiah($poAmount));
    $p->Ln(4);
    $p->SetX(20);
    $p->SetFont('Helvetica', '', 9);
    $p->SetTextColor(79, 95, 102);
    $p->MultiCell(170, 5, pdf_text('Dana dicairkan setelah kontrak ditandatangani konsumen. Serahkan unit setelah menerima konfirmasi pencairan.'));

    foreach (['KONTRAK' => [$k, "$contractNo.pdf"], 'PO' => [$p, "$poNo.pdf"]] as $type => [$pdf, $name]) {
        $tmp = tempnam(sys_get_temp_dir(), 'jkl');
        $pdf->Output('F', $tmp);
        store_document((int) $app['id'], $user, $type, $tmp, $name, false);
        @unlink($tmp);
    }

    q('DELETE FROM purchase_orders WHERE application_id = ?', [$app['id']]);
    q('INSERT INTO purchase_orders (application_id, po_no, dealer_id, amount, issued_at) VALUES (?, ?, ?, ?, ?)',
        [$app['id'], $poNo, $app['dealer_id'], $poAmount, now()]);
}
