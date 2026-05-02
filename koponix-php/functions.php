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

function require_login(string $redirect = ''): void {
    if (!is_logged_in()) {
        $dest = $redirect ?: (defined('PORTAL_URL') ? PORTAL_URL . '/' : '../portal/');
        header('Location: ' . $dest . '?login_required=1');
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

// ── Password (bcrypt, backward-compat with old SHA256 hashes) ─
function hash_password(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 11]);
}

function verify_password(string $password, string $hash): bool {
    // Detect old SHA256 hashes (64 hex chars) and support them during migration
    if (strlen($hash) === 64 && ctype_xdigit($hash)) {
        return hash_equals(hash('sha256', $password), $hash);
    }
    return password_verify($password, $hash);
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
    db()->prepare('INSERT INTO sellers (id,name,koperasi_id,category,service_title,area,price_range,availability,description,contact,experience,registered_date,status,image,gallery1,gallery2) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $id, $data['name'], $data['koperasi_id'], $data['category'],
            $data['service_title'], $data['area'], $data['price_range'],
            $data['availability'] ?? '', $data['description'] ?? '',
            $data['contact'] ?? 'WhatsApp available upon request',
            $data['experience'] ?? '', date('Y-m-d'), $data['status'] ?? 'active',
            $data['image'] ?? '', $data['gallery1'] ?? '', $data['gallery2'] ?? '',
        ]);
    return $id;
}

function update_seller(string $id, array $data): void {
    db()->prepare('UPDATE sellers SET name=?,category=?,service_title=?,area=?,price_range=?,availability=?,description=?,contact=?,experience=?,status=? WHERE id=?')
        ->execute([
            $data['name'], $data['category'], $data['service_title'],
            $data['area'], $data['price_range'], $data['availability'] ?? '',
            $data['description'] ?? '', $data['contact'] ?? 'WhatsApp available upon request',
            $data['experience'] ?? '', $data['status'] ?? 'active', $id,
        ]);
    if (!empty($data['image']))    db()->prepare('UPDATE sellers SET image=?    WHERE id=?')->execute([$data['image'],    $id]);
    if (!empty($data['gallery1'])) db()->prepare('UPDATE sellers SET gallery1=? WHERE id=?')->execute([$data['gallery1'], $id]);
    if (!empty($data['gallery2'])) db()->prepare('UPDATE sellers SET gallery2=? WHERE id=?')->execute([$data['gallery2'], $id]);
}

function get_seller_by_id(string $id): ?array {
    $stmt = db()->prepare('SELECT * FROM sellers WHERE id=?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function update_seller_status(string $id, string $status): void {
    db()->prepare('UPDATE sellers SET status=? WHERE id=?')->execute([$status, $id]);
}

function approve_seller(string $id, string $note = ''): void {
    db()->prepare('UPDATE sellers SET status=?, admin_note=? WHERE id=?')->execute(['active', $note, $id]);
}

function reject_seller(string $id, string $note = ''): void {
    db()->prepare('UPDATE sellers SET status=?, admin_note=? WHERE id=?')->execute(['rejected', $note, $id]);
}

function get_pending_sellers(): array {
    return db()->query("SELECT * FROM sellers WHERE status='pending' ORDER BY registered_date ASC")->fetchAll();
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
    db()->prepare('INSERT INTO requests (id,buyer_name,buyer_contact,member_kop_id,category,location,service_description,preferred_date,urgency,budget,special_notes,submitted_date,status,credits_used) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $id, $data['buyer_name'], $data['buyer_contact'],
            $data['member_kop_id'] ?? '', $data['category'], $data['location'],
            $data['service_description'], $data['preferred_date'] ?? '',
            $data['urgency'] ?? '', $data['budget'] ?? '',
            $data['special_notes'] ?? '', date('Y-m-d'), 'open',
            (int)($data['credits_used'] ?? 0),
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
    if (!$member || !verify_password($password, $member['password_hash'])) {
        return null;
    }
    // Auto-upgrade old SHA256 hash to bcrypt on successful login
    if (strlen($member['password_hash']) === 64 && ctype_xdigit($member['password_hash'])) {
        $new_hash = hash_password($password);
        update_member_password($kop_id, $new_hash);
        $member['password_hash'] = $new_hash;
    }
    return $member;
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

function update_member_profile(string $kop_id, array $data): void {
    $fields = [];
    $params = [];
    foreach (['name', 'email', 'bio', 'avatar'] as $col) {
        if (array_key_exists($col, $data)) {
            $fields[] = "{$col}=?";
            $params[]  = $data[$col];
        }
    }
    if (!$fields) return;
    $params[] = $kop_id;
    db()->prepare('UPDATE members SET ' . implode(',', $fields) . ' WHERE koperasi_id=?')->execute($params);
}

// ── Image upload helper ───────────────────────────────────────
function handle_image_upload(string $field, string $dest_subdir): ?string {
    if (empty($_FILES[$field]['tmp_name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $tmp  = $_FILES[$field]['tmp_name'];
    $info = @getimagesize($tmp);
    if (!$info) return null; // not an image

    $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($info['mime'], $allowed_mime, true)) return null;

    if (filesize($tmp) > 2 * 1024 * 1024) return null; // max 2 MB

    $ext  = match($info['mime']) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        default      => 'jpg',
    };
    $filename = gen_short_id() . '.' . $ext;
    $dest_dir = __DIR__ . '/uploads/' . $dest_subdir;
    if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
    $dest = $dest_dir . '/' . $filename;
    if (move_uploaded_file($tmp, $dest)) {
        return $dest_subdir . '/' . $filename; // relative path for DB
    }
    return null;
}

function img_url(string $path): string {
    if (!$path) return '';
    return (defined('SITE_URL') ? rtrim(SITE_URL, '/') : '') . '/uploads/' . ltrim($path, '/');
}

function get_all_members(): array {
    return db()->query('SELECT * FROM members ORDER BY joined_date DESC')->fetchAll();
}

/**
 * Returns the current member's view role:
 *   'public'   – not logged in
 *   'admin'    – role = admin
 *   'provider' – member with at least one listing (any status)
 *   'member'   – logged-in member with no listings yet
 */
function view_role(): string {
    $m = current_member();
    if (!$m) return 'public';
    if (($m['role'] ?? '') === 'admin') return 'admin';
    static $checked = false, $result = 'member';
    if (!$checked) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM sellers WHERE koperasi_id=?');
        $stmt->execute([$m['koperasi_id']]);
        $result  = (int)$stmt->fetchColumn() > 0 ? 'provider' : 'member';
        $checked = true;
    }
    return $result;
}

function set_member_status(string $kop_id, string $status): void {
    db()->prepare('UPDATE members SET status=? WHERE koperasi_id=?')->execute([$status, $kop_id]);
}

function admin_reset_member_password(string $kop_id, string $new_password): void {
    db()->prepare('UPDATE members SET password_hash=? WHERE koperasi_id=?')
        ->execute([hash_password($new_password), $kop_id]);
}

function delete_member(string $kop_id): void {
    db()->prepare('DELETE FROM members WHERE koperasi_id=?')->execute([$kop_id]);
}

/**
 * Bulk-import members from CSV content.
 * Expected CSV columns (header row required): name,koperasi_id,email,password
 * Returns ['imported'=>int, 'skipped'=>int, 'errors'=>string[]]
 */
function import_members_csv(string $csv_content): array {
    $lines    = preg_split('/\r\n|\n|\r/', trim($csv_content));
    $imported = 0; $skipped  = 0; $errors = [];
    $header   = null;
    foreach ($lines as $i => $line) {
        if (!trim($line)) continue;
        $cols = str_getcsv($line);
        if ($header === null) {
            $header = array_map('strtolower', array_map('trim', $cols));
            continue;
        }
        $row = array_combine($header, $cols);
        $kop_id = strtoupper(trim($row['koperasi_id'] ?? ''));
        $name   = trim($row['name'] ?? '');
        $email  = trim($row['email'] ?? '');
        $pass   = trim($row['password'] ?? 'temppass' . rand(1000, 9999));
        if (!$kop_id || !$name) {
            $errors[] = "Row " . ($i + 1) . ": missing name or koperasi_id";
            $skipped++;
            continue;
        }
        // Skip if member ID already exists
        $existing = db()->prepare('SELECT id FROM members WHERE koperasi_id=?');
        $existing->execute([$kop_id]);
        if ($existing->fetch()) { $skipped++; continue; }
        $id = gen_uuid();
        db()->prepare('INSERT INTO members (id,name,koperasi_id,email,password_hash,role,joined_date) VALUES (?,?,?,?,?,?,?)')
            ->execute([$id, $name, $kop_id, $email, hash_password($pass), 'member', date('Y-m-d')]);
        $imported++;
    }
    return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
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

// ── Referral & Credits ───────────────────────────────────────

/**
 * Generate a unique referral code for a member.
 * Format: [ABBREV prefix][6 alphanumeric chars]  e.g. KKBR2F8X4A
 */
function gen_referral_code(string $kop_id): string {
    $abbrev = defined('KOPERASI_ABBREV') ? KOPERASI_ABBREV : 'KOP';
    $hash   = strtoupper(substr(hash('sha256', $kop_id . 'koponix_ref_salt'), 0, 6));
    return $abbrev . $hash;
}

/**
 * Get or create the referral code for a member.
 */
function ensure_referral_code(string $kop_id): string {
    $m = get_member_by_kop_id($kop_id);
    if (!empty($m['referral_code'])) return $m['referral_code'];
    $code = gen_referral_code($kop_id);
    db()->prepare('UPDATE members SET referral_code=? WHERE koperasi_id=?')->execute([$code, $kop_id]);
    return $code;
}

/**
 * Get current credit balance for a member.
 */
function get_credits(string $kop_id): int {
    $stmt = db()->prepare('SELECT credits FROM members WHERE koperasi_id=?');
    $stmt->execute([$kop_id]);
    return (int)($stmt->fetchColumn() ?? 0);
}

/**
 * Add credits to a member and log the transaction.
 */
function add_credits(string $kop_id, int $amount, string $description): void {
    db()->prepare('UPDATE members SET credits = credits + ? WHERE koperasi_id=?')
        ->execute([$amount, $kop_id]);
    db()->prepare('INSERT INTO credit_transactions (id,member_kop_id,amount,type,description,created_at) VALUES (?,?,?,?,?,NOW())')
        ->execute([gen_short_id(), $kop_id, $amount, 'earn', $description]);
}

/**
 * Spend credits. Returns false if balance insufficient.
 */
function spend_credits(string $kop_id, int $amount, string $description): bool {
    if (get_credits($kop_id) < $amount) return false;
    db()->prepare('UPDATE members SET credits = credits - ? WHERE koperasi_id=?')
        ->execute([$amount, $kop_id]);
    db()->prepare('INSERT INTO credit_transactions (id,member_kop_id,amount,type,description,created_at) VALUES (?,?,?,?,?,NOW())')
        ->execute([gen_short_id(), $kop_id, -$amount, 'spend', $description]);
    return true;
}

/**
 * Process a referral when a new member registers.
 * - Referrer gets +10 credits
 * - New member gets +5 welcome credits
 * Returns true on success, false if code invalid or self-referral.
 */
function apply_referral(string $new_kop_id, string $referral_code): bool {
    $code = strtoupper(trim($referral_code));
    if (!$code) return false;
    $stmt = db()->prepare('SELECT koperasi_id FROM members WHERE referral_code=?');
    $stmt->execute([$code]);
    $referrer_id = $stmt->fetchColumn();
    if (!$referrer_id || $referrer_id === $new_kop_id) return false;

    // Link the new member to their referrer
    db()->prepare('UPDATE members SET referred_by=? WHERE koperasi_id=?')
        ->execute([$code, $new_kop_id]);

    // Award referrer 10 credits
    add_credits($referrer_id, 10, 'Referral reward — ' . $new_kop_id . ' joined using your code');
    // Award new member 5 welcome credits
    add_credits($new_kop_id, 5, 'Welcome bonus — joined via referral');

    return true;
}

/**
 * How many people used this referral code.
 */
function get_referral_count(string $referral_code): int {
    $stmt = db()->prepare('SELECT COUNT(*) FROM members WHERE referred_by=?');
    $stmt->execute([$referral_code]);
    return (int)$stmt->fetchColumn();
}

/**
 * Credit transaction history for a member.
 */
function get_credit_history(string $kop_id, int $limit = 30): array {
    $stmt = db()->prepare(
        'SELECT * FROM credit_transactions WHERE member_kop_id=? ORDER BY created_at DESC LIMIT ?'
    );
    $stmt->execute([$kop_id, $limit]);
    return $stmt->fetchAll();
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

// ── CSRF Protection ───────────────────────────────────────────
function csrf_token(): string {
    session_start_safe();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:2rem;text-align:center">'
            . '<h2>⚠️ Invalid Request</h2>'
            . '<p>Security token mismatch. Please <a href="javascript:history.back()">go back</a> and try again.</p>'
            . '</div>');
    }
}

/**
 * Verify CSRF for JSON/AJAX endpoints via X-CSRF-Token header.
 * Sends 403 JSON response on failure.
 */
function verify_csrf_ajax(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$header || !hash_equals(csrf_token(), $header)) {
        http_response_code(403);
        header('Content-Type: application/json');
        die(json_encode(['error' => 'CSRF token invalid']));
    }
}

/**
 * Session-based rate limiter for AI endpoints.
 * Returns true if allowed; false if within the cooldown window.
 */
function ai_rate_limit(string $key = 'ai', int $seconds = 3): bool {
    session_start_safe();
    $ts_key = 'rl_' . $key;
    $now    = time();
    $last   = (int)($_SESSION[$ts_key] ?? 0);
    if ($now - $last < $seconds) return false;
    $_SESSION[$ts_key] = $now;
    return true;
}

// ── Admin Rate Limiting ───────────────────────────────────────
function check_admin_rate_limit(): ?int {
    session_start_safe();
    $attempts  = (int)($_SESSION['admin_attempts']  ?? 0);
    $last_fail = (int)($_SESSION['admin_last_fail'] ?? 0);
    $max       = defined('ADMIN_MAX_ATTEMPTS') ? ADMIN_MAX_ATTEMPTS : 5;
    $lockout   = defined('ADMIN_LOCKOUT_SEC')  ? ADMIN_LOCKOUT_SEC  : 900;
    if ($attempts >= $max) {
        $remaining = $lockout - (time() - $last_fail);
        if ($remaining > 0) return $remaining;
        $_SESSION['admin_attempts'] = 0; // reset after lockout expires
    }
    return null;
}

function record_admin_fail(): void {
    session_start_safe();
    $_SESSION['admin_attempts'] = (int)($_SESSION['admin_attempts'] ?? 0) + 1;
    $_SESSION['admin_last_fail'] = time();
}

function reset_admin_rate_limit(): void {
    session_start_safe();
    unset($_SESSION['admin_attempts'], $_SESSION['admin_last_fail']);
}

// ── Email Notifications ───────────────────────────────────────
function send_notification_email(string $to, string $subject, string $body_html): bool {
    if (!$to || NOTIFY_EMAIL !== '1') return false;
    $from    = defined('SITE_EMAIL') ? SITE_EMAIL : 'noreply@koponix.my';
    $name    = defined('SITE_NAME')  ? SITE_NAME  : 'Koponix';
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        "From: {$name} <{$from}>",
        "Reply-To: {$from}",
        'X-Mailer: Koponix Platform',
    ]);
    $wrapped = '<!DOCTYPE html><html><body style="font-family:Inter,sans-serif;background:#f0f4f8;padding:20px">'
        . '<div style="max-width:560px;margin:auto;background:#fff;border-radius:12px;padding:28px;box-shadow:0 2px 8px rgba(0,0,0,.08)">'
        . '<div style="font-size:1.3rem;font-weight:800;color:#1a5276;margin-bottom:16px">🤝 Koponix</div>'
        . $body_html
        . '<hr style="margin:24px 0;border:none;border-top:1px solid #eee">'
        . '<div style="font-size:.75rem;color:#aaa">Koponix — ' . (defined('KOPERASI_NAME') ? KOPERASI_NAME : '') . '<br>'
        . '<a href="' . (defined('SITE_URL') ? SITE_URL : '') . '" style="color:#2e86c1">' . (defined('SITE_URL') ? SITE_URL : '') . '</a></div>'
        . '</div></body></html>';
    return @mail($to, $subject, $wrapped, $headers);
}

function notify_listing_approved(array $seller): void {
    $m = get_member_by_kop_id($seller['koperasi_id']);
    if (!$m || empty($m['email'])) return;
    $title = htmlspecialchars($seller['service_title'], ENT_QUOTES, 'UTF-8');
    send_notification_email(
        $m['email'],
        '✅ Your listing has been approved — Koponix',
        "<h2 style='color:#1e8449'>✅ Listing Approved!</h2>
        <p>Hi <strong>{$m['name']}</strong>,</p>
        <p>Your listing <strong>\"{$title}\"</strong> has been reviewed and is now <strong>live</strong> on the Koponix marketplace.</p>
        <p>Members can now find and contact you through your listing.</p>
        <p><a href='" . MARKET_URL . "/' style='background:#1a5276;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;display:inline-block'>View Marketplace →</a></p>"
    );
}

function notify_listing_rejected(array $seller, string $reason): void {
    $m = get_member_by_kop_id($seller['koperasi_id']);
    if (!$m || empty($m['email'])) return;
    $title  = htmlspecialchars($seller['service_title'], ENT_QUOTES, 'UTF-8');
    $reason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
    send_notification_email(
        $m['email'],
        '❌ Action required on your Koponix listing',
        "<h2 style='color:#c0392b'>❌ Listing Needs Revision</h2>
        <p>Hi <strong>{$m['name']}</strong>,</p>
        <p>Your listing <strong>\"{$title}\"</strong> could not be approved at this time.</p>
        <p><strong>Reason:</strong> {$reason}</p>
        <p>Please log in, edit your listing to address the issue, and resubmit for review.</p>
        <p><a href='" . PORTAL_URL . "/?tab=listings' style='background:#1a5276;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;display:inline-block'>Edit My Listings →</a></p>"
    );
}

function notify_new_message(string $recipient_kop_id, string $sender_name, string $conv_id): void {
    $m = get_member_by_kop_id($recipient_kop_id);
    if (!$m || empty($m['email'])) return;
    $sender = htmlspecialchars($sender_name, ENT_QUOTES, 'UTF-8');
    send_notification_email(
        $m['email'],
        "💬 New message from {$sender_name} — Koponix",
        "<h2 style='color:#1a5276'>💬 You have a new message</h2>
        <p>Hi <strong>{$m['name']}</strong>,</p>
        <p><strong>{$sender}</strong> has sent you a message on Koponix.</p>
        <p><a href='" . PORTAL_URL . "/messages.php?conv=" . urlencode($conv_id) . "' style='background:#1a5276;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;display:inline-block'>Read Message →</a></p>
        <p style='color:#888;font-size:.85rem'>Log in to reply. You can disable email notifications in your profile settings.</p>"
    );
}

function notify_referral_bonus(string $referrer_kop_id, string $new_member_name, int $credits): void {
    $m = get_member_by_kop_id($referrer_kop_id);
    if (!$m || empty($m['email'])) return;
    $new_name = htmlspecialchars($new_member_name, ENT_QUOTES, 'UTF-8');
    send_notification_email(
        $m['email'],
        "🎉 You earned {$credits} credits! — Koponix Referral",
        "<h2 style='color:#e67e22'>🎉 Referral Bonus Credited!</h2>
        <p>Hi <strong>{$m['name']}</strong>,</p>
        <p><strong>{$new_name}</strong> just joined Koponix using your referral code.</p>
        <p>You've been credited <strong style='font-size:1.3rem;color:#e67e22'>{$credits} credits</strong> (RM " . number_format($credits, 2) . ")!</p>
        <p><a href='" . PORTAL_URL . "/?tab=credits' style='background:#e67e22;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;display:inline-block'>View My Credits →</a></p>"
    );
}

// ── Escape ───────────────────────────────────────────────────
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// ── Redirect ─────────────────────────────────────────────────
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}
