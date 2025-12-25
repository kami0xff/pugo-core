<?php
/**
 * Pugo Content Types Registry
 * 
 * Defines different content types with their visual styling and behavior.
 * This is extensible - add new content types as needed for your site.
 * 
 * Each content type can have:
 * - name: Display name
 * - icon: Icon name (from Icons.php)
 * - color: Brand color for badges/accents
 * - description: Brief description
 * - section_match: Regex or array of section names that use this type
 * - frontmatter_match: Frontmatter field/value to identify this type
 * - default_layout: Hugo layout to use
 * - fields: Custom frontmatter fields for this type
 */

// Default content types - can be overridden by custom/content_types.php
$default_content_types = [
    'article' => [
        'name' => 'Article',
        'plural' => 'Articles',
        'icon' => 'file-text',
        'color' => '#3b82f6',
        'description' => 'Standard blog posts and articles',
        'section_match' => ['blog', 'posts', 'articles', 'news'],
        'default_layout' => 'single',
    ],
    
    'page' => [
        'name' => 'Page',
        'plural' => 'Pages',
        'icon' => 'layout',
        'color' => '#a78bfa',
        'description' => 'Standalone pages like About, Contact, Terms',
        'section_match' => [], // Detected by _index.md in root content folders
        'is_single_page' => true,
        'default_layout' => 'page',
    ],
    
    'review' => [
        'name' => 'Review',
        'plural' => 'Reviews',
        'icon' => 'star',
        'color' => '#f59e0b',
        'description' => 'Product or service reviews with ratings',
        'section_match' => ['reviews', 'ratings'],
        'frontmatter_match' => ['type' => 'review'],
        'default_layout' => 'review',
        'fields' => [
            'rating' => ['type' => 'number', 'min' => 1, 'max' => 5, 'label' => 'Rating (1-5)'],
            'pros' => ['type' => 'list', 'label' => 'Pros'],
            'cons' => ['type' => 'list', 'label' => 'Cons'],
            'verdict' => ['type' => 'textarea', 'label' => 'Verdict'],
        ],
    ],
    
    'tutorial' => [
        'name' => 'Tutorial',
        'plural' => 'Tutorials',
        'icon' => 'book-open',
        'color' => '#10b981',
        'description' => 'Step-by-step guides and how-tos',
        'section_match' => ['tutorials', 'guides', 'howto', 'how-to'],
        'frontmatter_match' => ['type' => 'tutorial'],
        'default_layout' => 'tutorial',
        'fields' => [
            'difficulty' => ['type' => 'select', 'options' => ['beginner', 'intermediate', 'advanced'], 'label' => 'Difficulty'],
            'duration' => ['type' => 'text', 'label' => 'Duration', 'placeholder' => '30 min'],
            'prerequisites' => ['type' => 'list', 'label' => 'Prerequisites'],
        ],
    ],
    
    'interview' => [
        'name' => 'Interview',
        'plural' => 'Interviews',
        'icon' => 'message-circle',
        'color' => '#ec4899',
        'description' => 'Q&A style interviews with people',
        'section_match' => ['interviews', 'qa', 'conversations'],
        'frontmatter_match' => ['type' => 'interview'],
        'default_layout' => 'interview',
        'fields' => [
            'interviewee' => ['type' => 'text', 'label' => 'Interviewee Name', 'required' => true],
            'interviewee_title' => ['type' => 'text', 'label' => 'Interviewee Title'],
            'interviewee_image' => ['type' => 'image', 'label' => 'Interviewee Photo'],
        ],
    ],
    
    'video' => [
        'name' => 'Video',
        'plural' => 'Videos',
        'icon' => 'video',
        'color' => '#ef4444',
        'description' => 'Video content with embedded players',
        'section_match' => ['videos', 'media', 'watch'],
        'frontmatter_match' => ['type' => 'video'],
        'default_layout' => 'video',
        'fields' => [
            'video_url' => ['type' => 'text', 'label' => 'Video URL', 'required' => true],
            'duration' => ['type' => 'text', 'label' => 'Duration'],
            'thumbnail' => ['type' => 'image', 'label' => 'Thumbnail'],
        ],
    ],
    
    'gallery' => [
        'name' => 'Gallery',
        'plural' => 'Galleries',
        'icon' => 'image',
        'color' => '#06b6d4',
        'description' => 'Photo galleries and image collections',
        'section_match' => ['gallery', 'galleries', 'photos', 'portfolio'],
        'frontmatter_match' => ['type' => 'gallery'],
        'default_layout' => 'gallery',
        'fields' => [
            'images' => ['type' => 'image_list', 'label' => 'Gallery Images'],
        ],
    ],
    
    'product' => [
        'name' => 'Product',
        'plural' => 'Products',
        'icon' => 'shopping-bag',
        'color' => '#84cc16',
        'description' => 'Product pages with pricing and details',
        'section_match' => ['products', 'shop', 'store'],
        'frontmatter_match' => ['type' => 'product'],
        'default_layout' => 'product',
        'fields' => [
            'price' => ['type' => 'text', 'label' => 'Price'],
            'sku' => ['type' => 'text', 'label' => 'SKU'],
            'in_stock' => ['type' => 'checkbox', 'label' => 'In Stock'],
            'buy_url' => ['type' => 'text', 'label' => 'Buy Link'],
        ],
    ],
    
    'event' => [
        'name' => 'Event',
        'plural' => 'Events',
        'icon' => 'calendar',
        'color' => '#a855f7',
        'description' => 'Events with dates and locations',
        'section_match' => ['events', 'calendar', 'meetups'],
        'frontmatter_match' => ['type' => 'event'],
        'default_layout' => 'event',
        'fields' => [
            'event_date' => ['type' => 'datetime', 'label' => 'Event Date', 'required' => true],
            'end_date' => ['type' => 'datetime', 'label' => 'End Date'],
            'location' => ['type' => 'text', 'label' => 'Location'],
            'ticket_url' => ['type' => 'text', 'label' => 'Ticket URL'],
        ],
    ],
    
    'faq' => [
        'name' => 'FAQ',
        'plural' => 'FAQs',
        'icon' => 'help-circle',
        'color' => '#64748b',
        'description' => 'Frequently asked questions',
        'section_match' => ['faq', 'faqs', 'help', 'support'],
        'frontmatter_match' => ['type' => 'faq'],
        'default_layout' => 'faq',
    ],
    
    'documentation' => [
        'name' => 'Documentation',
        'plural' => 'Docs',
        'icon' => 'book',
        'color' => '#0ea5e9',
        'description' => 'Technical documentation and reference',
        'section_match' => ['docs', 'documentation', 'reference', 'api'],
        'frontmatter_match' => ['type' => 'docs'],
        'default_layout' => 'docs',
        'fields' => [
            'version' => ['type' => 'text', 'label' => 'Version'],
            'order' => ['type' => 'number', 'label' => 'Sort Order'],
        ],
    ],
];

/**
 * Get all registered content types
 */
function get_content_types() {
    global $default_content_types;
    
    // Check for custom content types file
    $custom_path = dirname(__DIR__, 2) . '/custom/content_types.php';
    if (file_exists($custom_path)) {
        $custom_types = require $custom_path;
        return array_merge($default_content_types, $custom_types);
    }
    
    return $default_content_types;
}

/**
 * Detect content type from section name and frontmatter
 */
function detect_content_type($section, $frontmatter = []) {
    $types = get_content_types();
    
    // First check frontmatter for explicit type
    if (!empty($frontmatter['type'])) {
        $explicit_type = strtolower($frontmatter['type']);
        if (isset($types[$explicit_type])) {
            return $explicit_type;
        }
        // Check if frontmatter type matches any type's frontmatter_match
        foreach ($types as $type_key => $type) {
            if (!empty($type['frontmatter_match']['type']) && 
                strtolower($type['frontmatter_match']['type']) === $explicit_type) {
                return $type_key;
            }
        }
    }
    
    // Check section_match
    $section_lower = strtolower($section);
    foreach ($types as $type_key => $type) {
        if (!empty($type['section_match'])) {
            foreach ($type['section_match'] as $match) {
                if (strtolower($match) === $section_lower) {
                    return $type_key;
                }
            }
        }
    }
    
    // Check if it's a single page (has _index.md in root folder)
    if (!empty($frontmatter['_is_index'])) {
        return 'page';
    }
    
    // Default to article
    return 'article';
}

/**
 * Get content type info
 */
function get_content_type($type_key) {
    $types = get_content_types();
    return $types[$type_key] ?? $types['article'];
}

/**
 * Get content type badge HTML
 */
function content_type_badge($type_key, $size = 'normal') {
    $type = get_content_type($type_key);
    $icon = pugo_icon($type['icon'], $size === 'small' ? 12 : 14);
    
    $padding = $size === 'small' ? '2px 6px' : '4px 10px';
    $font_size = $size === 'small' ? '10px' : '11px';
    $gap = $size === 'small' ? '4px' : '6px';
    
    return sprintf(
        '<span class="content-type-badge" style="display: inline-flex; align-items: center; gap: %s; padding: %s; border-radius: 4px; font-size: %s; font-weight: 600; background: %s20; color: %s;">
            %s %s
        </span>',
        $gap,
        $padding,
        $font_size,
        $type['color'],
        $type['color'],
        $icon,
        htmlspecialchars($type['name'])
    );
}

/**
 * Get section with content type info
 */
function get_sections_with_types($lang = 'en') {
    global $config;
    
    $sections = get_sections_with_counts($lang);
    $types = get_content_types();
    
    foreach ($sections as $key => &$section) {
        $detected_type = detect_content_type($key);
        $type_info = get_content_type($detected_type);
        
        $section['content_type'] = $detected_type;
        $section['type_name'] = $type_info['name'];
        $section['type_icon'] = $type_info['icon'];
        $section['type_color'] = $type_info['color'];
    }
    
    return $sections;
}

/**
 * Get content statistics by type
 */
function get_content_stats_by_type($lang = 'en') {
    $articles = get_articles($lang);
    $types = get_content_types();
    $stats = [];
    
    // Initialize stats for all types
    foreach ($types as $key => $type) {
        $stats[$key] = [
            'type' => $type,
            'count' => 0,
            'items' => []
        ];
    }
    
    // Count articles by type
    foreach ($articles as $article) {
        $type_key = detect_content_type($article['section'], $article['frontmatter']);
        if (isset($stats[$type_key])) {
            $stats[$type_key]['count']++;
            if (count($stats[$type_key]['items']) < 5) {
                $stats[$type_key]['items'][] = $article;
            }
        }
    }
    
    // Remove empty types
    $stats = array_filter($stats, fn($s) => $s['count'] > 0);
    
    // Sort by count
    uasort($stats, fn($a, $b) => $b['count'] - $a['count']);
    
    return $stats;
}

