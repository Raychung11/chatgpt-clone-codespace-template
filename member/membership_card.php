<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/points.php';
auth_require(ROLE_MEMBER);

$user       = auth_user();
$page_title = 'My Membership Card';
$active_nav = 'card';

// Load card data
$card    = null;
$profile = null;
$wallet  = ['balance'=>0,'lifetime_earned'=>0,'lifetime_spent'=>0];
$tier_label = 'Free Member';

try {
    $pdo = db();

    $stmt = $pdo->prepare("SELECT * FROM member_cards WHERE user_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$user['id']]);
    $card = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT * FROM member_profiles WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT balance, lifetime_earned, lifetime_spent FROM points_wallets WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $wallet = $stmt->fetch() ?: $wallet;

    // Check subscription tier
    $stmt = $pdo->prepare("
        SELECT p.slug FROM subscriptions s
        JOIN membership_plans p ON p.id = s.plan_id
        WHERE s.user_id = ? AND s.status = 'active'
          AND (s.ends_at IS NULL OR s.ends_at > NOW())
        ORDER BY p.price_myr DESC LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $plan = $stmt->fetchColumn();
    $tier_label = match($plan ?: 'free') {
        'gold'   => '🥇 Gold Member',
        'silver' => '🥈 Silver Member',
        default  => '🆓 Free Member',
    };
    if ($card) {
        $card['tier'] = $plan ?: $card['tier'] ?: 'free';
    }

} catch (PDOException $e) { error_log('[MemberCard] ' . $e->getMessage()); }

// QR payload for display — sanitised (no raw IC data)
$qrData = '';
if ($card && $card['qr_code_data']) {
    $qrData = $card['qr_code_data'];
} elseif ($user['status'] === 'active') {
    // Fallback payload
    $qrData = json_encode(['uid' => $user['id'], 'type' => 'silverdeals_member']);
}

// Recent points transactions
$transactions = [];
try {
    $stmt = db()->prepare("
        SELECT type, amount, balance_after, source, description, created_at
        FROM points_transactions
        WHERE user_id = ?
        ORDER BY created_at DESC LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $transactions = $stmt->fetchAll();
} catch (PDOException) {}

include __DIR__ . '/../inc/member_layout.php';
?>

<!-- ── Print / Download button ──────────────────────────────────────────── -->
<div class="flex-between" style="margin-bottom:var(--space-lg);">
  <div></div>
  <div style="display:flex;gap:var(--space-sm);">
    <button onclick="window.print()" class="btn btn--muted btn--sm">🖨 Print Card</button>
    <button onclick="downloadCard()" class="btn btn--secondary btn--sm">💾 Save Card</button>
  </div>
</div>

<div class="grid grid-2" style="align-items:start;gap:var(--space-xl);">

  <!-- ─── Card display ──────────────────────────────────────────────────── -->
  <div>
    <!-- The Digital Card -->
    <div id="memberCardEl" style="background:linear-gradient(135deg,#FF6B00 0%,#E55F00 60%,#CC5500 100%);border-radius:24px;padding:28px;color:#fff;position:relative;overflow:hidden;box-shadow:0 16px 48px rgba(255,107,0,.40);max-width:420px;">

      <!-- Background circles -->
      <div style="position:absolute;top:-50px;right:-50px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.08);pointer-events:none;"></div>
      <div style="position:absolute;bottom:-60px;left:-40px;width:260px;height:260px;border-radius:50%;background:rgba(0,0,0,.06);pointer-events:none;"></div>

      <!-- Top row -->
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;position:relative;z-index:1;">
        <div>
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;opacity:.7;margin-bottom:4px;">SilverDeals MY</div>
          <div style="font-size:13px;font-weight:600;background:rgba(255,255,255,.2);padding:4px 12px;border-radius:100px;display:inline-block;"><?= e($tier_label) ?></div>
        </div>
        <div style="font-size:28px;">🟠</div>
      </div>

      <!-- Member name & number -->
      <div style="position:relative;z-index:1;margin-bottom:20px;">
        <div style="font-size:22px;font-weight:800;letter-spacing:.02em;margin-bottom:4px;"><?= e($user['name']) ?></div>
        <div style="font-size:13px;opacity:.75;letter-spacing:.08em;">
          <?= $profile ? e($profile['member_number'] ?? 'PENDING') : 'Complete profile to activate' ?>
        </div>
      </div>

      <!-- Points & QR row -->
      <div style="display:flex;justify-content:space-between;align-items:flex-end;position:relative;z-index:1;">
        <div>
          <div style="font-size:36px;font-weight:800;line-height:1;"><?= number_format((int)$wallet['balance']) ?></div>
          <div style="font-size:12px;opacity:.75;margin-top:2px;">SilverPoints</div>
          <div style="font-size:11px;opacity:.6;margin-top:8px;">
            Valid Since <?= date('M Y', strtotime($user['status'] === 'active' ? ($card['issued_at'] ?? 'now') : 'now')) ?>
          </div>
        </div>

        <!-- QR code box -->
        <?php if ($user['status'] === 'active' && $qrData): ?>
          <div style="background:#fff;border-radius:12px;padding:10px;width:90px;height:90px;display:flex;align-items:center;justify-content:center;">
            <div id="qrcode" style="width:70px;height:70px;"></div>
          </div>
        <?php else: ?>
          <div style="background:rgba(255,255,255,.15);border-radius:12px;padding:10px;width:90px;height:90px;display:flex;align-items:center;justify-content:center;text-align:center;font-size:11px;opacity:.8;">
            Verify to<br>activate
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Card status info -->
    <div style="margin-top:var(--space-lg);">
      <?php if ($user['status'] !== 'active'): ?>
        <div class="alert alert--warning">
          <span class="alert__icon">⚠</span>
          <div>
            <strong>Card not yet active.</strong>
            <div style="font-size:15px;margin-top:4px;">
              <?php if (!$card): ?>
                Please <a href="/member/verification.php">submit your senior verification</a> to activate your digital card.
              <?php else: ?>
                Your verification is under review. We'll notify you once approved.
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="alert alert--success">
          <span class="alert__icon">✓</span>
          <span>Your card is active. Show the QR code at any partner merchant to redeem deals.</span>
        </div>
      <?php endif; ?>
    </div>

    <!-- How to use -->
    <div class="card" style="padding:var(--space-lg);margin-top:var(--space-lg);">
      <h4 style="margin-bottom:var(--space-md);">📖 How to Use Your Card</h4>
      <ol style="display:flex;flex-direction:column;gap:10px;padding-left:var(--space-lg);color:var(--text-muted);font-size:15px;line-height:1.6;">
        <li>Browse and select a deal you'd like to redeem.</li>
        <li>Tap <strong>"Get Voucher"</strong> to claim your voucher code.</li>
        <li>Visit the merchant and show your QR card or voucher code.</li>
        <li>The merchant scans or enters your code to validate.</li>
        <li>Enjoy your deal and earn SilverPoints!</li>
      </ol>
    </div>
  </div>

  <!-- ─── Right side: Stats + Transactions ──────────────────────────────── -->
  <div>
    <!-- Wallet stats -->
    <h3 style="margin-bottom:var(--space-md);">💰 Points Wallet</h3>
    <div class="grid grid-2" style="margin-bottom:var(--space-xl);">
      <div class="stat-card">
        <div class="stat-card__number"><?= number_format((int)$wallet['balance']) ?></div>
        <div class="stat-card__label">Current Balance</div>
      </div>
      <div class="stat-card" style="border-left-color:var(--success);">
        <div class="stat-card__number" style="color:var(--success);"><?= number_format((int)$wallet['lifetime_earned']) ?></div>
        <div class="stat-card__label">Total Earned</div>
      </div>
      <div class="stat-card" style="border-left-color:#8B5CF6;">
        <div class="stat-card__number" style="color:#8B5CF6;"><?= number_format((int)$wallet['lifetime_spent']) ?></div>
        <div class="stat-card__label">Total Spent</div>
      </div>
      <a href="/member/rewards.php" class="stat-card" style="border-left-color:#3B82F6;text-decoration:none;display:block;">
        <div class="stat-card__number" style="color:#3B82F6;font-size:20px;">Redeem →</div>
        <div class="stat-card__label">Use your points</div>
      </a>
    </div>

    <!-- Points history -->
    <div class="flex-between" style="margin-bottom:var(--space-md);">
      <h3>📜 Points History</h3>
      <a href="/member/rewards.php" class="btn btn--secondary btn--sm">Full History</a>
    </div>

    <?php if (!empty($transactions)): ?>
      <div class="card" style="overflow:hidden;">
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr><th>Description</th><th style="text-align:right;">Points</th><th>Balance</th><th>Date</th></tr>
            </thead>
            <tbody>
              <?php foreach ($transactions as $tx): ?>
                <tr>
                  <td>
                    <div style="font-size:14px;font-weight:500;"><?= e($tx['description'] ?? ucfirst($tx['source'])) ?></div>
                  </td>
                  <td style="text-align:right;font-weight:700;color:<?= $tx['amount'] > 0 ? 'var(--success)' : 'var(--error)' ?>;">
                    <?= $tx['amount'] > 0 ? '+' : '' ?><?= number_format($tx['amount']) ?>
                  </td>
                  <td style="font-size:13px;color:var(--text-muted);"><?= number_format($tx['balance_after']) ?></td>
                  <td style="font-size:12px;color:var(--text-muted);"><?= time_ago($tx['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php else: ?>
      <div class="empty-state" style="padding:var(--space-xl);">
        <div class="empty-state__icon">💰</div>
        <h4 class="empty-state__title">No transactions yet</h4>
        <p class="empty-state__text">Start earning points by verifying your account and redeeming deals.</p>
        <a href="/member/deals.php" class="btn btn--primary">Browse Deals</a>
      </div>
    <?php endif; ?>

    <!-- Upgrade CTA -->
    <?php if (($card['tier'] ?? 'free') === 'free'): ?>
    <div style="margin-top:var(--space-xl);background:linear-gradient(135deg,#1F2937,#374151);border-radius:var(--radius-lg);padding:var(--space-xl);color:#fff;text-align:center;">
      <div style="font-size:36px;margin-bottom:var(--space-sm);">🥈</div>
      <h4 style="color:#fff;margin-bottom:var(--space-sm);">Upgrade to Silver</h4>
      <p style="opacity:.8;font-size:15px;margin-bottom:var(--space-lg);">Get 500 bonus points, exclusive deals, and more at RM 49/year.</p>
      <a href="/member/subscription.php" class="btn btn--primary">Upgrade Now</a>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- QR Code Library (lightweight, no external CDN needed in production — self-host) -->
<script>
<?php if ($user['status'] === 'active' && $qrData): ?>
// Minimal QR code generator using qrcodejs (inline for offline support)
// In production: download qrcode.min.js to /assets/js/ and reference locally
(function(){
  // Load QR library dynamically
  const s = document.createElement('script');
  s.src   = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
  s.onload = () => {
    try {
      new QRCode(document.getElementById('qrcode'), {
        text:           <?= json_encode($qrData) ?>,
        width:          70,
        height:         70,
        colorDark:      '#1F2937',
        colorLight:     '#ffffff',
        correctLevel:   QRCode.CorrectLevel.M
      });
    } catch(e) { console.warn('QR failed', e); }
  };
  document.head.appendChild(s);
})();
<?php endif; ?>

// Download card as image
function downloadCard() {
  const el = document.getElementById('memberCardEl');
  if (!el) return;
  // Simple approach: open print dialog for the card only
  const w = window.open('', '_blank');
  w.document.write('<html><head><title>SilverDeals MY Card</title>');
  w.document.write('<link rel="stylesheet" href="/assets/css/theme.css">');
  w.document.write('</head><body style="margin:20px;">');
  w.document.write(el.outerHTML);
  w.document.write('</body></html>');
  w.document.close();
  w.print();
}
</script>

<!-- Print styles -->
<style>
@media print {
  .dash-layout, .dash-sidebar, .dash-topbar, .bottom-nav, .btn, .alert, h3, .grid { display: block !important; }
  .dash-main { padding: 0; }
  .dash-content { padding: 0; }
  #memberCardEl { box-shadow: none; }
  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
</style>

<?php include __DIR__ . '/../inc/member_layout_end.php'; ?>
