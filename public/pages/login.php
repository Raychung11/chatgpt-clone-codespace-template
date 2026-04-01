<?php
/**
 * Customer Login Page
 * /public/pages/login.php
 */

$appTitle = 'Sign In – F&B Loyalty';
$hideNav  = true;
require BASE_PATH . '/public/layout/app_shell.php';
?>

<style>
body { background: linear-gradient(135deg, #1a1a2e, #0f3460); padding-bottom: 0; }
.container-fluid { padding: 0 !important; }
</style>

<div class="auth-wrap">
    <div>
        <div class="auth-logo">🍽️</div>
        <div class="auth-sheet">
            <h5 class="fw-bold mb-1 text-center">Welcome Back</h5>
            <p class="text-muted text-center small mb-4">Sign in with your phone number</p>

            <!-- Step 1: Phone -->
            <div id="step-phone">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Phone Number</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0" style="border-radius:10px 0 0 10px;">🇲🇾 +60</span>
                        <input type="tel" id="phone-input" class="form-control form-control-app border-start-0"
                               placeholder="12 3456789" style="border-radius: 0 10px 10px 0;"
                               maxlength="10" inputmode="numeric">
                    </div>
                    <div class="form-text">Enter your Malaysian mobile number without country code.</div>
                </div>
                <button class="btn-brand" id="btn-send-otp">Send OTP</button>
                <div class="text-center mt-3">
                    <span class="text-muted small">Don't have an account? </span>
                    <a href="/app/register" class="text-decoration-none small fw-semibold" style="color:#e94560;">Register</a>
                </div>
            </div>

            <!-- Step 2: OTP -->
            <div id="step-otp" style="display:none;">
                <p class="text-muted small text-center mb-3">Enter the 6-digit OTP sent to <strong id="display-phone"></strong></p>
                <div class="otp-inputs mb-3" id="otp-inputs">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                    <input type="number" class="otp-digit" maxlength="1" min="0" max="9">
                    <?php endfor; ?>
                </div>
                <button class="btn-brand" id="btn-verify-otp">Verify & Sign In</button>
                <div class="text-center mt-3">
                    <button class="btn btn-link text-muted small p-0" id="btn-resend" disabled>
                        Resend OTP in <span id="countdown">30</span>s
                    </button>
                </div>
                <div class="text-center mt-2">
                    <button class="btn btn-link small p-0" id="btn-back" style="color:#e94560;">← Change number</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let phoneNumber = '';
let countdownTimer;

// OTP input handling
document.querySelectorAll('.otp-digit').forEach((input, i, inputs) => {
    input.addEventListener('input', () => {
        input.value = input.value.slice(-1);
        if (input.value && i < inputs.length - 1) inputs[i + 1].focus();
    });
    input.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !input.value && i > 0) inputs[i - 1].focus();
    });
    input.addEventListener('paste', e => {
        const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
        [...pasted].forEach((c, j) => { if (inputs[j]) inputs[j].value = c; });
        if (inputs[Math.min(pasted.length, 5)]) inputs[Math.min(pasted.length, 5)].focus();
        e.preventDefault();
    });
});

function getOtp() {
    return [...document.querySelectorAll('.otp-digit')].map(i => i.value).join('');
}

function startCountdown() {
    let seconds = 30;
    document.getElementById('btn-resend').disabled = true;
    countdownTimer = setInterval(() => {
        seconds--;
        document.getElementById('countdown').textContent = seconds;
        if (seconds <= 0) {
            clearInterval(countdownTimer);
            document.getElementById('btn-resend').disabled = false;
            document.getElementById('btn-resend').textContent = 'Resend OTP';
        }
    }, 1000);
}

async function sendOtp(phone) {
    const btn = document.getElementById('btn-send-otp');
    btn.disabled = true;
    btn.textContent = 'Sending…';

    try {
        const res = await apiCall('auth/request-otp', 'POST', { phone: '60' + phone, purpose: 'login' });
        if (res.status === 'success') {
            document.getElementById('display-phone').textContent = '60' + phone;
            document.getElementById('step-phone').style.display = 'none';
            document.getElementById('step-otp').style.display   = 'block';
            startCountdown();
            // Debug mode: auto-fill OTP inputs
            if (res.data && res.data.otp_debug) {
                const digits = res.data.otp_debug.toString().split('');
                document.querySelectorAll('.otp-digit').forEach((el, i) => { el.value = digits[i] || ''; });
                showToast('Debug OTP auto-filled: ' + res.data.otp_debug, 'info');
            }
        } else {
            showToast(res.message, 'error');
        }
    } catch (e) {
        showToast('Network error. Please try again.', 'error');
    }
    btn.disabled = false;
    btn.textContent = 'Send OTP';
}

document.getElementById('btn-send-otp').onclick = () => {
    const phone = document.getElementById('phone-input').value.trim().replace(/\D/g,'');
    if (!phone || phone.length < 9) { showToast('Enter a valid phone number.', 'error'); return; }
    phoneNumber = phone;
    sendOtp(phone);
};

document.getElementById('btn-verify-otp').onclick = async () => {
    const otp = getOtp();
    if (otp.length !== 6) { showToast('Enter all 6 digits.', 'error'); return; }

    const btn = document.getElementById('btn-verify-otp');
    btn.disabled = true;
    btn.textContent = 'Verifying…';

    try {
        const res = await apiCall('auth/verify-otp', 'POST', { phone: '60' + phoneNumber, otp, purpose: 'login' });
        if (res.status === 'success') {
            localStorage.setItem('fnb_token', res.data.token);
            localStorage.setItem('fnb_user', JSON.stringify(res.data.user));
            showToast('Welcome back!', 'success');
            setTimeout(() => window.location.href = '/app/dashboard', 800);
        } else {
            showToast(res.message, 'error');
        }
    } catch (e) {
        showToast('Verification failed. Try again.', 'error');
    }
    btn.disabled = false;
    btn.textContent = 'Verify & Sign In';
};

document.getElementById('btn-resend').onclick = () => {
    if (phoneNumber) sendOtp(phoneNumber);
};
document.getElementById('btn-back').onclick = () => {
    document.getElementById('step-otp').style.display   = 'none';
    document.getElementById('step-phone').style.display = 'block';
    clearInterval(countdownTimer);
};
</script>

<?php require BASE_PATH . '/public/layout/app_footer.php'; ?>
