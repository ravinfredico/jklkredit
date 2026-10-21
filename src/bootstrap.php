<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
date_default_timezone_set('Asia/Jakarta');

require ROOT . '/lib/fpdf/fpdf.php';
foreach (['db', 'auth', 'format', 'calc', 'validation', 'workflow', 'documents', 'view', 'install'] as $file) {
    require ROOT . "/src/$file.php";
}

/** A business rule refused the request; the message is shown to the user. */
final class DomainError extends RuntimeException {}
/** The record changed since the user loaded it (optimistic lock lost). */
final class ConflictError extends RuntimeException {}
