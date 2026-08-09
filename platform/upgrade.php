<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

Auth::requireLogin();

$user = Auth::user();
$from = trim($_GET['from'] ?? '');

// Tool names for display
$toolNames = [
    'email'              => 'AI Email Writer',
    'customer_reply'     => 'Customer Reply AI',
    'sales_proposal'     => 'Sales Proposal Generator',
    'ad_copy'            => 'Ad Copy Generator',
    'social'             => 'Social Post Planner',
    'product_description'=> 'Product Description Writer',
    'cold_outreach'      => 'Cold Outreach Writer',
    'whatsapp_templates' => 'WhatsApp Templates',
    'invoice'            => 'Invoice Generator',
    'financial_analysis' => 'Financial Analysis AI',
    'job_description'    => 'Job Description Writer',
    'performance_review' => 'Performance Review Writer',
    'leave_reason'       => 'Leave Request Assistant',
    'meeting_minutes'    => 'Meeting Minutes AI',
    'sop'                => 'SOP Generator',
    'training_creator'   => 'Training Creator',
    'business_report'    => 'Business Report AI',
    'contract_drafter'   => 'Contract Drafter',
    'pitch_deck'         => 'Pitch Deck Builder',
    'chatbot_flow'       => 'Chatbot Flow Designer',
];

$toolName = isset($toolNames[$from]) ? $toolNames[$from] : 'this AI tool';

// Calculate trial info
$trialEnd      = strtotime($user['created_at']) + (TRIAL_DAYS * 86400);
$trialExpired  = time() > $trialEnd;
$daysAgo       = (int)ceil((time() - $trialEnd) / 86400);

$pageTitle = 'Upgrade to Continue';
require_once 'includes/header.php';
?>

<div class="min-vh-100 d-flex align-items-center py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 text-center">

                <!-- Icon -->
                <div class="mb-4" style="width:72px;height:72px;border-radius:50%;background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.3);display:flex;align-items:center;justify-content:center;margin:0 auto">
                    <i class="bi bi-lock-fill text-primary fs-3"></i>
                </div>

                <!-- Headline -->
                <h2 class="text-white fw-bold mb-2">
                    <?php if ($trialExpired): ?>
                    Your free trial has ended
                    <?php else: ?>
                    Subscription required
                    <?php endif; ?>
                </h2>
                <p class="text-muted mb-4">
                    <?php if ($from && isset($toolNames[$from])): ?>
                    To access <strong class="text-white"><?= htmlspecialchars($toolName) ?></strong>
                    <?php if ($trialExpired): ?>
                    — and all <?= count($toolNames) ?> AI Capsules —
                    <?php endif; ?>
                    you need an active plan.
                    <?php elseif ($trialExpired): ?>
                    Your <?= TRIAL_DAYS ?>-day trial ended <?= $daysAgo ?> day<?= $daysAgo !== 1 ? 's' : '' ?> ago.
                    Choose a plan to keep all <?= count($toolNames) ?> AI Capsules running for your business.
                    <?php else: ?>
                    An active subscription is required to use the AI tools.
                    <?php endif; ?>
                </p>

                <!-- What they're missing -->
                <div class="glass-card rounded-4 p-4 mb-5 text-start">
                    <h6 class="text-white fw-semibold mb-3"><i class="bi bi-stars me-2 text-warning"></i>Everything included in your plan</h6>
                    <div class="row g-2">
                        <?php
                        $highlights = [
                            ['bi-envelope-paper',    'AI Email Writer'],
                            ['bi-share',             'Social Post Planner'],
                            ['bi-file-earmark-text', 'Sales Proposals'],
                            ['bi-megaphone',         'Ad Copy Generator'],
                            ['bi-receipt',           'Invoice Generator'],
                            ['bi-chat-dots',         'Customer Reply AI'],
                            ['bi-journal-text',      'Meeting Minutes AI'],
                            ['bi-graph-up',          'Financial Analysis'],
                            ['bi-person-badge',      'Job Description Writer'],
                            ['bi-shield-check',      'Contract Drafter'],
                            ['bi-diagram-3',         'Chatbot Flow Designer'],
                            ['bi-rocket-takeoff',    'Pitch Deck Builder'],
                        ];
                        foreach ($highlights as [$icon, $name]):
                        ?>
                        <div class="col-6 col-md-4">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi <?= $icon ?> text-primary" style="font-size:13px;flex-shrink:0"></i>
                                <span class="text-muted small"><?= $name ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="col-6 col-md-4">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-plus-circle text-muted" style="font-size:13px;flex-shrink:0"></i>
                                <span class="text-muted small">+ <?= count($toolNames) - 12 ?> more tools</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CTA -->
                <div class="d-flex flex-wrap gap-3 justify-content-center">
                    <a href="/pricing.php" class="btn btn-primary btn-lg px-5">
                        <i class="bi bi-rocket-takeoff me-2"></i>View Plans &amp; Pricing
                    </a>
                    <a href="/dashboard.php" class="btn btn-outline-secondary btn-lg px-4">
                        Back to Dashboard
                    </a>
                </div>

                <!-- Trial detail -->
                <?php if ($trialExpired): ?>
                <p class="text-muted small mt-4">
                    Started trial: <?= date('d M Y', strtotime($user['created_at'])) ?>
                    &bull; Trial ended: <?= date('d M Y', $trialEnd) ?>
                </p>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
