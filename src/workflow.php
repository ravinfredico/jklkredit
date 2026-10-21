<?php
declare(strict_types=1);

/**
 * The application lifecycle. Every status change goes through transition(),
 * which checks the move, the role, ownership and maker-checker, then writes
 * the new status and a history row in one database transaction.
 */
const TRANSITIONS = [
    'submit'        => ['from' => 'DRAFT',        'to' => 'SUBMITTED',    'role' => 'MARKETING', 'label' => 'Kirim untuk approval'],
    'approve'       => ['from' => 'SUBMITTED',    'to' => 'APPROVED',     'role' => 'ATASAN',    'label' => 'Setujui'],
    'reject'        => ['from' => 'SUBMITTED',    'to' => 'REJECTED',     'role' => 'ATASAN',    'label' => 'Tolak'],
    'revise'        => ['from' => 'REJECTED',     'to' => 'DRAFT',        'role' => 'MARKETING', 'label' => 'Revisi pengajuan'],
    'generate_docs' => ['from' => 'APPROVED',     'to' => 'DOCS_PRINTED', 'role' => 'ADMIN_BO',  'label' => 'Buat kontrak dan PO'],
    'mark_signed'   => ['from' => 'DOCS_PRINTED', 'to' => 'SIGNED',       'role' => 'MARKETING', 'label' => 'Tandai sudah ditandatangani'],
    'disburse'      => ['from' => 'SIGNED',       'to' => 'DISBURSED',    'role' => 'ADMIN_BO',  'label' => 'Cairkan dana'],
];

function find_application(int $id): ?array
{
    $row = q(
        'SELECT a.*, c.nik, c.full_name, c.birth_date, c.marital_status, c.phone, c.address,
                s.nik AS spouse_nik, s.full_name AS spouse_name,
                v.brand, v.model, v.type, v.color, v.year,
                d.name AS dealer_name, d.code AS dealer_code, d.bank_name, d.bank_account,
                m.name AS marketing_name, m.supervisor_id
         FROM loan_applications a
         JOIN customers c ON c.id = a.customer_id
         LEFT JOIN customer_spouses s ON s.customer_id = c.id
         JOIN vehicles v ON v.application_id = a.id
         JOIN dealers d ON d.id = a.dealer_id
         JOIN users m ON m.id = a.marketing_id
         WHERE a.id = ?',
        [$id]
    )->fetch();
    return $row ?: null;
}

/** Why $user cannot run $action on $app, or null if they can. */
function action_blocker(array $user, array $app, string $action): ?string
{
    $t = TRANSITIONS[$action] ?? null;
    if (!$t) {
        return 'Aksi tidak dikenal.';
    }
    if ($app['status'] !== $t['from']) {
        return 'Status pengajuan sudah berubah. Muat ulang halaman.';
    }
    if ($user['role'] !== $t['role']) {
        return 'Peran Anda tidak bisa melakukan aksi ini.';
    }
    if ($t['role'] === 'MARKETING' && (int) $app['marketing_id'] !== (int) $user['id']) {
        return 'Hanya Marketing pemilik pengajuan yang bisa melakukan aksi ini.';
    }
    if ($t['role'] === 'ATASAN') {
        if (!is_supervisor_of($user, (int) $app['marketing_id'])) {
            return 'Pengajuan ini bukan dari tim Anda.';
        }
        if ((int) $app['marketing_id'] === (int) $user['id']) {
            return 'Anda tidak bisa menyetujui pengajuan sendiri.';
        }
    }
    if ($action === 'submit') {
        $missing = missing_documents((int) $app['id']);
        if ($missing) {
            return 'Lengkapi dokumen dulu: ' . implode(', ', array_map(fn($d) => DOC_LABELS[$d], $missing)) . '.';
        }
    }
    if ($action === 'mark_signed' && !has_document((int) $app['id'], 'KONTRAK_SIGNED')) {
        return 'Unggah kontrak yang sudah ditandatangani dulu.';
    }
    return null;
}

function available_actions(array $user, array $app): array
{
    $out = [];
    foreach (TRANSITIONS as $action => $t) {
        if ($app['status'] === $t['from'] && $user['role'] === $t['role']) {
            $out[$action] = ['label' => $t['label'], 'blocked' => action_blocker($user, $app, $action)];
        }
    }
    return $out;
}

/**
 * Run one status change. $input carries the action's own fields:
 * note (reject: required), disbursement_ref (disburse: required).
 */
function transition(int $appId, array $user, string $action, array $input = []): array
{
    return in_transaction(function () use ($appId, $user, $action, $input) {
        $app = find_application($appId) ?? throw new DomainError('Pengajuan tidak ditemukan.');
        if ($reason = action_blocker($user, $app, $action)) {
            throw new DomainError($reason);
        }
        $t = TRANSITIONS[$action];
        $note = trim((string) ($input['note'] ?? ''));
        $set = ['status = ?', 'version = version + 1', 'updated_at = ?'];
        $vals = [$t['to'], now()];

        switch ($action) {
            case 'approve':
                $set[] = 'approved_by = ?';
                $set[] = 'approved_at = ?';
                array_push($vals, $user['id'], now());
                break;
            case 'reject':
                if ($note === '') {
                    throw new DomainError('Tulis alasan penolakan agar Marketing tahu apa yang perlu diperbaiki.');
                }
                $set[] = 'rejection_reason = ?';
                $vals[] = $note;
                break;
            case 'revise':
                $set[] = 'rejection_reason = NULL';
                break;
            case 'generate_docs':
                generate_contract_and_po($app, $user);
                break;
            case 'disburse':
                $ref = strtoupper(trim((string) ($input['disbursement_ref'] ?? '')));
                if (!preg_match('/^[A-Z0-9-]{6,40}$/', $ref)) {
                    throw new DomainError('Isi nomor referensi transfer (6–40 huruf atau angka).');
                }
                if (q('SELECT 1 FROM loan_applications WHERE disbursement_ref = ?', [$ref])->fetchColumn()) {
                    throw new DomainError('Nomor referensi ini sudah dipakai. Cek kembali bukti transfer.');
                }
                $set[] = 'disbursement_ref = ?';
                $set[] = 'disbursed_by = ?';
                $set[] = 'disbursed_at = ?';
                array_push($vals, $ref, $user['id'], now());
                $note = $note ?: "Transfer ke {$app['bank_name']} {$app['bank_account']}, ref $ref";
                break;
        }

        // Optimistic lock: only update if nobody moved it since we read it.
        $vals[] = $appId;
        $vals[] = $t['from'];
        $vals[] = $app['version'];
        $updated = q(
            'UPDATE loan_applications SET ' . implode(', ', $set) . ' WHERE id = ? AND status = ? AND version = ?',
            $vals
        )->rowCount();
        if ($updated !== 1) {
            throw new ConflictError('Pengajuan baru saja diubah orang lain. Muat ulang halaman.');
        }

        q(
            'INSERT INTO status_histories (application_id, from_status, to_status, actor_id, note, created_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$appId, $t['from'], $t['to'], $user['id'], $note ?: null, now()]
        );
        return find_application($appId);
    });
}

/** Create or update a DRAFT. Returns the application id. */
function save_application(array $user, array $c, ?int $appId = null): int
{
    return in_transaction(function () use ($user, $c, $appId) {
        if ($appId !== null) {
            $app = find_application($appId);
            if (!$app || (int) $app['marketing_id'] !== (int) $user['id']) {
                throw new DomainError('Pengajuan tidak ditemukan.');
            }
            if ($app['status'] !== 'DRAFT') {
                throw new DomainError('Hanya draf yang bisa diubah.');
            }
        }

        $customerId = q('SELECT id FROM customers WHERE nik = ?', [$c['nik']])->fetchColumn();
        $customerVals = [$c['full_name'], $c['birth_date'], $c['marital_status'], $c['phone'], $c['address']];
        if ($customerId) {
            q('UPDATE customers SET full_name = ?, birth_date = ?, marital_status = ?, phone = ?, address = ? WHERE id = ?',
                [...$customerVals, $customerId]);
        } else {
            q('INSERT INTO customers (full_name, birth_date, marital_status, phone, address, nik) VALUES (?, ?, ?, ?, ?, ?)',
                [...$customerVals, $c['nik']]);
            $customerId = db()->lastInsertId();
        }
        q('DELETE FROM customer_spouses WHERE customer_id = ?', [$customerId]);
        if ($c['marital_status'] === 'KAWIN') {
            q('INSERT INTO customer_spouses (customer_id, nik, full_name) VALUES (?, ?, ?)',
                [$customerId, $c['spouse_nik'], $c['spouse_name']]);
        }

        $rate = (int) param('FLAT_RATE_BP');
        $calc = calc_installment($c['otr_price'], $c['down_payment'], $c['tenor_months'], $rate);
        $loan = [
            $customerId, $c['dealer_id'], $c['spk_no'], $c['tanda_jadi'], $c['otr_price'], $c['down_payment'],
            $c['tenor_months'], $rate, $c['insurance_type'], $calc['principal'], $calc['total_interest'],
            $calc['installment'], now(),
        ];

        if ($appId === null) {
            q('INSERT INTO loan_applications (customer_id, dealer_id, spk_no, tanda_jadi, otr_price, down_payment,
                 tenor_months, flat_rate_bp, insurance_type, principal, total_interest, installment, updated_at,
                 app_no, marketing_id, status, created_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [...$loan, 'TMP-' . bin2hex(random_bytes(4)), $user['id'], 'DRAFT', now()]);
            $appId = (int) db()->lastInsertId();
            q('UPDATE loan_applications SET app_no = ? WHERE id = ?', [sprintf('APP-%s-%04d', date('Ym'), $appId), $appId]);
            q('INSERT INTO vehicles (brand, model, type, color, year, application_id) VALUES (?, ?, ?, ?, ?, ?)',
                [$c['brand'], $c['model'], $c['type'], $c['color'], $c['year'], $appId]);
            q('INSERT INTO status_histories (application_id, from_status, to_status, actor_id, note, created_at)
               VALUES (?, NULL, ?, ?, ?, ?)', [$appId, 'DRAFT', $user['id'], 'Draf dibuat', now()]);
        } else {
            q('UPDATE loan_applications SET customer_id = ?, dealer_id = ?, spk_no = ?, tanda_jadi = ?, otr_price = ?,
                 down_payment = ?, tenor_months = ?, flat_rate_bp = ?, insurance_type = ?, principal = ?,
                 total_interest = ?, installment = ?, updated_at = ?, version = version + 1
               WHERE id = ?', [...$loan, $appId]);
            q('UPDATE vehicles SET brand = ?, model = ?, type = ?, color = ?, year = ? WHERE application_id = ?',
                [$c['brand'], $c['model'], $c['type'], $c['color'], $c['year'], $appId]);
        }
        return $appId;
    });
}

function history(int $appId): array
{
    return q(
        'SELECT h.*, u.name AS actor_name, u.role AS actor_role
         FROM status_histories h JOIN users u ON u.id = h.actor_id
         WHERE h.application_id = ? ORDER BY h.id',
        [$appId]
    )->fetchAll();
}

/** Applications that need this user's action next. */
function my_tasks(array $user): array
{
    $base = 'SELECT a.id, a.app_no, a.status, a.otr_price, a.installment, a.updated_at, c.full_name, d.name AS dealer_name
             FROM loan_applications a JOIN customers c ON c.id = a.customer_id JOIN dealers d ON d.id = a.dealer_id ';
    return match ($user['role']) {
        'MARKETING' => q($base . "WHERE a.marketing_id = ? AND a.status IN ('DRAFT','REJECTED','DOCS_PRINTED') ORDER BY a.updated_at DESC", [$user['id']])->fetchAll(),
        'ATASAN' => q($base . "JOIN users m ON m.id = a.marketing_id WHERE m.supervisor_id = ? AND a.status = 'SUBMITTED' ORDER BY a.updated_at", [$user['id']])->fetchAll(),
        'ADMIN_BO' => q($base . "WHERE a.status IN ('APPROVED','SIGNED') ORDER BY a.updated_at")->fetchAll(),
        default => [],
    };
}

/** Applications this user may see, optionally filtered by status. */
function list_applications(array $user, ?string $status = null): array
{
    $sql = 'SELECT a.id, a.app_no, a.status, a.otr_price, a.installment, a.tenor_months, a.updated_at,
                   c.full_name, d.name AS dealer_name, m.name AS marketing_name
            FROM loan_applications a
            JOIN customers c ON c.id = a.customer_id
            JOIN dealers d ON d.id = a.dealer_id
            JOIN users m ON m.id = a.marketing_id WHERE 1 = 1 ';
    $p = [];
    if ($user['role'] === 'MARKETING') {
        $sql .= 'AND a.marketing_id = ? ';
        $p[] = $user['id'];
    } elseif ($user['role'] === 'ATASAN') {
        $sql .= 'AND m.supervisor_id = ? ';
        $p[] = $user['id'];
    }
    if ($status && isset(STATUS_LABELS[$status])) {
        $sql .= 'AND a.status = ? ';
        $p[] = $status;
    }
    return q($sql . 'ORDER BY a.updated_at DESC', $p)->fetchAll();
}

function status_counts(array $user): array
{
    $counts = array_fill_keys(array_keys(STATUS_LABELS), 0);
    foreach (list_applications($user) as $row) {
        $counts[$row['status']]++;
    }
    return $counts;
}
