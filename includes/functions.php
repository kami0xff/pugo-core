<?php
/**
 * Hugo Admin - Core Functions
 */

/**
 * Get the content directory for a specific language
 * 
 * This uses the content_dir from config, NOT hardcoded assumptions about 'en'.
 * Supports projects where English might be in content.en instead of content/
 * 
 * @param string $lang Language code
 * @return string Full path to content directory
 */
function get_content_dir_for_lang($lang = 'en') {
    global $config;
    
    // Get content_dir from config, with smart fallbacks
    $content_dir = $config['languages'][$lang]['content_dir'] ?? null;
    
    if ($content_dir) {
        // If it's already an absolute path, use it directly
        if (str_starts_with($content_dir, '/')) {
            return $content_dir;
        }
        // Otherwise, prepend HUGO_ROOT
        return HUGO_ROOT . '/' . $content_dir;
    }
    
    // Fallback: use CONTENT_DIR for default language, content.{lang} for others
    $default_lang = $config['default_language'] ?? 'en';
    if ($lang === $default_lang) {
        return CONTENT_DIR;
    }
    
    return HUGO_ROOT . '/content.' . $lang;
}

/**
 * Parse YAML frontmatter from markdown content
 */
function parse_frontmatter($content) {
    $pattern = '/^---\s*\n(.*?)\n---\s*\n(.*)$/s';
    if (preg_match($pattern, $content, $matches)) {
        $yaml = $matches[1];
        $body = $matches[2];
        
        // Simple YAML parser for frontmatter
        $frontmatter = parse_simple_yaml($yaml);
        
        return [
            'frontmatter' => $frontmatter,
            'body' => $body
        ];
    }
    return [
        'frontmatter' => [],
        'body' => $content
    ];
}

/**
 * Simple YAML parser (handles our frontmatter structure)
 */
function parse_simple_yaml($yaml) {
    $result = [];
    $lines = explode("\n", $yaml);
    $current_key = null;
    $current_array = null;
    
    foreach ($lines as $line) {
        // Skip empty lines
        if (trim($line) === '') continue;
        
        // Array item
        if (preg_match('/^(\s*)- (.*)$/', $line, $m)) {
            $indent = strlen($m[1]);
            $value = trim($m[2], '"\'');
            if ($current_key && $indent > 0) {
                if (!isset($result[$current_key]) || !is_array($result[$current_key])) {
                    $result[$current_key] = [];
                }
                $result[$current_key][] = $value;
            }
            continue;
        }
        
        // Key: value pair
        if (preg_match('/^([a-zA-Z_]+):\s*(.*)$/', $line, $m)) {
            $key = $m[1];
            $value = trim($m[2], '"\'');
            
            if ($value === '') {
                // Start of array
                $current_key = $key;
                $result[$key] = [];
            } else {
                // Boolean handling
                if ($value === 'true') $value = true;
                elseif ($value === 'false') $value = false;
                
                $result[$key] = $value;
                $current_key = null;
            }
        }
    }
    
    return $result;
}

/**
 * Convert frontmatter array to YAML string
 */
function frontmatter_to_yaml($data) {
    $yaml = "---\n";
    
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $yaml .= "$key:\n";
            foreach ($value as $item) {
                $yaml .= "  - \"" . addslashes($item) . "\"\n";
            }
        } elseif (is_bool($value)) {
            $yaml .= "$key: " . ($value ? 'true' : 'false') . "\n";
        } elseif (strpos($value, "\n") !== false || strpos($value, ':') !== false) {
            $yaml .= "$key: \"" . addslashes($value) . "\"\n";
        } else {
            $yaml .= "$key: \"$value\"\n";
        }
    }
    
    $yaml .= "---\n\n";
    return $yaml;
}

/**
 * Get all articles from a content directory
 */
function get_articles($lang = 'en', $section = null) {
    global $config;
    
    $content_dir = get_content_dir_for_lang($lang);
    
    if (!is_dir($content_dir)) {
        return [];
    }
    
    $articles = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($content_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            $path = $file->getPathname();
            $relative_path = str_replace($content_dir . '/', '', $path);
            
            // Skip _index.md files for listing (they're section pages)
            if (basename($path) === '_index.md') continue;
            
            // Filter by section if specified
            if ($section && strpos($relative_path, $section . '/') !== 0) continue;
            
            $content = file_get_contents($path);
            $parsed = parse_frontmatter($content);
            
            $articles[] = [
                'path' => $path,
                'relative_path' => $relative_path,
                'section' => explode('/', $relative_path)[0],
                'category' => count(explode('/', $relative_path)) > 2 ? explode('/', $relative_path)[1] : null,
                'filename' => basename($path, '.md'),
                'frontmatter' => $parsed['frontmatter'],
                'modified' => $file->getMTime(),
            ];
        }
    }
    
    // Sort by modified date descending
    usort($articles, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
    
    return $articles;
}

/**
 * Discover sections from content directory or config
 * Provides a fallback if not defined elsewhere
 */
if (!function_exists('discover_sections')) {
    function discover_sections() {
        global $config;
        
        // First check if sections are defined in config (from pugo.yaml)
        if (!empty($config['sections'])) {
            $sections = [];
            foreach ($config['sections'] as $key => $section) {
                $sections[$key] = [
                    'name' => $section['name'] ?? ucfirst($key),
                    'color' => $section['color'] ?? '#6b7280',
                    'icon' => $section['icon'] ?? 'file-text',
                    'path' => CONTENT_DIR . '/' . $key,
                ];
            }
            return $sections;
        }
        
        // Fallback: discover from filesystem
        $sections = [];
        $section_colors = [
            'blog' => '#e11d48', 'pages' => '#3b82f6', 'tutorials' => '#10b981',
            'reviews' => '#f59e0b', 'docs' => '#0ea5e9', 'news' => '#8b5cf6',
            'interviews' => '#ec4899',
        ];
        
        if (defined('CONTENT_DIR') && is_dir(CONTENT_DIR)) {
            foreach (scandir(CONTENT_DIR) as $item) {
                if ($item[0] === '.') continue;
                $path = CONTENT_DIR . '/' . $item;
                if (is_dir($path)) {
                    $name = ucfirst(str_replace('-', ' ', $item));
                    $sections[$item] = [
                        'name' => $name,
                        'color' => $section_colors[$item] ?? '#6b7280',
                        'path' => $path
                    ];
                }
            }
        }
        return $sections;
    }
}

/**
 * Get content sections with article counts (uses dynamic sections)
 */
function get_sections_with_counts($lang = 'en') {
    global $config;
    
    $content_dir = get_content_dir_for_lang($lang);
    $discovered_sections = discover_sections();
    $sections = [];
    
    foreach ($discovered_sections as $key => $section) {
        $section_path = $content_dir . '/' . $key;
        $count = 0;
        
        if (is_dir($section_path)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($section_path, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'md' && basename($file) !== '_index.md') {
                    $count++;
                }
            }
        }
        
        $sections[$key] = array_merge($section, [
            'count' => $count,
            'exists' => is_dir($section_path)
        ]);
    }
    
    return $sections;
}

/**
 * Get all articles for selection (used in related articles picker)
 */
function get_all_articles_for_selection($lang = 'en') {
    global $config;
    
    $content_dir = get_content_dir_for_lang($lang);
    
    if (!is_dir($content_dir)) return [];
    
    $articles = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($content_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            $path = $file->getPathname();
            $relative_path = str_replace($content_dir . '/', '', $path);
            
            // Skip _index.md files
            if (basename($path) === '_index.md') continue;
            
            $content = file_get_contents($path);
            $parsed = parse_frontmatter($content);
            
            $slug = '/' . str_replace('.md', '', $relative_path);
            $title = $parsed['frontmatter']['title'] ?? basename($path, '.md');
            $section = explode('/', $relative_path)[0];
            
            $articles[] = [
                'slug' => $slug,
                'title' => $title,
                'section' => $section,
                'relative_path' => $relative_path,
                'display' => "[$section] $title"
            ];
        }
    }
    
    // Sort by section then title
    usort($articles, function($a, $b) {
        $cmp = strcmp($a['section'], $b['section']);
        return $cmp !== 0 ? $cmp : strcmp($a['title'], $b['title']);
    });
    
    return $articles;
}

/**
 * Get all unique tags with counts
 */
function get_all_tags($lang = 'en') {
    global $config;
    
    $content_dir = get_content_dir_for_lang($lang);
    $tags = [];
    
    if (!is_dir($content_dir)) return $tags;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($content_dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            $content = file_get_contents($file->getPathname());
            $parsed = parse_frontmatter($content);
            
            if (!empty($parsed['frontmatter']['tags']) && is_array($parsed['frontmatter']['tags'])) {
                foreach ($parsed['frontmatter']['tags'] as $tag) {
                    $tag = trim($tag);
                    if (!isset($tags[$tag])) {
                        $tags[$tag] = ['count' => 0, 'articles' => []];
                    }
                    $tags[$tag]['count']++;
                    $tags[$tag]['articles'][] = [
                        'title' => $parsed['frontmatter']['title'] ?? basename($file->getPathname(), '.md'),
                        'path' => $file->getPathname()
                    ];
                }
            }
        }
    }
    
    ksort($tags);
    return $tags;
}

/**
 * Get all unique keywords with counts
 */
function get_all_keywords($lang = 'en') {
    global $config;
    
    $content_dir = get_content_dir_for_lang($lang);
    $keywords = [];
    
    if (!is_dir($content_dir)) return $keywords;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($content_dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            $content = file_get_contents($file->getPathname());
            $parsed = parse_frontmatter($content);
            
            if (!empty($parsed['frontmatter']['keywords']) && is_array($parsed['frontmatter']['keywords'])) {
                foreach ($parsed['frontmatter']['keywords'] as $keyword) {
                    $keyword = trim($keyword);
                    if (!isset($keywords[$keyword])) {
                        $keywords[$keyword] = ['count' => 0, 'articles' => []];
                    }
                    $keywords[$keyword]['count']++;
                    $keywords[$keyword]['articles'][] = [
                        'title' => $parsed['frontmatter']['title'] ?? basename($file->getPathname(), '.md'),
                        'path' => $file->getPathname()
                    ];
                }
            }
        }
    }
    
    ksort($keywords);
    return $keywords;
}

/**
 * Get section parity across languages (for scanner)
 */
function get_section_language_parity() {
    global $config;
    
    $sections_by_lang = [];
    
    foreach ($config['languages'] as $lang => $lang_config) {
        $content_dir = get_content_dir_for_lang($lang);
        $sections_by_lang[$lang] = [];
        
        if (is_dir($content_dir)) {
            foreach (scandir($content_dir) as $item) {
                if ($item[0] === '.') continue;
                if (is_dir($content_dir . '/' . $item)) {
                    $sections_by_lang[$lang][] = $item;
                }
            }
        }
    }
    
    // Find sections that exist in default language but not in others
    $issues = [];
    $default_sections = $sections_by_lang[$config['default_language']] ?? [];
    
    foreach ($config['languages'] as $lang => $lang_config) {
        if ($lang === $config['default_language']) continue;
        
        foreach ($default_sections as $section) {
            if (!in_array($section, $sections_by_lang[$lang])) {
                $issues[] = [
                    'type' => 'warning',
                    'message' => "Section '$section' exists in {$config['default_language']} but not in $lang",
                    'language' => $lang,
                    'section' => $section
                ];
            }
        }
        
        // Also check for sections in other languages that don't exist in default
        foreach ($sections_by_lang[$lang] as $section) {
            if (!in_array($section, $default_sections)) {
                $issues[] = [
                    'type' => 'info',
                    'message' => "Section '$section' exists in $lang but not in {$config['default_language']}",
                    'language' => $lang,
                    'section' => $section
                ];
            }
        }
    }
    
    return $issues;
}

/**
 * Get article counts by section and category
 */
function get_article_taxonomy($lang = 'en') {
    global $config;
    
    $content_dir = get_content_dir_for_lang($lang);
    
    $taxonomy = [];
    
    if (!is_dir($content_dir)) return $taxonomy;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($content_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'md' && basename($file->getPathname()) !== '_index.md') {
            $path = $file->getPathname();
            $relative_path = str_replace($content_dir . '/', '', $path);
            $parts = explode('/', $relative_path);
            
            $section = $parts[0] ?? 'root';
            $category = count($parts) > 2 ? $parts[1] : null;
            
            $content = file_get_contents($path);
            $parsed = parse_frontmatter($content);
            
            if (!isset($taxonomy[$section])) {
                $taxonomy[$section] = [
                    'count' => 0,
                    'categories' => [],
                    'articles' => []
                ];
            }
            
            $taxonomy[$section]['count']++;
            
            $article_data = [
                'path' => $path,
                'relative_path' => $relative_path,
                'title' => $parsed['frontmatter']['title'] ?? basename($path, '.md'),
                'tags' => $parsed['frontmatter']['tags'] ?? [],
                'keywords' => $parsed['frontmatter']['keywords'] ?? [],
                'draft' => !empty($parsed['frontmatter']['draft']),
                'date' => $parsed['frontmatter']['date'] ?? null,
            ];
            
            if ($category) {
                if (!isset($taxonomy[$section]['categories'][$category])) {
                    $taxonomy[$section]['categories'][$category] = [
                        'count' => 0,
                        'articles' => []
                    ];
                }
                $taxonomy[$section]['categories'][$category]['count']++;
                $taxonomy[$section]['categories'][$category]['articles'][] = $article_data;
            } else {
                $taxonomy[$section]['articles'][] = $article_data;
            }
        }
    }
    
    ksort($taxonomy);
    return $taxonomy;
}

/**
 * Get categories within a section
 */
function get_categories($section, $lang = 'en') {
    global $config;
    
    $content_dir = get_content_dir_for_lang($lang);
    $section_path = $content_dir . '/' . $section;
    
    if (!is_dir($section_path)) return [];
    
    $categories = [];
    foreach (scandir($section_path) as $item) {
        if ($item[0] === '.') continue;
        $path = $section_path . '/' . $item;
        if (is_dir($path)) {
            // Count articles in category
            $count = 0;
            foreach (scandir($path) as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'md' && $file !== '_index.md') {
                    $count++;
                }
            }
            $categories[$item] = [
                'name' => ucwords(str_replace('-', ' ', $item)),
                'slug' => $item,
                'count' => $count
            ];
        }
    }
    
    return $categories;
}

/**
 * Save an article
 */
function save_article($path, $frontmatter, $body) {
    $content = frontmatter_to_yaml($frontmatter) . $body;
    
    // Ensure directory exists
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    return file_put_contents($path, $content) !== false;
}

/**
 * Get translation status for an article
 */
function get_translation_status($translation_key, $source_lang = 'en') {
    global $config;
    
    if (!$translation_key) return [];
    
    $status = [];
    
    foreach ($config['languages'] as $lang => $lang_config) {
        $content_dir = get_content_dir_for_lang($lang);
        
        // Search for file with this translation key
        $found = false;
        if (is_dir($content_dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($content_dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'md') {
                    $content = file_get_contents($file->getPathname());
                    $parsed = parse_frontmatter($content);
                    if (isset($parsed['frontmatter']['translationKey']) && 
                        $parsed['frontmatter']['translationKey'] === $translation_key) {
                        $found = true;
                        $status[$lang] = [
                            'exists' => true,
                            'path' => $file->getPathname(),
                            'lastmod' => $parsed['frontmatter']['lastmod'] ?? null
                        ];
                        break;
                    }
                }
            }
        }
        
        if (!$found) {
            $status[$lang] = ['exists' => false];
        }
    }
    
    return $status;
}

/**
 * Fix Hugo directory permissions (no-op in dev, may be needed in prod)
 */
function fix_hugo_permissions() {
    // In dev containers running as root, this is not needed
    // In production, configure proper ownership during deployment
}

/**
 * Build Hugo site
 */
function build_hugo() {
    global $config;
    
    $output = [];
    $return_code = 0;
    
    // Fix permissions before building (handles new folders created from host)
    fix_hugo_permissions();
    
    // Get hugo command from config, with fallback
    $hugo_command = $config['hugo_command'] ?? ('cd ' . HUGO_ROOT . ' && hugo --minify');
    
    // Build Hugo site
    exec($hugo_command . ' 2>&1', $output, $return_code);
    
    if ($return_code === 0) {
        // Run Pagefind to rebuild search index (optional, don't fail if not available)
        $pagefind_output = [];
        exec('cd ' . HUGO_ROOT . ' && pagefind --site public 2>&1', $pagefind_output, $pagefind_code);
        
        if ($pagefind_code === 0) {
            $output = array_merge($output, ['', '--- Pagefind ---'], $pagefind_output);
        }
        // Don't report pagefind errors - it's optional
    }
    
    return [
        'success' => $return_code === 0,
        'output' => implode("\n", $output)
    ];
}

/**
 * Get git status (changed files)
 */
function git_status() {
    $output = [];
    $return_code = 0;
    
    exec('cd ' . HUGO_ROOT . ' && git status --porcelain 2>&1', $output, $return_code);
    
    $changes = [];
    foreach ($output as $line) {
        if (trim($line)) {
            $status = substr($line, 0, 2);
            $file = trim(substr($line, 3));
            $changes[] = [
                'status' => trim($status),
                'file' => $file
            ];
        }
    }
    
    return [
        'success' => $return_code === 0,
        'changes' => $changes,
        'has_changes' => count($changes) > 0
    ];
}

/**
 * Commit and push changes to trigger CI/CD
 */
function git_publish($message = 'Content update from admin') {
    global $config;
    
    $output = [];
    $all_output = [];
    
    // Get git config from config.php
    $git_email = $config['git']['user_email'] ?? 'admin@xlovecam.com';
    $git_name = $config['git']['user_name'] ?? 'Hugo Admin';
    
    // Configure git user if not set
    exec('cd ' . HUGO_ROOT . ' && git config user.email "' . $git_email . '" 2>&1', $output);
    exec('cd ' . HUGO_ROOT . ' && git config user.name "' . $git_name . '" 2>&1', $output);
    
    // Add all changes
    exec('cd ' . HUGO_ROOT . ' && git add -A 2>&1', $output, $return_code);
    $all_output = array_merge($all_output, ['--- Git Add ---'], $output);
    
    if ($return_code !== 0) {
        return [
            'success' => false,
            'output' => implode("\n", $all_output),
            'error' => 'Failed to stage changes'
        ];
    }
    
    // Commit
    $output = [];
    $commit_message = escapeshellarg($message . ' [' . date('Y-m-d H:i:s') . ']');
    exec('cd ' . HUGO_ROOT . ' && git commit -m ' . $commit_message . ' 2>&1', $output, $return_code);
    $all_output = array_merge($all_output, ['', '--- Git Commit ---'], $output);
    
    // If nothing to commit, that's ok
    if ($return_code !== 0 && strpos(implode("\n", $output), 'nothing to commit') === false) {
        return [
            'success' => false,
            'output' => implode("\n", $all_output),
            'error' => 'Failed to commit changes'
        ];
    }
    
    // Check if there was nothing to commit
    $nothing_to_commit = strpos(implode("\n", $output), 'nothing to commit') !== false;
    
    // Push
    $output = [];
    exec('cd ' . HUGO_ROOT . ' && git push 2>&1', $output, $return_code);
    $all_output = array_merge($all_output, ['', '--- Git Push ---'], $output);
    
    if ($return_code !== 0) {
        // Check for common SSH issues
        $error_msg = implode("\n", $output);
        if (strpos($error_msg, 'Permission denied') !== false) {
            return [
                'success' => false,
                'output' => implode("\n", $all_output),
                'error' => 'SSH permission denied. Ensure the container has SSH key access to the repository.'
            ];
        }
        if (strpos($error_msg, 'Could not resolve hostname') !== false) {
            return [
                'success' => false,
                'output' => implode("\n", $all_output),
                'error' => 'Cannot reach Git server. Check network connectivity.'
            ];
        }
        return [
            'success' => false,
            'output' => implode("\n", $all_output),
            'error' => 'Failed to push changes. Check git remote configuration.'
        ];
    }
    
    $message = $nothing_to_commit 
        ? 'No changes to publish.' 
        : 'Changes published! CI/CD pipeline triggered.';
    
    return [
        'success' => true,
        'output' => implode("\n", $all_output),
        'message' => $message,
        'nothing_to_commit' => $nothing_to_commit
    ];
}

/**
 * Trigger GitLab CI/CD pipeline directly via API (alternative to git push)
 * Useful when you want to rebuild without committing
 */
function trigger_pipeline() {
    global $config;
    
    // Check if pipeline trigger is configured
    if (empty($config['gitlab']['trigger_token']) || empty($config['gitlab']['project_id'])) {
        return [
            'success' => false,
            'error' => 'GitLab pipeline trigger not configured. Set gitlab.trigger_token and gitlab.project_id in config.php'
        ];
    }
    
    $gitlab_url = $config['gitlab']['url'] ?? 'https://gitlab.com';
    $project_id = $config['gitlab']['project_id'];
    $trigger_token = $config['gitlab']['trigger_token'];
    $ref = $config['gitlab']['ref'] ?? 'main';
    
    $url = "{$gitlab_url}/api/v4/projects/{$project_id}/trigger/pipeline";
    
    $data = [
        'token' => $trigger_token,
        'ref' => $ref
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => 'cURL error: ' . $error
        ];
    }
    
    $result = json_decode($response, true);
    
    if ($http_code === 201) {
        return [
            'success' => true,
            'message' => 'Pipeline triggered successfully!',
            'pipeline_id' => $result['id'] ?? null,
            'web_url' => $result['web_url'] ?? null
        ];
    }
    
    return [
        'success' => false,
        'error' => $result['message'] ?? 'Failed to trigger pipeline',
        'http_code' => $http_code
    ];
}

/**
 * Generate a slug from a title
 */
function generate_slug($title) {
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

/**
 * Get media files from a directory
 */
function get_media_files($subdirectory = '') {
    $path = IMAGES_DIR . ($subdirectory ? '/' . $subdirectory : '');
    
    if (!is_dir($path)) return ['files' => [], 'directories' => []];
    
    $files = [];
    $directories = [];
    
    foreach (scandir($path) as $item) {
        if ($item[0] === '.') continue;
        
        $full_path = $path . '/' . $item;
        
        if (is_dir($full_path)) {
            $directories[] = [
                'name' => $item,
                'path' => $subdirectory ? $subdirectory . '/' . $item : $item
            ];
        } else {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'mp4', 'webm'])) {
                $files[] = [
                    'name' => $item,
                    'path' => '/images/' . ($subdirectory ? $subdirectory . '/' : '') . $item,
                    'full_path' => $full_path,
                    'type' => in_array($ext, ['mp4', 'webm']) ? 'video' : 'image',
                    'size' => filesize($full_path),
                    'modified' => filemtime($full_path)
                ];
            }
        }
    }
    
    return ['files' => $files, 'directories' => $directories];
}

/**
 * Format file size
 */
function format_size($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Time ago formatting
 */
function time_ago($timestamp) {
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    
    return date('M j, Y', $timestamp);
}

/**
 * Rename a tag across all articles
 */
function rename_tag($old_tag, $new_tag, $lang = 'en') {
    global $config;
    
    $content_dir = get_content_dir_for_lang($lang);
    $count = 0;
    
    if (!is_dir($content_dir)) {
        return ['success' => false, 'error' => 'Content directory not found', 'count' => 0];
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($content_dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            $path = $file->getPathname();
            $content = file_get_contents($path);
            $parsed = parse_frontmatter($content);
            
            if (!empty($parsed['frontmatter']['tags']) && is_array($parsed['frontmatter']['tags'])) {
                $tags = $parsed['frontmatter']['tags'];
                $found = false;
                
                foreach ($tags as $i => $tag) {
                    if (strtolower(trim($tag)) === strtolower($old_tag)) {
                        $tags[$i] = $new_tag;
                        $found = true;
                    }
                }
                
                if ($found) {
                    // Remove duplicates (in case new_tag already existed)
                    $tags = array_unique($tags);
                    $parsed['frontmatter']['tags'] = array_values($tags);
                    
                    // Rebuild content
                    $new_content = "---\n" . frontmatter_to_yaml($parsed['frontmatter']) . "---\n" . $parsed['body'];
                    file_put_contents($path, $new_content);
                    $count++;
                }
            }
        }
    }
    
    return ['success' => true, 'count' => $count];
}

/**
 * Merge one tag into another (removes source tag, adds target tag if not present)
 */
function merge_tags($source_tag, $target_tag, $lang = 'en') {
    global $config;
    
    $content_dir = get_content_dir_for_lang($lang);
    $count = 0;
    
    if (!is_dir($content_dir)) {
        return ['success' => false, 'error' => 'Content directory not found', 'count' => 0];
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($content_dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            $path = $file->getPathname();
            $content = file_get_contents($path);
            $parsed = parse_frontmatter($content);
            
            if (!empty($parsed['frontmatter']['tags']) && is_array($parsed['frontmatter']['tags'])) {
                $tags = $parsed['frontmatter']['tags'];
                $has_source = false;
                $has_target = false;
                
                foreach ($tags as $tag) {
                    if (strtolower(trim($tag)) === strtolower($source_tag)) {
                        $has_source = true;
                    }
                    if (strtolower(trim($tag)) === strtolower($target_tag)) {
                        $has_target = true;
                    }
                }
                
                if ($has_source) {
                    // Remove source tag
                    $tags = array_filter($tags, fn($t) => strtolower(trim($t)) !== strtolower($source_tag));
                    
                    // Add target tag if not already present
                    if (!$has_target) {
                        $tags[] = $target_tag;
                    }
                    
                    $parsed['frontmatter']['tags'] = array_values($tags);
                    
                    // Rebuild content
                    $new_content = "---\n" . frontmatter_to_yaml($parsed['frontmatter']) . "---\n" . $parsed['body'];
                    file_put_contents($path, $new_content);
                    $count++;
                }
            }
        }
    }
    
    return ['success' => true, 'count' => $count];
}

/**
 * Delete a tag from all articles
 */
function delete_tag($tag, $lang = 'en') {
    global $config;
    
    $content_dir = get_content_dir_for_lang($lang);
    $count = 0;
    
    if (!is_dir($content_dir)) {
        return ['success' => false, 'error' => 'Content directory not found', 'count' => 0];
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($content_dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            $path = $file->getPathname();
            $content = file_get_contents($path);
            $parsed = parse_frontmatter($content);
            
            if (!empty($parsed['frontmatter']['tags']) && is_array($parsed['frontmatter']['tags'])) {
                $tags = $parsed['frontmatter']['tags'];
                $original_count = count($tags);
                
                // Remove the tag
                $tags = array_filter($tags, fn($t) => strtolower(trim($t)) !== strtolower($tag));
                
                if (count($tags) < $original_count) {
                    $parsed['frontmatter']['tags'] = array_values($tags);
                    
                    // Remove tags key if empty
                    if (empty($parsed['frontmatter']['tags'])) {
                        unset($parsed['frontmatter']['tags']);
                    }
                    
                    // Rebuild content
                    $new_content = "---\n" . frontmatter_to_yaml($parsed['frontmatter']) . "---\n" . $parsed['body'];
                    file_put_contents($path, $new_content);
                    $count++;
                }
            }
        }
    }
    
    return ['success' => true, 'count' => $count];
}

/**
 * Get all tags as a simple list (for autocomplete)
 */
function get_tag_list($lang = 'en') {
    $tags = get_all_tags($lang);
    return array_keys($tags);
}

/**
 * Detect which Hugo template will be used for content
 * 
 * Hugo template lookup order:
 * 1. layouts/<type>/<layout>.html
 * 2. layouts/<section>/<layout>.html  
 * 3. layouts/_default/<layout>.html
 * 
 * @param string $section The content section (e.g., 'blog', 'tutorials')
 * @param array $frontmatter The frontmatter array
 * @param bool $is_index Whether this is an _index.md file
 * @return array Template info with 'path', 'type', 'exists'
 */
function detect_hugo_template($section, $frontmatter = [], $is_index = false) {
    $layout = $frontmatter['layout'] ?? null;
    $type = $frontmatter['type'] ?? $section;
    $kind = $is_index ? 'list' : 'single';
    
    $layouts_dir = HUGO_ROOT . '/layouts';
    $templates_to_check = [];
    
    // If explicit layout is set in frontmatter
    if ($layout) {
        $templates_to_check[] = "{$type}/{$layout}.html";
        $templates_to_check[] = "{$section}/{$layout}.html";
        $templates_to_check[] = "_default/{$layout}.html";
    }
    
    // Standard lookup order
    $templates_to_check[] = "{$type}/{$kind}.html";
    $templates_to_check[] = "{$section}/{$kind}.html";
    $templates_to_check[] = "_default/{$kind}.html";
    
    // Also check for baseof
    $templates_to_check[] = "_default/baseof.html";
    
    foreach ($templates_to_check as $template) {
        $full_path = $layouts_dir . '/' . $template;
        if (file_exists($full_path)) {
            return [
                'path' => $template,
                'full_path' => $full_path,
                'type' => $kind,
                'exists' => true,
                'explicit' => !empty($layout)
            ];
        }
    }
    
    // No template found - Hugo will use theme or built-in
    return [
        'path' => "_default/{$kind}.html",
        'full_path' => null,
        'type' => $kind,
        'exists' => false,
        'explicit' => false
    ];
}

/**
 * Get all content files that use a specific template
 * 
 * @param string $template_path Relative path like '_default/single.html'
 * @param string $lang Language code
 * @return array List of content files
 */
function get_content_using_template($template_path, $lang = 'en') {
    global $config;
    
    $content_dir = get_content_dir_for_lang($lang);
    if (!is_dir($content_dir)) return [];
    
    // Parse template path to understand what it handles
    $parts = explode('/', $template_path);
    $filename = basename($template_path, '.html');
    $folder = count($parts) > 1 ? $parts[0] : '_default';
    
    $is_list = ($filename === 'list' || strpos($filename, 'list') !== false);
    $is_single = ($filename === 'single' || strpos($filename, 'single') !== false);
    $is_default = ($folder === '_default');
    
    $matching_content = [];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($content_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'md') continue;
        
        $path = $file->getPathname();
        $relative_path = str_replace($content_dir . '/', '', $path);
        $is_index = (basename($path) === '_index.md');
        
        // Get section from path
        $path_parts = explode('/', $relative_path);
        $section = $path_parts[0] ?? '';
        
        // Parse frontmatter
        $content = file_get_contents($path);
        $parsed = parse_frontmatter($content);
        $fm = $parsed['frontmatter'];
        
        // Check if this content would use this template
        $detected = detect_hugo_template($section, $fm, $is_index);
        
        if ($detected['path'] === $template_path) {
            $matching_content[] = [
                'path' => $relative_path,
                'title' => $fm['title'] ?? basename($path, '.md'),
                'section' => $section,
                'is_index' => $is_index
            ];
        }
    }
    
    return $matching_content;
}

