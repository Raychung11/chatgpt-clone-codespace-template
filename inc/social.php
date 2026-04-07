<?php
declare(strict_types=1);

/**
 * inc/social.php
 * Social sharing helpers — caption/hashtag generation and share logging.
 * Kept completely separate from video generation logic.
 */

/**
 * Auto-generate a share caption from the video prompt.
 * In Phase 4 this can be replaced with a Claude API call.
 */
function social_generate_caption(string $prompt, string $platform = 'generic'): string
{
    // Strip template placeholders like {{variable}} or {{variable name}}
    $clean = preg_replace('/\{\{[^}]*\}\}/', '', $prompt);
    // Collapse extra whitespace and punctuation left behind
    $clean = preg_replace('/\s{2,}/', ' ', $clean);
    $clean = preg_replace('/[,.\s]+$/', '', trim($clean));

    // Take first meaningful sentence (up to 100 chars)
    $sentences = preg_split('/(?<=[.!?])\s+/', $clean, 2);
    $snippet   = trim($sentences[0] ?? $clean);
    if (mb_strlen($snippet) > 100) {
        $snippet = mb_substr($snippet, 0, 97) . '…';
    }
    if (mb_strlen($snippet) < 10) {
        $snippet = 'Check out this AI-generated marketing video!';
    }

    $ctas = [
        '✨ Created with AI in seconds.',
        '🚀 Bringing ideas to life with AI.',
        '🎯 AI-powered video marketing.',
        '💡 New AI video — what do you think?',
    ];
    $cta = $ctas[abs(crc32($prompt)) % count($ctas)];

    $siteName = defined('APP_ENV') ? (setting('site_name', 'VideoSaaS') ?: 'VideoSaaS') : 'VideoSaaS';

    return match ($platform) {
        'twitter'  => mb_substr("🎬 {$snippet} {$cta}", 0, 240),
        'linkedin' => "🎬 {$snippet}\n\n{$cta}\n\nMade with {$siteName}",
        'whatsapp' => "🎬 *{$snippet}*\n\n{$cta}",
        default    => "🎬 {$snippet}\n\n{$cta}",
    };
}

/**
 * Auto-generate hashtags from the prompt.
 */
function social_generate_hashtags(string $prompt): string
{
    // Extract likely keywords (nouns / adjectives, 4+ chars, no stop words)
    $stopWords = ['with','this','that','from','have','will','your','their',
                  'been','were','they','them','what','when','which','into',
                  'more','some','also','just','like','make','than','about',
                  'very','show','should','would','could'];

    $words = preg_split('/\W+/', strtolower($prompt), -1, PREG_SPLIT_NO_EMPTY);
    $tags  = [];

    foreach ($words as $word) {
        if (strlen($word) >= 4 && !in_array($word, $stopWords, true)) {
            $tags[$word] = true;
        }
    }

    // Generic marketing hashtags always included
    $always = ['AIVideo','MarketingVideo','AIMarketing','VideoMarketing','DigitalMarketing'];
    $custom = array_slice(array_keys($tags), 0, 5);

    $hashtags = array_map(fn($t) => '#' . ucfirst($t), $custom);
    foreach ($always as $a) {
        $hashtags[] = '#' . $a;
    }

    return implode(' ', array_unique($hashtags));
}

/**
 * Build share URLs for each platform.
 *
 * @param string $shareUrl  Public URL to the video (or platform page)
 * @param string $caption   Pre-filled caption text
 */
function social_share_urls(string $shareUrl, string $caption = ''): array
{
    $enc     = urlencode($shareUrl);
    $encText = urlencode($caption . ' ' . $shareUrl);

    return [
        'whatsapp'  => 'https://wa.me/?text='   . urlencode($caption . "\n" . $shareUrl),
        'facebook'  => 'https://www.facebook.com/sharer/sharer.php?u=' . $enc,
        'twitter'   => 'https://twitter.com/intent/tweet?text=' . urlencode($caption) . '&url=' . $enc,
        'linkedin'  => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $enc,
    ];
}

/**
 * Log a social share action.
 */
function social_log_share(
    int    $user_id,
    int    $job_id,
    string $platform,
    string $caption,
    string $hashtags,
    string $share_type = 'link'
): void {
    try {
        db()->prepare(
            'INSERT INTO `social_share_logs`
             (`user_id`,`video_job_id`,`platform`,`caption`,`hashtags`,`share_type`)
             VALUES (?,?,?,?,?,?)'
        )->execute([$user_id, $job_id, $platform, $caption, $hashtags, $share_type]);
    } catch (PDOException $e) {
        error_log('[social_log] ' . $e->getMessage());
    }
}
