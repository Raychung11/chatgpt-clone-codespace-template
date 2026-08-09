<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
Auth::requireLogin();

/* ── Cart AJAX / action handler ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action']     ?? '';
    $product_id = intval($_POST['product_id'] ?? 0);
    $delta      = intval($_POST['delta']      ?? 0);

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if ($action === 'update_qty' && $product_id > 0) {
        $current = $_SESSION['cart'][$product_id] ?? 0;
        $new     = max(0, $current + $delta);
        if ($new === 0) {
            unset($_SESSION['cart'][$product_id]);
        } else {
            $_SESSION['cart'][$product_id] = $new;
        }
        header('Location: /cart.php');
        exit;
    }

    if ($action === 'remove' && $product_id > 0) {
        unset($_SESSION['cart'][$product_id]);
        header('Location: /cart.php');
        exit;
    }

    if ($action === 'add' && $product_id > 0) {
        $_SESSION['cart'][$product_id] = ($_SESSION['cart'][$product_id] ?? 0) + 1;
        header('Location: /cart.php');
        exit;
    }
}

/* ── Build cart items from DB ── */
$cartItems = [];
$subtotal  = 0;

if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $ids = array_map('intval', array_keys($_SESSION['cart']));
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $products = DB::fetchAll(
            "SELECT id, name, slug, price, category_id FROM products WHERE id IN ($placeholders)",
            $ids
        );
        foreach ($products as $p) {
            $qty   = $_SESSION['cart'][$p['id']] ?? 1;
            $line  = $p['price'] * $qty;
            $subtotal += $line;
            $cartItems[] = [
                'id'    => $p['id'],
                'name'  => $p['name'],
                'slug'  => $p['slug'],
                'price' => $p['price'],
                'qty'   => $qty,
                'line'  => $line,
            ];
        }
    }
}

$pageTitle = 'Your Cart';
require_once 'includes/header.php';
?>

<section class="py-5">
  <div class="container py-3">
    <h1 class="fw-bold mb-1 fs-2"><i class="bi bi-cart3 me-2 text-primary"></i>Your Cart</h1>
    <p class="text-muted mb-5">Review your selected Capsules before checkout.</p>

    <?php if (empty($cartItems)): ?>
    <!-- Empty state -->
    <div class="text-center py-5">
      <div class="glass-card rounded-4 p-5 d-inline-block">
        <div class="display-1 mb-3">🛒</div>
        <h3 class="fw-bold mb-2">Your cart is empty</h3>
        <p class="text-muted mb-4">You haven't added any Capsules yet. Browse the Capsule Store to find the perfect tools for your business.</p>
        <a href="/marketplace.php" class="btn btn-primary px-5">
          <i class="bi bi-shop me-2"></i>Browse Capsule Store
        </a>
      </div>
    </div>

    <?php else: ?>
    <!-- Cart content -->
    <div class="row g-4 align-items-start">

      <!-- Left: items -->
      <div class="col-lg-8">
        <div class="glass-card rounded-4 overflow-hidden">
          <div class="table-responsive">
            <table class="table align-middle mb-0" style="color:inherit;">
              <thead>
                <tr style="background:rgba(255,255,255,0.04);border-bottom:1px solid rgba(255,255,255,0.08);">
                  <th class="py-3 px-4 fw-semibold border-0" style="min-width:220px;">Product</th>
                  <th class="py-3 px-4 fw-semibold border-0 text-center">Price</th>
                  <th class="py-3 px-4 fw-semibold border-0 text-center">Quantity</th>
                  <th class="py-3 px-4 fw-semibold border-0 text-end">Total</th>
                  <th class="py-3 px-4 border-0"></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($cartItems as $item): ?>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">
                  <td class="py-3 px-4 border-0">
                    <div class="d-flex align-items-center gap-3">
                      <div class="flex-shrink-0" style="width:44px;height:44px;border-radius:10px;background:rgba(99,102,241,0.12);display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-cpu text-primary"></i>
                      </div>
                      <div>
                        <a href="/product.php?slug=<?= htmlspecialchars($item['slug']) ?>" class="fw-semibold text-white text-decoration-none small">
                          <?= htmlspecialchars($item['name']) ?>
                        </a>
                        <div class="text-muted" style="font-size:11px;">AI Agent</div>
                      </div>
                    </div>
                  </td>
                  <td class="py-3 px-4 border-0 text-center text-muted small">
                    <?= APP_CURRENCY ?><?= number_format($item['price'], 2) ?>/mo
                  </td>
                  <td class="py-3 px-4 border-0 text-center">
                    <div class="d-flex align-items-center justify-content-center gap-2">
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="update_qty">
                        <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                        <input type="hidden" name="delta" value="-1">
                        <button type="submit" class="btn btn-sm btn-outline-secondary" style="width:28px;height:28px;padding:0;display:flex;align-items:center;justify-content:center;"
                          <?= $item['qty'] <= 1 ? 'disabled' : '' ?>>
                          <i class="bi bi-dash"></i>
                        </button>
                      </form>
                      <span class="fw-semibold small" style="min-width:20px;text-align:center;"><?= $item['qty'] ?></span>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="update_qty">
                        <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                        <input type="hidden" name="delta" value="1">
                        <button type="submit" class="btn btn-sm btn-outline-secondary" style="width:28px;height:28px;padding:0;display:flex;align-items:center;justify-content:center;">
                          <i class="bi bi-plus"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                  <td class="py-3 px-4 border-0 text-end fw-semibold small">
                    <?= APP_CURRENCY ?><?= number_format($item['line'], 2) ?>
                  </td>
                  <td class="py-3 px-4 border-0">
                    <form method="POST">
                      <input type="hidden" name="action" value="remove">
                      <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger" style="width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center;" title="Remove">
                        <i class="bi bi-trash3"></i>
                      </button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="mt-3">
          <a href="/marketplace.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Continue Shopping
          </a>
        </div>
      </div>

      <!-- Right: summary -->
      <div class="col-lg-4">
        <div class="glass-card rounded-4 p-4 sticky-top" style="top:80px;">
          <h6 class="fw-bold mb-4">Order Summary</h6>

          <div class="d-flex justify-content-between align-items-center mb-2 small">
            <span class="text-muted">Subtotal (<?= count($cartItems) ?> agent<?= count($cartItems) > 1 ? 's' : '' ?>)</span>
            <span class="fw-semibold"><?= APP_CURRENCY ?><?= number_format($subtotal, 2) ?>/mo</span>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-2 small">
            <span class="text-muted">Discount</span>
            <span class="text-success fw-semibold">–<?= APP_CURRENCY ?>0.00</span>
          </div>

          <!-- Trial badge -->
          <div class="rounded-3 p-3 mb-4 mt-3 d-flex align-items-center gap-2"
               style="background:rgba(16,185,129,0.10);border:1px solid rgba(16,185,129,0.25);">
            <i class="bi bi-gift-fill text-success fs-5"></i>
            <div>
              <div class="fw-semibold small text-success">14-Day Free Trial</div>
              <div class="text-muted" style="font-size:11px;">You won't be charged until the trial ends</div>
            </div>
          </div>

          <hr style="border-color:rgba(255,255,255,0.08);">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <span class="fw-bold">Total / month</span>
            <span class="fw-bold fs-5"><?= APP_CURRENCY ?><?= number_format($subtotal, 2) ?></span>
          </div>

          <a href="/checkout.php" class="btn btn-primary w-100 py-2 fw-semibold mb-2">
            <i class="bi bi-lock-fill me-2"></i>Proceed to Checkout
          </a>
          <p class="text-center text-muted mb-0" style="font-size:11px;">
            <i class="bi bi-shield-check me-1"></i>Secured by Stripe · SSL encrypted
          </p>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
