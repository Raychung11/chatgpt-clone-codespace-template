<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
auth_require(ROLE_MEMBER);

$user       = auth_user();
$page_title = 'My Referrals — SilverDeals MY';
$active_nav = 'referrals';

// ─── Load referral record & stats ─────────────────────────────────────────
$referral  = null;
$ref_link  = '';
$ref_stats = ['total' => 0, 'pending' => 0, 'converted' => 0, 'earned' => 0];

try {
    $pdo = db();

    $stmt = $pdo->prepare("SELECT * FROM referrals WHERE referrer_id=? LIMIT 1");
    $stmt->execute([$user['id']]);
    $referral = $stmt->fetch();

    if (!$referral) {
        // Generate referral record if missing
        $code = generate_referral_code();
        $pdo->prepare("INSERT INTO referrals(referrer_id,referral_code,created_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE referral_code=referral_code")
            ->execute([$user['id'], $code]);
        $stmt = $pdo->prepare("SELECT * FROM referrals WHERE referrer_id=? LIMIT 1");
        $stmt->execute([$user['id']]);
        $referral = $stmt->fetch();
    }

    if ($referral) {
        $ref_link = referral_link($referral['referral_code']);

        $st = $pdo->prepare("
            SELECT
                COUNT(*)                                                            AS total,
                SUM(CASE WHEN rr.status='pending'   THEN 1 ELSE 0 END)            AS pending,
                SUM(CASE WHEN rr.status='converted' THEN 1 ELSE 0 END)            AS converted
            FROM referral_records rr
            WHERE rr.referrer_id=?
        ");
        $st->execute([$user['id']]);
        $row = $st->fetch();
        $ref_stats['total']     = (int)($row['total']     ?? 0);
        $ref_stats['pending']   = (int)($row['pending']   ?? 0);
        $ref_stats['converted'] = (int)($row['converted'] ?? 0);

        // Points earned from referrals
        $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM points_transactions WHERE user_id=? AND source='referral'");
        $st->execute([$user['id']]);
        $ref_stats['earned'] = (int)$st->fetchColumn();
    }
} catch (PDOException $e) { error_log('[Member referrals] '.$e->getMessage()); }

// ─── Referred members list ────────────────────────────────────────────────
$referred = [];
try {
    $st = db()->prepare("
        SELECT rr.status AS ref_status, rr.converted_at,
               u.name, u.created_at AS joined_at,
               mp.profile_photo
        FROM referral_records rr
        JOIN users u ON u.id=rr.referred_id
        LEFT JOIN member_profiles mp ON mp.user_id=u.id
        WHERE rr.referrer_id=?
        ORDER BY rr.created_at DESC
        LIMIT 50
    ");
    $st->execute([$user['id']]);
    $referred = $st->fetchAll();
} catch (PDOException $e) { error_log('[Member referrals list] '.$e->getMessage()); }

include __DIR__ . '/../inc/member_layout.php';
?>

<div style="margin-bottom:var(--space-xl);">
  <h2 style="margin-bottom:var(--space-xs);">Refer Friends &amp; Earn</h2>
  <p style="color:var(--text-muted);font-size:16px;">Invite your friends and family to SilverDeals MY. Earn <strong style="color:var(--orange-primary);"><?= format_points(POINTS_REFERRAL_BONUS) ?> SilverPoints</strong> for each successful referral.</p>
</div>

<!-- How it works banner -->
<div class="card" style="padding:var(--space-xl);margin-bottom:var(--space-xl);background:linear-gradient(135deg,var(--orange-primary),var(--orange-secondary));border:none;">
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:var(--space-xl);text-align:center;">
    <div>
      <div style="font-size:36px;margin-bottom:var(--space-sm);">🔗</div>
      <div style="font-weight:700;color:#fff;margin-bottom:4px;">Share Your Link</div>
      <div style="font-size:13px;color:rgba(255,255,255,.85);">Send your unique referral link to friends via WhatsApp or any app</div>
    </div>
    <div>
      <div style="font-size:36px;margin-bottom:var(--space-sm);">👤</div>
      <div style="font-weight:700;color:#fff;margin-bottom:4px;">They Register</div>
      <div style="font-size:13px;color:rgba(255,255,255,.85);">Your friend signs up and completes senior verification</div>
    </div>
    <div>
      <div style="font-size:36px;margin-bottom:var(--space-sm);">🪙</div>
      <div style="font-weight:700;color:#fff;margin-bottom:4px;">Both Earn Points</div>
      <div style="font-size:13px;color:rgba(255,255,255,.85);">You earn <?= format_points(POINTS_REFERRAL_BONUS) ?> pts, they earn <?= format_points(POINTS_REFERRAL_BONUS / 2) ?> pts as a welcome bonus</div>
    </div>
  </div>
</div>

<!-- Stats -->
<div class="grid grid-4" style="margin-bottom:var(--space-xl);">
  <div class="stat-card">
    <div class="stat-card__icon">👥</div>
    <div class="stat-card__value"><?= $ref_stats['total'] ?></div>
    <div class="stat-card__label">Total Referred</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon">⏳</div>
    <div class="stat-card__value"><?= $ref_stats['pending'] ?></div>
    <div class="stat-card__label">Pending Verification</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon">✅</div>
    <div class="stat-card__value"><?= $ref_stats['converted'] ?></div>
    <div class="stat-card__label">Verified Members</div>
  </div>
  <div class="stat-card" style="border-color:var(--orange-primary);">
    <div class="stat-card__icon">🪙</div>
    <div class="stat-card__value" style="color:var(--orange-primary);"><?= format_points($ref_stats['earned']) ?></div>
    <div class="stat-card__label">Points Earned</div>
  </div>
</div>

<div class="grid grid-2" style="align-items:start;gap:var(--space-xl);">

  <!-- Referral link + QR -->
  <div>
    <div class="card" style="padding:var(--space-xl);margin-bottom:var(--space-lg);">
      <h4 style="margin-bottom:var(--space-lg);">Your Referral Link</h4>

      <div style="display:flex;gap:var(--space-sm);margin-bottom:var(--space-xl);">
        <input type="text" id="ref-link" value="<?= e($ref_link) ?>" class="form-control" readonly style="font-size:13px;background:var(--bg-light);">
        <button class="btn btn--primary btn--sm" onclick="copyRefLink()" id="copy-btn" style="white-space:nowrap;">Copy Link</button>
      </div>

      <div style="display:flex;gap:var(--space-sm);flex-wrap:wrap;margin-bottom:var(--space-xl);">
        <a href="<?= whatsapp_url('Hi! Join me on SilverDeals MY — exclusive deals and rewards for Malaysians 50+! Register free: ' . $ref_link) ?>" target="_blank" rel="noopener" class="btn btn--primary" style="flex:1;min-width:140px;background:#25D366;border-color:#25D366;display:flex;align-items:center;justify-content:center;gap:8px;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          Share on WhatsApp
        </a>
        <button onclick="copyRefLink()" class="btn btn--muted" style="flex:1;min-width:120px;">📋 Copy Link</button>
      </div>

      <div style="font-size:13px;color:var(--text-muted);background:var(--orange-bg);border-radius:var(--radius-md);padding:var(--space-md);">
        Your referral code: <strong style="font-family:monospace;font-size:15px;color:var(--orange-dark);"><?= e($referral['referral_code'] ?? '') ?></strong>
      </div>
    </div>

    <!-- QR card -->
    <div class="card" style="padding:var(--space-xl);text-align:center;">
      <h4 style="margin-bottom:var(--space-sm);">QR Code to Share</h4>
      <p style="color:var(--text-muted);font-size:13px;margin-bottom:var(--space-lg);">Show this QR code and let friends scan to register directly.</p>
      <div id="referral-qr" style="display:inline-block;padding:var(--space-md);border:2px solid var(--orange-primary);border-radius:var(--radius-md);background:#fff;margin-bottom:var(--space-lg);"></div>
      <div>
        <button onclick="window.print()" class="btn btn--muted btn--sm">🖨 Print QR Code</button>
      </div>
    </div>
  </div>

  <!-- Referred members list -->
  <div>
    <h4 style="margin-bottom:var(--space-md);">People You've Referred</h4>

    <?php if (!empty($referred)): ?>
      <div class="card" style="overflow:hidden;">
        <div style="display:flex;flex-direction:column;">
          <?php foreach ($referred as $r): ?>
            <div style="display:flex;align-items:center;gap:var(--space-md);padding:var(--space-md) var(--space-lg);border-bottom:1px solid var(--border-color);">
              <!-- Avatar -->
              <div style="width:44px;height:44px;border-radius:50%;overflow:hidden;flex-shrink:0;">
                <?php if ($r['profile_photo']): ?>
                  <img src="<?= e($r['profile_photo']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                  <div style="width:100%;height:100%;background:var(--orange-bg);display:flex;align-items:center;justify-content:center;font-size:18px;">👤</div>
                <?php endif; ?>
              </div>

              <div style="flex:1;min-width:0;">
                <div style="font-weight:600;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($r['name']) ?></div>
                <div style="font-size:12px;color:var(--text-muted);">Joined <?= time_ago($r['joined_at']) ?></div>
              </div>

              <div style="text-align:right;flex-shrink:0;">
                <?php if ($r['ref_status'] === 'converted'): ?>
                  <span class="badge badge--success" style="font-size:11px;">✓ Verified</span>
                  <div style="font-size:11px;color:var(--orange-primary);font-weight:700;margin-top:2px;">+<?= format_points(POINTS_REFERRAL_BONUS) ?> pts</div>
                <?php else: ?>
                  <span class="badge badge--warning" style="font-size:11px;">Pending</span>
                  <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">Awaiting verify</div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

    <?php else: ?>
      <div class="empty-state" style="padding:var(--space-2xl);">
        <div class="empty-state__icon">👥</div>
        <h4 class="empty-state__title">No referrals yet</h4>
        <p class="empty-state__text">Share your referral link above to start earning bonus SilverPoints!</p>
      </div>
    <?php endif; ?>

    <!-- Pending points note -->
    <?php if ($ref_stats['pending'] > 0): ?>
      <div style="margin-top:var(--space-md);padding:var(--space-md);background:var(--orange-bg);border-radius:var(--radius-md);font-size:13px;color:var(--orange-dark);">
        ⏳ You have <strong><?= $ref_stats['pending'] ?></strong> pending referral<?= $ref_stats['pending']!==1?'s':'' ?>. Points are awarded once your friends complete senior verification.
      </div>
    <?php endif; ?>

    <!-- Terms -->
    <div class="card" style="padding:var(--space-lg);margin-top:var(--space-lg);">
      <div style="font-weight:700;margin-bottom:var(--space-sm);">Referral Programme Terms</div>
      <ul style="font-size:13px;color:var(--text-muted);padding-left:var(--space-lg);line-height:1.9;margin:0;">
        <li>Referral points are credited after the referred friend completes verification</li>
        <li>Self-referrals are not permitted</li>
        <li>Each referred friend can only be credited to one referrer</li>
        <li>SilverDeals MY reserves the right to modify the programme at any time</li>
        <li>Points have no cash value and cannot be transferred</li>
      </ul>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSe2keI06RokmYfcoYWYbGr7AEDn" crossorigin="anonymous"></script>
<script>
new QRCode(document.getElementById('referral-qr'), {
    text: '<?= addslashes($ref_link) ?>',
    width: 180, height: 180,
    colorDark: '#FF6B00', colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.M
});

function copyRefLink() {
    const inp = document.getElementById('ref-link');
    inp.select(); inp.setSelectionRange(0, 99999);
    try {
        navigator.clipboard.writeText(inp.value).then(() => showCopied()).catch(() => {
            document.execCommand('copy'); showCopied();
        });
    } catch(e) { document.execCommand('copy'); showCopied(); }
}
function showCopied() {
    const btn = document.getElementById('copy-btn');
    const orig = btn.textContent;
    btn.textContent = '✓ Copied!';
    btn.style.background = 'var(--success)';
    setTimeout(() => { btn.textContent = orig; btn.style.background = ''; }, 2000);
}
</script>

<?php include __DIR__ . '/../inc/member_layout_end.php'; ?>
