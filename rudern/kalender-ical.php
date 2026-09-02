<?php
/**
 * RV Wanderer — Kalender iCal Feed
 * Gibt kalender.json als .ics aus — kann in Google/Apple/Outlook als
 * Kalender-Abonnement eingetragen werden (automatische Aktualisierung).
 */

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="rv-wanderer-kalender.ics"');
header('Cache-Control: no-cache, must-revalidate');

$FILE = __DIR__ . '/kalender.json';
$events = file_exists($FILE) ? json_decode(file_get_contents($FILE), true) : [];
if (!is_array($events)) $events = [];

function ical_escape($str) {
    $str = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $str ?? '');
    return preg_replace('/\r?\n/', '\\n', $str);
}

function ical_fold($line) {
    // RFC 5545: lines > 75 octets must be folded
    $out = '';
    while (strlen($line) > 75) {
        $out .= substr($line, 0, 75) . "\r\n ";
        $line = substr($line, 75);
    }
    return $out . $line;
}

$now = gmdate('Ymd\THis\Z');

$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//RV Wanderer e.V.//Ruderkalender//DE',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'X-WR-CALNAME:RV Wanderer Ruderkalender',
    'X-WR-TIMEZONE:Europe/Berlin',
    'X-WR-CALDESC:Veranstaltungen der Rudervereinigung Wanderer e.V. Berlin Spandau',
];

$type_labels = [
    'regatta'     => 'Regatta',
    'ausfahrt'    => 'Ausfahrt',
    'training'    => 'Training',
    'vereinsabend'=> 'Vereinsabend',
];

foreach ($events as $ev) {
    $date_raw = $ev['date'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_raw)) continue;

    $dtstart  = str_replace('-', '', $date_raw);
    $d        = new DateTime($date_raw);
    $d->modify('+1 day');
    $dtend    = $d->format('Ymd');

    $summary  = ical_escape($ev['title'] ?? '');
    $location = ical_escape($ev['ort']   ?? '');
    $type_lbl = $type_labels[$ev['type'] ?? ''] ?? ($ev['type'] ?? '');

    $desc_parts = [];
    if (!empty($ev['time'])) $desc_parts[] = $ev['time'] . ' Uhr';
    if (!empty($type_lbl))   $desc_parts[] = $type_lbl;
    if (!empty($ev['desc'])) $desc_parts[] = $ev['desc'];
    $description = ical_escape(implode(' — ', $desc_parts));

    $uid = ($ev['id'] ?? uniqid()) . '@rv-wanderer-berlin.de';

    $vevent = [
        'BEGIN:VEVENT',
        'UID:'       . $uid,
        'DTSTAMP:'   . $now,
        'DTSTART;VALUE=DATE:' . $dtstart,
        'DTEND;VALUE=DATE:'   . $dtend,
        'SUMMARY:'   . $summary,
    ];
    if ($location)    $vevent[] = 'LOCATION:'    . $location;
    if ($description) $vevent[] = 'DESCRIPTION:' . $description;
    $vevent[] = 'END:VEVENT';

    foreach ($vevent as $line) {
        $lines[] = ical_fold($line);
    }
}

$lines[] = 'END:VCALENDAR';

echo implode("\r\n", $lines) . "\r\n";
