<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireAdmin();

/* ── Handle POST saves ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['save_action'] ?? '';

    /* Save active theme */
    if ($action === 'save_theme') {
        $theme = $_POST['theme'] ?? 'dark';
        $allowed = ['dark','bright','modern','zen','punk'];
        if (!in_array($theme, $allowed)) $theme = 'dark';
        $existing = DB::fetch("SELECT id FROM settings WHERE `key`='active_theme'");
        if ($existing) {
            DB::update('settings', ['value' => $theme], 'key=?', ['active_theme']);
        } else {
            DB::insert('settings', ['key' => 'active_theme', 'value' => $theme]);
        }
    }

    /* Save sections order + visibility */
    if ($action === 'save_sections') {
        $order      = $_POST['sections_order'] ?? '[]';
        $visibility = [];
        $sections   = ['hero','categories','featured','how_it_works','pricing','testimonials','cta','contact'];
        foreach ($sections as $s) {
            $visibility[$s] = isset($_POST['vis_' . $s]) ? 1 : 0;
        }

        $orderArr = json_decode($order, true);
        if (!is_array($orderArr)) $orderArr = $sections;

        $orderJson = json_encode($orderArr);
        $visJson   = json_encode($visibility);

        foreach ([['page_sections_order', $orderJson], ['page_sections_visibility', $visJson]] as [$k, $v]) {
            $exists = DB::fetch("SELECT id FROM settings WHERE `key`=?", [$k]);
            if ($exists) {
                DB::update('settings', ['value' => $v], 'key=?', [$k]);
            } else {
                DB::insert('settings', ['key' => $k, 'value' => $v]);
            }
        }
    }

    /* Save nav items */
    if ($action === 'save_nav') {
        $labels = $_POST['nav_label'] ?? [];
        $urls   = $_POST['nav_url']   ?? [];
        $navItems = [];
        for ($i = 0; $i < count($labels); $i++) {
            $label = trim($labels[$i] ?? '');
            $url   = trim($urls[$i]   ?? '');
            if ($label && $url) {
                $navItems[] = ['label' => htmlspecialchars($label), 'url' => htmlspecialchars($url)];
            }
        }
        $navJson = json_encode($navItems);
        $exists  = DB::fetch("SELECT id FROM settings WHERE `key`='nav_items'");
        if ($exists) {
            DB::update('settings', ['value' => $navJson], 'key=?', ['nav_items']);
        } else {
            DB::insert('settings', ['key' => 'nav_items', 'value' => $navJson]);
        }
    }

    header('Location: /admin/homepage-builder.php?saved=1');
    exit;
}

/* ── Load settings ── */
$activeTheme = DB::fetch("SELECT value FROM settings WHERE `key`='active_theme'")['value'] ?? 'dark';
$sectionsOrderRaw = DB::fetch("SELECT value FROM settings WHERE `key`='page_sections_order'")['value'] ?? '[]';
$sectionsVisRaw   = DB::fetch("SELECT value FROM settings WHERE `key`='page_sections_visibility'")['value'] ?? '{}';
$navItemsRaw      = DB::fetch("SELECT value FROM settings WHERE `key`='nav_items'")['value'] ?? '[]';

$sectionsOrder = json_decode($sectionsOrderRaw, true) ?: ['hero','categories','featured','how_it_works','pricing','testimonials','cta','contact'];
$sectionsVis   = json_decode($sectionsVisRaw, true)   ?: [];
$navItems      = json_decode($navItemsRaw, true)       ?: [];

$sectionLabels = [
    'hero'         => 'Hero Banner',
    'categories'   => 'Category Grid',
    'featured'     => 'Featured Products',
    'how_it_works' => 'How It Works',
    'pricing'      => 'Pricing Section',
    'testimonials' => 'Testimonials',
    'cta'          => 'Call to Action',
    'contact'      => 'Contact Strip',
];

$sectionIcons = [
    'hero'         => 'bi-layout-text-window',
    'categories'   => 'bi-grid-3x3-gap',
    'featured'     => 'bi-star',
    'how_it_works' => 'bi-list-ol',
    'pricing'      => 'bi-tag',
    'testimonials' => 'bi-chat-quote',
    'cta'          => 'bi-megaphone',
    'contact'      => 'bi-envelope',
];

$pageTitle = 'Homepage Builder';
$extraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.css">';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid px-4 py-4">

  <!-- Page header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="fw-bold mb-1"><i class="bi bi-layout-text-window-reverse me-2 text-primary"></i>Homepage Builder</h4>
      <p class="text-muted small mb-0">Customise your site's theme, page sections, and navigation</p>
    </div>
    <div class="d-flex gap-2">
      <a href="/" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye me-1"></i>Preview Site</a>
    </div>
  </div>

  <?php if (isset($_GET['saved'])): ?>
  <div class="alert alert-success d-flex align-items-center gap-2 rounded-3 mb-4" role="alert" id="saveAlert">
    <i class="bi bi-check-circle-fill"></i>
    <span>Settings saved successfully!</span>
  </div>
  <?php endif; ?>

  <div class="row g-4">

    <!-- ── Theme Selector ── -->
    <div class="col-12">
      <div class="card bg-dark border-secondary rounded-4">
        <div class="card-header border-secondary d-flex align-items-center gap-2 py-3">
          <i class="bi bi-palette2 text-primary"></i>
          <h6 class="mb-0 fw-semibold">Theme Selector</h6>
        </div>
        <div class="card-body p-4">
          <form method="POST">
            <input type="hidden" name="save_action" value="save_theme">
            <?php
            $themes = [
              'dark'   => ['label'=>'Dark',    'desc'=>'Classic deep dark with indigo',  'text'=>'#e5e7eb', 'bg'=>'#0a0a0f', 'primary'=>'#6366f1'],
              'bright' => ['label'=>'Bright',  'desc'=>'Clean white with indigo accents','text'=>'#111827', 'bg'=>'#f8fafc', 'primary'=>'#6366f1'],
              'modern' => ['label'=>'Modern',  'desc'=>'Slate dark with cyan energy',    'text'=>'#e2e8f0', 'bg'=>'#0f172a', 'primary'=>'#06b6d4'],
              'zen'    => ['label'=>'Zen',     'desc'=>'Warm cream with emerald calm',   'text'=>'#2d3748', 'bg'=>'#faf7f0', 'primary'=>'#059669'],
              'punk'   => ['label'=>'Punk',    'desc'=>'Dark with hot pink & neon green','text'=>'#f0e6ff', 'bg'=>'#0d0008', 'primary'=>'#f000b8'],
            ];
            foreach ($themes as $key => $theme):
              $isActive = ($activeTheme === $key);
            ?>
            <div class="form-check d-none">
              <input class="form-check-input" type="radio" name="theme" id="theme_<?= $key ?>" value="<?= $key ?>" <?= $isActive ? 'checked' : '' ?>>
            </div>
            <label for="theme_<?= $key ?>" class="d-inline-flex flex-column align-items-center me-3 mb-3" style="cursor:pointer;">
              <div class="theme-preview-card rounded-3 mb-2 position-relative overflow-hidden"
                   style="width:140px;height:100px;background:<?= $theme['bg'] ?>;border:2px solid <?= $isActive ? $theme['primary'] : 'rgba(255,255,255,0.10)' ?>;transition:border-color 0.2s;">
                <!-- Mini navbar -->
                <div style="height:18px;background:<?= $theme['bg'] ?>;border-bottom:1px solid <?= $theme['primary'] ?>33;display:flex;align-items:center;padding:0 8px;gap:4px;">
                  <div style="width:6px;height:6px;border-radius:50%;background:<?= $theme['primary'] ?>;"></div>
                  <div style="width:20px;height:3px;background:<?= $theme['text'] ?>33;border-radius:2px;"></div>
                  <div style="width:20px;height:3px;background:<?= $theme['text'] ?>22;border-radius:2px;margin-left:auto;"></div>
                </div>
                <!-- Hero mockup -->
                <div style="padding:8px;">
                  <div style="width:60%;height:6px;background:<?= $theme['primary'] ?>;border-radius:3px;margin-bottom:4px;"></div>
                  <div style="width:80%;height:4px;background:<?= $theme['text'] ?>33;border-radius:2px;margin-bottom:6px;"></div>
                  <div style="width:40px;height:16px;background:<?= $theme['primary'] ?>;border-radius:4px;"></div>
                </div>
                <!-- Color swatches -->
                <div style="position:absolute;bottom:6px;right:6px;display:flex;gap:3px;">
                  <div style="width:12px;height:12px;border-radius:50%;background:<?= $theme['bg'] ?>;border:1px solid rgba(255,255,255,0.2);"></div>
                  <div style="width:12px;height:12px;border-radius:50%;background:<?= $theme['primary'] ?>;"></div>
                  <div style="width:12px;height:12px;border-radius:50%;background:<?= $theme['text'] ?>;opacity:0.6;"></div>
                </div>
                <?php if ($isActive): ?>
                <div style="position:absolute;top:4px;right:4px;background:<?= $theme['primary'] ?>;color:#fff;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:11px;">
                  <i class="bi bi-check"></i>
                </div>
                <?php endif; ?>
              </div>
              <div class="fw-semibold small text-white"><?= htmlspecialchars($theme['label']) ?></div>
              <div class="text-muted" style="font-size:11px;text-align:center;max-width:120px;"><?= htmlspecialchars($theme['desc']) ?></div>
            </label>
            <?php endforeach; ?>
            <div class="mt-3">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle me-1"></i>Save Theme
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ── Page Sections Manager ── -->
    <div class="col-lg-7">
      <div class="card bg-dark border-secondary rounded-4 h-100">
        <div class="card-header border-secondary d-flex align-items-center gap-2 py-3">
          <i class="bi bi-list-ul text-primary"></i>
          <h6 class="mb-0 fw-semibold">Page Sections Manager</h6>
          <span class="text-muted small ms-auto">Drag to reorder</span>
        </div>
        <div class="card-body p-4">
          <form method="POST" id="sectionsForm">
            <input type="hidden" name="save_action" value="save_sections">
            <input type="hidden" name="sections_order" id="sections_order_input" value="<?= htmlspecialchars($sectionsOrderRaw) ?>">

            <div id="sortable-sections">
              <?php foreach ($sectionsOrder as $sKey):
                if (!isset($sectionLabels[$sKey])) continue;
                $isVisible = ($sectionsVis[$sKey] ?? 1) == 1;
              ?>
              <div class="section-row d-flex align-items-center gap-3 p-3 mb-2 rounded-3"
                   style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);cursor:grab;"
                   data-key="<?= $sKey ?>">
                <i class="bi bi-grip-vertical text-muted" style="cursor:grab;"></i>
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(99,102,241,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                  <i class="bi <?= $sectionIcons[$sKey] ?> text-primary"></i>
                </div>
                <div class="flex-grow-1">
                  <div class="fw-semibold small"><?= htmlspecialchars($sectionLabels[$sKey]) ?></div>
                  <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($sKey) ?></div>
                </div>
                <div class="form-check form-switch mb-0">
                  <input class="form-check-input" type="checkbox" name="vis_<?= $sKey ?>"
                    id="vis_<?= $sKey ?>" <?= $isVisible ? 'checked' : '' ?> style="cursor:pointer;">
                  <label class="form-check-label text-muted small" for="vis_<?= $sKey ?>">
                    <?= $isVisible ? 'Visible' : 'Hidden' ?>
                  </label>
                </div>
              </div>
              <?php endforeach; ?>
            </div>

            <div class="mt-3">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>Save Sections
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ── Nav Builder + Quick Links ── -->
    <div class="col-lg-5">

      <!-- Quick Links -->
      <div class="card bg-dark border-secondary rounded-4 mb-4">
        <div class="card-header border-secondary d-flex align-items-center gap-2 py-3">
          <i class="bi bi-link-45deg text-primary"></i>
          <h6 class="mb-0 fw-semibold">Quick Links</h6>
        </div>
        <div class="card-body p-4">
          <p class="text-muted small mb-3">Edit these pages directly or preview them in a new tab.</p>
          <div class="d-flex flex-column gap-2">
            <a href="/about.php" target="_blank" class="btn btn-outline-secondary text-start d-flex align-items-center gap-2">
              <i class="bi bi-people-fill text-primary"></i>About Us Page
              <i class="bi bi-box-arrow-up-right ms-auto text-muted small"></i>
            </a>
            <a href="/contact.php" target="_blank" class="btn btn-outline-secondary text-start d-flex align-items-center gap-2">
              <i class="bi bi-chat-heart text-primary"></i>Contact Page
              <i class="bi bi-box-arrow-up-right ms-auto text-muted small"></i>
            </a>
            <a href="/pricing.php" target="_blank" class="btn btn-outline-secondary text-start d-flex align-items-center gap-2">
              <i class="bi bi-tag-fill text-primary"></i>Pricing Page
              <i class="bi bi-box-arrow-up-right ms-auto text-muted small"></i>
            </a>
            <a href="/marketplace.php" target="_blank" class="btn btn-outline-secondary text-start d-flex align-items-center gap-2">
              <i class="bi bi-shop text-primary"></i>Marketplace
              <i class="bi bi-box-arrow-up-right ms-auto text-muted small"></i>
            </a>
          </div>
        </div>
      </div>

      <!-- Nav Builder -->
      <div class="card bg-dark border-secondary rounded-4">
        <div class="card-header border-secondary d-flex align-items-center gap-2 py-3">
          <i class="bi bi-list text-primary"></i>
          <h6 class="mb-0 fw-semibold">Nav Builder</h6>
        </div>
        <div class="card-body p-4">
          <form method="POST" id="navForm">
            <input type="hidden" name="save_action" value="save_nav">
            <div id="nav-items-list">
              <?php foreach ($navItems as $ni): ?>
              <div class="nav-item-row d-flex gap-2 align-items-center mb-2">
                <input type="text" name="nav_label[]" class="form-control form-control-sm" placeholder="Label"
                  value="<?= htmlspecialchars($ni['label'] ?? '') ?>">
                <input type="text" name="nav_url[]" class="form-control form-control-sm" placeholder="/url"
                  value="<?= htmlspecialchars($ni['url'] ?? '') ?>">
                <button type="button" class="btn btn-sm btn-outline-danger remove-nav-row" style="flex-shrink:0;width:32px;height:32px;padding:0;">
                  <i class="bi bi-x"></i>
                </button>
              </div>
              <?php endforeach; ?>
            </div>
            <button type="button" id="addNavRow" class="btn btn-sm btn-outline-secondary w-100 mb-3">
              <i class="bi bi-plus me-1"></i>Add Nav Item
            </button>
            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-save me-1"></i>Save Navigation
            </button>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
  // ── Theme radio select via card click ──
  document.querySelectorAll('.theme-preview-card').forEach(function (card) {
    card.addEventListener('click', function () {
      var label  = card.closest('label');
      var radioId = label.getAttribute('for');
      var radio   = document.getElementById(radioId);
      if (radio) radio.checked = true;
      document.querySelectorAll('.theme-preview-card').forEach(function (c) {
        c.style.borderColor = 'rgba(255,255,255,0.10)';
      });
      // set colour from data stored in next sibling div text (we know which theme)
      card.style.borderColor = radio.value === 'dark'   ? '#6366f1' :
                               radio.value === 'bright' ? '#6366f1' :
                               radio.value === 'modern' ? '#06b6d4' :
                               radio.value === 'zen'    ? '#059669' :
                               radio.value === 'punk'   ? '#f000b8' : '#6366f1';
    });
  });

  // ── SortableJS for sections ──
  var sortable = Sortable.create(document.getElementById('sortable-sections'), {
    handle: '.bi-grip-vertical',
    animation: 150,
    onEnd: function () {
      var order = [];
      document.querySelectorAll('#sortable-sections .section-row').forEach(function (row) {
        order.push(row.dataset.key);
      });
      document.getElementById('sections_order_input').value = JSON.stringify(order);
    }
  });

  // ── Visibility label update ──
  document.querySelectorAll('.form-check-input[type=checkbox]').forEach(function (chk) {
    chk.addEventListener('change', function () {
      var lbl = chk.nextElementSibling;
      if (lbl) lbl.textContent = chk.checked ? 'Visible' : 'Hidden';
    });
  });

  // ── Nav builder add/remove rows ──
  document.getElementById('addNavRow').addEventListener('click', function () {
    var list = document.getElementById('nav-items-list');
    var row  = document.createElement('div');
    row.className = 'nav-item-row d-flex gap-2 align-items-center mb-2';
    row.innerHTML = '<input type="text" name="nav_label[]" class="form-control form-control-sm" placeholder="Label">'
      + '<input type="text" name="nav_url[]" class="form-control form-control-sm" placeholder="/url">'
      + '<button type="button" class="btn btn-sm btn-outline-danger remove-nav-row" style="flex-shrink:0;width:32px;height:32px;padding:0;"><i class="bi bi-x"></i></button>';
    list.appendChild(row);
    row.querySelector('.remove-nav-row').addEventListener('click', function () { row.remove(); });
  });

  document.querySelectorAll('.remove-nav-row').forEach(function (btn) {
    btn.addEventListener('click', function () { btn.closest('.nav-item-row').remove(); });
  });

  // ── Auto-dismiss save alert ──
  var alert = document.getElementById('saveAlert');
  if (alert) { setTimeout(function () { alert.style.opacity = '0'; setTimeout(function () { alert.remove(); }, 500); }, 4000); }
})();
</script>

<?php require_once '../includes/admin-footer.php'; ?>
