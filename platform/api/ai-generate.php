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

    default:
        echo json_encode(['ok' => false, 'error' => 'Unknown module: ' . htmlspecialchars($module)]);
}
