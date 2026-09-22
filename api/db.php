<?php
/* ------------------------------------------------------------------
   db.php — jediné miesto, ktoré otvára pripojenie na databázu.
   Volá sa len z iných api/*.php súborov (QR_SAVE_ENTRY guard),
   nikdy priamo z prehliadača.

   Ak api/dbconfig.php chýba (napr. na Verceli, alebo kým DB ešte
   nie je nastavená), qrDb() vráti null — volajúci sa má podľa toho
   správať tak, akoby účty prevádzok a štatistiky boli vypnuté,
   nie hádzať chybu.
   ------------------------------------------------------------------ */

declare(strict_types=1);

if (!defined('QR_SAVE_ENTRY')) {
    http_response_code(403);
    exit('Forbidden');
}

function qrDb(): ?PDO
{
    static $pdo = null;
    static $tried = false;
    if ($tried) {
        return $pdo;
    }
    $tried = true;

    $path = __DIR__ . '/dbconfig.php';
    if (!is_file($path)) {
        return null;
    }
    $cfg = require $path;

    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            (int)$cfg['port'],
            $cfg['name'],
            $cfg['charset'] ?? 'utf8mb4'
        );
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        $pdo = null;
    }
    return $pdo;
}

/* Vytvorí tabuľky, ak ešte neexistujú. Volá sa pri každom požiadavku,
   ktorý DB potrebuje — CREATE TABLE IF NOT EXISTS je lacný no-op,
   keď už tabuľka je, a ušetrí to samostatný inštalačný krok. */
function qrDbEnsureSchema(PDO $db): void
{
    $db->exec(
        'CREATE TABLE IF NOT EXISTS qr_venue_accounts (
            slug VARCHAR(48) PRIMARY KEY,
            password_hash VARCHAR(255) NOT NULL,
            token_version INT UNSIGNED NOT NULL DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $db->exec(
        'CREATE TABLE IF NOT EXISTS qr_stats_daily (
            venue_slug VARCHAR(48) NOT NULL,
            metric VARCHAR(64) NOT NULL,
            day DATE NOT NULL,
            count INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (venue_slug, metric, day)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    /* Stĺpec pribudol dodatočne — staršie inštalácie ho ešte nemajú.
       MariaDB pozná IF NOT EXISTS, MySQL nie; tam sa chyba ticho zhltne
       a druhý pokus už prejde, lebo stĺpec medzitým existuje. */
    try {
        $db->exec('ALTER TABLE qr_venue_accounts ADD COLUMN IF NOT EXISTS token_version INT UNSIGNED NOT NULL DEFAULT 1');
    } catch (Throwable $e) {
        try {
            $db->exec('ALTER TABLE qr_venue_accounts ADD COLUMN token_version INT UNSIGNED NOT NULL DEFAULT 1');
        } catch (Throwable $e2) {
            // stĺpec už existuje
        }
    }
}
