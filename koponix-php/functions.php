<?php
// ============================================================
//  KOPONIX – Shared Functions
// ============================================================
require_once __DIR__ . '/config.php';

// ── DB connection (singleton) ────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ── Session helpers ──────────────────────────────────────────
function session_start_safe(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

function is_logged_in(): bool {
    session_start_safe();
    return !empty($_SESSION['member']);
}

function current_member(): ?array {
    session_start_safe();
    return $_SESSION['member'] ?? null;
}

function login_member(array $member): void {
    session_start_safe();
    $_SESSION['member'] = $member;
}

function logout_member(): void {
    session_start_safe();
    session_destroy();
}

function require_login(string $redirect = 'member_portal.php'): void {
    if (!is_logged_in()) {
        header('Location: ' . $redirect . '?login_required=1');
        exit;
    }
}

// ── UUID ─────────────────────────────────────────────────────
function gen_uuid(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function gen_short_id(): string {
    return substr(str_replace('-', '', gen_uuid()), 0, 8);
}

// ── Password ─────────────────────────────────────────────────
function hash_password(string $password): string {
    return hash('sha256', $password);
}

// ── Constants helpers ────────────────────────────────────────
function categories(): array  { return json_decode(CATEGORIES, true); }
function locations(): array   { return json_decode(LOCATIONS, true); }
function cat_icons(): array   { return json_decode(CATEGORY_ICONS, true); }
function cat_colors(): array  { return json_decode(CATEGORY_COLORS, true); }
function exp_options(): array { return json_decode(EXPERIENCE_OPTIONS, true); }

function cat_badge(string $cat): string {
    $icons  = cat_icons();
    $colors = cat_colors();
    $icon   = $icons[$cat]  ?? '⭐';
    $color  = $colors[$cat] ?? '#607d8b';
    $safe   = htmlspecialchars($cat);
    return "<span class='cat-badge' style='background:{$color}'>{$icon} {$safe}</span>";
}

// ── Sellers ──────────────────────────────────────────────────
function get_sellers(array $filters = []): array {
    $sql    = 'SELECT * FROM sellers WHERE 1=1';
    $params = [];
    if (!empty($filters['status'])) {
        $sql .= ' AND status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['category'])) {
        $sql .= ' AND category = ?';
        $params[] = $filters['category'];
    }
    if (!empty($filters['area'])) {
        $sql .= ' AND area = ?';
        $params[] = $filters['area'];
    }
    if (!empty($filters['koperasi_id'])) {
        $sql .= ' AND koperasi_id = ?';
        $params[] = $filters['koperasi_id'];
    }
    $sql .= ' ORDER BY registered_date DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function save_seller(array $data): string {
    $id = gen_uuid();
    db()->prepare('INSERT INTO sellers (id,name,koperasi_id,category,service_title,area,price_range,availability,description,contact,experience,registered_date,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $id, $data['name'], $data['koperasi_id'], $data['category'],
            $data['service_title'], $data['area'], $data['price_range'],
            $data['availability'] ?? '', $data['description'] ?? '',
            $data['contact'] ?? 'WhatsApp available upon request',
            $data['experience'] ?? '', date('Y-m-d'), $data['status'] ?? 'active',
        ]);
    return $id;
}

function update_seller_status(string $id, string $status): void {
    db()->prepare('UPDATE sellers SET status=? WHERE id=?')->execute([$status, $id]);
}

function delete_seller(string $id): void {
    db()->prepare('DELETE FROM sellers WHERE id=?')->execute([$id]);
}

// ── Requests ─────────────────────────────────────────────────
function get_requests(array $filters = []): array {
    $sql    = 'SELECT * FROM requests WHERE 1=1';
    $params = [];
    if (!empty($filters['member_kop_id'])) {
        $sql .= ' AND member_kop_id = ?';
        $params[] = $filters['member_kop_id'];
    }
    if (!empty($filters['status'])) {
        $sql .= ' AND status = ?';
        $params[] = $filters['status'];
    }
    $sql .= ' ORDER BY submitted_date DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function save_request(array $data): string {
    $id = gen_uuid();
    db()->prepare('INSERT INTO requests (id,buyer_name,buyer_contact,member_kop_id,category,location,service_description,preferred_date,urgency,budget,special_notes,submitted_date,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $id, $data['buyer_name'], $data['buyer_contact'],
            $data['member_kop_id'] ?? '', $data['category'], $data['location'],
            $data['service_description'], $data['preferred_date'] ?? '',
            $data['urgency'] ?? '', $data['budget'] ?? '',
            $data['special_notes'] ?? '', date('Y-m-d'), 'open',
        ]);
    return $id;
}

function update_request_status(string $id, string $status): void {
    db()->prepare('UPDATE requests SET status=? WHERE id=?')->execute([$status, $id]);
}

// ── Members ──────────────────────────────────────────────────
function get_member_by_kop_id(string $kop_id): ?array {
    $stmt = db()->prepare('SELECT * FROM members WHERE koperasi_id=?');
    $stmt->execute([$kop_id]);
    return $stmt->fetch() ?: null;
}

function authenticate_member(string $kop_id, string $password): ?array {
    $member = get_member_by_kop_id($kop_id);
    if ($member && $member['password_hash'] === hash_password($password)) {
        return $member;
    }
    return null;
}

function save_member(array $data): string {
    $id = gen_uuid();
    db()->prepare('INSERT INTO members (id,name,koperasi_id,email,password_hash,role,joined_date) VALUES (?,?,?,?,?,?,?)')
        ->execute([
            $id, $data['name'], $data['koperasi_id'],
            $data['email'] ?? '', $data['password_hash'],
            'member', date('Y-m-d'),
        ]);
    return $id;
}

function update_member_password(string $kop_id, string $new_hash): void {
    db()->prepare('UPDATE members SET password_hash=? WHERE koperasi_id=?')->execute([$new_hash, $kop_id]);
}

function get_all_members(): array {
    return db()->query('SELECT * FROM members ORDER BY joined_date DESC')->fetchAll();
}

// ── Conversations & Messages ─────────────────────────────────
function get_conversations_for_member(string $kop_id): array {
    $stmt = db()->prepare('SELECT * FROM conversations WHERE buyer_kop_id=? OR seller_kop_id=? ORDER BY created_date DESC');
    $stmt->execute([$kop_id, $kop_id]);
    return $stmt->fetchAll();
}

function get_conversation(string $conv_id): ?array {
    $stmt = db()->prepare('SELECT * FROM conversations WHERE id=?');
    $stmt->execute([$conv_id]);
    return $stmt->fetch() ?: null;
}

function find_existing_conversation(string $buyer_kop, string $seller_kop, string $seller_id): ?string {
    $stmt = db()->prepare('SELECT id FROM conversations WHERE buyer_kop_id=? AND seller_kop_id=? AND seller_id=?');
    $stmt->execute([$buyer_kop, $seller_kop, $seller_id]);
    $row = $stmt->fetch();
    return $row ? $row['id'] : null;
}

function create_conversation(string $buyer_kop, string $buyer_name, string $seller_kop, string $seller_name, string $subject, string $seller_id = ''): string {
    $existing = find_existing_conversation($buyer_kop, $seller_kop, $seller_id);
    if ($existing) return $existing;
    $id = gen_short_id();
    db()->prepare('INSERT INTO conversations (id,buyer_kop_id,buyer_name,seller_kop_id,seller_name,subject,seller_id,created_date) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$id, $buyer_kop, $buyer_name, $seller_kop, $seller_name, $subject, $seller_id, date('Y-m-d')]);
    return $id;
}

function get_messages(string $conv_id): array {
    $stmt = db()->prepare('SELECT * FROM messages WHERE conv_id=? ORDER BY timestamp ASC');
    $stmt->execute([$conv_id]);
    return $stmt->fetchAll();
}

function add_message(string $conv_id, string $sender_kop, string $sender_name, string $text, bool $is_ai = false): void {
    $id = gen_short_id();
    db()->prepare('INSERT INTO messages (id,conv_id,sender_kop_id,sender_name,text,timestamp,is_ai) VALUES (?,?,?,?,?,NOW(),?)')
        ->execute([$id, $conv_id, $sender_kop, $sender_name, $text, $is_ai ? 1 : 0]);
}

// ── Match Engine ─────────────────────────────────────────────
function score_match(array $request, array $seller): int {
    $score = 0;
    if ($seller['status'] !== 'active') return 0;
    if ($seller['category'] !== $request['category']) return 0;
    $score += 40; // category match
    $req_loc = $request['location'] ?? '';
    $sel_loc = $seller['area'] ?? '';
    if ($req_loc === $sel_loc) {
        $score += 30;
    } elseif ($sel_loc === 'Online / Remote') {
        $score += 15;
    } elseif ($req_loc === 'Online / Remote') {
        $score += 15;
    }
    $exp_scores = [
        'Less than 1 year' => 5, '1 year' => 8, '2 years' => 10,
        '3 years' => 12, '4 years' => 13, '5 years' => 15, '6 years' => 16,
        '7 years' => 17, '8 years' => 18, '9 years' => 19,
        '10 years' => 20, 'More than 10 years' => 20,
    ];
    $score += $exp_scores[$seller['experience'] ?? ''] ?? 8;
    $score += 10; // active bonus
    return min(100, $score);
}

function get_top_matches(array $request, int $top_n = 5): array {
    $sellers = get_sellers(['status' => 'active', 'category' => $request['category']]);
    $scored  = [];
    foreach ($sellers as $seller) {
        $s = score_match($request, $seller);
        if ($s > 0) {
            $seller['_score'] = $s;
            $scored[] = $seller;
        }
    }
    usort($scored, fn($a, $b) => $b['_score'] - $a['_score']);
    return array_slice($scored, 0, $top_n);
}

// ── Claude API ───────────────────────────────────────────────
function claude_api(array $messages, string $system = '', int $max_tokens = 1024, string $model = ''): string {
    if (!$model) $model = CLAUDE_MODEL;
    $payload = ['model' => $model, 'max_tokens' => $max_tokens, 'messages' => $messages];
    if ($system) $payload['system'] = $system;

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT        => 60,
    ]);
    $result = curl_exec($ch);
    $errno  = curl_errno($ch);
    curl_close($ch);

    if ($errno) return '[AI unavailable — cURL error ' . $errno . ']';
    $data = json_decode($result, true);
    return $data['content'][0]['text'] ?? ('[AI error: ' . ($data['error']['message'] ?? 'unknown') . ']');
}

// ── Flash messages ───────────────────────────────────────────
function flash(string $msg, string $type = 'success'): void {
    session_start_safe();
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function get_flash(): ?array {
    session_start_safe();
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ── Escape ───────────────────────────────────────────────────
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// ── Redirect ─────────────────────────────────────────────────
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}
