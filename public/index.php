<?php
declare(strict_types=1);

// Let the PHP dev server serve static files from public/ directly.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require dirname(__DIR__) . '/src/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/') ?: '/';

if ($method === 'POST') {
    check_csrf();
}

try {
    route($method, $path);
} catch (DomainError | ConflictError $e) {
    flash($e->getMessage(), 'error');
    redirect($_SERVER['HTTP_REFERER'] ?? '/');
}

function route(string $method, string $path): void
{
    // Login and logout
    if ($path === '/login') {
        if ($method === 'POST') {
            if (attempt_login(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''))) {
                redirect('/');
            }
            render('login', ['error' => 'Username atau password salah.', 'username' => $_POST['username'] ?? ''], false);
            return;
        }
        render('login', ['error' => null, 'username' => ''], false);
        return;
    }
    if ($path === '/logout' && $method === 'POST') {
        session_destroy();
        redirect('/login');
    }

    $user = require_login();

    if ($path === '/') {
        if ($user['role'] === 'DEALER') {
            redirect('/po');
        }
        render('dashboard', ['tasks' => my_tasks($user), 'counts' => status_counts($user)]);
        return;
    }

    if ($path === '/applications' && $method === 'GET') {
        require_role($user, 'MARKETING', 'ATASAN', 'ADMIN_BO');
        $status = $_GET['status'] ?? null;
        render('applications', ['rows' => list_applications($user, $status), 'status' => $status]);
        return;
    }

    if ($path === '/applications/new' && $method === 'GET') {
        require_role($user, 'MARKETING');
        render('form', ['app' => null, 'old' => ['tenor_months' => 36, 'insurance_type' => 'ALL_RISK', 'year' => date('Y')], 'errors' => []]);
        return;
    }

    if ($path === '/applications' && $method === 'POST') {
        require_role($user, 'MARKETING');
        [$errors, $clean] = validate_application($_POST);
        if ($errors) {
            http_response_code(422);
            render('form', ['app' => null, 'old' => $_POST, 'errors' => $errors]);
            return;
        }
        $id = save_application($user, $clean);
        flash('Draf disimpan. Unggah dokumen, lalu kirim untuk approval.');
        redirect("/applications/$id");
    }

    if ($path === '/api/simulate') {
        $otr = parse_rupiah($_GET['otr'] ?? '') ?? 0;
        $dp = parse_rupiah($_GET['dp'] ?? '') ?? 0;
        $tenor = (int) ($_GET['tenor'] ?? 0);
        header('Content-Type: application/json');
        try {
            echo json_encode(['ok' => true, 'rate' => pct_from_bp(param('FLAT_RATE_BP'))]
                + array_map('rupiah', calc_installment($otr, $dp, $tenor, (int) param('FLAT_RATE_BP'))));
        } catch (InvalidArgumentException) {
            echo json_encode(['ok' => false]);
        }
        return;
    }

    if (preg_match('#^/applications/(\d+)(?:/(edit|documents|action/([a-z_]+)))?$#', $path, $m)) {
        $app = find_application((int) $m[1]);
        if (!$app || !can_view_application($user, $app)) {
            http_response_code(404);
            render('error', ['title' => 'Pengajuan tidak ditemukan', 'message' => 'Pengajuan ini tidak ada atau bukan milik tim Anda.']);
            return;
        }
        $sub = $m[2] ?? '';

        if ($sub === '' && $method === 'GET') {
            render('detail', [
                'app' => $app,
                'history' => history((int) $app['id']),
                'docs' => documents((int) $app['id']),
                'actions' => available_actions($user, $app),
                'uploadable' => uploadable_types($user, $app),
            ]);
            return;
        }
        if ($sub === 'edit' && $method === 'GET') {
            if ($app['status'] !== 'DRAFT' || (int) $app['marketing_id'] !== (int) $user['id']) {
                throw new DomainError('Hanya draf milik Anda yang bisa diubah.');
            }
            render('form', ['app' => $app, 'old' => $app, 'errors' => []]);
            return;
        }
        if ($sub === '' && $method === 'POST') {
            [$errors, $clean] = validate_application($_POST);
            if ($errors) {
                http_response_code(422);
                render('form', ['app' => $app, 'old' => $_POST, 'errors' => $errors]);
                return;
            }
            save_application($user, $clean, (int) $app['id']);
            flash('Perubahan draf disimpan.');
            redirect("/applications/{$app['id']}");
        }
        if ($sub === 'documents' && $method === 'POST') {
            $type = (string) ($_POST['doc_type'] ?? '');
            if (!in_array($type, uploadable_types($user, $app), true)) {
                throw new DomainError('Dokumen ini tidak bisa diunggah pada status sekarang.');
            }
            if ($err = validate_upload($_FILES['file'] ?? [])) {
                throw new DomainError($err);
            }
            store_document((int) $app['id'], $user, $type, $_FILES['file']['tmp_name'], $_FILES['file']['name']);
            flash(DOC_LABELS[$type] . ' diunggah.');
            redirect("/applications/{$app['id']}");
        }
        if ($sub !== '' && str_starts_with($sub, 'action/') && $method === 'POST') {
            $action = $m[3];
            transition((int) $app['id'], $user, $action, $_POST);
            $done = [
                'submit' => 'Pengajuan dikirim ke atasan untuk approval.',
                'approve' => 'Pengajuan disetujui. Admin Backoffice akan membuat kontrak dan PO.',
                'reject' => 'Pengajuan ditolak. Marketing menerima alasan penolakan.',
                'revise' => 'Pengajuan kembali menjadi draf dan bisa diubah.',
                'generate_docs' => 'Kontrak dan PO dibuat. PO sudah terkirim ke dealer.',
                'mark_signed' => 'Kontrak ditandai sudah ditandatangani. Siap dicairkan.',
                'disburse' => 'Dana dicairkan dan tercatat.',
            ];
            flash($done[$action] ?? 'Status diperbarui.');
            redirect("/applications/{$app['id']}");
        }
    }

    if (preg_match('#^/documents/(\d+)$#', $path, $m)) {
        $doc = q('SELECT * FROM application_documents WHERE id = ?', [(int) $m[1]])->fetch();
        $app = $doc ? find_application((int) $doc['application_id']) : null;
        $dealerPo = $doc && $user['role'] === 'DEALER' && $doc['doc_type'] === 'PO' && (int) $app['dealer_id'] === (int) $user['dealer_id'];
        if (!$doc || (!$dealerPo && !can_view_application($user, $app))) {
            http_response_code(404);
            render('error', ['title' => 'Dokumen tidak ditemukan', 'message' => 'Dokumen ini tidak ada atau tidak bisa Anda buka.']);
            return;
        }
        $file = storage_path($doc['stored_path']);
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        header('Content-Type: ' . (UPLOAD_TYPES[$ext] ?? 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . preg_replace('/[^\w.\-]/', '_', $doc['original_name']) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        return;
    }

    if ($path === '/po') {
        require_role($user, 'DEALER', 'ADMIN_BO');
        $sql = 'SELECT p.*, a.app_no, a.status, c.full_name, v.brand, v.model, v.type,
                       (SELECT id FROM application_documents d WHERE d.application_id = a.id AND d.doc_type = \'PO\') AS doc_id
                FROM purchase_orders p JOIN loan_applications a ON a.id = p.application_id
                JOIN customers c ON c.id = a.customer_id JOIN vehicles v ON v.application_id = a.id';
        $rows = $user['role'] === 'DEALER'
            ? q("$sql WHERE p.dealer_id = ? ORDER BY p.issued_at DESC", [$user['dealer_id']])->fetchAll()
            : q("$sql ORDER BY p.issued_at DESC")->fetchAll();
        render('po', ['rows' => $rows]);
        return;
    }

    if (preg_match('#^/po/(\d+)/ack$#', $path, $m) && $method === 'POST') {
        require_role($user, 'DEALER');
        q('UPDATE purchase_orders SET acknowledged_at = ? WHERE id = ? AND dealer_id = ? AND acknowledged_at IS NULL',
            [now(), (int) $m[1], $user['dealer_id']]);
        flash('PO dikonfirmasi diterima.');
        redirect('/po');
    }

    http_response_code(404);
    render('error', ['title' => 'Halaman tidak ditemukan', 'message' => 'Periksa alamat yang Anda buka.']);
}
