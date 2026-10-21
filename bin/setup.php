<?php
declare(strict_types=1);

/**
 * Create the database and load demo data.
 *   php bin/setup.php                                  -> SQLite at storage/database.sqlite (recreated)
 *   DB_DSN=mysql:... php bin/setup.php                 -> MySQL
 *   DATABASE_URL=postgres://... php bin/setup.php      -> PostgreSQL
 * Against MySQL/PostgreSQL it only installs into an empty database.
 * On Vercel this runs automatically on the first request.
 */
require dirname(__DIR__) . '/src/bootstrap.php';

[$dsn] = db_config();
if (str_starts_with($dsn, 'sqlite:')) {
    @unlink(substr($dsn, 7));
}
if (is_installed()) {
    exit("Database sudah berisi data. Kosongkan dulu jika ingin mengisi ulang.\n");
}
install_schema();
seed_demo();

echo 'Database siap: ' . q('SELECT COUNT(*) FROM loan_applications')->fetchColumn() . " pengajuan demo.\n";
echo "Login dengan rina / budi / sari / dimas / dealer.sunter, password rahasia123\n";
