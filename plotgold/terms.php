<?php
require_once __DIR__ . '/inc/bootstrap.php';

$page_title       = is_lang('zh') ? '服务条款' : 'Terms of Service';
$meta_description = 'PlotGold Malaysia Terms of Service — the rules governing use of our burial plot marketplace and funeral planning platform.';
$lastUpdated      = '20 April 2026';

include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<nav class="bg-white border-bottom">
    <div class="container py-2">
        <ol class="breadcrumb pg-breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= pg_url() ?>">Home</a></li>
            <li class="breadcrumb-item active"><?= _e('footer.terms') ?></li>
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
                    <i class="fas fa-file-contract"></i>
                    <?= is_lang('zh') ? '有效协议' : 'Binding Agreement' ?>
                </div>
                <h1 class="h2 fw-700 text-navy mb-2">
                    <?= is_lang('zh') ? '服务条款' : 'Terms of Service' ?>
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
                        '#acceptance'   => '1. 接受条款',
                        '#platform'     => '2. 平台性质',
                        '#eligibility'  => '3. 用户资格',
                        '#accounts'     => '4. 账号责任',
                        '#sellers'      => '5. 卖家规则',
                        '#buyers'       => '6. 买家规则',
                        '#providers'    => '7. 服务商规则',
                        '#prohibited'   => '8. 禁止行为',
                        '#ip'           => '9. 知识产权',
                        '#disclaimer'   => '10. 免责声明',
                        '#liability'    => '11. 责任限制',
                        '#indemnity'    => '12. 赔偿',
                        '#termination'  => '13. 终止服务',
                        '#governing'    => '14. 适用法律',
                        '#changes'      => '15. 条款变更',
                        '#contact'      => '16. 联系我们',
                        '#goldvault'    => '17. 黄金储存服务',
                    ] : [
                        '#acceptance'   => '1. Acceptance of Terms',
                        '#platform'     => '2. Nature of the Platform',
                        '#eligibility'  => '3. Eligibility',
                        '#accounts'     => '4. Account Responsibilities',
                        '#sellers'      => '5. Seller Rules',
                        '#buyers'       => '6. Buyer Rules',
                        '#providers'    => '7. Provider Rules',
                        '#prohibited'   => '8. Prohibited Conduct',
                        '#ip'           => '9. Intellectual Property',
                        '#disclaimer'   => '10. Disclaimer of Warranties',
                        '#liability'    => '11. Limitation of Liability',
                        '#indemnity'    => '12. Indemnification',
                        '#termination'  => '13. Termination',
                        '#governing'    => '14. Governing Law',
                        '#changes'      => '15. Changes to Terms',
                        '#contact'      => '16. Contact Us',
                        '#goldvault'    => '17. Gold Vault Storage Service',
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

                <div class="alert alert-warning d-flex gap-3 mb-4">
                    <i class="fas fa-exclamation-triangle fa-lg mt-1 flex-shrink-0"></i>
                    <div class="small">请在使用本平台前仔细阅读本服务条款。访问或使用PlotGold Malaysia即表示您同意受本条款约束。</div>
                </div>

                <h4 id="acceptance" class="fw-600 text-navy mt-4 mb-3">1. 接受条款</h4>
                <p>本服务条款（"条款"）构成您与PlotGold Malaysia之间的具有法律约束力的协议，适用于您对本平台及其所有功能和服务的使用。如您不同意本条款，请停止使用本平台。</p>

                <h4 id="platform" class="fw-600 text-navy mt-4 mb-3">2. 平台性质</h4>
                <p>PlotGold Malaysia是一个<strong>在线信息平台</strong>，为墓地买卖双方及殡葬服务商提供信息展示和连接服务。我们：</p>
                <ul>
                    <li>不参与买卖双方之间的任何交易</li>
                    <li>不持有、管理或控制任何墓地</li>
                    <li>不提供法律、财务或殡葬服务</li>
                    <li>不对房源信息的准确性、完整性或合法性作出保证</li>
                    <li>不保证任何房源均可购买或转让</li>
                </ul>
                <p>所有交易均由买卖双方直接进行，与PlotGold Malaysia无关。</p>

                <h4 id="eligibility" class="fw-600 text-navy mt-4 mb-3">3. 用户资格</h4>
                <p>使用本平台须满足以下条件：</p>
                <ul>
                    <li>年满18周岁</li>
                    <li>有能力订立具有法律约束力的合同</li>
                    <li>提供真实、准确、完整的注册信息</li>
                    <li>不在任何适用法律下被禁止使用本平台</li>
                </ul>

                <h4 id="accounts" class="fw-600 text-navy mt-4 mb-3">4. 账号责任</h4>
                <p>您对自己的账号安全负全部责任，包括：</p>
                <ul>
                    <li>保管好密码，不得与他人共享</li>
                    <li>通过您账号进行的所有活动均由您负责</li>
                    <li>发现未授权使用时立即通知我们</li>
                    <li>确保账号信息始终准确和最新</li>
                </ul>
                <p>对于因账号凭据遗失或被盗导致的损失，PlotGold Malaysia不承担责任。</p>

                <h4 id="sellers" class="fw-600 text-navy mt-4 mb-3">5. 卖家规则</h4>
                <p>刊登墓地的用户须：</p>
                <ul>
                    <li>对所刊登的墓地拥有合法所有权或被授权代为出售</li>
                    <li>提供真实、准确的房源信息，包括价格、位置及所有权状态</li>
                    <li>配合完成平台规定的验证流程</li>
                    <li>仅上传真实合法的所有权文件</li>
                    <li>在价格、状态发生变化时及时更新房源信息</li>
                    <li>不得刊登不拥有合法权利的墓地</li>
                    <li>不得提供虚假、误导性或夸大的信息</li>
                    <li>不得刊登已转让或已出售的墓地</li>
                </ul>
                <p>违反上述规则将导致房源被下架及账号被终止。</p>

                <h4 id="buyers" class="fw-600 text-navy mt-4 mb-3">6. 买家规则</h4>
                <p>买家须知：</p>
                <ul>
                    <li>发起询价须是真实的购买意向</li>
                    <li>交易前须自行核实所有权状态及转让条件</li>
                    <li>须聘请专业人士（律师、测量师）处理过户事宜</li>
                    <li>平台显示的价格仅供参考，须与卖家直接协商</li>
                    <li>不得利用本平台从事欺诈、骚扰或非法活动</li>
                </ul>

                <h4 id="providers" class="fw-600 text-navy mt-4 mb-3">7. 服务商规则</h4>
                <p>已注册的殡葬服务商须：</p>
                <ul>
                    <li>持有在马来西亚合法经营的有效营业执照</li>
                    <li>提供准确的服务信息及价格</li>
                    <li>在商定的时间内响应报价请求</li>
                    <li>遵守所有适用的专业标准及法规</li>
                    <li>不得在平台外以绕过平台的方式转移业务</li>
                </ul>

                <h4 id="prohibited" class="fw-600 text-navy mt-4 mb-3">8. 禁止行为</h4>
                <p>使用本平台，您不得：</p>
                <ul>
                    <li>上传或传播虚假、欺诈性或误导性内容</li>
                    <li>冒充他人或伪造身份</li>
                    <li>对任何用户实施骚扰、威胁或歧视</li>
                    <li>发布垃圾信息、广告或不相关的商业内容</li>
                    <li>未经授权访问他人账号或平台系统</li>
                    <li>干扰或损害平台正常运营</li>
                    <li>抓取、爬取或自动提取平台数据</li>
                    <li>进行任何违反马来西亚法律的活动</li>
                </ul>

                <h4 id="ip" class="fw-600 text-navy mt-4 mb-3">9. 知识产权</h4>
                <p>本平台上的所有内容（包括但不限于商标"PlotGold"、设计、文字、软件代码）均为PlotGold Malaysia所有或已获许可使用，受马来西亚及国际知识产权法保护。</p>
                <p>用户上传的内容（如图片、文件）归用户所有，但用户授予PlotGold Malaysia非独家、免版税的许可，用于在平台上展示和运营。</p>

                <h4 id="disclaimer" class="fw-600 text-navy mt-4 mb-3">10. 免责声明</h4>
                <div class="alert alert-light border">
                    <p class="mb-2">本平台按"现状"提供服务，不作任何明示或默示的保证，包括但不限于：</p>
                    <ul class="mb-0">
                        <li>房源信息的准确性、完整性或实时性</li>
                        <li>任何墓地的可用性、可转让性或合法性</li>
                        <li>平台不间断、无错误运行</li>
                        <li>通过平台完成的任何交易的结果</li>
                        <li>本平台丧葬规划工具所提供数据的准确性（仅供估算参考）</li>
                    </ul>
                </div>

                <h4 id="liability" class="fw-600 text-navy mt-4 mb-3">11. 责任限制</h4>
                <p>在法律允许的最大范围内，PlotGold Malaysia就以下事项不承担任何责任：</p>
                <ul>
                    <li>因依赖平台信息导致的任何间接或直接损失</li>
                    <li>买卖双方之间的纠纷</li>
                    <li>墓地过户失败或延迟</li>
                    <li>第三方服务提供商的行为或不作为</li>
                    <li>不可抗力事件导致的服务中断</li>
                </ul>
                <p>如PlotGold Malaysia被裁定须承担赔偿责任，最高赔偿额不超过您在争议发生前12个月内向PlotGold Malaysia支付的费用。</p>

                <h4 id="indemnity" class="fw-600 text-navy mt-4 mb-3">12. 赔偿</h4>
                <p>您同意就以下事项产生的任何索赔、损失、损害及费用（包括合理律师费），对PlotGold Malaysia及其董事、员工和代理人进行赔偿：您违反本条款、您通过平台上传或发布的内容、您侵犯第三方权利，或您违反任何适用法律的行为。</p>

                <h4 id="termination" class="fw-600 text-navy mt-4 mb-3">13. 终止服务</h4>
                <p>我们保留以下权利：</p>
                <ul>
                    <li>因违反本条款，随时暂停或终止任何账号</li>
                    <li>在不另行通知的情况下删除违规内容</li>
                    <li>全权决定拒绝任何新用户注册</li>
                </ul>
                <p>您可随时通过联系我们注销账号。账号终止后，与第三方的未决事宜不受影响。</p>

                <h4 id="governing" class="fw-600 text-navy mt-4 mb-3">14. 适用法律</h4>
                <p>本条款受马来西亚法律管辖并依据其解释。因本条款产生的任何争议，双方同意提交吉隆坡法院的专属管辖。</p>

                <h4 id="changes" class="fw-600 text-navy mt-4 mb-3">15. 条款变更</h4>
                <p>我们可能随时修改本条款。重大变更将提前通知。修改后继续使用本平台即表示接受新条款。建议您定期查阅本页面。</p>

                <h4 id="contact" class="fw-600 text-navy mt-4 mb-3">16. 联系我们</h4>
                <div class="pg-card p-4" style="background:var(--pg-gold-pale);border:none">
                    <p class="mb-2"><strong>PlotGold Malaysia</strong></p>
                    <p class="mb-1 small"><i class="fas fa-envelope me-2 text-gold"></i>legal@plotgold.my</p>
                    <p class="mb-1 small"><i class="fab fa-whatsapp me-2 text-success"></i><?= h(get_setting('site_phone', '+60 11-XXXX XXXX')) ?></p>
                    <p class="mb-0 small text-muted">如对本条款有任何疑问，请在使用本平台前联系我们。</p>
                </div>

                <h4 id="goldvault" class="fw-600 text-navy mt-5 mb-3">
                    17. 黄金储存服务
                    <span class="badge bg-warning text-dark ms-2" style="font-size:.65rem;vertical-align:middle;">新增</span>
                </h4>
                <div class="alert alert-info d-flex gap-3 mb-4">
                    <i class="fas fa-shield-alt fa-lg mt-1 flex-shrink-0"></i>
                    <div class="small">本条款第17条专门适用于我们的实物黄金储存服务。购买并储存黄金的用户须遵守以下附加条款。</div>
                </div>

                <p><strong>17.1 所有权</strong></p>
                <p>PlotGold Malaysia作为客户购买实物黄金的保管方。客户在任何时候保留对所储存黄金的完全所有权。PlotGold Malaysia对所储存黄金不持有任何受益权益。所有权通过购买时签发的数字证书证明。</p>

                <p><strong>17.2 储存服务</strong></p>
                <p>金库储存服务作为向购买实物黄金客户的免费补充服务提供。不收取任何储存费用。公司保留在提前30天书面通知后终止该服务的权利。终止服务后，将以物理方式返还黄金或协助客户转移至第三方保管方。</p>

                <p><strong>17.3 黄金价格风险</strong></p>
                <p>黄金的市场价值会波动。PlotGold Malaysia不对黄金未来价值作任何声明或保证。客户在购买时接受全部市场风险。本服务不提供任何固定回报、资本保值保证或收益承诺。</p>

                <p><strong>17.4 实物提取</strong></p>
                <p>客户可随时申请提取其黄金的实物形式，最低提取量为1克。提取申请将在<strong>7个工作日</strong>内处理。提取时可能适用交付费用，具体金额将在提取时告知。</p>

                <p><strong>17.5 保险与保全</strong></p>
                <p>储存黄金的金库经过审计，提供防火及安全保障，并投保最高<strong>1000万令吉</strong>的保险。如发生损失，赔偿上限以保险赔付为准。客户可要求查阅当前的保险证明。</p>

                <p><strong>17.6 伊斯兰教法合规性</strong></p>
                <p>本黄金储存服务基于<em>Qabdh</em>（推定占有）原则，与马来西亚国家银行黄金伊斯兰教法准则一致。每位客户的持有量可追溯至特定序列号或批次，黄金不进行混合或共用。</p>

                <p><strong>17.7 监管声明</strong></p>
                <div class="alert alert-light border mb-3">
                    <p class="mb-2 fw-600 small">重要监管声明</p>
                    <p class="mb-0 small">本黄金储存服务依据马来西亚公司委员会（SSM）授权的零售销售模式运营。本服务<strong>不构成</strong>《2007年资本市场和服务法》下的受管制投资产品，<strong>不受</strong>马来西亚证券委员会（SC）或马来西亚国家银行（BNM）监管。客户应注意黄金价值会随市场情况变化而波动。</p>
                </div>

                <p><strong>17.8 个人数据保护</strong></p>
                <p>黄金账户的个人数据依据《2010年个人数据保护法》（PDPA）处理，仅用于所有权记录及提取处理目的。数据保留期限最少7年，以符合SSM要求。</p>

                <?php else: ?>
                <!-- ── English Version ────────────────────────────── -->

                <div class="alert alert-warning d-flex gap-3 mb-4">
                    <i class="fas fa-exclamation-triangle fa-lg mt-1 flex-shrink-0"></i>
                    <div class="small">Please read these Terms of Service carefully before using PlotGold Malaysia. By accessing or using the Platform you agree to be bound by these Terms.</div>
                </div>

                <h4 id="acceptance" class="fw-600 text-navy mt-4 mb-3">1. Acceptance of Terms</h4>
                <p>These Terms of Service ("Terms") form a legally binding agreement between you and PlotGold Malaysia governing your use of the Platform and all its features and services. If you do not agree to these Terms, please discontinue use of the Platform.</p>

                <h4 id="platform" class="fw-600 text-navy mt-4 mb-3">2. Nature of the Platform</h4>
                <p>PlotGold Malaysia is an <strong>online information platform</strong> that connects burial plot buyers, sellers, and funeral service providers. We:</p>
                <ul>
                    <li>Do not participate in transactions between users</li>
                    <li>Do not own, manage, or control any burial plot or memorial park</li>
                    <li>Do not provide legal, financial, or funeral services directly</li>
                    <li>Make no representations as to the accuracy, completeness, or legality of any listing</li>
                    <li>Do not guarantee that any listing is available for purchase or transfer</li>
                </ul>
                <p>All transactions are conducted directly between buyers and sellers, independent of PlotGold Malaysia.</p>

                <h4 id="eligibility" class="fw-600 text-navy mt-4 mb-3">3. Eligibility</h4>
                <p>To use the Platform you must:</p>
                <ul>
                    <li>Be at least 18 years of age</li>
                    <li>Have the legal capacity to enter into binding contracts</li>
                    <li>Provide true, accurate, and complete registration information</li>
                    <li>Not be prohibited from using the Platform under any applicable law</li>
                </ul>

                <h4 id="accounts" class="fw-600 text-navy mt-4 mb-3">4. Account Responsibilities</h4>
                <p>You are solely responsible for:</p>
                <ul>
                    <li>Maintaining the confidentiality of your password and not sharing it</li>
                    <li>All activities that occur under your account</li>
                    <li>Notifying us immediately of any unauthorized use of your account</li>
                    <li>Keeping your account information accurate and up to date</li>
                </ul>
                <p>PlotGold Malaysia is not liable for any loss arising from lost or stolen account credentials.</p>

                <h4 id="sellers" class="fw-600 text-navy mt-4 mb-3">5. Seller Rules</h4>
                <p>Users listing burial plots must:</p>
                <ul>
                    <li>Hold legal ownership of, or be duly authorised to sell, the listed plot</li>
                    <li>Provide truthful and accurate information including price, location, and ownership status</li>
                    <li>Cooperate with the Platform's verification process and provide genuine supporting documents</li>
                    <li>Update listings promptly when price, availability, or status changes</li>
                    <li>Not list plots to which they have no legal title or authority</li>
                    <li>Not provide false, misleading, or exaggerated information</li>
                    <li>Not list plots that have already been transferred or sold</li>
                </ul>
                <p>Violation will result in listing removal and account termination.</p>

                <h4 id="buyers" class="fw-600 text-navy mt-4 mb-3">6. Buyer Rules</h4>
                <p>Buyers acknowledge and agree that:</p>
                <ul>
                    <li>Enquiries must represent genuine purchase intent</li>
                    <li>They must independently verify ownership status and transfer conditions before transacting</li>
                    <li>They should engage qualified professionals (solicitors, surveyors) for all transfers</li>
                    <li>Prices shown are indicative only and subject to direct negotiation with sellers</li>
                    <li>They must not use the Platform for fraudulent, harassing, or unlawful purposes</li>
                </ul>

                <h4 id="providers" class="fw-600 text-navy mt-4 mb-3">7. Provider Rules</h4>
                <p>Registered funeral service providers must:</p>
                <ul>
                    <li>Hold valid business licences to operate in Malaysia</li>
                    <li>Provide accurate service descriptions and pricing</li>
                    <li>Respond to quote requests within the committed timeframe</li>
                    <li>Comply with all applicable professional standards and regulations</li>
                    <li>Not solicit clients to circumvent the Platform for future business</li>
                </ul>

                <h4 id="prohibited" class="fw-600 text-navy mt-4 mb-3">8. Prohibited Conduct</h4>
                <p>You must not:</p>
                <ul>
                    <li>Upload or transmit false, fraudulent, or misleading content</li>
                    <li>Impersonate any person or falsify your identity</li>
                    <li>Harass, threaten, or discriminate against any user</li>
                    <li>Spam, advertise, or post irrelevant commercial content</li>
                    <li>Access others' accounts or Platform systems without authorisation</li>
                    <li>Disrupt or damage the Platform's normal operation</li>
                    <li>Scrape, crawl, or systematically extract Platform data</li>
                    <li>Engage in any activity that violates Malaysian law</li>
                </ul>

                <h4 id="ip" class="fw-600 text-navy mt-4 mb-3">9. Intellectual Property</h4>
                <p>All Platform content (including the "PlotGold" trademark, design, text, and software) is owned by or licensed to PlotGold Malaysia and is protected by Malaysian and international intellectual property law.</p>
                <p>Content you upload (photos, documents) remains your property, but you grant PlotGold Malaysia a non-exclusive, royalty-free licence to display and use such content to operate the Platform.</p>

                <h4 id="disclaimer" class="fw-600 text-navy mt-4 mb-3">10. Disclaimer of Warranties</h4>
                <div class="alert alert-light border">
                    <p class="mb-2">The Platform is provided "as is" without warranties of any kind, express or implied, including but not limited to:</p>
                    <ul class="mb-0">
                        <li>The accuracy, completeness, or currency of any listing</li>
                        <li>The availability, transferability, or legality of any burial plot</li>
                        <li>Uninterrupted or error-free operation of the Platform</li>
                        <li>The outcome of any transaction facilitated through the Platform</li>
                        <li>The accuracy of data provided by the funeral planning tool (indicative estimates only)</li>
                    </ul>
                </div>

                <h4 id="liability" class="fw-600 text-navy mt-4 mb-3">11. Limitation of Liability</h4>
                <p>To the fullest extent permitted by law, PlotGold Malaysia shall not be liable for:</p>
                <ul>
                    <li>Any indirect or direct loss arising from reliance on Platform information</li>
                    <li>Disputes between buyers and sellers</li>
                    <li>Failed or delayed plot transfers</li>
                    <li>Acts or omissions of third-party service providers</li>
                    <li>Service interruptions due to force majeure events</li>
                </ul>
                <p>If PlotGold Malaysia is found liable, maximum liability shall not exceed fees paid by you to PlotGold Malaysia in the 12 months preceding the dispute.</p>

                <h4 id="indemnity" class="fw-600 text-navy mt-4 mb-3">12. Indemnification</h4>
                <p>You agree to indemnify and hold harmless PlotGold Malaysia and its directors, employees, and agents against any claims, losses, damages, and expenses (including reasonable legal fees) arising from: your breach of these Terms; content you upload or post on the Platform; your infringement of any third-party rights; or your violation of any applicable law.</p>

                <h4 id="termination" class="fw-600 text-navy mt-4 mb-3">13. Termination</h4>
                <p>We reserve the right to:</p>
                <ul>
                    <li>Suspend or terminate any account at any time for breach of these Terms</li>
                    <li>Remove non-compliant content without prior notice</li>
                    <li>Refuse new registrations at our sole discretion</li>
                </ul>
                <p>You may close your account at any time by contacting us. Termination does not affect outstanding obligations to third parties.</p>

                <h4 id="governing" class="fw-600 text-navy mt-4 mb-3">14. Governing Law</h4>
                <p>These Terms are governed by and construed in accordance with the laws of Malaysia. You submit to the exclusive jurisdiction of the courts of Kuala Lumpur for any dispute arising from these Terms.</p>

                <h4 id="changes" class="fw-600 text-navy mt-4 mb-3">15. Changes to Terms</h4>
                <p>We may modify these Terms at any time. Material changes will be notified in advance. Continued use after changes take effect constitutes acceptance of the revised Terms. We recommend reviewing this page periodically.</p>

                <h4 id="contact" class="fw-600 text-navy mt-4 mb-3">16. Contact Us</h4>
                <div class="pg-card p-4" style="background:var(--pg-gold-pale);border:none">
                    <p class="mb-2"><strong>PlotGold Malaysia</strong></p>
                    <p class="mb-1 small"><i class="fas fa-envelope me-2 text-gold"></i>legal@plotgold.my</p>
                    <p class="mb-1 small"><i class="fab fa-whatsapp me-2 text-success"></i><?= h(get_setting('site_phone', '+60 11-XXXX XXXX')) ?></p>
                    <p class="mb-0 small text-muted">If you have any questions about these Terms, please contact us before using the Platform.</p>
                </div>

                <h4 id="goldvault" class="fw-600 text-navy mt-5 mb-3">
                    17. Gold Vault Storage Service
                    <span class="badge bg-warning text-dark ms-2" style="font-size:.65rem;vertical-align:middle;">New</span>
                </h4>
                <div class="alert alert-info d-flex gap-3 mb-4">
                    <i class="fas fa-shield-alt fa-lg mt-1 flex-shrink-0"></i>
                    <div class="small">This Section 17 applies specifically to our physical gold vault storage service. Users who purchase and store gold with us are subject to the following additional terms.</div>
                </div>

                <p><strong>17.1 Ownership</strong></p>
                <p>PlotGold Malaysia acts as custodian for physical gold purchased from us. The customer retains full legal ownership of their stored gold at all times. PlotGold Malaysia holds no beneficial interest in stored gold. Ownership is evidenced by the digital certificate issued to the customer upon purchase.</p>

                <p><strong>17.2 Storage Service</strong></p>
                <p>Vault storage is provided as a complimentary service to customers who purchase physical gold from PlotGold Malaysia. No storage fee is charged. We reserve the right to discontinue this service with 30 days' written notice, after which gold will be returned in physical form or transferred to a third-party custodian at the customer's direction.</p>

                <p><strong>17.3 Gold Price Risk</strong></p>
                <p>The market value of gold fluctuates. PlotGold Malaysia makes no representation or guarantee regarding the future value of gold. Customers accept full market risk at the point of purchase. No fixed returns, capital guarantees, or yield of any kind are offered or implied by this service.</p>

                <p><strong>17.4 Physical Redemption</strong></p>
                <p>Customers may request physical redemption of their gold at any time, subject to a minimum redemption quantity of <strong>1 gram</strong>. Redemption will be processed within <strong>7 business days</strong>. A delivery fee may apply at the time of redemption and will be disclosed before processing.</p>

                <p><strong>17.5 Insurance &amp; Security</strong></p>
                <p>Our gold vaults are audited, fireproof, and secured. Stored gold is insured up to <strong>RM 10,000,000</strong>. In the event of loss, compensation is limited to insurance proceeds. Customers may request a copy of current insurance certificates upon written request.</p>

                <p><strong>17.6 Shariah Compliance</strong></p>
                <p>This gold storage service operates on a <em>Qabdh</em> (constructive possession) basis consistent with Bank Negara Malaysia's Shariah Standards on Gold. Each customer's holding is traceable to a specific serial or lot number. Gold is not commingled or pooled between customers.</p>

                <p><strong>17.7 Regulatory Disclosure</strong></p>
                <div class="alert alert-light border mb-3">
                    <p class="mb-2 fw-600 small">Important Regulatory Statement</p>
                    <p class="mb-0 small">This gold vault storage service operates under a retail sale model authorised by the Companies Commission of Malaysia (SSM). This service does <strong>not</strong> constitute a regulated investment product under the Capital Markets and Services Act 2007 and is <strong>not</strong> regulated by the Securities Commission Malaysia (SC) or Bank Negara Malaysia (BNM). Customers should be aware that the value of gold can rise and fall in line with market conditions.</p>
                </div>

                <p><strong>17.8 Personal Data</strong></p>
                <p>Personal data collected for gold accounts is processed under the Personal Data Protection Act 2010 (PDPA) solely for ownership record-keeping and redemption processing. Data is retained for a minimum of 7 years in compliance with SSM requirements.</p>

                <?php endif; ?>

                <hr class="mt-5 mb-4">
                <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center">
                    <p class="text-muted small mb-0">
                        <?= is_lang('zh') ? '本条款受马来西亚法律管辖。' : 'These Terms are governed by the laws of Malaysia.' ?>
                    </p>
                    <div class="d-flex gap-3">
                        <a href="<?= pg_url('privacy.php') ?>" class="small text-gold"><?= _e('footer.privacy') ?> →</a>
                        <a href="<?= pg_url('contact.php') ?>" class="small text-muted"><?= _e('footer.contact') ?></a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include INC_PATH . '/footer.php'; ?>
