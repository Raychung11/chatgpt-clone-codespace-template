<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireLogin();
$pageTitle = 'AI Tools';
require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="mb-4">
        <h2 class="text-white fw-bold mb-1"><i class="bi bi-cpu-fill me-2 text-primary"></i>AI Tools</h2>
        <p class="text-muted">20 AI-powered tools to automate your everyday business tasks</p>
    </div>

    <!-- Search + Tag Filters -->
    <div class="glass-card rounded-4 p-3 mb-5">
        <div class="row g-3 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text" style="background:#1a1a2e;border-color:#2a2a3e;color:#6b7280">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="toolSearch" class="form-control" placeholder="Search tools…" style="background:#1a1a2e;border-color:#2a2a3e;color:#e5e7eb">
                </div>
            </div>
            <div class="col-md-8">
                <div class="d-flex flex-wrap gap-2" id="tagFilters">
                    <button class="btn btn-sm tag-btn active" data-tag="all" style="background:#6366f118;border:1px solid #6366f144;color:#6366f1">All</button>
                    <button class="btn btn-sm tag-btn" data-tag="writing">Writing</button>
                    <button class="btn btn-sm tag-btn" data-tag="sales">Sales</button>
                    <button class="btn btn-sm tag-btn" data-tag="finance">Finance</button>
                    <button class="btn btn-sm tag-btn" data-tag="hr">HR</button>
                    <button class="btn btn-sm tag-btn" data-tag="marketing">Marketing</button>
                    <button class="btn btn-sm tag-btn" data-tag="legal">Legal</button>
                    <button class="btn btn-sm tag-btn" data-tag="automation">Automation</button>
                </div>
            </div>
        </div>
    </div>

    <?php
    // [name, url, icon, color, desc, tags]
    $groups = [
        'Communication' => [
            ['Email Writer',         '/modules/email-writer.php',        'bi-envelope-paper',          '#6366f1', 'Write professional emails in seconds — sales, follow-ups, proposals.',                    'writing,communication'],
            ['Social Post Generator','/modules/social-post.php',         'bi-share',                   '#10b981', 'Platform-ready posts for Facebook, Instagram, LinkedIn & Twitter.',                        'writing,marketing'],
            ['Customer Reply',       '/modules/customer-reply.php',      'bi-chat-dots',               '#14b8a6', 'Reply to complaints, enquiries, and reviews with confidence.',                             'writing,communication'],
            ['Ad Copy Writer',       '/modules/ad-copy.php',             'bi-megaphone',               '#ec4899', 'High-converting ad copy for Google, Facebook, TikTok & LinkedIn.',                        'writing,marketing'],
        ],
        'Sales & Operations' => [
            ['Sales Proposal',       '/modules/sales-proposal.php',      'bi-file-earmark-text',       '#f97316', 'Draft a professional proposal that wins the deal.',                                        'writing,sales'],
            ['Invoice Generator',    '/modules/invoice-generator.php',   'bi-receipt',                 '#f59e0b', 'Create professional invoices and print or save as PDF.',                                   'finance,sales'],
            ['Product Description',  '/modules/product-description.php', 'bi-bag',                     '#6366f1', 'SEO-ready product copy for your website, Shopee, or Amazon.',                             'writing,marketing'],
            ['SOP Generator',        '/modules/sop-generator.php',       'bi-list-ol',                 '#3b82f6', 'Turn rough process notes into a full Standard Operating Procedure.',                      'hr,writing'],
        ],
        'HR & People' => [
            ['Job Description',      '/modules/job-description.php',     'bi-person-badge',            '#06b6d4', 'Create compelling JDs that attract the right talent.',                                     'hr,writing'],
            ['Leave Request',        '/modules/leave-request.php',       'bi-calendar-check',          '#ef4444', 'Submit leave requests with AI-drafted messages and track history.',                        'hr'],
            ['Performance Review',   '/modules/performance-review.php',  'bi-star-half',               '#84cc16', 'Generate balanced, professional reviews for any staff member.',                            'hr,writing'],
            ['Meeting Minutes',      '/modules/meeting-minutes.php',     'bi-journal-text',            '#8b5cf6', 'Transform rough notes into structured meeting minutes instantly.',                         'hr,writing'],
        ],
        'Strategy & Finance' => [
            ['Financial Analysis',   '/modules/financial-analysis.php',  'bi-bar-chart-line',          '#10b981', 'Paste your numbers — get plain-English analysis, red flags, and recommendations.',        'finance'],
            ['Business Report',      '/modules/business-report.php',     'bi-file-earmark-bar-chart',  '#3b82f6', 'Generate professional monthly, quarterly, or annual business reports.',                   'finance,writing'],
            ['Pitch Deck Generator', '/modules/pitch-deck.php',          'bi-easel',                   '#f59e0b', 'Slide-by-slide pitch deck content that wins investors and clients.',                       'sales,writing'],
            ['Cold Outreach',        '/modules/cold-outreach.php',       'bi-send',                    '#f97316', 'Multi-touch cold email and LinkedIn sequences that get replies.',                          'sales,writing'],
        ],
        'Automation & Systems' => [
            ['WhatsApp Templates',   '/modules/whatsapp-templates.php',  'bi-whatsapp',                '#25d366', 'Broadcast messages and campaign templates optimised for WhatsApp.',                       'marketing,communication'],
            ['Chatbot Flow Designer','/modules/chatbot-flow.php',        'bi-robot',                   '#06b6d4', 'Design your WhatsApp or website chatbot conversation flow with AI.',                      'automation'],
            ['Contract Drafter',     '/modules/contract-drafter.php',    'bi-file-earmark-lock',       '#14b8a6', 'Draft NDAs, service agreements, and employment contracts in minutes.',                    'legal'],
            ['Training Creator',     '/modules/training-creator.php',    'bi-mortarboard',             '#8b5cf6', 'Build full training modules, onboarding guides, and quizzes with AI.',                   'hr,writing'],
        ],
    ];
    foreach ($groups as $groupName => $tools):
    ?>
    <div class="tool-group mb-5">
        <h6 class="group-label text-muted fw-semibold mb-3 text-uppercase" style="font-size:11px;letter-spacing:.08em"><?= $groupName ?></h6>
        <div class="row g-3">
            <?php foreach ($tools as [$name, $url, $icon, $color, $desc, $tags]): ?>
            <div class="col-sm-6 col-lg-3 tool-col" data-name="<?= strtolower(htmlspecialchars($name)) ?>" data-desc="<?= strtolower(htmlspecialchars($desc)) ?>" data-tags="<?= htmlspecialchars($tags) ?>">
                <div class="glass-card rounded-4 p-4 h-100 d-flex flex-column" style="transition:border-color .2s" onmouseover="this.style.borderColor='<?= $color ?>44'" onmouseout="this.style.borderColor=''">
                    <div class="mb-3" style="width:48px;height:48px;border-radius:13px;background:<?= $color ?>18;color:<?= $color ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="bi <?= $icon ?> fs-5"></i>
                    </div>
                    <h6 class="text-white fw-semibold mb-2"><?= $name ?></h6>
                    <p class="text-muted small mb-4 flex-grow-1" style="font-size:12.5px"><?= $desc ?></p>
                    <a href="<?= $url ?>" class="btn btn-sm w-100" style="background:<?= $color ?>18;border:1px solid <?= $color ?>33;color:<?= $color ?>">
                        <i class="bi bi-magic me-1"></i>Open Tool
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- No results state -->
    <div id="noResults" class="text-center py-5 d-none">
        <i class="bi bi-search fs-1 d-block mb-3" style="opacity:0.2;color:#6366f1"></i>
        <p class="text-muted">No tools match your search. <button class="btn btn-link p-0 text-primary" onclick="resetFilters()">Clear filters</button></p>
    </div>

    <div class="mt-2">
        <a href="/dashboard.php" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>
</div>

<script>
const searchInput = document.getElementById('toolSearch');
const tagBtns     = document.querySelectorAll('.tag-btn');
let activeTag     = 'all';

/* Tag button styles */
const defaultStyle  = 'background:transparent;border:1px solid #2a2a3e;color:#6b7280';
const activeStyle   = 'background:#6366f118;border:1px solid #6366f144;color:#6366f1';

tagBtns.forEach(btn => {
    btn.setAttribute('style', btn.dataset.tag === 'all' ? activeStyle : defaultStyle);
    btn.addEventListener('click', () => {
        activeTag = btn.dataset.tag;
        tagBtns.forEach(b => b.setAttribute('style', defaultStyle));
        btn.setAttribute('style', activeStyle);
        applyFilters();
    });
});

searchInput.addEventListener('input', applyFilters);

function applyFilters() {
    const q    = searchInput.value.toLowerCase().trim();
    const cols = document.querySelectorAll('.tool-col');
    let anyVisible = false;

    cols.forEach(col => {
        const nameMatch = col.dataset.name.includes(q) || col.dataset.desc.includes(q);
        const tagMatch  = activeTag === 'all' || col.dataset.tags.split(',').includes(activeTag);
        const show      = nameMatch && tagMatch;
        col.style.display = show ? '' : 'none';
        if (show) anyVisible = true;
    });

    /* Hide group headers when all their cards are hidden */
    document.querySelectorAll('.tool-group').forEach(group => {
        const visible = [...group.querySelectorAll('.tool-col')].some(c => c.style.display !== 'none');
        group.style.display = visible ? '' : 'none';
    });

    document.getElementById('noResults').classList.toggle('d-none', anyVisible);
}

function resetFilters() {
    searchInput.value = '';
    activeTag = 'all';
    tagBtns.forEach(b => b.setAttribute('style', defaultStyle));
    document.querySelector('[data-tag="all"]').setAttribute('style', activeStyle);
    applyFilters();
}
</script>

<?php require_once '../includes/footer.php'; ?>
