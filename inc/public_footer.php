<?php
// ─── SilverDeals MY — Public Footer ─────────────────────────────────────────
?>
<!-- ─── Footer ──────────────────────────────────────────────────── -->
<footer class="footer">
  <div class="container">
    <div class="grid grid-4" style="gap:var(--space-2xl) var(--space-xl);">

      <div>
        <div class="footer__logo-text">🟠 SilverDeals MY</div>
        <div class="footer__tagline">Senior Membership &amp; Rewards Ecosystem</div>
        <p style="margin-top:var(--space-lg);font-size:14px;line-height:1.7;">
          Operated by <strong style="color:#fff;">SLV Lifestyle Sdn Bhd</strong><br>
          Powered by SLV Group Sdn Bhd
        </p>
        <div style="margin-top:var(--space-lg);display:flex;gap:var(--space-md);">
          <a href="<?= whatsapp_url() ?>" style="color:rgba(255,255,255,.6);font-size:22px;" title="WhatsApp">💬</a>
          <a href="#" style="color:rgba(255,255,255,.6);font-size:22px;" title="Facebook">📘</a>
          <a href="#" style="color:rgba(255,255,255,.6);font-size:22px;" title="Instagram">📸</a>
        </div>
      </div>

      <div>
        <div class="footer__heading">Members</div>
        <a href="/public/register.php"    class="footer__link">Join as Member</a>
        <a href="/public/login.php"       class="footer__link">Login</a>
        <a href="/member/dashboard.php"   class="footer__link">My Dashboard</a>
        <a href="/member/rewards.php"     class="footer__link">Rewards &amp; Points</a>
        <a href="/member/referrals.php"   class="footer__link">Refer &amp; Earn</a>
      </div>

      <div>
        <div class="footer__heading">Partners</div>
        <a href="/public/join-merchant.php"   class="footer__link">Become a Merchant</a>
        <a href="/public/join-community.php"  class="footer__link">Community Partner</a>
        <a href="/public/merchants.php"       class="footer__link">Merchant Directory</a>
        <a href="/public/deals.php"           class="footer__link">Browse Deals</a>
        <a href="/public/how-it-works.php"    class="footer__link">How It Works</a>
      </div>

      <div>
        <div class="footer__heading">Support</div>
        <a href="/public/faq.php"         class="footer__link">FAQ</a>
        <a href="/public/contact.php"     class="footer__link">Contact Us</a>
        <a href="/public/about.php"       class="footer__link">About Us</a>
        <a href="/public/privacy.php"     class="footer__link">Privacy Policy</a>
        <a href="/public/terms.php"       class="footer__link">Terms of Service</a>
        <div style="margin-top:var(--space-lg);">
          <a href="<?= whatsapp_url() ?>" class="btn btn--ghost btn--sm" target="_blank" rel="noopener">
            💬 WhatsApp Us
          </a>
        </div>
      </div>

    </div>

    <hr class="footer__divider">

    <div class="footer__bottom">
      <span>© <?= date('Y') ?> SilverDeals MY. All rights reserved. Operated by SLV Lifestyle Sdn Bhd (Malaysia).</span>
      <span>SSM Registered | PDPA Compliant</span>
    </div>
  </div>
</footer>

<!-- WhatsApp FAB -->
<a href="<?= whatsapp_url() ?>" class="whatsapp-fab" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
  <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
  </svg>
</a>

<script src="/assets/js/app.js"></script>
</body>
</html>
