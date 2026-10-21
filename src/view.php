<?php
declare(strict_types=1);

function render(string $view, array $data = [], bool $layout = true): void
{
    extract($data, EXTR_SKIP);
    $user = current_user();
    ob_start();
    require ROOT . "/views/$view.php";
    $content = ob_get_clean();
    if ($layout) {
        require ROOT . '/views/layout.php';
    } else {
        echo $content;
    }
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

function flash(?string $message = null, string $kind = 'ok'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'kind' => $kind];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function stamp(string $status): string
{
    return '<span class="stamp stamp-' . strtolower($status) . '">' . e(STATUS_LABELS[$status] ?? $status) . '</span>';
}

/** Form input helper: label, control, and the field's error under it. */
function field(string $name, string $label, array $old, array $errors, string $type = 'text', array $attrs = []): string
{
    $id = 'f-' . $name;
    $value = e($old[$name] ?? '');
    $err = $errors[$name] ?? null;
    $extra = '';
    foreach ($attrs as $k => $v) {
        $extra .= ' ' . $k . '="' . e($v) . '"';
    }
    $aria = $err ? ' aria-invalid="true" aria-describedby="' . $id . '-err"' : '';
    $html = '<div class="field' . ($err ? ' has-error' : '') . '"><label for="' . $id . '">' . e($label) . '</label>';
    $html .= '<input id="' . $id . '" name="' . e($name) . '" type="' . e($type) . '" value="' . $value . '"' . $aria . $extra . '>';
    if ($err) {
        $html .= '<p class="error" id="' . $id . '-err">' . e($err) . '</p>';
    }
    return $html . '</div>';
}

function select_field(string $name, string $label, array $options, array $old, array $errors, array $attrs = []): string
{
    $id = 'f-' . $name;
    $err = $errors[$name] ?? null;
    $extra = '';
    foreach ($attrs as $k => $v) {
        $extra .= ' ' . $k . '="' . e($v) . '"';
    }
    $html = '<div class="field' . ($err ? ' has-error' : '') . '"><label for="' . $id . '">' . e($label) . '</label>';
    $html .= '<select id="' . $id . '" name="' . e($name) . '"' . ($err ? ' aria-invalid="true" aria-describedby="' . $id . '-err"' : '') . $extra . '>';
    $html .= '<option value="">Pilih…</option>';
    foreach ($options as $value => $text) {
        $sel = ((string) ($old[$name] ?? '') === (string) $value) ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $sel . '>' . e($text) . '</option>';
    }
    $html .= '</select>';
    if ($err) {
        $html .= '<p class="error" id="' . $id . '-err">' . e($err) . '</p>';
    }
    return $html . '</div>';
}
