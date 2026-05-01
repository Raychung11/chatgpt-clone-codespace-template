<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireLogin();
$pageTitle = 'AI Tools';
require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="mb-5">
        <h2 class="text-white fw-bold mb-1"><i class="bi bi-cpu-fill me-2 text-primary"></i>AI Tools</h2>
        <p class="text-muted">20 AI-powered tools to automate your everyday business tasks</p>
    </div>

    <?php
    $groups = [
        'Communication' => [
            ['Email Writer',        '/modules/email-writer.php',       'bi-envelope-paper', '#6366f1', 'Write professional emails in seconds — sales, follow-ups, proposals.'],
            ['Social Post Generator','/modules/social-post.php',       'bi-share',          '#10b981', 'Platform-ready posts for Facebook, Instagram, LinkedIn & Twitter.'],
            ['Customer Reply',       '/modules/customer-reply.php',    'bi-chat-dots',      '#14b8a6', 'Reply to complaints, enquiries, and reviews with confidence.'],
            ['Ad Copy Writer',       '/modules/ad-copy.php',           'bi-megaphone',      '#ec4899', 'High-converting ad copy for Google, Facebook, TikTok & LinkedIn.'],
        ],
        'Sales & Operations' => [
            ['Sales Proposal',       '/modules/sales-proposal.php',    'bi-file-earmark-text','#f97316','Draft a professional proposal that wins the deal.'],
            ['Invoice Generator',    '/modules/invoice-generator.php', 'bi-receipt',        '#f59e0b', 'Create professional invoices and print or save as PDF.'],
            ['Product Description',  '/modules/product-description.php','bi-bag',           '#6366f1', 'SEO-ready product copy for your website, Shopee, or Amazon.'],
            ['SOP Generator',        '/modules/sop-generator.php',     'bi-list-ol',        '#3b82f6', 'Turn rough process notes into a full Standard Operating Procedure.'],
        ],
        'HR & People' => [
            ['Job Description',      '/modules/job-description.php',   'bi-person-badge',   '#06b6d4', 'Create compelling JDs that attract the right talent.'],
            ['Leave Request',        '/modules/leave-request.php',     'bi-calendar-check', '#ef4444', 'Submit leave requests with AI-drafted messages and track history.'],
            ['Performance Review',   '/modules/performance-review.php','bi-star-half',      '#84cc16', 'Generate balanced, professional reviews for any staff member.'],
            ['Meeting Minutes',      '/modules/meeting-minutes.php',   'bi-journal-text',   '#8b5cf6', 'Transform rough notes into structured meeting minutes instantly.'],
        ],
        'Strategy & Finance' => [
            ['Financial Analysis',   '/modules/financial-analysis.php','bi-bar-chart-line', '#10b981', 'Paste your numbers — get plain-English analysis, red flags, and recommendations.'],
            ['Business Report',      '/modules/business-report.php',   'bi-file-earmark-bar-chart','#3b82f6','Generate professional monthly, quarterly, or annual business reports.'],
            ['Pitch Deck Generator', '/modules/pitch-deck.php',        'bi-easel',          '#f59e0b', 'Slide-by-slide pitch deck content that wins investors and clients.'],
            ['Cold Outreach',        '/modules/cold-outreach.php',     'bi-send',           '#f97316', 'Multi-touch cold email and LinkedIn sequences that get replies.'],
        ],
        'Automation & Systems' => [
            ['WhatsApp Templates',   '/modules/whatsapp-templates.php','bi-whatsapp',       '#25d366', 'Broadcast messages and campaign templates optimised for WhatsApp.'],
            ['Chatbot Flow Designer','/modules/chatbot-flow.php',      'bi-robot',          '#06b6d4', 'Design your WhatsApp or website chatbot conversation flow with AI.'],
            ['Contract Drafter',     '/modules/contract-drafter.php',  'bi-file-earmark-lock','#14b8a6','Draft NDAs, service agreements, and employment contracts in minutes.'],
            ['Training Creator',     '/modules/training-creator.php',  'bi-mortarboard',    '#8b5cf6', 'Build full training modules, onboarding guides, and quizzes with AI.'],
        ],
    ];
    foreach ($groups as $groupName => $tools):
    ?>
    <div class="mb-5">
        <h6 class="text-muted fw-semibold mb-3 text-uppercase" style="font-size:11px;letter-spacing:.08em"><?= $groupName ?></h6>
        <div class="row g-3">
            <?php foreach ($tools as [$name, $url, $icon, $color, $desc]): ?>
            <div class="col-sm-6 col-lg-3">
                <div class="glass-card rounded-4 p-4 h-100 d-flex flex-column" style="transition:border-color .2s" onmouseover="this.style.borderColor='<?= $color ?>44'" onmouseout="this.style.borderColor=''">
                    <div class="mb-3" style="width:48px;height:48px;border-radius:13px;background:<?= $color ?>18;color:<?= $color ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="bi <?= $icon ?> fs-5"></i>
                    </div>
                    <h6 class="text-white fw-semibold mb-2"><?= $name ?></h6>
                    <p class="text-muted small mb-4 flex-grow-1" style="font-size:12.5px"><?= $desc ?></p>
                    <a href="<?= $url ?>" class="btn btn-sm w-100" style="background:<?= $color ?>18;border:1px solid <?= $color ?>33;color:<?= $color ?>">
                        <i class="bi bi-magic me-1"></i>Open Tool
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="mt-2">
        <a href="/dashboard.php" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
