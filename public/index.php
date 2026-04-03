<?php
declare(strict_types=1);

/**
 * public/index.php
 * Landing page — redirect to login or dashboard.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';

boot_session();

// Auto-redirect logged-in users to their dashboard
if (auth_user())  redirect(BASE_URL . '/client/dashboard.php');
if (auth_admin()) redirect(BASE_URL . '/admin/index.php');

$siteName = setting('site_name', 'VideoSaaS');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($siteName) ?> — AI Marketing Video Generator</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
<nav class="navbar">
    <div class="navbar-inner">
        <span class="navbar-brand">Video<span>SaaS</span></span>
        <ul class="navbar-nav" style="display:flex">
            <li><a href="login.php">Sign In</a></li>
            <li><a href="register.php" class="btn btn-primary btn-sm" style="margin-left:8px">Get Started</a></li>
        </ul>
    </div>
</nav>

<div style="text-align:center;padding:80px 16px 60px;
            background:radial-gradient(ellipse at 50% 0%,rgba(108,71,255,.2) 0%,transparent 70%)">
    <div style="display:inline-block;background:rgba(108,71,255,.15);border:1px solid rgba(108,71,255,.3);
                border-radius:20px;padding:6px 18px;font-size:.85rem;color:var(--color-primary);margin-bottom:20px">
        Powered by BytePlus / Bytedance AI
    </div>
    <h1 style="font-size:clamp(2rem,5vw,3.5rem);font-weight:900;line-height:1.15;margin-bottom:20px">
        Create AI Marketing Videos<br>
        <span style="color:var(--color-primary)">in Seconds</span>
    </h1>
    <p style="font-size:1.1rem;color:var(--color-muted);max-width:520px;margin:0 auto 32px">
        Turn your marketing brief into stunning videos. Pay only for what you generate — with our flexible credit system.
    </p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <a href="register.php" class="btn btn-primary btn-lg">Start Free →</a>
        <a href="login.php"    class="btn btn-ghost btn-lg">Sign In</a>
    </div>
</div>

<!-- Features grid -->
<div class="container" style="padding-bottom:60px">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px;margin-top:60px">
        <?php
        $features = [
            ['⚡', 'Credit-based',  'Buy only what you need. No subscriptions, no surprises.'],
            ['🎬', 'AI Video Gen',  'BytePlus 1.5 generates professional marketing videos from text.'],
            ['💳', 'Easy Payments', 'Bank transfer with instant admin approval.'],
            ['🔗', 'Referral Rewards', 'Invite friends and earn free credits automatically.'],
            ['📱', 'Social Sharing', 'Share videos directly to WhatsApp, Facebook, X, and more.'],
            ['🛡️', 'Secure & Reliable', 'Auto-refund if generation fails. Your credits are safe.'],
        ];
        foreach ($features as [$icon, $title, $desc]):
        ?>
        <div class="card" style="text-align:center">
            <div style="font-size:2rem;margin-bottom:12px"><?= $icon ?></div>
            <div style="font-weight:700;margin-bottom:8px"><?= e($title) ?></div>
            <div class="text-muted text-sm"><?= e($desc) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<footer style="text-align:center;padding:24px;border-top:1px solid var(--color-border);color:var(--color-muted);font-size:.85rem">
    © <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.
</footer>
</body>
</html>
