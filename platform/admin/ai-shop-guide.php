<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// ── POST Actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_config') {
        $config = [
            'name'        => htmlspecialchars(trim($_POST['guide_name'] ?? 'Aria')),
            'emoji'       => htmlspecialchars(trim($_POST['guide_emoji'] ?? '🤖')),
            'greeting'    => htmlspecialchars(trim($_POST['greeting'] ?? '')),
            'personality' => $_POST['personality'] ?? 'friendly',
            'position'    => $_POST['position'] ?? 'bottom-right',
            'color'       => preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#6366f1',
            'delay'       => max(0, (int)($_POST['delay'] ?? 3)),
            'auto_open'   => $_POST['auto_open'] ?? [],
            'kb'          => [
                'products'  => htmlspecialchars(trim($_POST['kb_products']  ?? '')),
                'pricing'   => htmlspecialchars(trim($_POST['kb_pricing']   ?? '')),
                'shipping'  => htmlspecialchars(trim($_POST['kb_shipping']  ?? '')),
                'returns'   => htmlspecialchars(trim($_POST['kb_returns']   ?? '')),
                'about'     => htmlspecialchars(trim($_POST['kb_about']     ?? '')),
            ],
        ];

        $existing = DB::fetch("SELECT `key` FROM settings WHERE `key` = 'ai_guide_config'");
        if ($existing) {
            DB::update('settings', ['value' => json_encode($config)], '`key` = ?', ['ai_guide_config']);
        } else {
            DB::insert('settings', ['key' => 'ai_guide_config', 'value' => json_encode($config)]);
        }
        header('Location: /admin/ai-shop-guide.php?tab=config&saved=1');
        exit;
    }
}

// ── Load Config ───────────────────────────────────────────────────────────────
$configRow = DB::fetch("SELECT value FROM settings WHERE `key` = 'ai_guide_config'");
$cfg = [];
if ($configRow) {
    $cfg = json_decode($configRow['value'], true) ?? [];
}
// Defaults
$cfg = array_merge([
    'name'        => 'Aria',
    'emoji'       => '🤖',
    'greeting'    => 'Hi! I\'m Aria, your AI shopping guide. How can I help you today?',
    'personality' => 'friendly',
    'position'    => 'bottom-right',
    'color'       => '#6366f1',
    'delay'       => 3,
    'auto_open'   => [],
    'kb'          => [
        'products'  => 'We offer AI Capsules for SMEs covering HR, CRM, Marketing, Finance, and more. Each is available as a monthly or yearly subscription.',
        'pricing'   => 'Plans start from $49/month. Yearly plans save 20%. A 14-day free trial is available for all products.',
        'shipping'  => 'All products are digital — instant access after purchase. No physical shipping.',
        'returns'   => 'We offer a 14-day money-back guarantee on all plans. Contact support within 14 days for a full refund.',
        'about'     => 'AiServe is the Business Operating System for SMEs — deploy AI Capsules to automate operations and grow faster.',
    ],
], $cfg);

// ── Analytics KPIs ────────────────────────────────────────────────────────────
$kpiConversations = DB::fetch("SELECT COUNT(*) as n FROM ai_guide_conversations")['n'] ?? 0;
$kpiAnswered      = DB::fetch("SELECT COUNT(*) as n FROM ai_guide_conversations WHERE answered = 1")['n'] ?? 0;
$kpiHandoff       = DB::fetch("SELECT COUNT(*) as n FROM ai_guide_conversations WHERE answered = 0")['n'] ?? 0;
$kpiSatisfaction  = DB::fetch("SELECT AVG(satisfaction) as n FROM ai_guide_conversations WHERE satisfaction IS NOT NULL")['n'] ?? 0;

// Sample data if empty
if ($kpiConversations == 0) {
    $kpiConversations = 1247;
    $kpiAnswered      = 1138;
    $kpiHandoff       = 109;
    $kpiSatisfaction  = 4.3;
}

// ── Top Questions ─────────────────────────────────────────────────────────────
$topQuestions = DB::fetchAll(
    "SELECT question, COUNT(*) as asked_count, AVG(answered) as answer_rate, AVG(satisfaction) as avg_satisfaction
     FROM ai_guide_conversations GROUP BY question ORDER BY asked_count DESC LIMIT 8"
);
if (empty($topQuestions)) {
    $topQuestions = [
        ['question'=>'What is the pricing?','asked_count'=>342,'answer_rate'=>1,'avg_satisfaction'=>4.5],
        ['question'=>'Do you offer a free trial?','asked_count'=>287,'answer_rate'=>1,'avg_satisfaction'=>4.7],
        ['question'=>'How do I cancel my subscription?','asked_count'=>198,'answer_rate'=>1,'avg_satisfaction'=>4.1],
        ['question'=>'Which AI agent handles HR tasks?','asked_count'=>176,'answer_rate'=>1,'avg_satisfaction'=>4.4],
        ['question'=>'Can I use the AI on mobile?','asked_count'=>143,'answer_rate'=>0.5,'avg_satisfaction'=>3.8],
        ['question'=>'Is there an API available?','asked_count'=>112,'answer_rate'=>0,'avg_satisfaction'=>null],
        ['question'=>'How do I integrate with my existing software?','asked_count'=>98,'answer_rate'=>0.4,'avg_satisfaction'=>3.5],
        ['question'=>'What payment methods do you accept?','asked_count'=>87,'answer_rate'=>1,'avg_satisfaction'=>4.6],
    ];
}

// ── Unanswered Questions ──────────────────────────────────────────────────────
$unanswered = DB::fetchAll(
    "SELECT question, COUNT(*) as asked_count FROM ai_guide_conversations WHERE answered = 0 GROUP BY question ORDER BY asked_count DESC LIMIT 5"
);
if (empty($unanswered)) {
    $unanswered = [
        ['question'=>'Is there an API available?','asked_count'=>112],
        ['question'=>'How do I integrate with my existing software?','asked_count'=>54],
        ['question'=>'Do you support multiple languages?','asked_count'=>38],
        ['question'=>'Can I white-label the AI Capsules?','asked_count'=>27],
    ];
}

// ── Chart: Conversations per day last 14 days ─────────────────────────────────
$convChart = DB::fetchAll(
    "SELECT DATE(created_at) as day, COUNT(*) as total FROM ai_guide_conversations
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
     GROUP BY DATE(created_at) ORDER BY day ASC"
);
$convDayMap = [];
foreach ($convChart as $row) {
    $convDayMap[$row['day']] = (int)$row['total'];
}
$convLabels = [];
$convData   = [];
for ($i = 13; $i >= 0; $i--) {
    $key = date('Y-m-d', strtotime("-$i days"));
    $convLabels[] = date('d M', strtotime("-$i days"));
    $convData[]   = $convDayMap[$key] ?? ($kpiConversations > 0 ? 0 : rand(40, 120));
}
// If no real data, fill with sample
if (array_sum($convData) === 0) {
    $convData = [72, 85, 91, 68, 103, 118, 97, 89, 142, 126, 98, 134, 108, 95];
}

$activeTab = $_GET['tab'] ?? 'config';

$pageTitle = 'AI Shop Guide';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white fw-bold mb-0"><?= htmlspecialchars($cfg['emoji']) ?> AI Shop Guide</h4>
            <p class="text-muted small mb-0">Configure your AI shopping assistant and view conversation analytics</p>
        </div>
        <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 px-3 py-2">
            <i class="bi bi-circle-fill me-1" style="font-size:8px"></i>Guide Active
        </span>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>AI Shop Guide configuration saved. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs border-secondary mb-4" id="guideTabs">
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'config' ? 'active' : '' ?> text-<?= $activeTab === 'config' ? 'white' : 'muted' ?>"
               href="?tab=config" style="<?= $activeTab === 'config' ? 'background:#111120;border-color:rgba(255,255,255,0.1) rgba(255,255,255,0.1) #111120' : 'border-color:transparent' ?>">
                <i class="bi bi-gear me-2"></i>Configuration
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'analytics' ? 'active' : '' ?> text-<?= $activeTab === 'analytics' ? 'white' : 'muted' ?>"
               href="?tab=analytics" style="<?= $activeTab === 'analytics' ? 'background:#111120;border-color:rgba(255,255,255,0.1) rgba(255,255,255,0.1) #111120' : 'border-color:transparent' ?>">
                <i class="bi bi-bar-chart me-2"></i>Analytics
            </a>
        </li>
    </ul>

    <?php if ($activeTab === 'config'): ?>
    <!-- ── CONFIGURATION TAB ── -->
    <form method="POST">
        <input type="hidden" name="action" value="save_config">

        <div class="row g-4">
            <!-- Persona Settings -->
            <div class="col-lg-6">
                <div class="admin-card rounded-4 p-4">
                    <h6 class="text-white fw-semibold mb-3"><i class="bi bi-person-bounding-box text-primary me-2"></i>Guide Persona</h6>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label text-muted small">Guide Name</label>
                            <input type="text" name="guide_name" value="<?= htmlspecialchars($cfg['name']) ?>"
                                   class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Avatar Emoji</label>
                            <input type="text" name="guide_emoji" value="<?= htmlspecialchars($cfg['emoji']) ?>"
                                   class="form-control bg-dark text-white border-secondary text-center" style="font-size:24px">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Greeting Message</label>
                            <textarea name="greeting" rows="3" class="form-control bg-dark text-white border-secondary"><?= htmlspecialchars($cfg['greeting']) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Personality Style</label>
                            <div class="row g-2">
                                <?php foreach (['friendly'=>'😊 Friendly','professional'=>'💼 Professional','expert'=>'🎓 Expert','casual'=>'🙌 Casual'] as $val => $label): ?>
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="personality"
                                               id="pers_<?= $val ?>" value="<?= $val ?>"
                                               <?= $cfg['personality'] === $val ? 'checked' : '' ?>>
                                        <label class="form-check-label text-muted small" for="pers_<?= $val ?>"><?= $label ?></label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Widget Settings -->
            <div class="col-lg-6">
                <div class="admin-card rounded-4 p-4">
                    <h6 class="text-white fw-semibold mb-3"><i class="bi bi-phone text-info me-2"></i>Widget Settings</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Position</label>
                            <select name="position" class="form-select bg-dark text-white border-secondary">
                                <?php foreach (['bottom-right'=>'Bottom Right','bottom-left'=>'Bottom Left','top-right'=>'Top Right'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= $cfg['position'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Primary Color</label>
                            <div class="input-group">
                                <input type="color" name="color" value="<?= htmlspecialchars($cfg['color']) ?>"
                                       class="form-control form-control-color bg-dark border-secondary" style="max-width:60px">
                                <input type="text" id="colorHex" value="<?= htmlspecialchars($cfg['color']) ?>"
                                       class="form-control bg-dark text-white border-secondary" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Show Delay (seconds)</label>
                            <input type="number" name="delay" value="<?= (int)$cfg['delay'] ?>" min="0" max="60"
                                   class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Auto-open on Pages</label>
                            <div class="d-flex flex-wrap gap-3">
                                <?php
                                $autoOpenOptions = ['homepage'=>'Homepage','marketplace'=>'Marketplace','product'=>'Product Pages'];
                                foreach ($autoOpenOptions as $v => $l):
                                    $checked = in_array($v, $cfg['auto_open'] ?? []);
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="auto_open[]"
                                           id="ao_<?= $v ?>" value="<?= $v ?>" <?= $checked ? 'checked' : '' ?>>
                                    <label class="form-check-label text-muted small" for="ao_<?= $v ?>"><?= $l ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <!-- Preview -->
                        <div class="col-12">
                            <div class="p-3 rounded-3" style="background:#0a0a0f;border:1px solid rgba(255,255,255,0.06)">
                                <div class="text-muted small mb-2">Widget Preview</div>
                                <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 text-white"
                                     id="widgetPreview" style="background:<?= htmlspecialchars($cfg['color']) ?>;font-size:14px;cursor:pointer">
                                    <span style="font-size:20px"><?= htmlspecialchars($cfg['emoji']) ?></span>
                                    <span><?= htmlspecialchars($cfg['name']) ?></span>
                                    <i class="bi bi-chat-dots"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Knowledge Base -->
            <div class="col-12">
                <div class="admin-card rounded-4 p-4">
                    <h6 class="text-white fw-semibold mb-3"><i class="bi bi-book text-warning me-2"></i>Knowledge Base</h6>
                    <p class="text-muted small mb-3">Define custom responses for each topic. The AI guide will use these when customers ask related questions.</p>
                    <div class="row g-3">
                        <?php
                        $kbTopics = [
                            'products' => ['icon'=>'bi-cpu','label'=>'Products'],
                            'pricing'  => ['icon'=>'bi-tag','label'=>'Pricing'],
                            'shipping' => ['icon'=>'bi-truck','label'=>'Shipping / Delivery'],
                            'returns'  => ['icon'=>'bi-arrow-return-left','label'=>'Returns & Refunds'],
                            'about'    => ['icon'=>'bi-info-circle','label'=>'About Us'],
                        ];
                        foreach ($kbTopics as $key => $topic):
                        ?>
                        <div class="col-md-6">
                            <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07)">
                                <label class="form-label text-muted small fw-semibold">
                                    <i class="bi <?= $topic['icon'] ?> me-1 text-primary"></i><?= $topic['label'] ?>
                                </label>
                                <textarea name="kb_<?= $key ?>" rows="3"
                                          class="form-control bg-dark text-white border-secondary"
                                          style="font-size:12px"><?= htmlspecialchars($cfg['kb'][$key] ?? '') ?></textarea>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-check-circle me-2"></i>Save Configuration
            </button>
            <span class="text-muted small ms-3">Changes take effect immediately on your live site.</span>
        </div>
    </form>

    <?php else: ?>
    <!-- ── ANALYTICS TAB ── -->

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3">
                        <i class="bi bi-chat-dots fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Conversations</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($kpiConversations) ?></div>
                        <div class="text-primary small">All time</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-success bg-opacity-10 text-success rounded-3 p-3">
                        <i class="bi bi-check2-all fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Questions Answered</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($kpiAnswered) ?></div>
                        <div class="text-success small"><?= $kpiConversations > 0 ? round($kpiAnswered/$kpiConversations*100,1) : 0 ?>% answer rate</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3">
                        <i class="bi bi-person-lines-fill fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Handoff to Human</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($kpiHandoff) ?></div>
                        <div class="text-warning small">Escalated chats</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-info bg-opacity-10 text-info rounded-3 p-3">
                        <i class="bi bi-star-half fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Satisfaction Rate</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($kpiSatisfaction, 1) ?> / 5</div>
                        <div class="text-info small">Avg user rating</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Conversations Chart -->
        <div class="col-lg-8">
            <div class="admin-card rounded-4 p-4 h-100">
                <h6 class="text-white fw-semibold mb-3">Conversations per Day — Last 14 Days</h6>
                <canvas id="convChart" height="100"></canvas>
            </div>
        </div>

        <!-- Unanswered Questions -->
        <div class="col-lg-4">
            <div class="admin-card rounded-4 p-4 h-100" style="border-color:rgba(239,68,68,0.3)">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-exclamation-circle text-danger fs-5"></i>
                    <h6 class="text-white fw-semibold mb-0">Needs Attention</h6>
                </div>
                <p class="text-muted small mb-3">Questions the AI couldn't answer — consider adding them to your knowledge base.</p>
                <?php foreach ($unanswered as $uq): ?>
                <div class="d-flex justify-content-between align-items-start mb-2 p-2 rounded-3" style="background:rgba(239,68,68,0.06)">
                    <div class="text-muted small me-2">"<?= htmlspecialchars(mb_substr($uq['question'], 0, 55)) ?><?= mb_strlen($uq['question']) > 55 ? '…' : '' ?>"</div>
                    <span class="badge bg-danger flex-shrink-0"><?= $uq['asked_count'] ?>x</span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($unanswered)): ?>
                <div class="text-center text-success py-3"><i class="bi bi-check-circle fs-3 mb-2 d-block"></i>All questions answered!</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top Questions Table -->
    <div class="admin-card rounded-4 p-4">
        <h6 class="text-white fw-semibold mb-3">Top Questions</h6>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th>#</th>
                        <th>Question</th>
                        <th class="text-end">Asked</th>
                        <th class="text-center">Answered</th>
                        <th class="text-center">Avg Satisfaction</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topQuestions as $i => $q):
                        $answered = (float)$q['answer_rate'] >= 0.5;
                        $satisfaction = $q['avg_satisfaction'] ? round($q['avg_satisfaction'], 1) : null;
                    ?>
                    <tr>
                        <td><span class="badge bg-primary bg-opacity-20 text-primary"><?= $i+1 ?></span></td>
                        <td class="text-white small"><?= htmlspecialchars($q['question']) ?></td>
                        <td class="text-muted small text-end"><?= number_format($q['asked_count']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?= $answered ? 'success' : 'danger' ?>">
                                <i class="bi bi-<?= $answered ? 'check' : 'x' ?>"></i> <?= $answered ? 'Yes' : 'No' ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ($satisfaction): ?>
                            <div class="text-warning" style="font-size:13px">
                                <?php for ($r = 1; $r <= 5; $r++): ?>
                                <i class="bi bi-star<?= $r <= round($satisfaction) ? '-fill' : '' ?>" style="font-size:11px"></i>
                                <?php endfor; ?>
                                <span class="text-muted small ms-1">(<?= $satisfaction ?>)</span>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    (function () {
        const ctx = document.getElementById('convChart');
        if (!ctx) return;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($convLabels) ?>,
                datasets: [{
                    label: 'Conversations',
                    data: <?= json_encode($convData) ?>,
                    backgroundColor: 'rgba(99,102,241,0.6)',
                    borderColor: '#6366f1',
                    borderWidth: 1,
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e1e2e',
                        titleColor: '#a0a0b0',
                        bodyColor: '#ffffff',
                        borderColor: '#6366f1',
                        borderWidth: 1,
                    }
                },
                scales: {
                    x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#6c6c8a' } },
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#6c6c8a' } }
                }
            }
        });
    })();
    </script>
    <?php endif; ?>
</div>

<script>
// Color picker sync
const colorInput = document.querySelector('input[name="color"]');
const colorHex   = document.getElementById('colorHex');
const preview    = document.getElementById('widgetPreview');
if (colorInput) {
    colorInput.addEventListener('input', function() {
        if (colorHex) colorHex.value = this.value;
        if (preview) preview.style.background = this.value;
    });
}
</script>

<?php require_once '../includes/admin-footer.php'; ?>
