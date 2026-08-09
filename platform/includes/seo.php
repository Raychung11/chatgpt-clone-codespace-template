<?php
/**
 * SEO Helper Class — AiServe / BizAI Platform
 * Generates structured data (JSON-LD), Open Graph, Twitter Card, and canonical tags.
 */
class SEO {

    private static function siteUrl(): string {
        return defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://bizai.my';
    }

    private static function siteName(): string {
        return defined('SITE_NAME') ? SITE_NAME : 'AiServe';
    }

    /**
     * Generate canonical URL tag.
     */
    public static function canonical(string $path = ''): string {
        $base = self::siteUrl();
        if ($path === '') {
            // Use current request URI, strip query string for canonical
            $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
            $path = $uri;
        }
        $url = $base . '/' . ltrim($path, '/');
        return '<link rel="canonical" href="' . htmlspecialchars($url, ENT_QUOTES) . '">' . "\n";
    }

    /**
     * Generate Open Graph meta tags.
     */
    public static function og(string $title, string $desc, string $url, string $type = 'website', string $image = ''): string {
        $base      = self::siteUrl();
        $siteName  = self::siteName();
        $image     = $image ?: $base . '/assets/img/og-default.png';
        $out  = '<meta property="og:type"        content="' . htmlspecialchars($type,  ENT_QUOTES) . '">' . "\n";
        $out .= '<meta property="og:site_name"   content="' . htmlspecialchars($siteName, ENT_QUOTES) . '">' . "\n";
        $out .= '<meta property="og:title"       content="' . htmlspecialchars($title, ENT_QUOTES) . '">' . "\n";
        $out .= '<meta property="og:description" content="' . htmlspecialchars($desc,  ENT_QUOTES) . '">' . "\n";
        $out .= '<meta property="og:url"         content="' . htmlspecialchars($url,   ENT_QUOTES) . '">' . "\n";
        $out .= '<meta property="og:image"       content="' . htmlspecialchars($image, ENT_QUOTES) . '">' . "\n";
        return $out;
    }

    /**
     * Generate Twitter Card meta tags.
     */
    public static function twitterCard(string $title, string $desc, string $image = ''): string {
        $base  = self::siteUrl();
        $image = $image ?: $base . '/assets/img/og-default.png';
        $out  = '<meta name="twitter:card"        content="summary_large_image">' . "\n";
        $out .= '<meta name="twitter:title"       content="' . htmlspecialchars($title, ENT_QUOTES) . '">' . "\n";
        $out .= '<meta name="twitter:description" content="' . htmlspecialchars($desc,  ENT_QUOTES) . '">' . "\n";
        $out .= '<meta name="twitter:image"       content="' . htmlspecialchars($image, ENT_QUOTES) . '">' . "\n";
        return $out;
    }

    /**
     * Generate BreadcrumbList JSON-LD.
     * items = [['name' => 'Home', 'url' => '/'], ['name' => 'Blog', 'url' => '/blog/'], ['name' => 'Post Title', 'url' => '']]
     */
    public static function breadcrumbs(array $items): string {
        $base     = self::siteUrl();
        $elements = [];
        foreach ($items as $position => $item) {
            $entry = [
                '@type'    => 'ListItem',
                'position' => $position + 1,
                'name'     => $item['name'],
            ];
            if (!empty($item['url'])) {
                $entry['item'] = $base . '/' . ltrim($item['url'], '/');
            }
            $elements[] = $entry;
        }
        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    /**
     * Generate Organization JSON-LD schema (sitewide).
     */
    public static function organization(): string {
        $base = self::siteUrl();
        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => self::siteName(),
            'url'      => $base,
            'logo'     => $base . '/assets/img/logo.png',
            'sameAs'   => [],
            'contactPoint' => [
                '@type'       => 'ContactPoint',
                'contactType' => 'customer service',
                'email'       => defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'hello@bizai.my',
                'areaServed'  => 'MY',
                'availableLanguage' => ['English', 'Malay'],
            ],
            'address' => [
                '@type'           => 'PostalAddress',
                'addressCountry'  => 'MY',
            ],
        ];
        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    /**
     * Generate SoftwareApplication JSON-LD for a product/capsule.
     * Expects $product array with keys: name, tagline/description, slug, price_monthly, cat_name
     */
    public static function softwareApp(array $product): string {
        $base     = self::siteUrl();
        $currency = defined('CURRENCY') ? CURRENCY : 'MYR';
        $name     = $product['name'] ?? '';
        $desc     = $product['tagline'] ?? ($product['description'] ?? '');
        $slug     = $product['slug'] ?? '';
        $price    = $product['price_monthly'] ?? 0;
        $category = $product['cat_name'] ?? 'BusinessApplication';
        $schema = [
            '@context'            => 'https://schema.org',
            '@type'               => 'SoftwareApplication',
            'name'                => $name,
            'description'         => $desc,
            'url'                 => $base . '/product.php?slug=' . rawurlencode($slug),
            'applicationCategory' => $category,
            'operatingSystem'     => 'Web',
            'offers'              => [
                '@type'         => 'Offer',
                'price'         => number_format((float)$price, 2, '.', ''),
                'priceCurrency' => $currency,
                'priceSpecification' => [
                    '@type'           => 'UnitPriceSpecification',
                    'price'           => number_format((float)$price, 2, '.', ''),
                    'priceCurrency'   => $currency,
                    'unitCode'        => 'MON',
                    'billingDuration' => 1,
                    'billingIncrement' => 1,
                ],
            ],
            'provider' => [
                '@type' => 'Organization',
                'name'  => self::siteName(),
                'url'   => $base,
            ],
        ];
        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    /**
     * Generate FAQPage JSON-LD.
     * items = [['q' => 'Question?', 'a' => 'Answer text.']]
     */
    public static function faq(array $items): string {
        $entities = [];
        foreach ($items as $item) {
            if (empty($item['q']) || empty($item['a'])) continue;
            $entities[] = [
                '@type'          => 'Question',
                'name'           => $item['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $item['a'],
                ],
            ];
        }
        if (empty($entities)) return '';
        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $entities,
        ];
        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    /**
     * Generate Article (BlogPosting) JSON-LD.
     * Expects $post array: title, slug, excerpt, content, published_at, updated_at, author_name, category, featured_image
     */
    public static function blogPost(array $post): string {
        $base       = self::siteUrl();
        $title      = $post['title'] ?? '';
        $slug       = $post['slug'] ?? '';
        $excerpt    = $post['excerpt'] ?? '';
        $content    = strip_tags($post['content'] ?? '');
        $published  = $post['published_at'] ?? ($post['created_at'] ?? '');
        $modified   = $post['updated_at'] ?? $published;
        $authorName = $post['author_name'] ?? self::siteName();
        $image      = !empty($post['featured_image']) ? $post['featured_image'] : $base . '/assets/img/blog-og.png';
        // Normalise to ISO8601
        $pubDate    = $published ? date('c', strtotime($published)) : '';
        $modDate    = $modified  ? date('c', strtotime($modified))  : '';
        $schema = [
            '@context'         => 'https://schema.org',
            '@type'            => 'BlogPosting',
            'headline'         => $title,
            'description'      => $excerpt,
            'articleBody'      => mb_substr($content, 0, 500),
            'url'              => $base . '/blog/' . rawurlencode($slug),
            'image'            => $image,
            'datePublished'    => $pubDate,
            'dateModified'     => $modDate,
            'author'           => [
                '@type' => 'Person',
                'name'  => $authorName,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name'  => self::siteName(),
                'logo'  => [
                    '@type' => 'ImageObject',
                    'url'   => $base . '/assets/img/logo.png',
                ],
            ],
        ];
        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
}
