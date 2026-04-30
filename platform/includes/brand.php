<?php
class Brand {
    private static array $data = [];
    private static bool  $loaded = false;

    private static array $defaults = [
        'site_name'            => '',
        'brand_tagline'        => '',
        'brand_description'    => '',
        'brand_logo_url'       => '',
        'brand_favicon_url'    => '',
        'brand_email'          => '',
        'brand_phone'          => '',
        'brand_address'        => '',
        'brand_country'        => '',
        'brand_website'        => '',
        'brand_twitter'        => '',
        'brand_linkedin'       => '',
        'brand_facebook'       => '',
        'brand_youtube'        => '',
        'brand_instagram'      => '',
        'brand_whatsapp'       => '',
        'brand_currency_symbol'=> '$',
        'brand_currency_code'  => 'USD',
        'brand_timezone'       => 'UTC',
        'brand_language'       => 'en',
        'brand_color_primary'  => '#6366f1',
        'brand_color_accent'   => '#8b5cf6',
        'brand_ga_id'          => '',
        'brand_title_suffix'   => '',
        'active_theme'         => 'dark',
    ];

    public static function load(): void {
        if (self::$loaded) return;
        self::$data = self::$defaults;
        // Fallback from config constants where they exist
        if (defined('SITE_NAME'))      self::$data['site_name']             = SITE_NAME;
        if (defined('CURRENCY_SYMBOL')) self::$data['brand_currency_symbol'] = CURRENCY_SYMBOL;
        if (defined('ADMIN_EMAIL'))    self::$data['brand_email']            = ADMIN_EMAIL;
        try {
            $rows = DB::fetchAll("SELECT `key`, `value` FROM settings WHERE `key` = 'site_name' OR `key` LIKE 'brand_%' OR `key` = 'active_theme'");
            foreach ($rows as $r) {
                if ($r['value'] !== null && $r['value'] !== '') {
                    self::$data[$r['key']] = $r['value'];
                }
            }
        } catch (Throwable $e) { /* graceful fallback */ }
        self::$loaded = true;
    }

    public static function get(string $key, string $default = ''): string {
        self::load();
        return self::$data[$key] ?? $default;
    }

    public static function all(): array {
        self::load();
        return self::$data;
    }

    public static function name(): string {
        return self::get('site_name', defined('SITE_NAME') ? SITE_NAME : 'Platform');
    }

    /** Returns <style> tag injecting CSS custom properties for brand colors */
    public static function colorStyle(): string {
        self::load();
        $p = htmlspecialchars(self::$data['brand_color_primary']);
        $a = htmlspecialchars(self::$data['brand_color_accent']);
        if ($p === '#6366f1' && $a === '#8b5cf6') return ''; // defaults — no override needed
        return "<style>:root{--brand-primary:{$p};--brand-accent:{$a}}</style>";
    }

    /** Save a single key to the settings table */
    public static function save(string $key, string $value): void {
        $exists = DB::fetch("SELECT `key` FROM settings WHERE `key`=?", [$key]);
        if ($exists) {
            DB::update('settings', ['value' => $value], '`key`=?', [$key]);
        } else {
            DB::insert('settings', ['key' => $key, 'value' => $value]);
        }
        self::$data[$key] = $value;
    }

    /** Save multiple key→value pairs in one call */
    public static function saveMany(array $map): void {
        foreach ($map as $k => $v) self::save($k, $v);
    }
}
