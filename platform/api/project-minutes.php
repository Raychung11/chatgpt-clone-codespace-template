<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/claude.php';
require_once __DIR__ . '/../includes/projectos.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['ok' => false, 'error' => 'Login required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$meetingId = (int)($_POST['meeting_id'] ?? 0);

if ($meetingId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid meeting ID']);
    exit;
}

try {
    $meeting = DB::fetch("SELECT * FROM meetings WHERE id = ?", [$meetingId]);
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Database error loading meeting']);
    exit;
}

if (!$meeting) {
    echo json_encode(['ok' => false, 'error' => 'Meeting not found']);
    exit;
}

try {
    $attendees = DB::fetchAll("SELECT name, attended FROM meeting_attendees WHERE meeting_id = ? ORDER BY name ASC", [$meetingId]);
} catch (\Throwable $e) {
    $attendees = [];
}

$attendeeLines = [];
foreach ($attendees as $a) {
    $attendeeLines[] = '- ' . $a['name'] . ($a['attended'] ? ' (Present)' : ' (Absent)');
}
$attendeeText = $attendeeLines ? implode("\n", $attendeeLines) : 'No attendees recorded';

$systemPrompt = <<<SYSTEM
You are a professional meeting minutes writer for an international-standard project management system.
Your task is to produce clear, structured, and formal meeting minutes that meet international documentation standards.
The output must be well-organised, precise, and suitable for distribution to stakeholders and project records.
Use professional language, avoid ambiguity, and ensure all sections are complete and consistent.
SYSTEM;

$userPrompt = <<<USER
Generate formal meeting minutes for the following meeting:

Meeting Title: {$meeting['title']}
Meeting Type: {$meeting['type']}
Date: {$meeting['meeting_date']}
Time: {$meeting['meeting_time']}
Venue: {$meeting['venue']}

Objective:
{$meeting['objective']}

Attendees:
{$attendeeText}

Raw Notes / Discussion Points:
{$meeting['raw_notes']}

---

Produce the minutes with exactly these 10 sections:

1. Meeting Information
2. Attendance
3. Agenda
4. Discussion Summary
5. Decisions
6. Action Items
7. Open Issues
8. Risks
9. Next Steps
10. Next Meeting

Be thorough, professional, and ensure each section is clearly labelled and populated based on the information provided.
USER;

$result = Claude::generate($systemPrompt, $userPrompt, 2000);

if (!$result['ok']) {
    echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'AI generation failed']);
    exit;
}

$minutes = $result['result'];

try {
    DB::update('meetings', ['ai_minutes' => $minutes], 'id = ?', [$meetingId]);
    ProjectOS::log(
        (int)$meeting['project_id'],
        'meeting',
        $meetingId,
        'ai_minutes_generated',
        'AI minutes generated for meeting: ' . $meeting['title']
    );
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Failed to save minutes: ' . $e->getMessage()]);
    exit;
}

echo json_encode(['ok' => true, 'result' => $minutes]);
