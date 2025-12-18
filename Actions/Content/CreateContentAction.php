<?php
/**
 * Pugo - Create Content Action
 * 
 * Creates a new content item with frontmatter and body.
 * Works with any content type (articles, reviews, tutorials, etc.)
 */

namespace Pugo\Actions\Content;

require_once dirname(__DIR__) . '/ActionResult.php';

use Pugo\Actions\ActionResult;

final readonly class CreateContentAction
{
    public function __construct(
        private string $contentDir
    ) {}

    /**
     * Create a new content item
     * 
     * @param string $section The section (e.g., 'users', 'models', 'reviews')
     * @param string $slug The content slug (filename without .md)
     * @param array $frontmatter Content frontmatter
     * @param string $body Content body
     * @param string|null $category Optional sub-category
     */
    public function handle(
        string $section,
        string $slug,
        array $frontmatter,
        string $body = '',
        ?string $category = null
    ): ActionResult {
        if (empty($section)) {
            return ActionResult::failure('Section is required');
        }

        if (empty($slug)) {
            return ActionResult::failure('Slug is required');
        }

        // Sanitize slug
        $slug = $this->sanitizeSlug($slug);



        //categories are like subsections and not that categories cannot have subcategories.
        // Build path
        $relativePath = $category 
            ? "{$section}/{$category}/{$slug}.md"
            : "{$section}/{$slug}.md";
        
        $fullPath = $this->contentDir . '/' . $relativePath;

        // Check if already exists
        if (file_exists($fullPath)) {
            return ActionResult::failure("Content already exists: {$relativePath}");
        }

        // Ensure directory exists
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                return ActionResult::failure("Could not create directory: {$dir}");
            }
        }

        try {
            //set default frontmatter fields
            $frontmatter['title'] = $frontmatter['title'] ?? '';
            $frontmatter['description'] = $frontmatter['description'] ?? '';
            $frontmatter['author'] = $frontmatter['author'] ?? 'XloveCam Team';
            $frontmatter['date'] = $frontmatter['date'] ?? date('Y-m-d');
            $frontmatter['lastmod'] = $frontmatter['lastmod'] ?? date('Y-m-d');
            $frontmatter['draft'] = $frontmatter['draft'] ?? false;
            $frontmatter['image'] = $frontmatter['image'] ?? null;
            $frontmatter['keywords'] = $frontmatter['keywords'] ?? [];
            $frontmatter['tags'] = $frontmatter['tags'] ?? [];
            $frontmatter['translationKey'] = $frontmatter['translationKey'] ?? null;
            $frontmatter['category'] = $frontmatter['category'] ?? null;
            $frontmatter['related'] = $frontmatter['related'] ?? [];
            $frontmatter['weight'] = $frontmatter['weight'] ?? 0;


            
            // Build content
            $content = $this->buildContent($frontmatter, $body);

            // Save
            file_put_contents($fullPath, $content);

            return ActionResult::success(
                message: 'Content created successfully',
                data: [
                    'path' => $fullPath,
                    'relative_path' => $relativePath,
                    'slug' => $slug,
                    'section' => $section,
                    'category' => $category
                ]
            );

            //POTENTIAL UPGRADES ACTIONS SHOULD BE TRANSACTIONS and
            //TODO dispatch event to modification queue 
            //with action undo and redo functions 

        } catch (\Exception $e) {
            return ActionResult::failure('Error creating content: ' . $e->getMessage());
        }
    }

    private function sanitizeSlug(string $slug): string
    {
        // Remove .md extension if present
        $slug = preg_replace('/\.md$/', '', $slug);
        
        // Convert to lowercase, replace spaces with hyphens
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/\s+/', '-', $slug);
        
        // Remove special characters except hyphens
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        
        // Remove multiple consecutive hyphens
        $slug = preg_replace('/-+/', '-', $slug);
        
        return trim($slug, '-');
    }

    private function buildContent(array $frontmatter, string $body): string
    {
        $yaml = '';
        
        // Define preferred order for common frontmatter keys
        $order = ['title', 'description', 'author', 'date', 'lastmod', 'draft', 'image', 'keywords', 'tags', 'related', 'translationKey', 'weight'];
        
        // Output in preferred order first
        foreach ($order as $key) {
            if (isset($frontmatter[$key])) {
                $yaml .= $this->formatYamlValue($key, $frontmatter[$key]);
                unset($frontmatter[$key]);
            }
        }
        
        // Output remaining keys (custom fields for specific content types)
        foreach ($frontmatter as $key => $value) {
            $yaml .= $this->formatYamlValue($key, $value);
        }

        return "---\n{$yaml}---\n{$body}";
    }

    private function formatYamlValue(string $key, mixed $value): string
    {
        if (is_array($value)) {
            $yaml = "{$key}:\n";
            foreach ($value as $item) {
                $yaml .= "  - \"{$item}\"\n";
            }
            return $yaml;
        }
        
        if (is_bool($value)) {
            return "{$key}: " . ($value ? 'true' : 'false') . "\n";
        }
        
        if (is_numeric($value) && !str_contains((string)$value, '-')) {
            return "{$key}: {$value}\n";
        }
        
        return "{$key}: \"{$value}\"\n";
    }
}

