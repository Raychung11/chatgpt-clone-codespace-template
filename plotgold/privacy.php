<?php
require_once __DIR__ . '/inc/bootstrap.php';

$page_title       = is_lang('zh') ? '隐私政策' : 'Privacy Policy';
$meta_description = 'PlotGold Malaysia Privacy Policy — how we collect, use and protect your personal data in compliance with Malaysia\'s Personal Data Protection Act 2010 (PDPA).';
$lastUpdated      = '10 April 2025';

include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<nav class="bg-white border-bottom">
    <div class="container py-2">
        <ol class="breadcrumb pg-breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= pg_url() ?>">Home</a></li>
            <li class="breadcrumb-item active"><?= _e('footer.privacy') ?></li>
        </ol>
    </div>
</nav>

<!-- Hero -->
<div class="bg-white border-bottom py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-inline-flex align-items-center gap-2 mb-3 px-3 py-1 rounded-pill"
                     style="background:var(--pg-gold-pale);font-size:.82rem;color:var(--pg-gold)">
                    <i class="fas fa-shield-alt"></i>
                    <?= is_lang('zh') ? '依据《2010年个人数据保护法》' : 'In compliance with Malaysia PDPA 2010' ?>
                </div>
                <h1 class="h2 fw-700 text-navy mb-2">
                    <?= is_lang('zh') ? '隐私政策' : 'Privacy Policy' ?>
                </h1>
                <p class="text-muted mb-0">
                    <?= is_lang('zh')
                        ? '最后更新：' . $lastUpdated
                        : 'Last updated: ' . $lastUpdated ?>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="row g-4">

        <!-- TOC Sidebar -->
        <div class="col-lg-3 d-none d-lg-block">
            <div class="pg-card p-3 sticky-top" style="top:80px">
                <div class="small fw-600 text-muted text-uppercase mb-3" style="letter-spacing:.08em">
                    <?= is_lang('zh') ? '目录' : 'Contents' ?>
                </div>
                <nav class="nav flex-column gap-1" style="font-size:.85rem">
                    <?php
                    $sections = is_lang('zh') ? [
                        '#collect'    => '1. 我们收集的信息',
                        '#use'        => '2. 信息使用方式',
                        '#share'      => '3. 信息共享',
                        '#cookies'    => '4. Cookie 政策',
                        '#security'   => '5. 数据安全',
                        '#retention'  => '6. 数据保留',
                        '#rights'     => '7. 您的权利（PDPA）',
                        '#children'   => '8. 儿童隐私',
                        '#third'      => '9. 第三方链接',
                        '#changes'    => '10. 政策变更',
                        '#contact'    => '11. 联系我们',
                    ] : [
                        '#collect'    => '1. Information We Collect',
                        '#use'        => '2. How We Use Your Information',
                        '#share'      => '3. Information Sharing',
                        '#cookies'    => '4. Cookies Policy',
                        '#security'   => '5. Data Security',
                        '#retention'  => '6. Data Retention',
                        '#rights'     => '7. Your Rights (PDPA)',
                        '#children'   => '8. Children\'s Privacy',
                        '#third'      => '9. Third-Party Links',
                        '#changes'    => '10. Changes to This Policy',
                        '#contact'    => '11. Contact Us',
                    ];
                    foreach ($sections as $href => $label): ?>
                    <a href="<?= $href ?>" class="nav-link py-1 px-2 text-muted rounded-2"
                       style="line-height:1.4"><?= h($label) ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">
            <div class="pg-card p-4 p-md-5">

                <?php if (is_lang('zh')): ?>
                <!-- ── 中文版本 ───────────────────────────────────── -->

                <p class="text-muted">PlotGold Malaysia（"PlotGold"、"我们"、"平台"）承诺依据马来西亚《2010年个人数据保护法》（PDPA）保护您的个人数据。本政策说明我们如何收集、使用、披露及保护您的信息。访问或使用本平台即表示您同意本政策的条款。</p>

                <hr class="my-4">

                <h4 id="collect" class="fw-600 text-navy mt-4 mb-3">1. 我们收集的信息</h4>
                <p>我们可能收集以下类型的个人数据：</p>
                <ul>
                    <li><strong>账号信息：</strong>姓名、电子邮件、手机号码、密码（经加密存储）、用户角色</li>
                    <li><strong>个人资料信息：</strong>NRIC/护照号（用于卖家验证）、地址、公司注册号</li>
                    <li><strong>房源信息：</strong>墓地位置、地段号、所有权文件、产权证书</li>
                    <li><strong>通信内容：</strong>通过平台发送的询价、消息及报价请求</li>
                    <li><strong>技术数据：</strong>IP地址、浏览器类型、设备标识符、访问日志</li>
                    <li><strong>支付信息：</strong>账单地址（不存储完整卡号，支付经第三方处理）</li>
                    <li><strong>规划数据：</strong>丧葬规划偏好及黄金储蓄参考数据（自愿提供）</li>
                </ul>

                <h4 id="use" class="fw-600 text-navy mt-4 mb-3">2. 信息使用方式</h4>
                <p>我们使用您的个人数据用于：</p>
                <ul>
                    <li>创建和管理您的账号</li>
                    <li>处理房源提交、询价及报价</li>
                    <li>执行卖家身份及所有权验证</li>
                    <li>促进买家与卖家/服务商之间的沟通</li>
                    <li>发送交易通知（非营销邮件，除非您选择接收）</li>
                    <li>改善平台功能及用户体验</li>
                    <li>遵守法律义务及解决纠纷</li>
                    <li>防止欺诈及滥用行为</li>
                </ul>

                <h4 id="share" class="fw-600 text-navy mt-4 mb-3">3. 信息共享</h4>
                <p>我们<strong>不会</strong>出售您的个人数据。我们仅在以下情况下共享数据：</p>
                <ul>
                    <li><strong>经您同意：</strong>当买家发起询价时，其联系信息将与相关卖家共享</li>
                    <li><strong>服务提供商：</strong>为运营平台提供服务的可信第三方（如托管商、邮件服务商）</li>
                    <li><strong>法律要求：</strong>依据法院命令、法规或监管要求</li>
                    <li><strong>业务转让：</strong>合并、收购或资产出售时（届时将提前通知）</li>
                    <li><strong>汇总统计数据：</strong>不含个人身份信息的匿名分析数据</li>
                </ul>

                <h4 id="cookies" class="fw-600 text-navy mt-4 mb-3">4. Cookie 政策</h4>
                <p>我们使用以下类型的 Cookie：</p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light"><tr><th>类型</th><th>用途</th><th>有效期</th></tr></thead>
                        <tbody>
                            <tr><td>必要 Cookie</td><td>会话管理、登录状态、CSRF 安全</td><td>会话期间</td></tr>
                            <tr><td>功能 Cookie</td><td>语言偏好（pg_lang）、记住登录</td><td>1年</td></tr>
                            <tr><td>分析 Cookie</td><td>网站使用统计（如适用）</td><td>最多2年</td></tr>
                        </tbody>
                    </table>
                </div>
                <p>您可通过浏览器设置管理 Cookie，但禁用必要 Cookie 可能影响平台功能。</p>

                <h4 id="security" class="fw-600 text-navy mt-4 mb-3">5. 数据安全</h4>
                <p>我们采取以下措施保护您的数据：</p>
                <ul>
                    <li>全站 HTTPS/TLS 加密传输</li>
                    <li>密码使用 bcrypt（强度系数12）加密存储</li>
                    <li>登录失败5次后账号锁定</li>
                    <li>文件上传经 MIME 类型验证</li>
                    <li>敏感文件目录不对外公开访问</li>
                    <li>所有表单设有 CSRF 防护</li>
                    <li>定期安全审查</li>
                </ul>
                <p class="text-muted small">尽管我们尽力保护您的数据，但互联网传输无法保证百分之百安全。如发现安全问题，请立即联系我们。</p>

                <h4 id="retention" class="fw-600 text-navy mt-4 mb-3">6. 数据保留</h4>
                <ul>
                    <li><strong>账号数据：</strong>账号存续期间保留，注销后保留7年（税务/法律合规）</li>
                    <li><strong>房源文件：</strong>房源下线后保留5年</li>
                    <li><strong>通信记录：</strong>保留3年</li>
                    <li><strong>访问日志：</strong>保留12个月</li>
                </ul>

                <h4 id="rights" class="fw-600 text-navy mt-4 mb-3">7. 您的权利（PDPA）</h4>
                <p>依据《2010年个人数据保护法》，您享有以下权利：</p>
                <ul>
                    <li><strong>查阅权：</strong>要求查阅我们持有的您的个人数据</li>
                    <li><strong>更正权：</strong>要求更正不准确的数据</li>
                    <li><strong>撤回同意：</strong>撤回对特定用途的数据处理同意</li>
                    <li><strong>投诉权：</strong>向个人数据保护专员提出投诉</li>
                </ul>
                <p>如需行使上述权利，请通过以下联系方式与我们联系。我们将在收到请求后21个工作日内回复。</p>

                <h4 id="children" class="fw-600 text-navy mt-4 mb-3">8. 儿童隐私</h4>
                <p>本平台不面向18岁以下人士。我们不会主动收集未成年人的个人数据。如发现未成年人账号，请立即联系我们予以删除。</p>

                <h4 id="third" class="fw-600 text-navy mt-4 mb-3">9. 第三方链接</h4>
                <p>本平台可能包含第三方网站链接（如园区官网、WhatsApp）。本政策不适用于这些第三方网站，建议您查阅其各自的隐私政策。</p>

                <h4 id="changes" class="fw-600 text-navy mt-4 mb-3">10. 政策变更</h4>
                <p>我们可能不时更新本政策。重大变更将通过电子邮件或平台显著位置通知您。继续使用本平台即表示您接受最新版政策。</p>

                <h4 id="contact" class="fw-600 text-navy mt-4 mb-3">11. 联系我们</h4>
                <div class="pg-card p-4" style="background:var(--pg-gold-pale);border:none">
                    <p class="mb-2"><strong>PlotGold Malaysia — 数据保护专员</strong></p>
                    <p class="mb-1 small"><i class="fas fa-envelope me-2 text-gold"></i>privacy@plotgold.my</p>
                    <p class="mb-1 small"><i class="fab fa-whatsapp me-2 text-success"></i>WhatsApp: <?= h(get_setting('site_phone', '+60 11-XXXX XXXX')) ?></p>
                    <p class="mb-0 small text-muted">我们将在21个工作日内回复数据保护相关请求。</p>
                </div>

                <?php else: ?>
                <!-- ── English Version ────────────────────────────── -->

                <p class="text-muted">PlotGold Malaysia ("PlotGold", "we", "us", "the Platform") is committed to protecting your personal data in accordance with Malaysia's Personal Data Protection Act 2010 (PDPA). This policy explains how we collect, use, disclose, and protect your information. By accessing or using the Platform you agree to the terms of this policy.</p>

                <hr class="my-4">

                <h4 id="collect" class="fw-600 text-navy mt-4 mb-3">1. Information We Collect</h4>
                <p>We may collect the following categories of personal data:</p>
                <ul>
                    <li><strong>Account information:</strong> Full name, email address, mobile number, password (stored encrypted), user role</li>
                    <li><strong>Identity information:</strong> NRIC/passport number (for seller verification), address, business registration number</li>
                    <li><strong>Listing information:</strong> Plot location, lot number, ownership documents, title deeds uploaded for verification</li>
                    <li><strong>Communications:</strong> Enquiries, messages, and quote requests sent through the Platform</li>
                    <li><strong>Technical data:</strong> IP address, browser type, device identifiers, access logs</li>
                    <li><strong>Payment information:</strong> Billing address (we do not store full card numbers; payments are processed by third-party gateways)</li>
                    <li><strong>Planning data:</strong> Funeral planning preferences and gold savings reference data you voluntarily provide</li>
                </ul>

                <h4 id="use" class="fw-600 text-navy mt-4 mb-3">2. How We Use Your Information</h4>
                <p>We use your personal data to:</p>
                <ul>
                    <li>Create and manage your account</li>
                    <li>Process listing submissions, enquiries, and quote requests</li>
                    <li>Carry out seller identity and ownership verification</li>
                    <li>Facilitate communication between buyers, sellers, and service providers</li>
                    <li>Send transactional notifications (not marketing, unless you opt in)</li>
                    <li>Improve Platform features and user experience</li>
                    <li>Comply with legal obligations and resolve disputes</li>
                    <li>Detect and prevent fraud or abusive behaviour</li>
                </ul>

                <h4 id="share" class="fw-600 text-navy mt-4 mb-3">3. Information Sharing</h4>
                <p>We do <strong>not</strong> sell your personal data. We share data only in these circumstances:</p>
                <ul>
                    <li><strong>With your consent:</strong> When a buyer submits an enquiry, their contact details are shared with the relevant seller</li>
                    <li><strong>Service providers:</strong> Trusted third parties that help us operate the Platform (hosting, email delivery) under confidentiality obligations</li>
                    <li><strong>Legal requirements:</strong> When required by court order, statute, or regulatory authority</li>
                    <li><strong>Business transfers:</strong> In the event of a merger, acquisition, or asset sale (advance notice will be given)</li>
                    <li><strong>Aggregated analytics:</strong> Anonymous, de-identified statistical data that cannot be used to identify individuals</li>
                </ul>

                <h4 id="cookies" class="fw-600 text-navy mt-4 mb-3">4. Cookies Policy</h4>
                <p>We use the following types of cookies:</p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light"><tr><th>Type</th><th>Purpose</th><th>Duration</th></tr></thead>
                        <tbody>
                            <tr><td>Strictly Necessary</td><td>Session management, login state, CSRF security tokens</td><td>Session</td></tr>
                            <tr><td>Functional</td><td>Language preference (pg_lang), remember-me login</td><td>1 year</td></tr>
                            <tr><td>Analytics</td><td>Site usage statistics (where applicable)</td><td>Up to 2 years</td></tr>
                        </tbody>
                    </table>
                </div>
                <p>You can manage cookies through your browser settings. Disabling strictly necessary cookies may affect Platform functionality.</p>

                <h4 id="security" class="fw-600 text-navy mt-4 mb-3">5. Data Security</h4>
                <p>We implement the following measures to protect your data:</p>
                <ul>
                    <li>HTTPS/TLS encryption for all data in transit</li>
                    <li>Passwords hashed with bcrypt (cost factor 12)</li>
                    <li>Account lockout after 5 failed login attempts</li>
                    <li>MIME-type validation on all file uploads</li>
                    <li>Sensitive document directories not publicly accessible</li>
                    <li>CSRF protection on all forms</li>
                    <li>Regular security reviews</li>
                </ul>
                <p class="text-muted small">Despite our best efforts, no internet transmission is 100% secure. If you discover a security issue, please contact us immediately.</p>

                <h4 id="retention" class="fw-600 text-navy mt-4 mb-3">6. Data Retention</h4>
                <ul>
                    <li><strong>Account data:</strong> Retained for the lifetime of your account, then 7 years for tax and legal compliance after closure</li>
                    <li><strong>Listing documents:</strong> 5 years after a listing is removed</li>
                    <li><strong>Communication records:</strong> 3 years</li>
                    <li><strong>Access logs:</strong> 12 months</li>
                </ul>

                <h4 id="rights" class="fw-600 text-navy mt-4 mb-3">7. Your Rights Under the PDPA</h4>
                <p>Under Malaysia's Personal Data Protection Act 2010, you have the right to:</p>
                <ul>
                    <li><strong>Access:</strong> Request a copy of the personal data we hold about you</li>
                    <li><strong>Correction:</strong> Request correction of inaccurate or incomplete data</li>
                    <li><strong>Withdraw consent:</strong> Withdraw consent to processing for specific purposes</li>
                    <li><strong>Complaint:</strong> Lodge a complaint with Malaysia's Personal Data Protection Commissioner</li>
                </ul>
                <p>To exercise any of these rights, contact us using the details below. We will respond within 21 working days.</p>

                <h4 id="children" class="fw-600 text-navy mt-4 mb-3">8. Children's Privacy</h4>
                <p>The Platform is not directed to persons under the age of 18. We do not knowingly collect personal data from minors. If you believe a minor has registered, please contact us immediately so we can remove the account.</p>

                <h4 id="third" class="fw-600 text-navy mt-4 mb-3">9. Third-Party Links</h4>
                <p>The Platform may contain links to third-party websites (such as memorial park websites and WhatsApp). This policy does not apply to those third-party sites. We encourage you to review their respective privacy policies.</p>

                <h4 id="changes" class="fw-600 text-navy mt-4 mb-3">10. Changes to This Policy</h4>
                <p>We may update this policy from time to time. Material changes will be communicated by email or a prominent notice on the Platform. Continued use after the effective date constitutes acceptance of the updated policy.</p>

                <h4 id="contact" class="fw-600 text-navy mt-4 mb-3">11. Contact Us</h4>
                <div class="pg-card p-4" style="background:var(--pg-gold-pale);border:none">
                    <p class="mb-2"><strong>PlotGold Malaysia — Data Protection Officer</strong></p>
                    <p class="mb-1 small"><i class="fas fa-envelope me-2 text-gold"></i>privacy@plotgold.my</p>
                    <p class="mb-1 small"><i class="fab fa-whatsapp me-2 text-success"></i>WhatsApp: <?= h(get_setting('site_phone', '+60 11-XXXX XXXX')) ?></p>
                    <p class="mb-0 small text-muted">We will respond to data protection requests within 21 working days.</p>
                </div>

                <?php endif; ?>

                <hr class="mt-5 mb-4">
                <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center">
                    <p class="text-muted small mb-0">
                        <?= is_lang('zh') ? '本政策受马来西亚法律管辖。' : 'This policy is governed by the laws of Malaysia.' ?>
                    </p>
                    <div class="d-flex gap-3">
                        <a href="<?= pg_url('terms.php') ?>" class="small text-gold"><?= _e('footer.terms') ?> →</a>
                        <a href="<?= pg_url('contact.php') ?>" class="small text-muted"><?= _e('footer.contact') ?></a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include INC_PATH . '/footer.php'; ?>
