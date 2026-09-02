<?php
/**
 * RV Wanderer — Kalender API
 * Liest und schreibt kalender.json im selben Verzeichnis.
 *
 * GET  → gibt alle Events zurück
 * POST → speichert ein neues Event   (Body: JSON-Objekt)
 * DELETE → löscht ein Event          (Body: {"id":"e123"})
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$FILE = __DIR__ . '/kalender.json';

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
    usort($sorted, fn($a,$b) => strcmp($a['date'], $b['date']));
    return file_put_contents($file, json_encode($sorted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$method = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────
if ($method === 'GET') {
    echo json_encode(readData($FILE), JSON_UNESCAPED_UNICODE);
    exit;
}

// ── POST ─────────────────────────────────────
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body || empty($body['title']) || empty($body['date'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Pflichtfelder fehlen: title, date']);
        exit;
    }
    $allowed_types = ['ausfahrt', 'regatta', 'training', 'vereinsabend'];
    $type = in_array($body['type'] ?? '', $allowed_types) ? $body['type'] : 'ausfahrt';
    $event = [
        'id'    => 'e' . time() . rand(100,999),
        'title' => htmlspecialchars(trim($body['title']), ENT_QUOTES, 'UTF-8'),
        'date'  => htmlspecialchars(trim($body['date']),  ENT_QUOTES, 'UTF-8'),
        'type'  => $type,
        'time'  => htmlspecialchars(trim($body['time'] ?? ''),  ENT_QUOTES, 'UTF-8'),
        'ort'   => htmlspecialchars(trim($body['ort']  ?? ''),  ENT_QUOTES, 'UTF-8'),
        'desc'  => htmlspecialchars(trim($body['desc'] ?? ''),  ENT_QUOTES, 'UTF-8'),
    ];
    $data = readData($FILE);
    $data[] = $event;
    writeData($FILE, $data);
    http_response_code(201);
    echo json_encode($event, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── DELETE ───────────────────────────────────
if ($method === 'DELETE') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body || empty($body['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'id fehlt']);
        exit;
    }
    $data   = readData($FILE);
    $id     = $body['id'];
    $before = count($data);
    $data   = array_values(array_filter($data, fn($e) => $e['id'] !== $id));
    if (count($data) === $before) {
        http_response_code(404);
        echo json_encode(['error' => 'Event nicht gefunden']);
        exit;
    }
    writeData($FILE, $data);
    echo json_encode(['success' => true, 'deleted' => $id]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Methode nicht erlaubt']);
