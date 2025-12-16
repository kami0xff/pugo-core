<?php
/**
 * Pugo - Actions Bootstrap
 * 
 * Loads all action classes and provides factory functions.
 */

// Ensure HUGO_ROOT and other constants are defined
if (!defined('HUGO_ROOT')) {
    throw new \RuntimeException('HUGO_ROOT must be defined before loading Actions');
}

// Load ActionResult
require_once __DIR__ . '/ActionResult.php';

// Load Tag Actions
require_once __DIR__ . '/Tags/ListTagsAction.php';
require_once __DIR__ . '/Tags/RenameTagAction.php';
require_once __DIR__ . '/Tags/MergeTagsAction.php';
require_once __DIR__ . '/Tags/DeleteTagAction.php';

// Load Content Actions (generic - works for articles, reviews, tutorials, etc.)
require_once __DIR__ . '/Content/ListContentAction.php';
require_once __DIR__ . '/Content/GetContentAction.php';
require_once __DIR__ . '/Content/CreateContentAction.php';
require_once __DIR__ . '/Content/UpdateContentAction.php';
require_once __DIR__ . '/Content/DeleteContentAction.php';

// Load Media Actions
require_once __DIR__ . '/Media/ListMediaAction.php';
require_once __DIR__ . '/Media/UploadMediaAction.php';
require_once __DIR__ . '/Media/DeleteMediaAction.php';

// Load Build Actions
require_once __DIR__ . '/Build/BuildHugoAction.php';
require_once __DIR__ . '/Build/PublishAction.php';

use Pugo\Actions\Tags\{ListTagsAction, RenameTagAction, MergeTagsAction, DeleteTagAction};
use Pugo\Actions\Content\{ListContentAction, GetContentAction, CreateContentAction, UpdateContentAction, DeleteContentAction};
use Pugo\Actions\Media\{ListMediaAction, UploadMediaAction, DeleteMediaAction};
use Pugo\Actions\Build\{BuildHugoAction, PublishAction};

/**
 * Action Factory
 * 
 * Creates action instances with proper dependencies.
 */
class Actions
{
    private static ?string $contentDir = null;
    private static ?string $imagesDir = null;
    private static ?string $hugoRoot = null;

    /**
     * Initialize with paths
     */
    public static function init(string $contentDir, string $imagesDir, string $hugoRoot): void
    {
        self::$contentDir = $contentDir;
        self::$imagesDir = $imagesDir;
        self::$hugoRoot = $hugoRoot;
    }

    /**
     * Get content directory for a specific language
     */
    public static function getContentDir(string $lang = 'en'): string
    {
        global $config;
        
        if ($lang === 'en') {
            return self::$contentDir ?? CONTENT_DIR;
        }
        
        return self::$hugoRoot . '/' . ($config['languages'][$lang]['content_dir'] ?? "content.{$lang}");
    }

    // === Tag Actions ===

    public static function listTags(string $lang = 'en'): ListTagsAction
    {
        return new ListTagsAction(self::getContentDir($lang));
    }

    public static function renameTag(string $lang = 'en'): RenameTagAction
    {
        return new RenameTagAction(self::getContentDir($lang));
    }

    public static function mergeTags(string $lang = 'en'): MergeTagsAction
    {
        return new MergeTagsAction(self::getContentDir($lang));
    }

    public static function deleteTag(string $lang = 'en'): DeleteTagAction
    {
        return new DeleteTagAction(self::getContentDir($lang));
    }

    // === Content Actions (Generic - works for any content type) ===

    public static function listContent(string $lang = 'en'): ListContentAction
    {
        return new ListContentAction(self::getContentDir($lang));
    }

    public static function getContent(string $lang = 'en'): GetContentAction
    {
        return new GetContentAction(self::getContentDir($lang));
    }

    public static function createContent(string $lang = 'en'): CreateContentAction
    {
        return new CreateContentAction(self::getContentDir($lang));
    }

    public static function updateContent(string $lang = 'en'): UpdateContentAction
    {
        return new UpdateContentAction(self::getContentDir($lang));
    }

    public static function deleteContent(string $lang = 'en'): DeleteContentAction
    {
        return new DeleteContentAction(self::getContentDir($lang));
    }

    // === Backwards Compatibility Aliases (Article -> Content) ===
    
    public static function listArticles(string $lang = 'en'): ListContentAction
    {
        return self::listContent($lang);
    }

    public static function getArticle(string $lang = 'en'): GetContentAction
    {
        return self::getContent($lang);
    }

    public static function createArticle(string $lang = 'en'): CreateContentAction
    {
        return self::createContent($lang);
    }

    public static function updateArticle(string $lang = 'en'): UpdateContentAction
    {
        return self::updateContent($lang);
    }

    public static function deleteArticle(string $lang = 'en'): DeleteContentAction
    {
        return self::deleteContent($lang);
    }

    // === Media Actions ===

    public static function listMedia(): ListMediaAction
    {
        return new ListMediaAction(self::$imagesDir ?? IMAGES_DIR);
    }

    public static function uploadMedia(): UploadMediaAction
    {
        return new UploadMediaAction(self::$imagesDir ?? IMAGES_DIR);
    }

    public static function deleteMedia(): DeleteMediaAction
    {
        return new DeleteMediaAction(self::$imagesDir ?? IMAGES_DIR);
    }

    // === Build Actions ===

    public static function buildHugo(): BuildHugoAction
    {
        global $config;
        return new BuildHugoAction(
            self::$hugoRoot ?? HUGO_ROOT,
            $config['hugo_command'] ?? 'hugo --minify'
        );
    }

    public static function publish(): PublishAction
    {
        global $config;
        return new PublishAction(
            self::$hugoRoot ?? HUGO_ROOT,
            $config['git_user_name'] ?? 'Pugo Admin',
            $config['git_user_email'] ?? 'admin@pugo.local'
        );
    }
}

// Auto-initialize if constants are defined
if (defined('CONTENT_DIR') && defined('IMAGES_DIR') && defined('HUGO_ROOT')) {
    Actions::init(CONTENT_DIR, IMAGES_DIR, HUGO_ROOT);
}
