<?php
/**
 * Hugo Admin - Help & Documentation
 * Opinionated guidelines for XloveCam Help Center
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

$page_title = 'Help & Documentation';

require __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Help & Documentation</h1>
        <p class="page-subtitle">
            Opinionated guidelines for the XloveCam Help Center
        </p>
    </div>
    <a href="scanner.php" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
            <circle cx="11" cy="11" r="8"/>
            <path d="M21 21l-4.35-4.35"/>
        </svg>
        Run Scanner
    </a>
</div>

<!-- Quick Navigation -->
<div class="card" style="margin-bottom: 24px; padding: 16px;">
    <div style="display: flex; gap: 16px; flex-wrap: wrap;">
        <a href="#structure" style="color: var(--accent-primary); font-size: 13px;">📁 Project Structure</a>
        <a href="#naming" style="color: var(--accent-primary); font-size: 13px;">📝 Naming Rules</a>
        <a href="#frontmatter" style="color: var(--accent-primary); font-size: 13px;">⚙️ Frontmatter</a>
        <a href="#images" style="color: var(--accent-primary); font-size: 13px;">🖼️ Images</a>
        <a href="#translations" style="color: var(--accent-primary); font-size: 13px;">🌍 Translations</a>
        <a href="#procedures" style="color: var(--accent-primary); font-size: 13px;">📋 Procedures</a>
    </div>
</div>

<!-- Project Structure -->
<div class="card" id="structure" style="margin-bottom: 24px;">
    <h2 class="card-title" style="margin-bottom: 20px;">
        📁 Project Structure
    </h2>
    
    <div class="grid grid-2" style="gap: 24px;">
        <div>
            <h4 style="margin-bottom: 12px; color: var(--accent-primary);">Content Structure</h4>
            <pre style="background: var(--bg-tertiary); padding: 16px; border-radius: 8px; font-size: 11px; font-family: 'JetBrains Mono', monospace; line-height: 1.8; overflow-x: auto;">
content/                         # English (default)
├── users/                       # Section
│   ├── _index.md               # Section page (required)
│   ├── getting-started/        # Category
│   │   ├── _index.md           # Category page (required)
│   │   ├── create-account.md   # Article
│   │   └── what-is-xlovecam.md # Article
│   └── tokens-payments/
│       ├── _index.md
│       └── purchase-tokens.md
├── models/
│   └── ...
├── studios/
│   └── ...
├── safety/
├── tutorials/
└── troubleshooting/

content.fr/                      # French translations
content.es/                      # Spanish translations
content.de/                      # German translations
content.it/                      # Italian translations
content.nl/                      # Dutch translations</pre>
        </div>
        
        <div>
            <h4 style="margin-bottom: 12px; color: var(--accent-green);">Image Structure (Mirrors Content!)</h4>
            <pre style="background: var(--bg-tertiary); padding: 16px; border-radius: 8px; font-size: 11px; font-family: 'JetBrains Mono', monospace; line-height: 1.8; overflow-x: auto;">
static/images/
├── articles/                    # Article images
│   ├── users/                   # Mirrors content/users/
│   │   ├── getting-started/
│   │   │   ├── create-account/  # Folder per article
│   │   │   │   ├── step-1.png
│   │   │   │   ├── step-2.png
│   │   │   │   └── hero.jpg
│   │   │   └── what-is-xlovecam/
│   │   │       └── feature.png
│   │   └── tokens-payments/
│   │       └── purchase-tokens/
│   ├── models/
│   └── studios/
├── shared/                      # Shared assets
│   ├── logos/
│   ├── icons/
│   └── ui/
├── og/                          # OpenGraph images
└── thumbnails/                  # Thumbnail images</pre>
            
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; padding: 12px; border-radius: 8px; margin-top: 16px;">
                <strong style="color: #10b981; font-size: 12px;">✓ KEY RULE:</strong>
                <span style="font-size: 12px;"> Images for an article go in a folder matching the article path.</span>
                <br><br>
                <span style="font-size: 11px; color: var(--text-secondary);">
                    Article: <code>content/users/getting-started/create-account.md</code><br>
                    Images: <code>static/images/articles/users/getting-started/create-account/</code>
                </span>
            </div>
        </div>
    </div>
    
    <div style="margin-top: 24px;">
        <h4 style="margin-bottom: 12px;">Valid Sections</h4>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <?php 
            $valid_sections = ['users', 'models', 'studios', 'safety', 'tutorials', 'troubleshooting'];
            $section_info = [
                'users' => ['color' => '#3b82f6', 'desc' => 'User-facing articles'],
                'models' => ['color' => '#ec4899', 'desc' => 'Model-facing articles'],
                'studios' => ['color' => '#8b5cf6', 'desc' => 'Studio-facing articles'],
                'safety' => ['color' => '#10b981', 'desc' => 'Safety & security'],
                'tutorials' => ['color' => '#f59e0b', 'desc' => 'Video tutorials'],
                'troubleshooting' => ['color' => '#ef4444', 'desc' => 'Common issues'],
            ];
            foreach ($valid_sections as $section):
            ?>
            <div style="background: <?= $section_info[$section]['color'] ?>15; border: 1px solid <?= $section_info[$section]['color'] ?>40; padding: 8px 16px; border-radius: 8px;">
                <strong style="color: <?= $section_info[$section]['color'] ?>;"><?= $section ?>/</strong>
                <span style="font-size: 11px; color: var(--text-secondary); display: block;"><?= $section_info[$section]['desc'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Naming Rules -->
<div class="card" id="naming" style="margin-bottom: 24px;">
    <h2 class="card-title" style="margin-bottom: 20px;">
        📝 Naming Rules (Strict!)
    </h2>
    
    <div class="grid grid-3" style="gap: 24px;">
        <div style="background: var(--bg-tertiary); padding: 20px; border-radius: 8px;">
            <h4 style="color: var(--accent-primary); margin-bottom: 12px;">Article Files</h4>
            <div style="font-size: 13px; line-height: 1.8;">
                <div style="color: #10b981;">✓ <code>create-account.md</code></div>
                <div style="color: #10b981;">✓ <code>what-is-xlovecam.md</code></div>
                <div style="color: #10b981;">✓ <code>_index.md</code></div>
                <div style="color: #e11d48; margin-top: 8px;">✗ <code>Create-Account.md</code></div>
                <div style="color: #e11d48;">✗ <code>create_account.md</code></div>
                <div style="color: #e11d48;">✗ <code>create account.md</code></div>
            </div>
        </div>
        
        <div style="background: var(--bg-tertiary); padding: 20px; border-radius: 8px;">
            <h4 style="color: var(--accent-primary); margin-bottom: 12px;">Image Files</h4>
            <div style="font-size: 13px; line-height: 1.8;">
                <div style="color: #10b981;">✓ <code>step-1-signup.png</code></div>
                <div style="color: #10b981;">✓ <code>hero-image.jpg</code></div>
                <div style="color: #10b981;">✓ <code>icon-tokens.svg</code></div>
                <div style="color: #e11d48; margin-top: 8px;">✗ <code>Step1_Signup.PNG</code></div>
                <div style="color: #e11d48;">✗ <code>Hero Image.jpg</code></div>
                <div style="color: #e11d48;">✗ <code>screenshot (1).png</code></div>
            </div>
        </div>
        
        <div style="background: var(--bg-tertiary); padding: 20px; border-radius: 8px;">
            <h4 style="color: var(--accent-primary); margin-bottom: 12px;">Folders</h4>
            <div style="font-size: 13px; line-height: 1.8;">
                <div style="color: #10b981;">✓ <code>getting-started/</code></div>
                <div style="color: #10b981;">✓ <code>tokens-payments/</code></div>
                <div style="color: #10b981;">✓ <code>private-shows/</code></div>
                <div style="color: #e11d48; margin-top: 8px;">✗ <code>Getting-Started/</code></div>
                <div style="color: #e11d48;">✗ <code>tokens_payments/</code></div>
                <div style="color: #e11d48;">✗ <code>Private Shows/</code></div>
            </div>
        </div>
    </div>
    
    <div style="background: rgba(225, 29, 72, 0.1); border: 1px solid #e11d48; padding: 16px; border-radius: 8px; margin-top: 20px;">
        <strong style="color: #e11d48;">⚠️ RULES SUMMARY:</strong>
        <ul style="margin: 8px 0 0 20px; font-size: 13px; line-height: 1.8;">
            <li><strong>All lowercase</strong> - no capital letters anywhere</li>
            <li><strong>Hyphens only</strong> - no underscores, no spaces</li>
            <li><strong>No special characters</strong> - only a-z, 0-9, and hyphens</li>
            <li><strong>Descriptive names</strong> - <code>step-1-click-signup.png</code> not <code>img1.png</code></li>
        </ul>
    </div>
</div>

<!-- Frontmatter Reference -->
<div class="card" id="frontmatter" style="margin-bottom: 24px;">
    <h2 class="card-title" style="margin-bottom: 20px;">
        ⚙️ Frontmatter Reference
    </h2>
    
    <div class="grid grid-2" style="gap: 24px;">
        <div>
            <h4 style="margin-bottom: 12px;">Complete Example</h4>
            <pre style="background: var(--bg-tertiary); padding: 16px; border-radius: 8px; font-size: 12px; font-family: 'JetBrains Mono', monospace; line-height: 1.6;">
---
title: "What is XloveCam?"
description: "Learn about XloveCam's 20-year journey in live entertainment."
author: "Joshua XL"
date: 2025-11-28
lastmod: 2025-11-28
image: "/images/articles/users/getting-started/what-is-xlovecam/hero.jpg"
keywords:
  - xlovecam
  - live streaming
  - cam site
tags:
  - getting-started
  - about
translationKey: "what-is-xlovecam"
draft: false
---

Your markdown content here...</pre>
        </div>
        
        <div>
            <h4 style="margin-bottom: 12px;">Field Requirements</h4>
            <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th style="text-align: left; padding: 10px 0;">Field</th>
                        <th style="text-align: center; padding: 10px 0;">Required</th>
                        <th style="text-align: left; padding: 10px 0;">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px 0;"><code>title</code></td>
                        <td style="text-align: center; color: #e11d48;">✓ Yes</td>
                        <td style="padding: 10px 0; color: var(--text-secondary); font-size: 12px;">The H1 of the page</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px 0;"><code>description</code></td>
                        <td style="text-align: center; color: #e11d48;">✓ Yes</td>
                        <td style="padding: 10px 0; color: var(--text-secondary); font-size: 12px;">Max 160 chars for SEO</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px 0;"><code>date</code></td>
                        <td style="text-align: center; color: #f59e0b;">Recommended</td>
                        <td style="padding: 10px 0; color: var(--text-secondary); font-size: 12px;">Format: YYYY-MM-DD</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px 0;"><code>author</code></td>
                        <td style="text-align: center; color: #f59e0b;">Recommended</td>
                        <td style="padding: 10px 0; color: var(--text-secondary); font-size: 12px;">Default: XloveCam Team</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px 0;"><code>translationKey</code></td>
                        <td style="text-align: center; color: #f59e0b;">Recommended</td>
                        <td style="padding: 10px 0; color: var(--text-secondary); font-size: 12px;">Unique, links translations</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px 0;"><code>image</code></td>
                        <td style="text-align: center; color: var(--text-muted);">Optional</td>
                        <td style="padding: 10px 0; color: var(--text-secondary); font-size: 12px;">Featured/OG image</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px 0;"><code>keywords</code></td>
                        <td style="text-align: center; color: var(--text-muted);">Optional</td>
                        <td style="padding: 10px 0; color: var(--text-secondary); font-size: 12px;">SEO keywords array</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px 0;"><code>tags</code></td>
                        <td style="text-align: center; color: var(--text-muted);">Optional</td>
                        <td style="padding: 10px 0; color: var(--text-secondary); font-size: 12px;">Categorization tags</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0;"><code>draft</code></td>
                        <td style="text-align: center; color: var(--text-muted);">Optional</td>
                        <td style="padding: 10px 0; color: var(--text-secondary); font-size: 12px;">true = hidden from site</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Images Section -->
<div class="card" id="images" style="margin-bottom: 24px;">
    <h2 class="card-title" style="margin-bottom: 20px;">
        🖼️ Image Guidelines
    </h2>
    
    <div class="grid grid-2" style="gap: 24px;">
        <div>
            <h4 style="margin-bottom: 12px; color: var(--accent-primary);">Image Organization</h4>
            <div style="background: var(--bg-tertiary); padding: 20px; border-radius: 8px; font-size: 13px; line-height: 2;">
                <p><strong>Rule:</strong> Each article's images go in a matching folder.</p>
                
                <div style="margin-top: 16px;">
                    <div style="color: var(--text-muted); font-size: 11px;">Article:</div>
                    <code style="color: var(--accent-blue);">content/users/getting-started/create-account.md</code>
                </div>
                
                <div style="margin-top: 8px;">
                    <div style="color: var(--text-muted); font-size: 11px;">Images folder:</div>
                    <code style="color: var(--accent-green);">static/images/articles/users/getting-started/create-account/</code>
                </div>
                
                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-color);">
                    <div style="color: var(--text-muted); font-size: 11px;">Reference in Markdown:</div>
                    <code style="font-size: 11px;">![Step 1](/images/articles/users/getting-started/create-account/step-1.png)</code>
                </div>
            </div>
        </div>
        
        <div>
            <h4 style="margin-bottom: 12px; color: var(--accent-primary);">Technical Requirements</h4>
            <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px 0; color: var(--text-muted);">Formats</td>
                    <td style="padding: 12px 0;"><code>.jpg</code>, <code>.png</code>, <code>.gif</code>, <code>.svg</code>, <code>.webp</code></td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px 0; color: var(--text-muted);">Max Size</td>
                    <td style="padding: 12px 0;"><strong>500 KB</strong> (compress larger images)</td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px 0; color: var(--text-muted);">Featured Image</td>
                    <td style="padding: 12px 0;">1200 × 630 px (OG image ratio)</td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px 0; color: var(--text-muted);">In-article</td>
                    <td style="padding: 12px 0;">Max 800 px wide</td>
                </tr>
                <tr>
                    <td style="padding: 12px 0; color: var(--text-muted);">Translations</td>
                    <td style="padding: 12px 0;"><strong>Share images</strong> - don't duplicate</td>
                </tr>
            </table>
            
            <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid #3b82f6; padding: 12px; border-radius: 8px; margin-top: 16px;">
                <strong style="color: #3b82f6; font-size: 12px;">💡 TIP:</strong>
                <span style="font-size: 12px;"> Use <code>/images/shared/</code> for logos, icons, and assets used across multiple articles.</span>
            </div>
        </div>
    </div>
</div>

<!-- Translations -->
<div class="card" id="translations" style="margin-bottom: 24px;">
    <h2 class="card-title" style="margin-bottom: 20px;">
        🌍 Translation Guidelines
    </h2>
    
    <div class="grid grid-2" style="gap: 24px;">
        <div>
            <h4 style="margin-bottom: 12px;">How Translations Work</h4>
            <div style="font-size: 13px; line-height: 1.8; color: var(--text-secondary);">
                <p>Translations are linked via the <code>translationKey</code> field. All language versions of an article must have the <strong>same</strong> translationKey.</p>
                
                <div style="background: var(--bg-tertiary); padding: 16px; border-radius: 8px; margin-top: 16px;">
                    <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 8px;">English (content/users/getting-started/create-account.md):</div>
                    <code style="font-size: 11px;">translationKey: "create-account"</code>
                    
                    <div style="font-size: 11px; color: var(--text-muted); margin: 16px 0 8px;">French (content.fr/users/getting-started/create-account.md):</div>
                    <code style="font-size: 11px;">translationKey: "create-account"</code>
                </div>
            </div>
        </div>
        
        <div>
            <h4 style="margin-bottom: 12px;">Supported Languages</h4>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($config['languages'] as $lang => $lang_config): ?>
                <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-tertiary); border-radius: 8px;">
                    <span style="font-size: 24px;"><?= $lang_config['flag'] ?></span>
                    <div>
                        <strong><?= $lang_config['name'] ?></strong>
                        <div style="font-size: 11px; color: var(--text-muted);">
                            <?= $lang === 'en' ? 'content/' : $lang_config['content_dir'] . '/' ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; padding: 16px; border-radius: 8px; margin-top: 20px;">
        <strong style="color: #10b981;">✓ IMPORTANT:</strong>
        <ul style="margin: 8px 0 0 20px; font-size: 13px; line-height: 1.8;">
            <li><strong>Images are shared</strong> - all languages use the same images from <code>/static/images/</code></li>
            <li><strong>Same file names</strong> - keep article filenames consistent across languages</li>
            <li><strong>Same structure</strong> - translation folders mirror the English content structure</li>
        </ul>
    </div>
</div>

<!-- Step-by-Step Procedures -->
<div class="card" id="procedures" style="margin-bottom: 24px;">
    <h2 class="card-title" style="margin-bottom: 24px;">
        📋 Step-by-Step Procedures
    </h2>
    
    <div class="grid grid-2" style="gap: 32px;">
        <!-- Adding New Article -->
        <div>
            <h3 style="font-size: 16px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid var(--accent-blue);">
                📝 Adding a New Article
            </h3>
            <ol style="font-size: 13px; color: var(--text-secondary); padding-left: 20px; line-height: 2.2;">
                <li>Go to <a href="new.php" style="color: var(--accent-primary);">New Article</a></li>
                <li>Select <strong>Section</strong> (users/models/studios)</li>
                <li>Select or create <strong>Category</strong></li>
                <li>Fill required fields: <strong>Title</strong>, <strong>Description</strong></li>
                <li>Add a <strong>translationKey</strong> (e.g., <code>create-account</code>)</li>
                <li>Write content in Markdown</li>
                <li>Create image folder:<br>
                    <code style="font-size: 11px;">static/images/articles/{section}/{category}/{article-slug}/</code></li>
                <li>Upload images to that folder</li>
                <li>Reference images in content</li>
                <li><strong>Save</strong> and <strong>Rebuild</strong> the site</li>
            </ol>
        </div>
        
        <!-- Adding Images -->
        <div>
            <h3 style="font-size: 16px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid var(--accent-green);">
                🖼️ Adding Images to an Article
            </h3>
            <ol style="font-size: 13px; color: var(--text-secondary); padding-left: 20px; line-height: 2.2;">
                <li>Identify the article path:<br>
                    <code style="font-size: 11px;">content/users/getting-started/create-account.md</code></li>
                <li>Go to <a href="media.php" style="color: var(--accent-primary);">Media Library</a></li>
                <li>Navigate to <code>articles/users/getting-started/</code></li>
                <li>Create folder: <code>create-account</code></li>
                <li>Upload your images with proper names:<br>
                    <code style="font-size: 11px;">step-1-click-signup.png</code></li>
                <li>In your article, reference the image:<br>
                    <code style="font-size: 11px;">![Step 1](/images/articles/users/getting-started/create-account/step-1-click-signup.png)</code></li>
            </ol>
        </div>
        
        <!-- Adding Translations -->
        <div>
            <h3 style="font-size: 16px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid var(--accent-purple);">
                🌍 Adding a Translation
            </h3>
            <ol style="font-size: 13px; color: var(--text-secondary); padding-left: 20px; line-height: 2.2;">
                <li>Open the English article in the editor</li>
                <li>Click a grayed-out language flag (e.g., 🇫🇷)</li>
                <li>Content is pre-filled from English</li>
                <li>Translate: <strong>Title</strong>, <strong>Description</strong>, <strong>Content</strong></li>
                <li>Keep the <strong>same translationKey</strong></li>
                <li>Keep the <strong>same image paths</strong></li>
                <li><strong>Save</strong> and <strong>Rebuild</strong></li>
            </ol>
            <div style="background: var(--bg-tertiary); padding: 12px; border-radius: 8px; margin-top: 12px; font-size: 12px;">
                <strong>Note:</strong> Images are shared across all languages. Only translate the text content.
            </div>
        </div>
        
        <!-- Adding Video Tutorials -->
        <div>
            <h3 style="font-size: 16px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid var(--accent-primary);">
                🎬 Adding Video Tutorials
            </h3>
            <ol style="font-size: 13px; color: var(--text-secondary); padding-left: 20px; line-height: 2.2;">
                <li>Upload video to YouTube</li>
                <li>Create article in <code>tutorials/</code> section</li>
                <li>Embed using Hugo shortcode:</li>
            </ol>
            <pre style="background: var(--bg-tertiary); padding: 12px; border-radius: 8px; font-size: 12px; margin-top: 8px; font-family: 'JetBrains Mono', monospace;">
{{&lt; youtube VIDEO_ID &gt;}}

<!-- Or for Vimeo -->
{{&lt; vimeo VIDEO_ID &gt;}}</pre>
            <div style="margin-top: 12px; font-size: 12px; color: var(--text-muted);">
                Update <code>data/tutorials.yaml</code> if adding to the tutorials index.
            </div>
        </div>
    </div>
</div>

<!-- Common Issues -->
<div class="card">
    <h2 class="card-title" style="margin-bottom: 20px;">
        ⚠️ Common Issues & Fixes
    </h2>
    
    <div class="grid grid-2" style="gap: 16px;">
        <div style="background: var(--bg-tertiary); padding: 16px; border-radius: 8px;">
            <h4 style="color: #e11d48; font-size: 13px; margin-bottom: 8px;">Article not showing on site</h4>
            <ul style="font-size: 12px; color: var(--text-secondary); padding-left: 16px; line-height: 1.8;">
                <li>Check <code>draft: true</code> is not set</li>
                <li>Verify you clicked "Rebuild Site"</li>
                <li>Check <code>date</code> isn't in the future</li>
            </ul>
        </div>
        
        <div style="background: var(--bg-tertiary); padding: 16px; border-radius: 8px;">
            <h4 style="color: #e11d48; font-size: 13px; margin-bottom: 8px;">Image not displaying</h4>
            <ul style="font-size: 12px; color: var(--text-secondary); padding-left: 16px; line-height: 1.8;">
                <li>Path must start with <code>/images/</code></li>
                <li>Check filename case (Linux is case-sensitive)</li>
                <li>Ensure file exists in <code>static/images/</code></li>
            </ul>
        </div>
        
        <div style="background: var(--bg-tertiary); padding: 16px; border-radius: 8px;">
            <h4 style="color: #e11d48; font-size: 13px; margin-bottom: 8px;">Translation not linked</h4>
            <ul style="font-size: 12px; color: var(--text-secondary); padding-left: 16px; line-height: 1.8;">
                <li>Both must have identical <code>translationKey</code></li>
                <li>Key is case-sensitive</li>
                <li>Run Scanner to detect mismatches</li>
            </ul>
        </div>
        
        <div style="background: var(--bg-tertiary); padding: 16px; border-radius: 8px;">
            <h4 style="color: #e11d48; font-size: 13px; margin-bottom: 8px;">Scanner shows naming errors</h4>
            <ul style="font-size: 12px; color: var(--text-secondary); padding-left: 16px; line-height: 1.8;">
                <li>Rename files to lowercase</li>
                <li>Replace spaces/underscores with hyphens</li>
                <li>Remove special characters</li>
            </ul>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
