<?php
/* ============================================================
   AJAX endpoint — powers all customer AI modules
   POST /api/ai-generate.php
   Body: { module: string, ...fields }
   Returns: { ok: bool, result: string, error: string }
   ============================================================ */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/claude.php';

header('Content-Type: application/json');

/* Must be logged in */
if (!Auth::check()) {
    echo json_encode(['ok' => false, 'error' => 'Please log in to use AI tools.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request method.']);
    exit;
}

$module = trim($_POST['module'] ?? '');

/* ── Helpers ── */
function req(string $key, string $default = ''): string {
    return trim($_POST[$key] ?? $default);
}
function sanitize(string $s): string {
    return htmlspecialchars(strip_tags($s), ENT_QUOTES, 'UTF-8');
}

/* ── Route by module ── */
switch ($module) {

    /* ── Email Writer ── */
    case 'email':
        $type      = sanitize(req('email_type', 'professional'));
        $recipient = sanitize(req('recipient'));
        $context   = sanitize(req('context'));
        $tone      = sanitize(req('tone', 'professional'));
        $length    = sanitize(req('length', 'medium'));

        if (!$context) { echo json_encode(['ok'=>false,'error'=>'Please describe what the email is about.']); exit; }

        $system = "You are an expert business email writer. Write clear, effective, professional emails. Output ONLY the email (subject line first, then body). Do not add commentary.";
        $user   = "Write a {$tone} {$type} email.
Recipient: {$recipient}
Key points / context: {$context}
Length: {$length} (short=3-5 sentences, medium=2-3 paragraphs, long=4+ paragraphs)

Format:
Subject: [subject line here]

[email body here]";

        $result = Claude::generate($system, $user, 800);
        echo json_encode($result);
        break;

    /* ── Social Post Generator ── */
    case 'social':
        $business  = sanitize(req('business'));
        $topic     = sanitize(req('topic'));
        $platforms = array_map('trim', explode(',', req('platforms', 'Facebook,Instagram,LinkedIn')));
        $tone      = sanitize(req('tone', 'engaging'));
        $cta       = sanitize(req('cta'));

        if (!$topic) { echo json_encode(['ok'=>false,'error'=>'Please describe your topic or product.']); exit; }

        $platformList = implode(', ', $platforms);
        $ctaLine = $cta ? "Call to action: {$cta}" : '';

        $system = "You are a social media expert creating posts for SMEs. Write engaging, platform-appropriate posts. Include relevant emojis. Respect character limits (Twitter: 280 chars, others: flexible).";
        $user   = "Business: {$business}
Topic/Product: {$topic}
Tone: {$tone}
{$ctaLine}

Write separate posts for: {$platformList}

Format each as:
📱 [PLATFORM NAME]
[post content]
---";

        $result = Claude::generate($system, $user, 1200);
        echo json_encode($result);
        break;

    /* ── Invoice Generator ── */
    case 'invoice':
        $from      = sanitize(req('from_company'));
        $to        = sanitize(req('to_company'));
        $invNumber = sanitize(req('invoice_number'));
        $invDate   = sanitize(req('invoice_date'));
        $dueDate   = sanitize(req('due_date'));
        $items     = req('items'); // JSON string of line items
        $notes     = sanitize(req('notes'));
        $currency  = sanitize(req('currency', 'USD'));

        if (!$from || !$to) { echo json_encode(['ok'=>false,'error'=>'Please fill in the From and To company details.']); exit; }

        $itemsDecoded = json_decode($items, true) ?: [];
        $itemsText = '';
        $subtotal  = 0;
        foreach ($itemsDecoded as $item) {
            $desc  = htmlspecialchars($item['desc'] ?? '');
            $qty   = (float)($item['qty'] ?? 1);
            $price = (float)($item['price'] ?? 0);
            $total = $qty * $price;
            $subtotal += $total;
            $itemsText .= "- {$desc} | Qty: {$qty} | Unit: {$price} | Total: {$total}\n";
        }

        $system = "You are a professional invoice writer. Create clean, business-appropriate invoice content. Output ONLY valid HTML for the invoice body (inside a <div>). Use inline styles for formatting. Do not use external CSS or JS.";
        $user   = "Create a professional invoice with these details:

FROM: {$from}
TO: {$to}
Invoice #: {$invNumber}
Date: {$invDate}
Due Date: {$dueDate}
Currency: {$currency}

Line Items:
{$itemsText}
Subtotal: {$subtotal} {$currency}

Notes: {$notes}

Generate a well-formatted HTML invoice. Use a white background, clean table for line items, include totals row with subtotal and total. Professional style.";

        $result = Claude::generate($system, $user, 2000);
        echo json_encode($result);
        break;

    /* ── Leave Request AI helper ── */
    case 'leave_reason':
        $leaveType = sanitize(req('leave_type', 'annual'));
        $days      = (int)req('days', 1);
        $brief     = sanitize(req('brief'));

        if (!$brief) { echo json_encode(['ok'=>false,'error'=>'Please provide a brief reason.']); exit; }

        $system = "You are an HR communication assistant. Write polite, professional leave request messages. Keep it concise and respectful.";
        $user   = "Write a professional leave request message for:
Leave type: {$leaveType}
Number of days: {$days}
Brief reason: {$brief}

Output ONLY the leave request message (2-3 sentences). No greeting needed.";

        $result = Claude::generate($system, $user, 300);
        echo json_encode($result);
        break;

    /* ── Meeting Minutes ── */
    case 'meeting_minutes':
        $title     = sanitize(req('meeting_title'));
        $date      = sanitize(req('meeting_date'));
        $attendees = sanitize(req('attendees'));
        $agenda    = sanitize(req('agenda'));
        $notes     = sanitize(req('raw_notes'));
        $language  = sanitize(req('language', 'English'));

        if (!$notes) { echo json_encode(['ok'=>false,'error'=>'Please provide meeting notes.']); exit; }

        $system = "You are a professional meeting secretary. Transform raw notes into clear, structured meeting minutes. Output in the specified language.";
        $user   = "Meeting: {$title}\nDate: {$date}\nAttendees: {$attendees}\nAgenda: {$agenda}\nRaw notes: {$notes}\nLanguage: {$language}\n\nWrite formal meeting minutes with these sections:\nMEETING MINUTES\n[Title & Date]\n\nATTENDEES\n[list]\n\nAGENDA\n[items]\n\nDISCUSSION & DECISIONS\n[key points and decisions]\n\nACTION ITEMS\n[Owner | Task | Deadline]\n\nNEXT MEETING\n[if mentioned]";
        $result = Claude::generate($system, $user, 1200);
        echo json_encode($result);
        break;

    /* ── Job Description ── */
    case 'job_description':
        $title      = sanitize(req('job_title'));
        $dept       = sanitize(req('department'));
        $empType    = sanitize(req('employment_type', 'Full-time'));
        $location   = sanitize(req('location'));
        $resp       = sanitize(req('responsibilities'));
        $reqs       = sanitize(req('requirements'));
        $niceToHave = sanitize(req('nice_to_have'));
        $salary     = sanitize(req('salary_range'));
        $company    = sanitize(req('company_info'));

        if (!$title || !$resp) { echo json_encode(['ok'=>false,'error'=>'Please fill in job title and responsibilities.']); exit; }

        $system = "You are an experienced HR specialist. Write compelling, clear job descriptions that attract the right candidates.";
        $user   = "Write a complete job description:\n\nJob Title: {$title}\nDepartment: {$dept}\nEmployment Type: {$empType}\nLocation: {$location}\nCompany: {$company}\nKey Responsibilities: {$resp}\nRequirements: {$reqs}\nNice to Have: {$niceToHave}\nSalary Range: {$salary}\n\nStructure:\nJOB TITLE\nABOUT THE ROLE\nKEY RESPONSIBILITIES\nREQUIREMENTS\nNICE TO HAVE\nWHAT WE OFFER\nHOW TO APPLY";
        $result = Claude::generate($system, $user, 1500);
        echo json_encode($result);
        break;

    /* ── Sales Proposal ── */
    case 'sales_proposal':
        $clientName    = sanitize(req('client_name'));
        $clientCompany = sanitize(req('client_company'));
        $painPoints    = sanitize(req('pain_points'));
        $solution      = sanitize(req('solution'));
        $pricing       = sanitize(req('pricing'));
        $timeline      = sanitize(req('timeline'));
        $ourCompany    = sanitize(req('our_company'));
        $tone          = sanitize(req('tone', 'consultative'));

        if (!$clientCompany || !$painPoints || !$solution) { echo json_encode(['ok'=>false,'error'=>'Please fill in client company, challenges, and your solution.']); exit; }

        $system = "You are a senior sales consultant. Write persuasive, professional sales proposals that win deals.";
        $user   = "Write a {$tone} sales proposal:\n\nFrom: {$ourCompany}\nTo: {$clientName} at {$clientCompany}\nClient challenges: {$painPoints}\nProposed solution: {$solution}\nPricing: {$pricing}\nTimeline: {$timeline}\n\nStructure:\nSALES PROPOSAL\nEXECUTIVE SUMMARY\nUNDERSTANDING YOUR CHALLENGES\nOUR PROPOSED SOLUTION\nHOW WE WORK\nTIMELINE\nINVESTMENT\nWHY CHOOSE US\nNEXT STEPS";
        $result = Claude::generate($system, $user, 1800);
        echo json_encode($result);
        break;

    /* ── Ad Copy Writer ── */
    case 'ad_copy':
        $product   = sanitize(req('product_name'));
        $desc      = sanitize(req('product_description'));
        $audience  = sanitize(req('target_audience'));
        $platforms = sanitize(req('platforms'));
        $objective = sanitize(req('objective', 'sales'));
        $usp       = sanitize(req('usp'));
        $tone      = sanitize(req('tone', 'professional'));

        if (!$product) { echo json_encode(['ok'=>false,'error'=>'Please enter a product or service name.']); exit; }
        if (!$platforms) { echo json_encode(['ok'=>false,'error'=>'Please select at least one platform.']); exit; }

        $system = "You are an expert digital advertising copywriter. Write high-converting ad copy for each platform, respecting their best practices and character limits.";
        $user   = "Write ad copy for:\n\nProduct/Service: {$product}\nDescription: {$desc}\nTarget Audience: {$audience}\nPlatforms: {$platforms}\nObjective: {$objective}\nUnique Selling Point: {$usp}\nTone: {$tone}\n\nFor each platform include headline, body copy, and CTA. Separate each platform section with ---\nGoogle Search: 3 headlines (≤30 chars each) + 2 descriptions (≤90 chars each)\nFacebook/Instagram: headline + body + CTA\nTikTok: hook line + 15-30s script outline + CTA\nLinkedIn: professional headline + body + CTA";
        $result = Claude::generate($system, $user, 1500);
        echo json_encode($result);
        break;

    /* ── Customer Reply ── */
    case 'customer_reply':
        $message    = sanitize(req('customer_message'));
        $msgType    = sanitize(req('message_type', 'complaint'));
        $resolution = sanitize(req('resolution'));
        $tone       = sanitize(req('tone', 'professional'));
        $yourName   = sanitize(req('your_name'));
        $company    = sanitize(req('company_name'));

        if (!$message) { echo json_encode(['ok'=>false,'error'=>'Please paste the customer message.']); exit; }

        $system = "You are a customer service expert. Write professional, empathetic replies that resolve issues and retain customers.";
        $user   = "Write a {$tone} reply to this customer {$msgType}:\n\nCUSTOMER MESSAGE:\n{$message}\n\nResolution/Action: {$resolution}\nReply from: {$yourName} at {$company}\n\nWrite a complete response that acknowledges the concern, explains the resolution, and closes positively. Output only the reply.";
        $result = Claude::generate($system, $user, 800);
        echo json_encode($result);
        break;

    /* ── Product Description ── */
    case 'product_description':
        $name     = sanitize(req('product_name'));
        $category = sanitize(req('product_category'));
        $features = sanitize(req('key_features'));
        $customer = sanitize(req('target_customer'));
        $tone     = sanitize(req('tone', 'professional'));
        $length   = sanitize(req('length', 'medium'));
        $platform = sanitize(req('platform', 'website'));

        if (!$name || !$features) { echo json_encode(['ok'=>false,'error'=>'Please enter the product name and key features.']); exit; }

        $system = "You are an expert product copywriter. Write compelling, SEO-friendly product descriptions that convert browsers into buyers.";
        $user   = "Write a {$tone} product description for {$platform}:\n\nProduct: {$name}\nCategory: {$category}\nKey Features: {$features}\nTarget Customer: {$customer}\nLength: {$length}\n\nInclude: compelling headline, short description (1-2 sentences), key benefits (bullets), detailed description, and 5-8 SEO keywords.";
        $result = Claude::generate($system, $user, 1200);
        echo json_encode($result);
        break;

    /* ── SOP Generator ── */
    case 'sop':
        $processName = sanitize(req('process_name'));
        $dept        = sanitize(req('department'));
        $objective   = sanitize(req('objective'));
        $steps       = sanitize(req('steps'));
        $tools       = sanitize(req('tools_needed'));
        $frequency   = sanitize(req('frequency', 'as needed'));
        $version     = sanitize(req('version', '1.0'));

        if (!$processName || !$steps) { echo json_encode(['ok'=>false,'error'=>'Please fill in the process name and steps.']); exit; }

        $system = "You are an operations management expert. Write clear, comprehensive Standard Operating Procedures that anyone can follow.";
        $user   = "Write a formal SOP:\n\nProcess: {$processName}\nDepartment: {$dept}\nObjective: {$objective}\nSteps: {$steps}\nTools Required: {$tools}\nFrequency: {$frequency}\nVersion: {$version}\n\nStructure:\nSTANDARD OPERATING PROCEDURE\nDocument Title | Version | Department | Frequency\n\n1. PURPOSE\n2. SCOPE\n3. TOOLS & RESOURCES REQUIRED\n4. RESPONSIBILITIES\n5. PROCEDURE (numbered, detailed steps)\n6. QUALITY CHECKS\n7. NOTES & EXCEPTIONS";
        $result = Claude::generate($system, $user, 1800);
        echo json_encode($result);
        break;

    /* ── Performance Review ── */
    case 'performance_review':
        $empName  = sanitize(req('employee_name'));
        $role     = sanitize(req('employee_role'));
        $period   = sanitize(req('review_period'));
        $revType  = sanitize(req('review_type', 'Annual'));
        $achieve  = sanitize(req('achievements'));
        $improve  = sanitize(req('areas_to_improve'));
        $goals    = sanitize(req('goals_next_period'));
        $rating   = sanitize(req('overall_rating', 'Meets Expectations'));
        $reviewer = sanitize(req('reviewer_name'));

        if (!$empName || !$role || !$achieve) { echo json_encode(['ok'=>false,'error'=>'Please fill in employee name, role, and achievements.']); exit; }

        $system = "You are an experienced HR manager. Write balanced, constructive performance reviews that motivate employees and support their development.";
        $user   = "Write a {$revType} performance review:\n\nEmployee: {$empName} | Role: {$role}\nReview Period: {$period} | Overall Rating: {$rating}\nReviewer: {$reviewer}\n\nAchievements/Strengths: {$achieve}\nAreas for Improvement: {$improve}\nGoals for Next Period: {$goals}\n\nStructure:\nPERFORMANCE REVIEW\nEmployee & Role | Period | Rating\n\nPERFORMANCE SUMMARY\nKEY ACHIEVEMENTS\nAREAS FOR DEVELOPMENT\nGOALS FOR NEXT PERIOD\nREVIEWER'S COMMENTS\nReviewed by: {$reviewer}";
        $result = Claude::generate($system, $user, 1500);
        echo json_encode($result);
        break;

    /* ── WhatsApp Templates ── */
    case 'whatsapp_templates':
        $type        = sanitize(req('template_type', 'promotional'));
        $business    = sanitize(req('business_name'));
        $message     = sanitize(req('key_message'));
        $cta         = sanitize(req('cta'));
        $tone        = sanitize(req('tone', 'friendly'));
        $language    = sanitize(req('language', 'English'));
        $variations  = (int)req('variations', 3);
        $emoji       = req('include_emoji') === 'on' ? 'yes' : 'no';

        if (!$message) { echo json_encode(['ok'=>false,'error'=>'Please describe your key message.']); exit; }

        $system = "You are a WhatsApp marketing expert. Write high-converting WhatsApp broadcast messages optimised for open rates and click-throughs. Keep messages concise, personal, and action-oriented.";
        $user   = "Write {$variations} WhatsApp {$type} message templates.

Business: {$business}
Key message: {$message}
Call to action: {$cta}
Tone: {$tone}
Language: {$language}
Include emojis: {$emoji}

For each template:
TEMPLATE [number]
[message body — max 160 words]
CTA: [button text or link instruction]
---

After all templates, add:
BEST PRACTICE TIPS
[3 tips for sending this type of message]";

        $result = Claude::generate($system, $user, 1500);
        echo json_encode($result);
        break;

    /* ── Business Report Generator ── */
    case 'business_report':
        $reportType  = sanitize(req('report_type', 'monthly'));
        $period      = sanitize(req('period'));
        $bizName     = sanitize(req('business_name'));
        $industry    = sanitize(req('industry'));
        $salesData   = sanitize(req('sales_data'));
        $highlights  = sanitize(req('highlights'));
        $challenges  = sanitize(req('challenges'));
        $nextActions = sanitize(req('next_actions'));
        $preparedBy  = sanitize(req('prepared_by'));

        if (!$salesData) { echo json_encode(['ok'=>false,'error'=>'Please enter your key performance data.']); exit; }

        $system = "You are a business analyst. Write clear, insightful business reports that help management make informed decisions. Use professional language and include data-driven observations.";
        $user   = "Write a {$reportType} business report.

Business: {$bizName} | Industry: {$industry}
Period: {$period}
Prepared by: {$preparedBy}

Performance Data:
{$salesData}

Highlights / Wins:
{$highlights}

Challenges / Issues:
{$challenges}

Planned Next Actions:
{$nextActions}

Structure:
BUSINESS REPORT — [period]
EXECUTIVE SUMMARY (3-4 sentences)
PERFORMANCE OVERVIEW (key metrics with analysis)
HIGHLIGHTS & ACHIEVEMENTS
CHALLENGES & RISKS
RECOMMENDATIONS & NEXT STEPS
OUTLOOK
Prepared by: {$preparedBy}";

        $result = Claude::generate($system, $user, 1800);
        echo json_encode($result);
        break;

    /* ── Cold Outreach Sequence ── */
    case 'cold_outreach':
        $product    = sanitize(req('product_service'));
        $targetRole = sanitize(req('target_role'));
        $painPoint  = sanitize(req('pain_point'));
        $usp        = sanitize(req('usp'));
        $seqLength  = (int)req('sequence_length', 3);
        $channel    = sanitize(req('channel', 'Email'));
        $tone       = sanitize(req('tone', 'professional'));
        $sender     = sanitize(req('sender_name'));

        if (!$product || !$targetRole) { echo json_encode(['ok'=>false,'error'=>'Please fill in your product/service and target role.']); exit; }

        $system = "You are a B2B sales expert. Write cold outreach sequences that get replies. Keep messages short, value-focused, and human — no templates that sound like templates.";
        $user   = "Write a {$seqLength}-touch cold outreach sequence via {$channel}.

Product/Service: {$product}
Target Role: {$targetRole}
Their Pain Point: {$painPoint}
Our USP / Differentiator: {$usp}
Tone: {$tone}
Sender: {$sender}

For each message:
TOUCH [number] — [Day X]
Subject: [subject line if email]
[message body]
---

After the sequence, add:
SEND TIPS
[timing, follow-up rules, personalisation advice]";

        $result = Claude::generate($system, $user, 1800);
        echo json_encode($result);
        break;

    /* ── Contract & Agreement Drafter ── */
    case 'contract_drafter':
        $contractType   = sanitize(req('contract_type', 'Service Agreement'));
        $partyA         = sanitize(req('party_a'));
        $partyB         = sanitize(req('party_b'));
        $keyTerms       = sanitize(req('key_terms'));
        $duration       = sanitize(req('duration'));
        $governingLaw   = sanitize(req('governing_law', 'Malaysia'));
        $specialClauses = sanitize(req('special_clauses'));

        if (!$partyA || !$partyB || !$keyTerms) { echo json_encode(['ok'=>false,'error'=>'Please fill in both parties and the key terms.']); exit; }

        $specialLine = $specialClauses ? "Special Clauses: {$specialClauses}" : '';

        $system = "You are a legal document specialist. Draft clear, comprehensive contracts that protect both parties. Use plain-English legal language. Include all standard protective clauses for the agreement type.";
        $user   = "Draft a {$contractType}.

Party A: {$partyA}
Party B: {$partyB}
Key Terms & Scope: {$keyTerms}
Duration: {$duration}
Governing Law: {$governingLaw}
{$specialLine}

Include these sections:
1. PARTIES
2. DEFINITIONS
3. SCOPE OF SERVICES / AGREEMENT
4. PAYMENT TERMS (if applicable)
5. TERM & TERMINATION
6. CONFIDENTIALITY
7. INTELLECTUAL PROPERTY
8. LIABILITY & INDEMNIFICATION
9. DISPUTE RESOLUTION
10. GOVERNING LAW
11. ENTIRE AGREEMENT
12. SIGNATURES

Add placeholder [DATE], [SIGNATURE], [NRIC/SSM] where appropriate.
End with: ⚠️ This is an AI-generated draft for reference only. Have a qualified lawyer review before signing.";

        $result = Claude::generate($system, $user, 2500);
        echo json_encode($result);
        break;

    /* ── Financial Analysis ── */
    case 'financial_analysis':
        $bizType    = sanitize(req('business_type'));
        $period     = sanitize(req('period'));
        $revenue    = sanitize(req('revenue'));
        $cogs       = sanitize(req('cogs'));
        $grossProfit= sanitize(req('gross_profit'));
        $expenses   = sanitize(req('expenses'));
        $netProfit  = sanitize(req('net_profit'));
        $vsLast     = sanitize(req('vs_last_period'));
        $concerns   = sanitize(req('concerns'));

        if (!$revenue || !$expenses) { echo json_encode(['ok'=>false,'error'=>'Please enter at least revenue and expenses.']); exit; }

        $grossLine   = $grossProfit ? "Gross Profit: {$grossProfit}" : '';
        $cogsLine    = $cogs        ? "COGS: {$cogs}"               : '';
        $netLine     = $netProfit   ? "Net Profit/Loss: {$netProfit}"  : '';
        $vsLastLine  = $vsLast      ? "vs Last Period: {$vsLast}"    : '';
        $concernLine = $concerns    ? "Key Concern: {$concerns}"     : '';

        $system = "You are a CFO-level financial analyst. Analyse business financials in plain English. Identify trends, red flags, and actionable recommendations. Be direct and specific — no generic advice.";
        $user   = "Analyse these financials and give a clear business health report.

Business Type: {$bizType} | Period: {$period}
Revenue: {$revenue}
{$cogsLine}
{$grossLine}
Key Expenses:
{$expenses}
{$netLine}
{$vsLastLine}
{$concernLine}

Write a structured analysis:
FINANCIAL HEALTH REPORT — {$period}

EXECUTIVE SNAPSHOT
[3-sentence summary with overall health rating: Healthy / Caution / Critical]

KEY METRICS ANALYSIS
[Revenue, margins, expense ratios — with commentary]

RED FLAGS 🚩
[Any warning signs in the numbers]

STRENGTHS ✅
[What's working well]

TOP 3 RECOMMENDATIONS
[Specific, actionable steps to improve profitability]

CASH FLOW OUTLOOK
[Near-term cash position advice]";

        $result = Claude::generate($system, $user, 1800);
        echo json_encode($result);
        break;

    /* ── Training Material Creator ── */
    case 'training_creator':
        $courseTitle  = sanitize(req('course_title'));
        $audience     = sanitize(req('target_audience'));
        $objectives   = sanitize(req('learning_objectives'));
        $topics       = sanitize(req('topics'));
        $duration     = sanitize(req('duration', '1 hour'));
        $format       = sanitize(req('format', 'written training guide'));
        $includeQuiz  = req('include_quiz') === 'on' ? true : false;

        if (!$courseTitle || !$audience || !$topics) { echo json_encode(['ok'=>false,'error'=>'Please fill in course title, audience, and topics.']); exit; }

        $quizSection = $includeQuiz ? "\n\nQUIZ (10 questions with answers)\n[Generate 10 multiple-choice questions covering the key topics above. Format: Q1. [question] A) B) C) D) Answer: [letter]]" : '';
        $objLine = $objectives ? "Learning Objectives: {$objectives}" : '';

        $system = "You are an expert instructional designer and corporate trainer. Create engaging, practical training materials that people actually remember and apply.";
        $user   = "Create a complete {$format} for:

Course: {$courseTitle}
Target Audience: {$audience}
{$objLine}
Topics to Cover:
{$topics}
Duration: {$duration}

Structure for the {$format}:
TRAINING MATERIAL: {$courseTitle}
For: {$audience} | Duration: {$duration}

LEARNING OBJECTIVES
[Clear, measurable outcomes]

MODULE OUTLINE
[Overview of sections]

[FULL CONTENT — section by section, with explanations, examples, tips, and practical exercises for each topic]

KEY TAKEAWAYS
[5 bullet points summarising the most important lessons]{$quizSection}";

        $result = Claude::generate($system, $user, 3000);
        echo json_encode($result);
        break;

    /* ── Chatbot Flow Designer ── */
    case 'chatbot_flow':
        $bizType     = sanitize(req('business_type'));
        $botName     = sanitize(req('bot_name', 'Ava'));
        $channel     = sanitize(req('channel', 'WhatsApp'));
        $language    = sanitize(req('language', 'English'));
        $botPurpose  = sanitize(req('bot_purpose', 'customer service and FAQ'));
        $scenarios   = sanitize(req('common_scenarios'));
        $escalation  = sanitize(req('escalation'));
        $bizHours    = sanitize(req('business_hours'));

        if (!$bizType || !$scenarios) { echo json_encode(['ok'=>false,'error'=>'Please fill in business type and customer scenarios.']); exit; }

        $escalationLine = $escalation ? "Escalate to human when: {$escalation}" : '';
        $hoursLine      = $bizHours   ? "Business Hours: {$bizHours}"          : '';

        $system = "You are a conversational AI designer specialising in WhatsApp and website chatbots for SMEs. Design flows that feel natural, resolve issues quickly, and hand off to humans at the right moment.";
        $user   = "Design a complete chatbot conversation flow.

Business Type: {$bizType}
Bot Name: {$botName}
Channel: {$channel}
Language: {$language}
Purpose: {$botPurpose}
{$hoursLine}
{$escalationLine}

Top Customer Scenarios:
{$scenarios}

Design the flow with these sections:

CHATBOT FLOW: {$botName} for {$bizType}
Platform: {$channel} | Language: {$language}

WELCOME MESSAGE
[Greeting + menu options]

MAIN MENU
[Numbered options matching the scenarios]

FLOW FOR EACH SCENARIO
[For each scenario: trigger phrases → bot responses → follow-up questions → resolution or handoff]

OUT-OF-HOURS MESSAGE
[After-hours auto-reply]

ESCALATION FLOW
[When and how to hand off to a human agent]

CLOSING MESSAGE
[How the bot ends a conversation]

IMPLEMENTATION NOTES
[Tips for setting this up on {$channel}]";

        $result = Claude::generate($system, $user, 2500);
        echo json_encode($result);
        break;

    /* ── Pitch Deck Generator ── */
    case 'pitch_deck':
        $company      = sanitize(req('company_name'));
        $industry     = sanitize(req('industry'));
        $problem      = sanitize(req('problem'));
        $solution     = sanitize(req('solution'));
        $targetMarket = sanitize(req('target_market'));
        $traction     = sanitize(req('traction'));
        $bizModel     = sanitize(req('business_model'));
        $fundingAsk   = sanitize(req('funding_ask'));
        $useOfFunds   = sanitize(req('use_of_funds'));
        $team         = sanitize(req('team'));

        if (!$company || !$problem || !$solution) { echo json_encode(['ok'=>false,'error'=>'Please fill in company name, problem, and solution.']); exit; }

        $tractionLine  = $traction   ? "Traction: {$traction}"          : '';
        $fundingLine   = $fundingAsk ? "Funding Ask: {$fundingAsk}"     : '';
        $useOfFundsLine= $useOfFunds ? "Use of Funds: {$useOfFunds}"    : '';
        $teamLine      = $team       ? "Key Team: {$team}"              : '';

        $system = "You are a top-tier pitch deck consultant who has helped startups raise millions. Write compelling, investor-ready pitch deck content slide by slide. Each slide should be punchy, specific, and data-driven where possible.";
        $user   = "Write complete pitch deck content for {$company}.

Company: {$company}
Industry: {$industry}
Problem: {$problem}
Solution: {$solution}
Target Market: {$targetMarket}
Business Model: {$bizModel}
{$tractionLine}
{$fundingLine}
{$useOfFundsLine}
{$teamLine}

Write content for each slide:

SLIDE 1 — COVER
[Company name, tagline, presenter info]

SLIDE 2 — THE PROBLEM
[3 bullet points. Make it visceral — data + pain]

SLIDE 3 — OUR SOLUTION
[Clear value proposition + how it works in 3 steps]

SLIDE 4 — MARKET OPPORTUNITY
[TAM / SAM / SOM — estimate if not provided]

SLIDE 5 — PRODUCT / HOW IT WORKS
[Key features / demo flow description]

SLIDE 6 — BUSINESS MODEL
[Revenue streams, pricing, unit economics]

SLIDE 7 — TRACTION
[Key metrics, milestones, social proof]

SLIDE 8 — GO-TO-MARKET STRATEGY
[Phase 1 → 2 → 3 expansion plan]

SLIDE 9 — COMPETITIVE LANDSCAPE
[Why you win — differentiation table]

SLIDE 10 — THE TEAM
[Founders' relevant background + why this team]

SLIDE 11 — FINANCIALS
[3-year projection overview + key assumptions]

SLIDE 12 — THE ASK
[Funding amount, use of funds breakdown, what you'll achieve with it]

For each slide: write the HEADLINE (bold) and 3-5 bullet points or short paragraphs of content.";

        $result = Claude::generate($system, $user, 3000);
        echo json_encode($result);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Unknown module: ' . htmlspecialchars($module)]);
}
