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
        <p class="text-muted">Powerful AI-powered tools to automate your business tasks</p>
    </div>

    <div class="row g-4">

        <!-- Email Writer -->
        <div class="col-md-6 col-lg-3">
            <div class="glass-card rounded-4 p-4 h-100 d-flex flex-column">
                <div class="cat-icon mb-3" style="width:52px;height:52px;border-radius:14px;background:rgba(99,102,241,0.15);color:#6366f1;display:flex;align-items:center;justify-content:center">
                    <i class="bi bi-envelope-paper fs-4"></i>
                </div>
                <h5 class="text-white fw-semibold mb-2">AI Email Writer</h5>
                <p class="text-muted small mb-4 flex-grow-1">Write professional emails in seconds. Sales, follow-ups, complaints, proposals — just describe it.</p>
                <a href="/modules/email-writer.php" class="btn btn-primary w-100">
                    <i class="bi bi-magic me-2"></i>Open Tool
                </a>
            </div>
        </div>

        <!-- Social Post Generator -->
        <div class="col-md-6 col-lg-3">
            <div class="glass-card rounded-4 p-4 h-100 d-flex flex-column">
                <div class="cat-icon mb-3" style="width:52px;height:52px;border-radius:14px;background:rgba(16,185,129,0.15);color:#10b981;display:flex;align-items:center;justify-content:center">
                    <i class="bi bi-share fs-4"></i>
                </div>
                <h5 class="text-white fw-semibold mb-2">Social Post Generator</h5>
                <p class="text-muted small mb-4 flex-grow-1">Generate platform-ready posts for Facebook, Instagram, LinkedIn, and Twitter from a single prompt.</p>
                <a href="/modules/social-post.php" class="btn btn-success w-100">
                    <i class="bi bi-magic me-2"></i>Open Tool
                </a>
            </div>
        </div>

        <!-- Invoice Generator -->
        <div class="col-md-6 col-lg-3">
            <div class="glass-card rounded-4 p-4 h-100 d-flex flex-column">
                <div class="cat-icon mb-3" style="width:52px;height:52px;border-radius:14px;background:rgba(245,158,11,0.15);color:#f59e0b;display:flex;align-items:center;justify-content:center">
                    <i class="bi bi-receipt fs-4"></i>
                </div>
                <h5 class="text-white fw-semibold mb-2">Invoice Generator</h5>
                <p class="text-muted small mb-4 flex-grow-1">Create professional invoices with AI-formatted layouts. Fill in the details and print or save as PDF.</p>
                <a href="/modules/invoice-generator.php" class="btn btn-warning w-100">
                    <i class="bi bi-magic me-2"></i>Open Tool
                </a>
            </div>
        </div>

        <!-- Leave Request -->
        <div class="col-md-6 col-lg-3">
            <div class="glass-card rounded-4 p-4 h-100 d-flex flex-column">
                <div class="cat-icon mb-3" style="width:52px;height:52px;border-radius:14px;background:rgba(239,68,68,0.15);color:#ef4444;display:flex;align-items:center;justify-content:center">
                    <i class="bi bi-calendar-check fs-4"></i>
                </div>
                <h5 class="text-white fw-semibold mb-2">Leave Request</h5>
                <p class="text-muted small mb-4 flex-grow-1">Submit leave requests with AI-drafted messages. Track status and view your leave history in one place.</p>
                <a href="/modules/leave-request.php" class="btn btn-danger w-100">
                    <i class="bi bi-magic me-2"></i>Open Tool
                </a>
            </div>
        </div>

    </div>

    <!-- Back to dashboard -->
    <div class="mt-5">
        <a href="/dashboard.php" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
