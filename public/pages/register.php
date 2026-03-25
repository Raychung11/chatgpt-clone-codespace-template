<?php
/**
 * Customer Registration Page
 * /public/pages/register.php
 */

$appTitle = 'Register – F&B Loyalty';
$hideNav  = true;
require BASE_PATH . '/public/layout/app_shell.php';

$refCode = sanitize_string($_GET['ref'] ?? '');
?>

<style>
body { background: linear-gradient(135deg, #1a1a2e, #0f3460); padding-bottom: 0; }
.container-fluid { padding: 0 !important; }
</style>

<div class="auth-wrap">
    <div>
        <div class="auth-logo">🍽️</div>
        <div class="auth-sheet">
            <h5 class="fw-bold mb-1 text-center">Create Account</h5>
            <p class="text-muted text-center small mb-4">Join and start earning points today</p>

            <!-- Step 1: Info -->
            <div id="step-info">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Full Name *</label>
                    <input type="text" id="reg-name" class="form-control form-control-app" placeholder="e.g. Ahmad Ibrahim">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Phone Number *</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0" style="border-radius:10px 0 0 10px;">🇲🇾 +60</span>
                        <input type="tel" id="reg-phone" class="form-control form-control-app border-start-0"
                               placeholder="12 3456789" style="border-radius:0 10px 10px 0;" maxlength="10" inputmode="numeric">
                    </div>
                </div>
                <?php if ($refCode): ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Referral Code</label>
                    <input type="text" id="reg-ref" class="form-control form-control-app" value="<?= htmlspecialchars($refCode) ?>" readonly>
                </div>
                <?php endif; ?>
                <button class="btn-brand" id="btn-reg-send">Send OTP</button>
                <div class="text-center mt-3">
                    <span class="text-muted small">Already have an account? </span>
                    <a href="/app/login" class="text-decoration-none small fw-semibold" style="color:#e94560;">Sign In</a>
                </div>
            </div>

            <!-- Step 2: OTP -->
            <div id="step-otp" style="display:none;">
                <p class="text-muted small text-center mb-3">Enter the 6-digit OTP sent to <strong id="display-phone"></strong></p>
                <div class="otp-inputs mb-3">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                    <input type="number" class="otp-digit" maxlength="1" min="0" max="9">
                    <?php endfor; ?>
                </div>
                <button class="btn-brand" id="btn-reg-verify">Create Account</button>
                <div class="text-center mt-3">
                    <button class="btn btn-link small p-0" id="btn-back-reg" style="color:#e94560;">← Go back</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let regPhone = '', regName = '', regRef = '';

document.querySelectorAll('.otp-digit').forEach((input, i, inputs) => {
    input.addEventListener('input', () => {
        input.value = input.value.slice(-1);
        if (input.value && i < inputs.length - 1) inputs[i + 1].focus();
    });
    input.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !input.value && i > 0) inputs[i - 1].focus();
    });
});
function getOtp() {
    return [...document.querySelectorAll('.otp-digit')].map(i => i.value).join('');
}

document.getElementById('btn-reg-send').onclick = async () => {
    regName  = document.getElementById('reg-name').value.trim();
    const ph = document.getElementById('reg-phone').value.trim().replace(/\D/g,'');
    regRef   = document.getElementById('reg-ref')?.value.trim() || '';

    if (!regName) { showToast('Please enter your name.', 'error'); return; }
    if (!ph || ph.length < 9) { showToast('Enter a valid phone number.', 'error'); return; }
    regPhone = ph;

    const btn = document.getElementById('btn-reg-send');
    btn.disabled = true; btn.textContent = 'Sending…';

    const res = await apiCall('auth/request-otp', 'POST', { phone: '60'+regPhone, purpose: 'register' });
    if (res.status === 'success') {
        document.getElementById('display-phone').textContent = '60'+regPhone;
        document.getElementById('step-info').style.display = 'none';
        document.getElementById('step-otp').style.display  = 'block';
    } else {
        showToast(res.message, 'error');
    }
    btn.disabled = false; btn.textContent = 'Send OTP';
};

document.getElementById('btn-reg-verify').onclick = async () => {
    const otp = getOtp();
    if (otp.length !== 6) { showToast('Enter all 6 digits.', 'error'); return; }

    const btn = document.getElementById('btn-reg-verify');
    btn.disabled = true; btn.textContent = 'Creating account…';

    const body = { phone: '60'+regPhone, otp, purpose: 'register', name: regName };
    if (regRef) body.referral_code = regRef;

    const res = await apiCall('auth/verify-otp', 'POST', body);
    if (res.status === 'success') {
        localStorage.setItem('fnb_token', res.data.token);
        localStorage.setItem('fnb_user', JSON.stringify(res.data.user));
        showToast('Welcome! 🎉 Account created.', 'success');
        setTimeout(() => window.location.href = '/app/', 1000);
    } else {
        showToast(res.message, 'error');
    }
    btn.disabled = false; btn.textContent = 'Create Account';
};

document.getElementById('btn-back-reg').onclick = () => {
    document.getElementById('step-otp').style.display  = 'none';
    document.getElementById('step-info').style.display = 'block';
};
</script>

<?php require BASE_PATH . '/public/layout/app_footer.php'; ?>
