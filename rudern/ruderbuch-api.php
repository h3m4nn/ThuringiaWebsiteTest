<?php
/**
 * RV Wanderer — Ruderbuch API
 * Liest und schreibt ruderbuch.json im selben Verzeichnis.
 *
 * GET  → gibt alle Einträge zurück
 * POST → speichert einen neuen Eintrag  (Body: JSON-Objekt)
 * DELETE → löscht einen Eintrag         (Body: {"id":"r123"})
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$FILE = __DIR__ . '/ruderbuch.json';

// Datei anlegen falls nicht vorhanden
if (!file_exists($FILE)) {
    file_put_contents($FILE, json_encode([], JSON_PRETTY_PRINT));
}

function readData($file) {
    $raw = file_get_contents($file);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function writeData($file, $data) {
    $sorted = $data;
    usort($sorted, fn($a,$b) => strcmp($b['date'], $a['date']));
    return file_put_contents($file, json_encode($sorted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: alle Einträge lesen ──────────────────
if ($method === 'GET') {
    echo json_encode(readData($FILE), JSON_UNESCAPED_UNICODE);
    exit;
}

// ── POST: neuen Eintrag speichern ────────────
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body || empty($body['name']) || empty($body['date']) || empty($body['boot']) || empty($body['km'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Pflichtfelder fehlen: name, date, boot, km']);
        exit;
    }
    $entry = [
        'id'      => 'r' . time() . rand(100,999),
        'name'    => htmlspecialchars(trim($body['name']),    ENT_QUOTES, 'UTF-8'),
        'date'    => htmlspecialchars(trim($body['date']),    ENT_QUOTES, 'UTF-8'),
        'boot'    => htmlspecialchars(trim($body['boot']),    ENT_QUOTES, 'UTF-8'),
        'km'      => round((float)$body['km'], 1),
        'strecke' => htmlspecialchars(trim($body['strecke'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'notiz'   => htmlspecialchars(trim($body['notiz']   ?? ''), ENT_QUOTES, 'UTF-8'),
    ];
    $data = readData($FILE);
    $data[] = $entry;
    writeData($FILE, $data);
    http_response_code(201);
    echo json_encode($entry, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── DELETE: Eintrag löschen ──────────────────
if ($method === 'DELETE') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body || empty($body['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'id fehlt']);
        exit;
    }
    $data    = readData($FILE);
    $id      = $body['id'];
    $before  = count($data);
    $data    = array_values(array_filter($data, fn($e) => $e['id'] !== $id));
    if (count($data) === $before) {
        http_response_code(404);
        echo json_encode(['error' => 'Eintrag nicht gefunden']);
        exit;
    }
    writeData($FILE, $data);
    echo json_encode(['success' => true, 'deleted' => $id]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Methode nicht erlaubt']);
