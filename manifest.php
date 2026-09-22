<?php
/* ------------------------------------------------------------------
   manifest.php — skutočný (nie blob:) manifest pre jednoúčelové
   nasadenie. Android/Chrome pri reálnej inštalácii appky (nie len
   záložky) sťahuje manifest cez svoju vlastnú službu na pozadí
   (WebAPK), ktorá sa k blob: URL nedostane — s ňou sa prompt zobrazí,
   ale inštalácia potichu zlyhá. Viacúčelový qr/qerko engine (viac
   prevádzok na jednej doméne) blob: manifest stále používa, tu to
   ide jednoducho — jedna prevádzka, jeden pevný súbor s ikonami.
   ------------------------------------------------------------------ */

declare(strict_types=1);

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

$slug = 'gobyus';
$file = __DIR__ . '/data/' . $slug . '.json';
$venue = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
$venue = is_array($venue) ? $venue : [];

$name = (string)($venue['name'] ?? 'Gobyus s.r.o.');
$deep = (string)($venue['theme']['deep'] ?? '#202225');

/* Rovnaká logika ako shortNameFor() v assets/js/pwa.js — celé slová
   (aj cez pomlčky), kým sa zmestia do limitu, nie orezané uprostred
   slova ani len iniciály (Android tým aj appku vyhľadáva). */
function shortNameFor(string $name): string
{
    if (mb_strlen($name) <= 15) {
        return $name;
    }
    $words = preg_split('/\s+/', str_replace('-', ' ', $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = '';
    foreach ($words as $word) {
        $next = $out === '' ? $word : $out . ' ' . $word;
        if (mb_strlen($next) > 15) {
            break;
        }
        $out = $next;
    }
    return $out !== '' ? $out : mb_substr($name, 0, 15);
}

echo json_encode([
    'id' => '/' . $slug,
    'name' => $name,
    'short_name' => shortNameFor($name),
    'start_url' => '/' . $slug,
    'scope' => '/',
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => $deep,
    'theme_color' => $deep,
    'icons' => [
        ['src' => '/assets/img/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => '/assets/img/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => '/assets/img/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
