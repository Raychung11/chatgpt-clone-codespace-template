<?php
declare(strict_types=1);

/**
 * public/index.php
 * Landing page — motions.my
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';

boot_session();

// Auto-redirect logged-in users
if (auth_user())  redirect(BASE_URL . '/client/dashboard.php');
if (auth_admin()) redirect(BASE_URL . '/admin/index.php');

$siteName = setting('site_name', 'Motions');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($siteName) ?> — AI Marketing Video Generator</title>
    <meta name="description" content="Create stunning AI marketing videos in seconds. Turn your brief into professional ads powered by BytePlus Seedance AI.">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
    <style>
        .hero {
            text-align: center;
            padding: 90px 16px 70px;
            background: radial-gradient(ellipse at 50% -10%, rgba(108,71,255,.25) 0%, transparent 65%);
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%236c47ff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }
        .hero-badge {
            display: inline-block;
            background: rgba(108,71,255,.15);
            border: 1px solid rgba(108,71,255,.35);
            border-radius: 20px;
            padding: 6px 18px;
            font-size: .82rem;
            color: var(--color-primary);
            margin-bottom: 24px;
            letter-spacing: .02em;
        }
        .hero h1 {
            font-size: clamp(2.2rem, 6vw, 4rem);
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 22px;
            letter-spacing: -.02em;
        }
        .hero h1 .accent { color: var(--color-primary); }
        .hero p {
            font-size: 1.1rem;
            color: var(--color-muted);
            max-width: 500px;
            margin: 0 auto 36px;
            line-height: 1.65;
        }
        .hero-cta { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }

        /* Stats row */
        .stats { display: flex; justify-content: center; gap: 48px; flex-wrap: wrap; padding: 40px 16px; border-bottom: 1px solid var(--color-border); }
        .stat { text-align: center; }
        .stat-num { font-size: 1.8rem; font-weight: 800; color: var(--color-text); }
        .stat-label { font-size: .8rem; color: var(--color-muted); margin-top: 2px; }

        /* Features */
        .features { padding: 70px 16px; }
        .features-title { text-align: center; font-size: 1.7rem; font-weight: 800; margin-bottom: 10px; }
        .features-sub { text-align: center; color: var(--color-muted); margin-bottom: 48px; }
        .features-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px; max-width: 960px; margin: 0 auto; }
        .feature-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg, 12px); padding: 24px; transition: border-color .2s, transform .2s; }
        .feature-card:hover { border-color: var(--color-primary); transform: translateY(-2px); }
        .feature-icon { font-size: 2rem; margin-bottom: 14px; }
        .feature-title { font-weight: 700; margin-bottom: 8px; }
        .feature-desc { color: var(--color-muted); font-size: .88rem; line-height: 1.6; }

        /* How it works */
        .how { padding: 70px 16px; background: var(--color-surface); border-top: 1px solid var(--color-border); border-bottom: 1px solid var(--color-border); }
        .how-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 30px; max-width: 800px; margin: 0 auto; }
        .how-step { text-align: center; }
        .how-num { width: 40px; height: 40px; border-radius: 50%; background: var(--color-primary); color: #fff; font-weight: 800; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px; }
        .how-title { font-weight: 700; margin-bottom: 6px; }
        .how-desc { font-size: .85rem; color: var(--color-muted); line-height: 1.5; }

        /* Pricing teaser */
        .pricing { padding: 70px 16px; text-align: center; }
        .credit-card { display: inline-block; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg, 12px); padding: 32px 40px; margin-top: 32px; min-width: 280px; }

        /* ── Video showcase ── */
        .showcase { padding: 70px 16px; background: var(--color-surface); border-top: 1px solid var(--color-border); border-bottom: 1px solid var(--color-border); }
        .showcase-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            max-width: 1040px;
            margin: 40px auto 0;
        }
        @media (max-width: 900px) { .showcase-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 540px) { .showcase-grid { grid-template-columns: 1fr; } }

        .vid-card {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            aspect-ratio: 16/9;
            background: var(--color-surface2);
            border: 1px solid var(--color-border);
            cursor: pointer;
            transition: transform .25s, box-shadow .25s;
        }
        .vid-card:hover { transform: scale(1.03); box-shadow: 0 16px 48px rgba(0,0,0,.5); }

        .vid-card video {
            position: absolute; inset: 0;
            width: 100%; height: 100%;
            object-fit: cover;
            display: block;
        }
        /* Animated gradient placeholder shown when no video src is set */
        .vid-card .vid-placeholder {
            position: absolute; inset: 0;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            background-size: 300% 300%;
            animation: gradShift 5s ease infinite;
        }
        .vid-card video[src]:not([src=""]) ~ .vid-placeholder { display: none; }
        .vid-card .vid-play {
            width: 52px; height: 52px; border-radius: 50%;
            background: rgba(255,255,255,.18);
            border: 2px solid rgba(255,255,255,.5);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            backdrop-filter: blur(4px);
            transition: background .2s;
        }
        .vid-card:hover .vid-play { background: rgba(108,71,255,.7); border-color: transparent; }
        .vid-card .vid-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,.7) 0%, transparent 55%);
            pointer-events: none;
        }
        .vid-card .vid-meta {
            position: absolute; bottom: 0; left: 0; right: 0;
            padding: 14px 14px 12px;
            display: flex; align-items: flex-end; justify-content: space-between;
        }
        .vid-badge {
            font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
            background: rgba(108,71,255,.85); color: #fff;
            padding: 3px 9px; border-radius: 20px;
            backdrop-filter: blur(4px);
        }
        .vid-dur {
            font-size: .72rem; color: rgba(255,255,255,.75);
            background: rgba(0,0,0,.4); padding: 2px 7px; border-radius: 4px;
        }
        @keyframes gradShift {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        /* Full-screen lightbox */
        .vid-lightbox {
            display: none; position: fixed; inset: 0; z-index: 9999;
            background: rgba(0,0,0,.92);
            align-items: center; justify-content: center;
        }
        .vid-lightbox.open { display: flex; }
        .vid-lightbox video { max-width: 92vw; max-height: 85vh; border-radius: 10px; outline: none; }
        .vid-lightbox-close {
            position: absolute; top: 20px; right: 28px;
            font-size: 2.2rem; color: #fff; cursor: pointer; line-height: 1;
            opacity: .7; transition: opacity .15s;
        }
        .vid-lightbox-close:hover { opacity: 1; }

        /* Footer */
        footer { text-align: center; padding: 28px 16px; border-top: 1px solid var(--color-border); color: var(--color-muted); font-size: .85rem; }
        footer a { color: var(--color-muted); margin: 0 8px; }
        footer a:hover { color: var(--color-text); }
    </style>
</head>
<body>

<!-- ── Navbar ── -->
<nav class="navbar">
    <div class="navbar-inner">
        <a href="<?= BASE_URL ?>/" class="navbar-brand"><?= e($siteName) ?></a>
        <ul class="navbar-nav" style="display:flex;margin-left:auto">
            <li><a href="<?= BASE_URL ?>/public/login.php" style="color:var(--color-muted);font-size:.9rem">Sign In</a></li>
            <li>
                <a href="<?= BASE_URL ?>/public/register.php" class="btn btn-primary btn-sm" style="margin-left:10px">
                    Get Started Free →
                </a>
            </li>
        </ul>
    </div>
</nav>

<!-- ── Hero ── -->
<section class="hero">
    <div class="hero-badge">🇲🇾 Made for Malaysian Marketers</div>
    <h1>
        Create AI Marketing Videos<br>
        <span class="accent">in Seconds, Not Days</span>
    </h1>
    <p>
        Turn your product brief into stunning video ads powered by
        BytePlus Seedance 1.5 — the same AI used by the world's top brands.
        No editing skills needed.
    </p>
    <div class="hero-cta">
        <a href="<?= BASE_URL ?>/public/register.php" class="btn btn-primary btn-lg">Start Free — No Card Required</a>
        <a href="<?= BASE_URL ?>/public/login.php"    class="btn btn-ghost btn-lg">Sign In</a>
    </div>
</section>

<!-- ── Stats ── -->
<div class="stats">
    <div class="stat"><div class="stat-num">10s–30s</div><div class="stat-label">Video length</div></div>
    <div class="stat"><div class="stat-num">720p–1080p</div><div class="stat-label">HD quality</div></div>
    <div class="stat"><div class="stat-num">5–30 min</div><div class="stat-label">Generation time</div></div>
    <div class="stat"><div class="stat-num">100%</div><div class="stat-label">Auto-refund if failed</div></div>
</div>

<!-- ── Video Showcase ── -->
<?php
/*
 * DEMO VIDEOS — paste your BytePlus CDN video URLs here.
 * Leave 'src' as '' to show the animated placeholder.
 * 'poster' is the thumbnail image shown before the video plays.
 */
$showcaseVideos = [
    ['label'=>'F&B',        'dur'=>'10s', 'src'=>'', 'poster'=>'', 'grad'=>'linear-gradient(135deg,#f97316,#ea580c,#9a3412)'],
    ['label'=>'Beauty',     'dur'=>'10s', 'src'=>'', 'poster'=>'', 'grad'=>'linear-gradient(135deg,#ec4899,#db2777,#9d174d)'],
    ['label'=>'Real Estate','dur'=>'10s', 'src'=>'', 'poster'=>'', 'grad'=>'linear-gradient(135deg,#6c47ff,#4f46e5,#3730a3)'],
    ['label'=>'Automotive', 'dur'=>'10s', 'src'=>'', 'poster'=>'', 'grad'=>'linear-gradient(135deg,#64748b,#334155,#0f172a)'],
    ['label'=>'Fashion',    'dur'=>'10s', 'src'=>'', 'poster'=>'', 'grad'=>'linear-gradient(135deg,#a855f7,#7c3aed,#4c1d95)'],
    ['label'=>'Tech',       'dur'=>'10s', 'src'=>'', 'poster'=>'', 'grad'=>'linear-gradient(135deg,#06b6d4,#0891b2,#164e63)'],
];
?>
<section class="showcase">
    <div style="text-align:center">
        <div class="features-title">See what Motions creates</div>
        <p class="features-sub" style="margin-bottom:0">AI-generated marketing videos — ready in minutes</p>
    </div>

    <div class="showcase-grid">
        <?php foreach ($showcaseVideos as $i => $v): ?>
        <div class="vid-card" onclick="openVidLight(<?= $i ?>)">
            <?php if ($v['src']): ?>
                <video src="<?= e($v['src']) ?>"
                       <?= $v['poster'] ? 'poster="'.e($v['poster']).'"' : '' ?>
                       muted loop playsinline preload="none"
                       id="showcase-vid-<?= $i ?>"></video>
            <?php endif ?>
            <div class="vid-placeholder" style="background:<?= $v['grad'] ?>">
                <div class="vid-play">▶</div>
            </div>
            <div class="vid-overlay"></div>
            <div class="vid-meta">
                <span class="vid-badge"><?= e($v['label']) ?></span>
                <span class="vid-dur"><?= e($v['dur']) ?></span>
            </div>
        </div>
        <?php endforeach ?>
    </div>

    <div style="text-align:center;margin-top:36px">
        <a href="<?= BASE_URL ?>/public/register.php" class="btn btn-primary btn-lg">
            Create your own video →
        </a>
    </div>
</section>

<!-- Lightbox -->
<div class="vid-lightbox" id="vidLightbox" onclick="closeVidLight()">
    <span class="vid-lightbox-close" onclick="closeVidLight()">✕</span>
    <video id="vidLightboxPlayer" controls playsinline></video>
</div>

<!-- ── Features ── -->
<section class="features">
    <div class="features-title">Everything you need to produce great video ads</div>
    <p class="features-sub">From single-clip promos to full 30-second brand stories</p>
    <div class="features-grid">
        <?php
        $features = [
            ['🎬', 'Text-to-Video',      'Write a prompt, pick a duration — Seedance 1.5 does the rest. 720p or 1080p.'],
            ['🧑‍🎤', 'AI Avatar',          'Upload a portrait + voice, get a talking-head spokesperson video in minutes.'],
            ['📹', '30-Second Ads',       'Chain 3 clips with first-frame continuity into one seamless 30s brand story.'],
            ['✨', 'AI Prompt Helper',    'Not sure what to write? Our LLM rewrites your brief into a cinematic prompt.'],
            ['📝', 'Ready Templates',     '10+ fill-in-the-blank video templates: F&B, beauty, real estate & more.'],
            ['💳', 'Credit System',       'Buy only what you need. No monthly fees. Credits never expire.'],
            ['🔗', 'Referral Rewards',    'Share your link, earn 10% of every purchase your referrals make.'],
            ['🔒', 'Safe & Reliable',     'Credits auto-refunded if any generation fails. Zero risk.'],
        ];
        foreach ($features as [$icon, $title, $desc]):
        ?>
        <div class="feature-card">
            <div class="feature-icon"><?= $icon ?></div>
            <div class="feature-title"><?= e($title) ?></div>
            <div class="feature-desc"><?= e($desc) ?></div>
        </div>
        <?php endforeach ?>
    </div>
</section>

<!-- ── How it works ── -->
<section class="how">
    <div style="text-align:center;margin-bottom:48px">
        <div class="features-title">How it works</div>
        <p class="features-sub" style="margin-bottom:0">From idea to video in 4 simple steps</p>
    </div>
    <div class="how-grid">
        <div class="how-step">
            <div class="how-num">1</div>
            <div class="how-title">Register & top up</div>
            <div class="how-desc">Create a free account, purchase a credit package via bank transfer.</div>
        </div>
        <div class="how-step">
            <div class="how-num">2</div>
            <div class="how-title">Write your brief</div>
            <div class="how-desc">Choose a template or write your own prompt. AI will polish it for you.</div>
        </div>
        <div class="how-step">
            <div class="how-num">3</div>
            <div class="how-title">Generate</div>
            <div class="how-desc">Hit submit — Seedance 1.5 renders your video. Usually ready in 5–30 min.</div>
        </div>
        <div class="how-step">
            <div class="how-num">4</div>
            <div class="how-title">Download & share</div>
            <div class="how-desc">Download your MP4 and share directly to WhatsApp, Facebook, or TikTok.</div>
        </div>
    </div>
</section>

<!-- ── Pricing teaser ── -->
<section class="pricing">
    <div class="features-title">Simple, pay-as-you-go pricing</div>
    <p class="features-sub">No subscriptions. No hidden fees. Credits never expire.</p>
    <div class="credit-card">
        <div style="font-size:.85rem;color:var(--color-muted);margin-bottom:16px;text-transform:uppercase;letter-spacing:.06em">Typical costs</div>
        <?php
        $plans = [
            ['5s video (HD)',   '5 credits'],
            ['10s video (HD)',  '35 credits'],
            ['AI Avatar 10s',  '5 credits'],
            ['30s Ad (3 clips)', '105 credits'],
        ];
        foreach ($plans as [$item, $cost]):
        ?>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--color-border);font-size:.9rem">
            <span><?= e($item) ?></span>
            <span style="font-weight:700;color:var(--color-primary)"><?= e($cost) ?></span>
        </div>
        <?php endforeach ?>
        <div style="margin-top:24px">
            <a href="<?= BASE_URL ?>/public/register.php" class="btn btn-primary" style="width:100%">Start Free →</a>
        </div>
    </div>
</section>

<!-- ── Footer ── -->
<footer>
    <div style="margin-bottom:8px">
        © <?= date('Y') ?> <?= e($siteName) ?> &nbsp;·&nbsp; motions.my
    </div>
    <div>
        <a href="<?= BASE_URL ?>/public/login.php">Sign In</a>
        <a href="<?= BASE_URL ?>/public/register.php">Register</a>
    </div>
</footer>

<script>
// ── Showcase video hover-play ─────────────────────────────────────────────────
document.querySelectorAll('.vid-card').forEach(card => {
    const vid = card.querySelector('video');
    if (!vid || !vid.src) return;
    card.addEventListener('mouseenter', () => { vid.play().catch(() => {}); });
    card.addEventListener('mouseleave', () => { vid.pause(); vid.currentTime = 0; });
});

// ── Lightbox ──────────────────────────────────────────────────────────────────
const showcaseSrcs = <?= json_encode(array_column($showcaseVideos, 'src')) ?>;
const lightbox  = document.getElementById('vidLightbox');
const lbPlayer  = document.getElementById('vidLightboxPlayer');

function openVidLight(idx) {
    const src = showcaseSrcs[idx];
    if (!src) return; // placeholder — no video yet
    lbPlayer.src = src;
    lbPlayer.play().catch(() => {});
    lightbox.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeVidLight() {
    lbPlayer.pause();
    lbPlayer.src = '';
    lightbox.classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeVidLight(); });
</script>
</body>
</html>
