<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

Auth::requireLogin('/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));

$productId = (int)($_GET['product'] ?? 0);
$plan      = in_array($_GET['plan'] ?? '', ['monthly','yearly','onetime']) ? $_GET['plan'] : 'monthly';

if (!$productId) { header('Location: /marketplace.php'); exit; }

$product = DB::fetch(
    'SELECT p.*, c.name as cat_name, c.color as cat_color, c.icon as cat_icon
     FROM products p LEFT JOIN categories c ON p.category_id=c.id
     WHERE p.id=? AND p.is_active=1',
    [$productId]
);
if (!$product) { header('Location: /marketplace.php'); exit; }

// Already owns?
if (Auth::owns($productId)) {
    header('Location: /dashboard.php');
    exit;
}

$user   = Auth::user();
$price  = $plan === 'yearly' ? $product['price_yearly'] : $product['price_monthly'];
$yearly_savings = $product['price_yearly'] > 0
    ? round((1 - ($product['price_yearly'] / ($product['price_monthly'] * 12))) * 100) : 0;

$pageTitle = 'Checkout - ' . $product['name'];

// Handle Stripe checkout creation (server-side redirect to Stripe Checkout)
$stripeError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_checkout'])) {
    // In production: use Stripe PHP SDK to create a Checkout Session
    // For now, create a pending subscription record and redirect to a payment page
    // You'll integrate Stripe SDK after uploading to Hostinger

    // Simulate success (remove this in production)
    if ($_POST['demo_mode'] ?? false) {
        $subId = DB::insert('subscriptions', [
            'user_id'    => Auth::id(),
            'product_id' => $productId,
            'plan'       => $plan,
            'status'     => 'trialing',
            'amount'     => $price,
            'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+'.TRIAL_DAYS.' days')),
            'current_period_start' => date('Y-m-d H:i:s'),
            'current_period_end'   => date('Y-m-d H:i:s', strtotime('+' . ($plan === 'yearly' ? '1 year' : '1 month'))),
        ]);
        header('Location: /dashboard.php?welcome=1');
        exit;
    }

    // Real Stripe integration (uncomment and configure after adding Stripe SDK)
    /*
    require_once 'vendor/autoload.php';
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
    $priceId = $plan === 'yearly' ? $product['stripe_price_yearly'] : $product['stripe_price_monthly'];
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'mode'                 => 'subscription',
        'customer_email'       => $user['email'],
        'line_items'           => [['price' => $priceId, 'quantity' => 1]],
        'success_url'          => SITE_URL . '/checkout-success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'           => SITE_URL . '/checkout.php?product=' . $productId . '&plan=' . $plan,
        'metadata'             => ['user_id' => Auth::id(), 'product_id' => $productId, 'plan' => $plan],
        'subscription_data'    => ['trial_period_days' => TRIAL_DAYS],
    ]);
    header('Location: ' . $session->url);
    exit;
    */
    $stripeError = 'Payment gateway not configured yet. Contact admin.';
}

require_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="row g-5 justify-content-center">
        <!-- Order Summary -->
        <div class="col-lg-4 order-lg-2">
            <div class="glass-card rounded-4 p-4 sticky-top" style="top:80px">
                <h6 class="text-white fw-semibold mb-4">Order Summary</h6>

                <!-- Product -->
                <div class="d-flex align-items-center gap-3 mb-4 p-3 rounded-3 bg-secondary bg-opacity-10">
                    <div class="cat-icon-sm" style="background:<?= $product['cat_color'] ?? '#6366f1' ?>22;color:<?= $product['cat_color'] ?? '#6366f1' ?>">
                        <i class="bi <?= $product['cat_icon'] ?? 'bi-cpu' ?>"></i>
                    </div>
                    <div>
                        <div class="text-white fw-semibold small"><?= htmlspecialchars($product['name']) ?></div>
                        <div class="text-muted" style="font-size:12px"><?= htmlspecialchars($product['tagline']) ?></div>
                    </div>
                </div>

                <!-- Plan Toggle -->
                <?php if ($product['price_yearly'] > 0): ?>
                <div class="d-flex gap-2 mb-4">
                    <a href="?product=<?= $productId ?>&plan=monthly"
                       class="btn btn-sm flex-fill <?= $plan === 'monthly' ? 'btn-primary' : 'btn-outline-secondary' ?>">Monthly</a>
                    <a href="?product=<?= $productId ?>&plan=yearly"
                       class="btn btn-sm flex-fill <?= $plan === 'yearly' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        Yearly <?php if ($yearly_savings > 0) echo "<span class='badge bg-success ms-1'>-$yearly_savings%</span>"; ?>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Pricing breakdown -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between text-muted small mb-2">
                        <span><?= htmlspecialchars($product['name']) ?> (<?= ucfirst($plan) ?>)</span>
                        <span><?= CURRENCY_SYMBOL ?><?= number_format($price, 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between text-muted small mb-2">
                        <span><?= TRIAL_DAYS ?>-day trial</span>
                        <span class="text-success">FREE</span>
                    </div>
                    <hr class="border-secondary">
                    <div class="d-flex justify-content-between text-white fw-semibold">
                        <span>Today's charge</span>
                        <span class="text-success">$0.00</span>
                    </div>
                    <div class="d-flex justify-content-between text-muted small mt-1">
                        <span>After <?= TRIAL_DAYS ?> days</span>
                        <span><?= CURRENCY_SYMBOL ?><?= number_format($price, 2) ?>/<?= $plan === 'yearly' ? 'yr' : 'mo' ?></span>
                    </div>
                </div>

                <!-- Trust -->
                <div class="d-flex flex-column gap-2 pt-3 border-top border-secondary border-opacity-25">
                    <div class="d-flex align-items-center gap-2 text-muted small"><i class="bi bi-lock-fill text-success"></i>Secured by Stripe</div>
                    <div class="d-flex align-items-center gap-2 text-muted small"><i class="bi bi-shield-check text-primary"></i>Cancel anytime</div>
                    <div class="d-flex align-items-center gap-2 text-muted small"><i class="bi bi-arrow-counterclockwise text-warning"></i>Money-back guarantee</div>
                </div>
            </div>
        </div>

        <!-- Checkout Form -->
        <div class="col-lg-6 order-lg-1">
            <h3 class="text-white fw-bold mb-1">Complete Your Order</h3>
            <p class="text-muted mb-4">Start your <?= TRIAL_DAYS ?>-day free trial. No charge today.</p>

            <?php if ($stripeError): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($stripeError) ?></div>
            <?php endif; ?>

            <div class="glass-card rounded-4 p-4 mb-4">
                <h6 class="text-white fw-semibold mb-3"><i class="bi bi-person me-2 text-primary"></i>Account Details</h6>
                <div class="row g-2">
                    <div class="col-sm-6">
                        <div class="text-muted small">Name</div>
                        <div class="text-white"><?= htmlspecialchars($user['name']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Email</div>
                        <div class="text-white"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Stripe Payment Section -->
            <div class="glass-card rounded-4 p-4 mb-4">
                <h6 class="text-white fw-semibold mb-3"><i class="bi bi-credit-card me-2 text-primary"></i>Payment Information</h6>
                <p class="text-muted small mb-3">Your card will NOT be charged today. We collect it to activate your subscription after the trial.</p>

                <!-- Stripe Elements will render here -->
                <div id="stripe-card-element" class="form-control bg-dark border-secondary text-white p-3" style="height:44px">
                    <span class="text-muted small">Card details will appear here (Stripe integration)</span>
                </div>
                <div id="card-errors" class="text-danger small mt-2"></div>
            </div>

            <!-- Submit -->
            <form method="POST">
                <input type="hidden" name="start_checkout" value="1">
                <input type="hidden" name="plan" value="<?= htmlspecialchars($plan) ?>">
                <input type="hidden" name="demo_mode" value="1"> <!-- Remove in production -->
                <button type="submit" class="btn btn-primary w-100 btn-lg py-3">
                    <i class="bi bi-rocket-takeoff me-2"></i>
                    Start <?= TRIAL_DAYS ?>-Day Free Trial
                </button>
                <p class="text-muted small text-center mt-3">
                    By continuing you agree to our <a href="#" class="text-primary">Terms of Service</a>.
                    Cancel any time before trial ends and you won't be charged.
                </p>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
