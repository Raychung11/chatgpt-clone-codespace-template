<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireLogin();
$pageTitle = 'AI Tools';
require_once '../includes/header.php';

// Load from ai_modules table; graceful fallback if table not yet created
$tableExists = DB::fetch("SHOW TABLES LIKE 'ai_modules'");
if ($tableExists) {
    $allModules = DB::fetchAll("SELECT * FROM ai_modules WHERE is_active = 1 ORDER BY sort_order, name");
} else {
    $allModules = [];
}

// Group by category preserving sort order of first appearance
$groups     = [];
$groupOrder = [];
foreach ($allModules as $m) {
    $cat = $m['category'];
    if (!isset($groups[$cat])) {
        $groups[$cat]     = [];
        $groupOrder[]     = $cat;
    }
    $groups[$cat][] = $m;
}
$totalCount = count($allModules);
?>

<div class="container py-5">
    <div class="mb-4">
        <h2 class="text-white fw-bold mb-1"><i class="bi bi-cpu-fill me-2 text-primary"></i>AI Tools</h2>
        <p class="text-muted"><?= $totalCount ?> AI-powered tools to automate your everyday business tasks</p>
    </div>

    <!-- Search + Tag Filters -->
    <div class="glass-card rounded-4 p-3 mb-5">
        <div class="row g-3 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text" style="background:#1a1a2e;border-color:#2a2a3e;color:#6b7280">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="toolSearch" class="form-control" placeholder="Search tools…"
                           style="background:#1a1a2e;border-color:#2a2a3e;color:#e5e7eb">
                </div>
            </div>
            <div class="col-md-8">
                <div class="d-flex flex-wrap gap-2" id="tagFilters">
                    <button class="btn btn-sm tag-btn active" data-tag="all"
                            style="background:#6366f118;border:1px solid #6366f144;color:#6366f1">All</button>
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

    <?php if (empty($allModules)): ?>
    <div class="text-center py-5">
        <i class="bi bi-cpu fs-1 d-block mb-3" style="opacity:0.2;color:#6366f1"></i>
        <p class="text-muted">No modules registered yet. Ask your admin to add modules via the admin panel.</p>
    </div>
    <?php else: ?>

    <?php foreach ($groupOrder as $groupName):
        $tools = $groups[$groupName];
    ?>
    <div class="tool-group mb-5">
        <h6 class="group-label text-muted fw-semibold mb-3 text-uppercase"
            style="font-size:11px;letter-spacing:.08em"><?= htmlspecialchars($groupName) ?></h6>
        <div class="row g-3">
            <?php foreach ($tools as $m): ?>
            <div class="col-sm-6 col-lg-3 tool-col"
                 data-name="<?= strtolower(htmlspecialchars($m['name'])) ?>"
                 data-desc="<?= strtolower(htmlspecialchars($m['description'] ?? '')) ?>"
                 data-tags="<?= htmlspecialchars($m['tags'] ?? '') ?>">
                <div class="glass-card rounded-4 p-4 h-100 d-flex flex-column"
                     style="transition:border-color .2s"
                     onmouseover="this.style.borderColor='<?= htmlspecialchars($m['color']) ?>44'"
                     onmouseout="this.style.borderColor=''">
                    <div class="mb-3" style="width:48px;height:48px;border-radius:13px;background:<?= htmlspecialchars($m['color']) ?>18;color:<?= htmlspecialchars($m['color']) ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="bi <?= htmlspecialchars($m['icon']) ?> fs-5"></i>
                    </div>
                    <h6 class="text-white fw-semibold mb-2"><?= htmlspecialchars($m['name']) ?></h6>
                    <p class="text-muted small mb-4 flex-grow-1" style="font-size:12.5px"><?= htmlspecialchars($m['description'] ?? '') ?></p>
                    <?php
                        $isFullApp = $m['slug'] === 'project-os';
                        $href  = $isFullApp ? '/projects/' : '/modules/' . htmlspecialchars($m['slug']) . '.php';
                        $label = $isFullApp ? '<i class="bi bi-kanban me-1"></i>Launch App' : '<i class="bi bi-magic me-1"></i>Open Tool';
                    ?>
                    <a href="<?= $href ?>"
                       class="btn btn-sm w-100"
                       style="background:<?= htmlspecialchars($m['color']) ?>18;border:1px solid <?= htmlspecialchars($m['color']) ?>33;color:<?= htmlspecialchars($m['color']) ?>">
                        <?= $label ?>
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
        <p class="text-muted">No tools match your search.
            <button class="btn btn-link p-0 text-primary" onclick="resetFilters()">Clear filters</button>
        </p>
    </div>

    <?php endif; ?>

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

const defaultStyle = 'background:transparent;border:1px solid #2a2a3e;color:#6b7280';
const activeStyle  = 'background:#6366f118;border:1px solid #6366f144;color:#6366f1';

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
    const q          = searchInput.value.toLowerCase().trim();
    const cols       = document.querySelectorAll('.tool-col');
    let   anyVisible = false;

    cols.forEach(col => {
        const nameMatch = col.dataset.name.includes(q) || col.dataset.desc.includes(q);
        const tagMatch  = activeTag === 'all' || (col.dataset.tags || '').split(',').includes(activeTag);
        const show      = nameMatch && tagMatch;
        col.style.display = show ? '' : 'none';
        if (show) anyVisible = true;
    });

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
