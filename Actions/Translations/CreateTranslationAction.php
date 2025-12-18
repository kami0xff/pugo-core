<?php
/**
 * Pugo - Create Translation Action
 * 
 * Creates a translation of an existing article in a target language.
 * - Copies the article to the target language content directory
 * - Ensures translationKey is set (generates if missing)
 * - Creates parent directories and _index.md files as needed
 */

namespace Pugo\Actions\Translations;

require_once dirname(__DIR__) . '/ActionResult.php';

use Pugo\Actions\ActionResult;

final class CreateTranslationAction
{
    public function __construct(
        private string $hugoRoot,
        private array $languages
    ) {}

    /**
     * Create a translation of an article
     * 
     * @param string $sourcePath Path relative to content dir (e.g., "users/getting-started/article.md")
     * @param string $sourceLang Source language code (e.g., "en")
     * @param string $targetLang Target language code (e.g., "fr")
     * @param array|null $targetFrontmatter Optional frontmatter overrides for target
     */
    public function handle(
        string $sourcePath,
        string $sourceLang,
        string $targetLang,
        ?array $targetFrontmatter = null
    ): ActionResult {
        // Validate languages
        if (!isset($this->languages[$sourceLang])) {
            return ActionResult::failure("Unknown source language: {$sourceLang}");
        }
        if (!isset($this->languages[$targetLang])) {
            return ActionResult::failure("Unknown target language: {$targetLang}");
        }
        
        // Get content directories
        $sourceDir = $this->getContentDir($sourceLang);
        $targetDir = $this->getContentDir($targetLang);
        
        $sourceFile = $sourceDir . '/' . ltrim($sourcePath, '/');
        $targetFile = $targetDir . '/' . ltrim($sourcePath, '/');
        
        // Check source exists
        if (!file_exists($sourceFile)) {
            return ActionResult::failure("Source article not found: {$sourcePath}");
        }
        
        // Check target doesn't exist
        if (file_exists($targetFile)) {
            return ActionResult::failure("Translation already exists in {$targetLang}");
        }
        
        // Read source content
        $content = file_get_contents($sourceFile);
        $parsed = $this->parseFrontmatter($content);
        
        // Ensure translationKey exists
        if (empty($parsed['frontmatter']['translationKey'])) {
            // Generate from filename
            $parsed['frontmatter']['translationKey'] = basename($sourcePath, '.md');
            
            // Update source file with translationKey
            $this->saveArticle($sourceFile, $parsed['frontmatter'], $parsed['body']);
        }
        
        // Create target frontmatter
        $newFrontmatter = $parsed['frontmatter'];
        
        // Apply overrides if provided
        if ($targetFrontmatter) {
            $newFrontmatter = array_merge($newFrontmatter, $targetFrontmatter);
        }
        
        // Update dates
        $newFrontmatter['date'] = date('Y-m-d');
        $newFrontmatter['lastmod'] = date('Y-m-d');
        
        // Create parent directories with _index.md files
        $this->ensureDirectoryStructure($targetDir, dirname($sourcePath), $targetLang);
        
        // Create target directory
        $targetDirectory = dirname($targetFile);
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }
        
        // Save translation
        $body = $parsed['body'];
        
        // Add translation notice at the top of body
        $notice = $this->getTranslationNotice($targetLang);
        $body = $notice . "\n\n" . $body;
        
        if (!$this->saveArticle($targetFile, $newFrontmatter, $body)) {
            return ActionResult::failure("Failed to create translation file");
        }
        
        return ActionResult::success(
            message: "Translation created in {$targetLang}",
            data: [
                'source' => $sourcePath,
                'target' => $targetFile,
                'translationKey' => $newFrontmatter['translationKey'],
            ]
        );
    }
    
    /**
     * Create all content in one language from another
     */
    public function handleBulk(string $sourceLang, string $targetLang): ActionResult
    {
        $sourceDir = $this->getContentDir($sourceLang);
        $targetDir = $this->getContentDir($targetLang);
        
        if (!is_dir($sourceDir)) {
            return ActionResult::failure("Source language has no content");
        }
        
        $created = 0;
        $skipped = 0;
        $errors = [];
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'md') continue;
            
            $relativePath = str_replace($sourceDir . '/', '', $file->getPathname());
            $targetFile = $targetDir . '/' . $relativePath;
            
            if (file_exists($targetFile)) {
                $skipped++;
                continue;
            }
            
            // For _index.md files, just create stub
            if (basename($relativePath) === '_index.md') {
                $this->ensureIndexFile($targetFile, $targetLang, dirname($relativePath));
                $created++;
                continue;
            }
            
            // For articles, use handle()
            $result = $this->handle($relativePath, $sourceLang, $targetLang);
            if ($result->isSuccess()) {
                $created++;
            } else {
                $errors[] = $relativePath . ': ' . $result->getMessage();
            }
        }
        
        return ActionResult::success(
            message: "Bulk translation complete: {$created} created, {$skipped} skipped",
            data: [
                'created' => $created,
                'skipped' => $skipped,
                'errors' => $errors,
            ]
        );
    }
    
    private function getContentDir(string $lang): string
    {
        if ($lang === 'en') {
            return $this->hugoRoot . '/content';
        }
        return $this->hugoRoot . '/' . ($this->languages[$lang]['content_dir'] ?? "content.{$lang}");
    }
    
    private function ensureDirectoryStructure(string $baseDir, string $relativePath, string $lang): void
    {
        $parts = explode('/', trim($relativePath, '/'));
        $currentPath = $baseDir;
        
        foreach ($parts as $part) {
            if (empty($part)) continue;
            
            $currentPath .= '/' . $part;
            
            if (!is_dir($currentPath)) {
                mkdir($currentPath, 0755, true);
            }
            
            // Create _index.md if missing
            $indexFile = $currentPath . '/_index.md';
            if (!file_exists($indexFile)) {
                $this->ensureIndexFile($indexFile, $lang, $part);
            }
        }
    }
    
    private function ensureIndexFile(string $path, string $lang, string $section): void
    {
        $title = ucfirst(str_replace('-', ' ', basename(dirname($path))));
        if ($title === '.') {
            $title = ucfirst(str_replace('-', ' ', $section));
        }
        
        $frontmatter = [
            'title' => $title,
            'description' => '',
        ];
        
        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $this->saveArticle($path, $frontmatter, '');
    }
    
    private function parseFrontmatter(string $content): array
    {
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            return ['frontmatter' => [], 'body' => $content];
        }
        
        $yaml = $matches[1];
        $body = $matches[2];
        $frontmatter = [];
        $currentKey = null;
        
        foreach (explode("\n", $yaml) as $line) {
            if (trim($line) === '') continue;
            
            if (preg_match('/^\s*-\s*(.*)$/', $line, $m)) {
                if ($currentKey) {
                    if (!isset($frontmatter[$currentKey]) || !is_array($frontmatter[$currentKey])) {
                        $frontmatter[$currentKey] = [];
                    }
                    $frontmatter[$currentKey][] = trim($m[1], '"\'');
                }
                continue;
            }
            
            if (preg_match('/^([a-zA-Z_]+):\s*(.*)$/', $line, $m)) {
                $currentKey = $m[1];
                $value = trim($m[2], '"\'');
                if ($value !== '') {
                    $frontmatter[$currentKey] = $value;
                }
            }
        }
        
        return ['frontmatter' => $frontmatter, 'body' => $body];
    }
    
    private function saveArticle(string $path, array $frontmatter, string $body): bool
    {
        $yaml = "---\n";
        
        foreach ($frontmatter as $key => $value) {
            if (is_array($value)) {
                $yaml .= "$key:\n";
                foreach ($value as $item) {
                    $yaml .= "  - \"" . addslashes($item) . "\"\n";
                }
            } elseif (is_bool($value)) {
                $yaml .= "$key: " . ($value ? 'true' : 'false') . "\n";
            } else {
                $yaml .= "$key: \"" . addslashes($value) . "\"\n";
            }
        }
        
        $yaml .= "---\n\n";
        
        return file_put_contents($path, $yaml . $body) !== false;
    }
    
    private function getTranslationNotice(string $lang): string
    {
        return match($lang) {
            'fr' => "{{< notice info >}}\n**Traduction en cours** - Cet article est en cours de traduction. Le contenu peut être incomplet.\n{{< /notice >}}",
            'es' => "{{< notice info >}}\n**Traducción en progreso** - Este artículo está siendo traducido. El contenido puede estar incompleto.\n{{< /notice >}}",
            'de' => "{{< notice info >}}\n**Übersetzung in Arbeit** - Dieser Artikel wird gerade übersetzt. Der Inhalt kann unvollständig sein.\n{{< /notice >}}",
            'it' => "{{< notice info >}}\n**Traduzione in corso** - Questo articolo è in fase di traduzione. Il contenuto potrebbe essere incompleto.\n{{< /notice >}}",
            'nl' => "{{< notice info >}}\n**Vertaling in uitvoering** - Dit artikel wordt vertaald. De inhoud kan onvolledig zijn.\n{{< /notice >}}",
            default => "",
        };
    }
}

