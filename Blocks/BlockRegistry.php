<?php
/**
 * Pugo Core 3.0 - Block Registry
 * 
 * Manages visual blocks that can be used in the page builder.
 * Blocks are reusable components with Hugo partials and editable fields.
 */

namespace Pugo\Blocks;

use Pugo\Config\PugoConfig;

class BlockRegistry
{
    private static ?BlockRegistry $instance = null;
    private array $blocks = [];
    private array $categories = [];
    
    private function __construct()
    {
        $this->registerDefaultCategories();
        $this->registerDefaultBlocks();
        $this->loadFromConfig();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Register default block categories
     */
    protected function registerDefaultCategories(): void
    {
        $this->categories = [
            'layout' => ['name' => 'Layout', 'icon' => 'layout', 'order' => 1],
            'content' => ['name' => 'Content', 'icon' => 'file-text', 'order' => 2],
            'media' => ['name' => 'Media', 'icon' => 'image', 'order' => 3],
            'data' => ['name' => 'Data Display', 'icon' => 'database', 'order' => 4],
            'forms' => ['name' => 'Forms', 'icon' => 'mail', 'order' => 5],
            'commerce' => ['name' => 'Commerce', 'icon' => 'shopping-cart', 'order' => 6],
            'social' => ['name' => 'Social', 'icon' => 'share-2', 'order' => 7],
        ];
    }
    
    /**
     * Register built-in blocks
     */
    protected function registerDefaultBlocks(): void
    {
        // Hero Section
        $this->register('hero', [
            'name' => 'Hero Section',
            'description' => 'Large banner with title, subtitle, and call-to-action',
            'icon' => 'layout',
            'category' => 'layout',
            'partial' => 'blocks/hero.html',
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title', 'required' => true],
                'subtitle' => ['type' => 'textarea', 'label' => 'Subtitle'],
                'cta_text' => ['type' => 'text', 'label' => 'Button Text'],
                'cta_url' => ['type' => 'text', 'label' => 'Button URL'],
                'background' => ['type' => 'image', 'label' => 'Background Image'],
                'overlay' => ['type' => 'checkbox', 'label' => 'Dark Overlay'],
                'align' => [
                    'type' => 'select',
                    'label' => 'Text Alignment',
                    'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'],
                    'default' => 'center',
                ],
            ],
            'preview_template' => '<div class="block-preview-hero"><h2>{title}</h2><p>{subtitle}</p></div>',
        ]);
        
        // Features Grid
        $this->register('features', [
            'name' => 'Features Grid',
            'description' => 'Grid of feature cards with icons',
            'icon' => 'grid',
            'category' => 'content',
            'partial' => 'blocks/features.html',
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Section Title'],
                'subtitle' => ['type' => 'textarea', 'label' => 'Section Subtitle'],
                'columns' => [
                    'type' => 'select',
                    'label' => 'Columns',
                    'options' => ['2' => '2 Columns', '3' => '3 Columns', '4' => '4 Columns'],
                    'default' => '3',
                ],
                'items' => [
                    'type' => 'repeater',
                    'label' => 'Features',
                    'fields' => [
                        'icon' => ['type' => 'icon', 'label' => 'Icon'],
                        'title' => ['type' => 'text', 'label' => 'Title'],
                        'description' => ['type' => 'textarea', 'label' => 'Description'],
                    ],
                ],
            ],
        ]);
        
        // Testimonials
        $this->register('testimonials', [
            'name' => 'Testimonials',
            'description' => 'Customer testimonials carousel or grid',
            'icon' => 'message-circle',
            'category' => 'social',
            'partial' => 'blocks/testimonials.html',
            'data_source' => 'testimonials',  // Links to data/testimonials.yaml
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Section Title'],
                'layout' => [
                    'type' => 'select',
                    'label' => 'Layout',
                    'options' => ['carousel' => 'Carousel', 'grid' => 'Grid'],
                    'default' => 'carousel',
                ],
                'limit' => ['type' => 'number', 'label' => 'Max Items', 'default' => 6],
            ],
        ]);
        
        // FAQ Accordion
        $this->register('faq', [
            'name' => 'FAQ Section',
            'description' => 'Frequently asked questions accordion',
            'icon' => 'help-circle',
            'category' => 'content',
            'partial' => 'blocks/faq.html',
            'data_source' => 'faqs',
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Section Title'],
                'subtitle' => ['type' => 'textarea', 'label' => 'Section Subtitle'],
                'limit' => ['type' => 'number', 'label' => 'Max Questions', 'default' => 10],
            ],
        ]);
        
        // Pricing Table
        $this->register('pricing', [
            'name' => 'Pricing Table',
            'description' => 'Pricing plans comparison table',
            'icon' => 'dollar-sign',
            'category' => 'commerce',
            'partial' => 'blocks/pricing.html',
            'data_source' => 'pricing',
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Section Title'],
                'subtitle' => ['type' => 'textarea', 'label' => 'Section Subtitle'],
                'show_annual' => ['type' => 'checkbox', 'label' => 'Show Annual Toggle'],
            ],
        ]);
        
        // Call to Action
        $this->register('cta', [
            'name' => 'Call to Action',
            'description' => 'Prominent call-to-action banner',
            'icon' => 'mouse-pointer',
            'category' => 'layout',
            'partial' => 'blocks/cta.html',
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title', 'required' => true],
                'description' => ['type' => 'textarea', 'label' => 'Description'],
                'button_text' => ['type' => 'text', 'label' => 'Button Text'],
                'button_url' => ['type' => 'text', 'label' => 'Button URL'],
                'style' => [
                    'type' => 'select',
                    'label' => 'Style',
                    'options' => ['default' => 'Default', 'gradient' => 'Gradient', 'dark' => 'Dark'],
                ],
            ],
        ]);
        
        // Image Gallery
        $this->register('gallery', [
            'name' => 'Image Gallery',
            'description' => 'Responsive image gallery with lightbox',
            'icon' => 'image',
            'category' => 'media',
            'partial' => 'blocks/gallery.html',
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Section Title'],
                'columns' => [
                    'type' => 'select',
                    'label' => 'Columns',
                    'options' => ['2' => '2', '3' => '3', '4' => '4'],
                    'default' => '3',
                ],
                'images' => [
                    'type' => 'repeater',
                    'label' => 'Images',
                    'fields' => [
                        'image' => ['type' => 'image', 'label' => 'Image'],
                        'caption' => ['type' => 'text', 'label' => 'Caption'],
                    ],
                ],
            ],
        ]);
        
        // Video Section
        $this->register('video', [
            'name' => 'Video Section',
            'description' => 'Embedded video (YouTube, Vimeo, or self-hosted)',
            'icon' => 'video',
            'category' => 'media',
            'partial' => 'blocks/video.html',
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Section Title'],
                'video_url' => ['type' => 'url', 'label' => 'Video URL', 'required' => true],
                'poster' => ['type' => 'image', 'label' => 'Poster Image'],
                'autoplay' => ['type' => 'checkbox', 'label' => 'Autoplay'],
            ],
        ]);
        
        // Text Content
        $this->register('text', [
            'name' => 'Text Content',
            'description' => 'Rich text content block',
            'icon' => 'type',
            'category' => 'content',
            'partial' => 'blocks/text.html',
            'fields' => [
                'content' => ['type' => 'markdown', 'label' => 'Content', 'required' => true],
                'max_width' => [
                    'type' => 'select',
                    'label' => 'Max Width',
                    'options' => ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large', 'full' => 'Full'],
                    'default' => 'md',
                ],
            ],
        ]);
        
        // Stats/Numbers
        $this->register('stats', [
            'name' => 'Statistics',
            'description' => 'Animated number statistics',
            'icon' => 'trending-up',
            'category' => 'content',
            'partial' => 'blocks/stats.html',
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Section Title'],
                'items' => [
                    'type' => 'repeater',
                    'label' => 'Stats',
                    'fields' => [
                        'value' => ['type' => 'text', 'label' => 'Value (e.g., 10K+)'],
                        'label' => ['type' => 'text', 'label' => 'Label'],
                    ],
                ],
            ],
        ]);
        
        // Contact Form
        $this->register('contact', [
            'name' => 'Contact Form',
            'description' => 'Contact form with customizable fields',
            'icon' => 'mail',
            'category' => 'forms',
            'partial' => 'blocks/contact.html',
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Form Title'],
                'email' => ['type' => 'email', 'label' => 'Recipient Email', 'required' => true],
                'success_message' => ['type' => 'text', 'label' => 'Success Message'],
                'show_phone' => ['type' => 'checkbox', 'label' => 'Include Phone Field'],
                'show_subject' => ['type' => 'checkbox', 'label' => 'Include Subject Field'],
            ],
        ]);
        
        // Newsletter Signup
        $this->register('newsletter', [
            'name' => 'Newsletter Signup',
            'description' => 'Email newsletter subscription form',
            'icon' => 'send',
            'category' => 'forms',
            'partial' => 'blocks/newsletter.html',
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'description' => ['type' => 'textarea', 'label' => 'Description'],
                'button_text' => ['type' => 'text', 'label' => 'Button Text', 'default' => 'Subscribe'],
                'provider' => [
                    'type' => 'select',
                    'label' => 'Provider',
                    'options' => ['mailchimp' => 'Mailchimp', 'convertkit' => 'ConvertKit', 'custom' => 'Custom'],
                ],
                'form_action' => ['type' => 'url', 'label' => 'Form Action URL'],
            ],
        ]);
        
        // Team Members
        $this->register('team', [
            'name' => 'Team Members',
            'description' => 'Team member cards with social links',
            'icon' => 'users',
            'category' => 'content',
            'partial' => 'blocks/team.html',
            'data_source' => 'team',
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Section Title'],
                'layout' => [
                    'type' => 'select',
                    'label' => 'Layout',
                    'options' => ['grid' => 'Grid', 'carousel' => 'Carousel'],
                    'default' => 'grid',
                ],
            ],
        ]);
        
        // Logo Cloud
        $this->register('logos', [
            'name' => 'Logo Cloud',
            'description' => 'Client/partner logo showcase',
            'icon' => 'award',
            'category' => 'social',
            'partial' => 'blocks/logos.html',
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Section Title'],
                'grayscale' => ['type' => 'checkbox', 'label' => 'Grayscale Logos'],
                'logos' => [
                    'type' => 'repeater',
                    'label' => 'Logos',
                    'fields' => [
                        'image' => ['type' => 'image', 'label' => 'Logo'],
                        'name' => ['type' => 'text', 'label' => 'Company Name'],
                        'url' => ['type' => 'url', 'label' => 'Link URL'],
                    ],
                ],
            ],
        ]);
    }
    
    /**
     * Load blocks from pugo.yaml config
     */
    protected function loadFromConfig(): void
    {
        $config = PugoConfig::getInstance();
        $configBlocks = $config->blocks();
        
        foreach ($configBlocks as $id => $block) {
            $this->register($id, $block);
        }
    }
    
    /**
     * Register a block
     */
    public function register(string $id, array $config): self
    {
        $this->blocks[$id] = array_merge([
            'id' => $id,
            'name' => ucfirst(str_replace('-', ' ', $id)),
            'description' => '',
            'icon' => 'box',
            'category' => 'content',
            'partial' => "blocks/{$id}.html",
            'fields' => [],
            'data_source' => null,
            'enabled' => true,
        ], $config);
        
        return $this;
    }
    
    /**
     * Get all blocks
     */
    public function all(bool $enabledOnly = true): array
    {
        if (!$enabledOnly) {
            return $this->blocks;
        }
        return array_filter($this->blocks, fn($b) => $b['enabled'] ?? true);
    }
    
    /**
     * Get a block by ID
     */
    public function get(string $id): ?array
    {
        return $this->blocks[$id] ?? null;
    }
    
    /**
     * Get blocks by category
     */
    public function byCategory(?string $category = null): array
    {
        $blocks = $this->all();
        
        if ($category === null) {
            // Group by category
            $grouped = [];
            foreach ($this->categories as $catId => $cat) {
                $grouped[$catId] = [
                    'info' => $cat,
                    'blocks' => array_filter($blocks, fn($b) => ($b['category'] ?? 'content') === $catId),
                ];
            }
            return $grouped;
        }
        
        return array_filter($blocks, fn($b) => ($b['category'] ?? 'content') === $category);
    }
    
    /**
     * Get categories
     */
    public function getCategories(): array
    {
        return $this->categories;
    }
    
    /**
     * Add a category
     */
    public function addCategory(string $id, string $name, string $icon = 'folder', int $order = 100): self
    {
        $this->categories[$id] = [
            'name' => $name,
            'icon' => $icon,
            'order' => $order,
        ];
        return $this;
    }
    
    /**
     * Check if a block exists
     */
    public function has(string $id): bool
    {
        return isset($this->blocks[$id]);
    }
    
    /**
     * Enable a block
     */
    public function enable(string $id): self
    {
        if (isset($this->blocks[$id])) {
            $this->blocks[$id]['enabled'] = true;
        }
        return $this;
    }
    
    /**
     * Disable a block
     */
    public function disable(string $id): self
    {
        if (isset($this->blocks[$id])) {
            $this->blocks[$id]['enabled'] = false;
        }
        return $this;
    }
    
    /**
     * Get block field definitions
     */
    public function getFields(string $id): array
    {
        return $this->blocks[$id]['fields'] ?? [];
    }
    
    /**
     * Render block for preview
     */
    public function renderPreview(string $id, array $data = []): string
    {
        $block = $this->get($id);
        
        if (!$block) {
            return '<div class="block-error">Unknown block: ' . htmlspecialchars($id) . '</div>';
        }
        
        // Use preview template if available
        if (!empty($block['preview_template'])) {
            $html = $block['preview_template'];
            foreach ($data as $key => $value) {
                $html = str_replace('{' . $key . '}', htmlspecialchars($value), $html);
            }
            return $html;
        }
        
        // Generic preview
        $name = htmlspecialchars($block['name']);
        return "<div class='block-preview block-preview-{$id}'><strong>{$name}</strong></div>";
    }
}

/**
 * Global helper
 */
function pugo_blocks(): BlockRegistry
{
    return BlockRegistry::getInstance();
}

