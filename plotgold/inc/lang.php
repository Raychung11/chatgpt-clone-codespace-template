<?php
/**
 * PlotGold Malaysia — Multilingual / i18n Helper
 *
 * Language resolution order:
 *   1. ?lang= URL param  →  persists to session + cookie
 *   2. $_SESSION['lang']
 *   3. pg_lang cookie
 *   4. Default: 'en'
 *
 * Supported locales: 'en'  (English)
 *                    'zh'  (Simplified Chinese)
 */
defined('PLOTGOLD') || die;

/* ── Language Detection & Persistence ──────────────────────────── */

(function () {
    $supported = ['en', 'zh'];

    // 1. Explicit ?lang= switch in URL
    if (isset($_GET['lang']) && in_array($_GET['lang'], $supported, true)) {
        $lang = $_GET['lang'];
        $_SESSION['lang'] = $lang;
        setcookie('pg_lang', $lang, time() + (86400 * 365), '/', '', false, false);
    }
    // 2. Session
    elseif (isset($_SESSION['lang']) && in_array($_SESSION['lang'], $supported, true)) {
        $lang = $_SESSION['lang'];
    }
    // 3. Cookie
    elseif (isset($_COOKIE['pg_lang']) && in_array($_COOKIE['pg_lang'], $supported, true)) {
        $lang = $_COOKIE['pg_lang'];
        $_SESSION['lang'] = $lang;
    }
    // 4. Default
    else {
        $lang = 'en';
    }

    $GLOBALS['_pg_lang'] = $lang;

    // Load strings into global array
    $file = ROOT_PATH . '/lang/' . $lang . '.php';
    if (file_exists($file)) {
        $GLOBALS['_lang'] = require $file;
    } else {
        // Fallback to English if requested file missing
        $GLOBALS['_lang'] = require ROOT_PATH . '/lang/en.php';
    }
})();


/* ── Core Translation Function ──────────────────────────────────── */

/**
 * Translate a string key, with optional parameter interpolation.
 *
 * Usage:
 *   __('nav.browse')
 *   __('browse.showing', ['from'=>1,'to'=>20,'total'=>150])
 *
 * Parameters are replaced as :key in the string.
 * Falls back to the key itself if not found.
 */
function __( string $key, array $params = [] ): string {
    $str = $GLOBALS['_lang'][$key] ?? $key;

    if ( ! empty($params) ) {
        foreach ( $params as $placeholder => $value ) {
            $str = str_replace(':' . $placeholder, (string) $value, $str);
        }
    }

    return $str;
}


/* ── Escaped Translation (for HTML output) ──────────────────────── */

/**
 * Translate and HTML-escape.  Use inside echo in HTML templates.
 */
function _e( string $key, array $params = [] ): string {
    return htmlspecialchars(__($key, $params), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


/* ── Current Language Accessor ──────────────────────────────────── */

function current_lang(): string {
    return $GLOBALS['_pg_lang'] ?? 'en';
}

function is_lang( string $lang ): bool {
    return current_lang() === $lang;
}


/* ── DB Label Column Helper ─────────────────────────────────────── */

/**
 * Return the localised label from a DB row that has label_en / label_zh columns.
 *
 * Example:
 *   lang_label($row)           // uses 'label_en' or 'label_zh'
 *   lang_label($row, 'name')   // uses 'name_en'  or 'name_zh'
 *
 * Falls back to the _en column if the _zh column is empty.
 */
function lang_label( array $row, string $prefix = 'label' ): string {
    $lang   = current_lang();
    $col    = $prefix . '_' . $lang;
    $colFb  = $prefix . '_en';

    $val = $row[$col] ?? '';
    if ( $val === '' || $val === null ) {
        $val = $row[$colFb] ?? '';
    }

    return htmlspecialchars((string) $val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


/* ── Language Switcher URL Builder ──────────────────────────────── */

/**
 * Build a URL to switch to the given language,
 * preserving all existing GET params except 'lang'.
 */
function lang_switch_url( string $lang ): string {
    $params        = $_GET;
    $params['lang'] = $lang;
    $qs            = http_build_query($params);
    $base          = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    return htmlspecialchars($base . '?' . $qs, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


/* ── HTML lang Attribute ────────────────────────────────────────── */

/**
 * Returns the BCP 47 lang tag for the <html> element.
 *   'en' → 'en'
 *   'zh' → 'zh-Hans'
 */
function html_lang(): string {
    $map = [
        'en' => 'en',
        'zh' => 'zh-Hans',
    ];
    return $map[current_lang()] ?? 'en';
}
