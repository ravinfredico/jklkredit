<?php
declare(strict_types=1);

const STATUS_LABELS = [
    'DRAFT' => 'Draf',
    'SUBMITTED' => 'Menunggu approval',
    'APPROVED' => 'Disetujui',
    'REJECTED' => 'Ditolak',
    'DOCS_PRINTED' => 'Kontrak dan PO dibuat',
    'SIGNED' => 'Sudah ditandatangani',
    'DISBURSED' => 'Dana dicairkan',
];

const DOC_LABELS = [
    'KTP' => 'KTP',
    'KK' => 'Kartu Keluarga',
    'BUKTI_TANDA_JADI' => 'Bukti bayar tanda jadi',
    'KONTRAK' => 'Dokumen kontrak',
    'PO' => 'Dokumen PO',
    'KONTRAK_SIGNED' => 'Kontrak bertanda tangan',
];

const MARITAL_LABELS = [
    'BELUM_KAWIN' => 'Belum kawin',
    'KAWIN' => 'Kawin',
    'CERAI' => 'Cerai',
];

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rupiah(int|string|null $amount): string
{
    return 'Rp' . number_format((int) $amount, 0, ',', '.');
}

function pct_from_bp(int|string $bp): string
{
    return rtrim(rtrim(number_format(((int) $bp) / 100, 2, ',', '.'), '0'), ',') . '%';
}

function tanggal(?string $datetime, bool $withTime = true): string
{
    if (!$datetime) {
        return '–';
    }
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $ts = strtotime($datetime);
    $out = date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
    return $withTime ? $out . ', ' . date('H.i', $ts) : $out;
}

/** Parse "Rp300.000.000", "300000000" or "300.000.000" into whole rupiah. */
function parse_rupiah(mixed $raw): ?int
{
    $digits = preg_replace('/\D/', '', (string) $raw);
    return $digits === '' ? null : (int) $digits;
}
