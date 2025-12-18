<?php
/**
 * Pugo Core 3.0 - CLI Tool
 * 
 * Command-line interface for Pugo operations.
 * Usage: ./pugo [command] [options]
 */

namespace Pugo\CLI;

use Pugo\Config\PugoConfig;
use Pugo\Deployment\DeploymentManager;
use Pugo\Blocks\BlockRegistry;

class PugoCLI
{
    private array $commands = [];
    private string $projectRoot;
    
    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? getcwd();
        $this->registerDefaultCommands();
    }
    
    /**
     * Register default commands
     */
    protected function registerDefaultCommands(): void
    {
        // Help
        $this->register('help', function($args) {
            $this->showHelp();
        }, 'Show help information');
        
        // Version
        $this->register('version', function($args) {
            $this->output('Pugo Core v3.0.0');
        }, 'Show Pugo version');
        
        // Init
        $this->register('init', function($args) {
            $this->initProject($args);
        }, 'Initialize a new Pugo project');
        
        // Build
        $this->register('build', function($args) {
            $this->build($args);
        }, 'Build the Hugo site');
        
        // Deploy
        $this->register('deploy', function($args) {
            $this->deploy($args);
        }, 'Deploy the site');
        
        // Serve
        $this->register('serve', function($args) {
            $this->serve($args);
        }, 'Start development server');
        
        // Make commands
        $this->register('make:block', function($args) {
            $this->makeBlock($args);
        }, 'Create a new block');
        
        $this->register('make:data-type', function($args) {
            $this->makeDataType($args);
        }, 'Create a new data type editor');
        
        $this->register('make:plugin', function($args) {
            $this->makePlugin($args);
        }, 'Create a new plugin');
        
        $this->register('make:page', function($args) {
            $this->makePage($args);
        }, 'Create a new page layout');
        
        // List commands
        $this->register('list:blocks', function($args) {
            $this->listBlocks();
        }, 'List available blocks');
        
        $this->register('list:adapters', function($args) {
            $this->listAdapters();
        }, 'List deployment adapters');
        
        // Config
        $this->register('config:show', function($args) {
            $this->showConfig($args);
        }, 'Show configuration');
        
        $this->register('config:set', function($args) {
            $this->setConfig($args);
        }, 'Set configuration value');
    }
    
    /**
     * Register a command
     */
    public function register(string $name, callable $handler, string $description = ''): self
    {
        $this->commands[$name] = [
            'handler' => $handler,
            'description' => $description,
        ];
        return $this;
    }
    
    /**
     * Run the CLI
     */
    public function run(array $argv): int
    {
        array_shift($argv); // Remove script name
        
        if (empty($argv)) {
            $this->showHelp();
            return 0;
        }
        
        $command = array_shift($argv);
        
        if (!isset($this->commands[$command])) {
            $this->error("Unknown command: {$command}");
            $this->output("Run 'pugo help' for available commands.");
            return 1;
        }
        
        try {
            call_user_func($this->commands[$command]['handler'], $argv);
            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
    
    /**
     * Show help
     */
    protected function showHelp(): void
    {
        $this->output("\n  \033[1;36m██████╗ ██╗   ██╗ ██████╗  ██████╗ \033[0m");
        $this->output("  \033[1;36m██╔══██╗██║   ██║██╔════╝ ██╔═══██╗\033[0m");
        $this->output("  \033[1;36m██████╔╝██║   ██║██║  ███╗██║   ██║\033[0m");
        $this->output("  \033[1;36m██╔═══╝ ██║   ██║██║   ██║██║   ██║\033[0m");
        $this->output("  \033[1;36m██║     ╚██████╔╝╚██████╔╝╚██████╔╝\033[0m");
        $this->output("  \033[1;36m╚═╝      ╚═════╝  ╚═════╝  ╚═════╝ \033[0m v3.0");
        $this->output("\n  \033[1mThe Ultimate Hugo Admin Panel\033[0m\n");
        
        $this->output("  \033[33mUsage:\033[0m pugo <command> [options]\n");
        $this->output("  \033[33mCommands:\033[0m\n");
        
        $maxLen = max(array_map('strlen', array_keys($this->commands)));
        
        foreach ($this->commands as $name => $cmd) {
            $padding = str_repeat(' ', $maxLen - strlen($name) + 2);
            $this->output("    \033[32m{$name}\033[0m{$padding}{$cmd['description']}");
        }
        
        $this->output("\n  \033[33mExamples:\033[0m");
        $this->output("    pugo init                    # Initialize new project");
        $this->output("    pugo build                   # Build Hugo site");
        $this->output("    pugo deploy                  # Deploy to production");
        $this->output("    pugo make:block hero         # Create a new hero block");
        $this->output("    pugo make:data-type faqs     # Create FAQ data editor");
        $this->output("");
    }
    
    /**
     * Initialize a new project
     */
    protected function initProject(array $args): void
    {
        $name = $args[0] ?? 'my-pugo-site';
        $dir = $this->projectRoot . '/' . $name;
        
        $this->output("\n  \033[1;36m🚀 Initializing Pugo project: {$name}\033[0m\n");
        
        // Create directory structure
        $dirs = [
            'admin/custom',
            'admin/plugins',
            'content',
            'data',
            'layouts/blocks',
            'layouts/partials',
            'static/images',
            'static/css',
            'static/js',
        ];
        
        foreach ($dirs as $d) {
            $path = $dir . '/' . $d;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
                $this->output("  \033[32m✓\033[0m Created {$d}/");
            }
        }
        
        // Create pugo.yaml
        $pugoYaml = $this->getPugoYamlTemplate($name);
        file_put_contents($dir . '/pugo.yaml', $pugoYaml);
        $this->output("  \033[32m✓\033[0m Created pugo.yaml");
        
        // Create hugo.toml
        $hugoToml = $this->getHugoTomlTemplate($name);
        file_put_contents($dir . '/hugo.toml', $hugoToml);
        $this->output("  \033[32m✓\033[0m Created hugo.toml");
        
        // Create .gitignore
        $gitignore = "/public\n/resources\n.hugo_build.lock\n.DS_Store\nnode_modules\n";
        file_put_contents($dir . '/.gitignore', $gitignore);
        $this->output("  \033[32m✓\033[0m Created .gitignore");
        
        $this->output("\n  \033[1;32m✨ Project initialized!\033[0m\n");
        $this->output("  Next steps:");
        $this->output("    cd {$name}");
        $this->output("    pugo serve\n");
    }
    
    /**
     * Build the site
     */
    protected function build(array $args): void
    {
        $this->output("\n  \033[1;36m🔨 Building site...\033[0m\n");
        
        $manager = new DeploymentManager(null, $this->projectRoot);
        $result = $manager->build([
            'clean' => in_array('--clean', $args),
            'pagefind' => !in_array('--no-pagefind', $args),
        ]);
        
        if ($result->isSuccess()) {
            $this->output("  \033[32m✓\033[0m " . $result->message);
            if (!empty($result->data['output'])) {
                $this->output("\n" . $result->data['output']);
            }
        } else {
            $this->error($result->message);
            if ($result->error) {
                $this->output($result->error);
            }
        }
    }
    
    /**
     * Deploy the site
     */
    protected function deploy(array $args): void
    {
        $this->output("\n  \033[1;36m🚀 Deploying site...\033[0m\n");
        
        $manager = new DeploymentManager(null, $this->projectRoot);
        
        // Get adapter from args or use default
        $adapterName = null;
        foreach ($args as $i => $arg) {
            if (str_starts_with($arg, '--to=')) {
                $adapterName = substr($arg, 5);
                break;
            }
        }
        
        $options = [
            'message' => $this->getArgValue($args, '--message', 'Deploy from CLI'),
            'build' => in_array('--build', $args),
        ];
        
        if ($adapterName) {
            $result = $manager->deployTo($adapterName, $options);
        } else {
            $result = $manager->deploy($options);
        }
        
        if ($result->isSuccess()) {
            $this->output("  \033[32m✓\033[0m " . $result->message);
        } elseif ($result->isPending()) {
            $this->output("  \033[33m⏳\033[0m " . $result->message);
        } else {
            $this->error($result->message);
        }
    }
    
    /**
     * Start dev server
     */
    protected function serve(array $args): void
    {
        $port = $this->getArgValue($args, '--port', '1313');
        
        $this->output("\n  \033[1;36m🌐 Starting development server...\033[0m\n");
        $this->output("  Hugo server: http://localhost:{$port}");
        $this->output("  Admin panel: http://localhost:8080/admin\n");
        $this->output("  Press Ctrl+C to stop.\n");
        
        passthru("cd {$this->projectRoot} && hugo server -D --port {$port}");
    }
    
    /**
     * Create a new block
     */
    protected function makeBlock(array $args): void
    {
        if (empty($args)) {
            $this->error("Block name required. Usage: pugo make:block <name>");
            return;
        }
        
        $name = $args[0];
        $id = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $name));
        
        $this->output("\n  \033[1;36m📦 Creating block: {$name}\033[0m\n");
        
        // Create Hugo partial
        $partialDir = $this->projectRoot . '/layouts/blocks';
        if (!is_dir($partialDir)) {
            mkdir($partialDir, 0755, true);
        }
        
        $partialContent = $this->getBlockPartialTemplate($id, $name);
        file_put_contents($partialDir . "/{$id}.html", $partialContent);
        $this->output("  \033[32m✓\033[0m Created layouts/blocks/{$id}.html");
        
        // Add to pugo.yaml
        $pugoConfig = $this->projectRoot . '/pugo.yaml';
        if (file_exists($pugoConfig)) {
            $content = file_get_contents($pugoConfig);
            $blockEntry = "\n  {$id}:\n    name: {$name}\n    icon: box\n    category: content\n    partial: blocks/{$id}.html\n";
            
            if (str_contains($content, 'blocks:')) {
                $content = preg_replace('/blocks:\s*\n/', "blocks:\n{$blockEntry}", $content);
            } else {
                $content .= "\nblocks:{$blockEntry}";
            }
            
            file_put_contents($pugoConfig, $content);
            $this->output("  \033[32m✓\033[0m Added to pugo.yaml");
        }
        
        $this->output("\n  \033[1;32m✨ Block created!\033[0m\n");
    }
    
    /**
     * Create a new data type
     */
    protected function makeDataType(array $args): void
    {
        if (empty($args)) {
            $this->error("Data type name required. Usage: pugo make:data-type <name>");
            return;
        }
        
        $name = $args[0];
        $id = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $name));
        
        $this->output("\n  \033[1;36m📊 Creating data type: {$name}\033[0m\n");
        
        // Create data file
        $dataDir = $this->projectRoot . '/data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        file_put_contents($dataDir . "/{$id}.yaml", "# {$name} data\n");
        $this->output("  \033[32m✓\033[0m Created data/{$id}.yaml");
        
        // Create admin page
        $adminDir = $this->projectRoot . '/admin/custom';
        if (!is_dir($adminDir)) {
            mkdir($adminDir, 0755, true);
        }
        
        $adminContent = $this->getDataEditorTemplate($id, $name);
        file_put_contents($adminDir . "/{$id}.php", $adminContent);
        $this->output("  \033[32m✓\033[0m Created admin/custom/{$id}.php");
        
        $this->output("\n  \033[1;32m✨ Data type created!\033[0m\n");
    }
    
    /**
     * Create a new plugin
     */
    protected function makePlugin(array $args): void
    {
        if (empty($args)) {
            $this->error("Plugin name required. Usage: pugo make:plugin <name>");
            return;
        }
        
        $name = $args[0];
        $id = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $name));
        
        $this->output("\n  \033[1;36m🔌 Creating plugin: {$name}\033[0m\n");
        
        $pluginDir = $this->projectRoot . '/admin/plugins/' . $id;
        if (!is_dir($pluginDir)) {
            mkdir($pluginDir, 0755, true);
        }
        
        $pluginContent = $this->getPluginTemplate($id, $name);
        file_put_contents($pluginDir . '/plugin.php', $pluginContent);
        $this->output("  \033[32m✓\033[0m Created admin/plugins/{$id}/plugin.php");
        
        $this->output("\n  \033[1;32m✨ Plugin created!\033[0m\n");
    }
    
    /**
     * Create a new page layout
     */
    protected function makePage(array $args): void
    {
        if (empty($args)) {
            $this->error("Page name required. Usage: pugo make:page <name>");
            return;
        }
        
        $name = $args[0];
        $id = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $name));
        
        $this->output("\n  \033[1;36m📄 Creating page layout: {$name}\033[0m\n");
        
        $layoutsDir = $this->projectRoot . '/data/page-layouts';
        if (!is_dir($layoutsDir)) {
            mkdir($layoutsDir, 0755, true);
        }
        
        $layoutContent = "meta:\n  title: \"{$name}\"\n  created_at: \"" . date('c') . "\"\n  updated_at: \"" . date('c') . "\"\n\nsections: []\n";
        file_put_contents($layoutsDir . "/{$id}.yaml", $layoutContent);
        $this->output("  \033[32m✓\033[0m Created data/page-layouts/{$id}.yaml");
        
        // Create content file
        $contentDir = $this->projectRoot . '/content';
        $contentFile = <<<HUGO
---
title: "{$name}"
layout: "page-builder"
page_layout: "{$id}"
---
HUGO;
        file_put_contents($contentDir . "/{$id}.md", $contentFile);
        $this->output("  \033[32m✓\033[0m Created content/{$id}.md");
        
        $this->output("\n  \033[1;32m✨ Page layout created!\033[0m\n");
    }
    
    /**
     * List blocks
     */
    protected function listBlocks(): void
    {
        $this->output("\n  \033[1;36m📦 Available Blocks\033[0m\n");
        
        $blocks = BlockRegistry::getInstance()->all();
        
        foreach ($blocks as $id => $block) {
            $this->output("  \033[32m{$id}\033[0m - {$block['name']}");
            $this->output("    {$block['description']}");
            $this->output("");
        }
    }
    
    /**
     * List deployment adapters
     */
    protected function listAdapters(): void
    {
        $this->output("\n  \033[1;36m🚀 Deployment Adapters\033[0m\n");
        
        $manager = new DeploymentManager(null, $this->projectRoot);
        $adapters = $manager->getAdapters();
        
        foreach ($adapters as $id => $adapter) {
            $configured = $adapter->isConfigured() ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";
            $this->output("  {$configured} \033[33m{$id}\033[0m - {$adapter->getName()}");
            $this->output("      {$adapter->getDescription()}");
        }
        $this->output("");
    }
    
    /**
     * Show config
     */
    protected function showConfig(array $args): void
    {
        $config = PugoConfig::getInstance();
        
        if (!empty($args[0])) {
            $value = $config->get($args[0]);
            $this->output(json_encode($value, JSON_PRETTY_PRINT));
        } else {
            $this->output(json_encode($config->all(), JSON_PRETTY_PRINT));
        }
    }
    
    /**
     * Set config value
     */
    protected function setConfig(array $args): void
    {
        if (count($args) < 2) {
            $this->error("Usage: pugo config:set <key> <value>");
            return;
        }
        
        $key = $args[0];
        $value = $args[1];
        
        // Try to parse as JSON
        $decoded = json_decode($value, true);
        if ($decoded !== null) {
            $value = $decoded;
        }
        
        $config = PugoConfig::getInstance();
        $config->set($key, $value);
        $config->save();
        
        $this->output("  \033[32m✓\033[0m Set {$key}");
    }
    
    // ========== Templates ==========
    
    protected function getPugoYamlTemplate(string $name): string
    {
        return <<<YAML
# Pugo Configuration
# https://pugo.dev/docs/configuration

site:
  name: "{$name}"
  url: "http://localhost:1313"
  default_language: en

languages:
  en:
    name: English
    flag: 🇬🇧
    suffix: ""

deployment:
  method: git
  git:
    branch: main
    auto_commit: false

features:
  page_builder: true
  media_library: true
  seo_tools: true

auth:
  enabled: true
  session_lifetime: 86400

blocks: {}

data_types: {}

plugins: {}
YAML;
    }
    
    protected function getHugoTomlTemplate(string $name): string
    {
        return <<<TOML
baseURL = 'http://localhost:1313/'
languageCode = 'en-us'
title = '{$name}'

[build]
  writeStats = true

[params]
  description = "Built with Pugo"
TOML;
    }
    
    protected function getBlockPartialTemplate(string $id, string $name): string
    {
        return <<<HTML
{{/* Block: {$name} */}}
{{ \$title := .title | default "" }}
{{ \$content := .content | default "" }}

<section class="block block-{$id}">
    <div class="container">
        {{ with \$title }}
        <h2 class="block-title">{{ . }}</h2>
        {{ end }}
        
        {{ with \$content }}
        <div class="block-content">
            {{ . | markdownify }}
        </div>
        {{ end }}
    </div>
</section>
HTML;
    }
    
    protected function getDataEditorTemplate(string $id, string $name): string
    {
        $className = ucfirst($id);
        return <<<PHP
<?php
/**
 * {$name} Data Editor
 * Generated by Pugo CLI
 */

define('PUGO_ROOT', dirname(__DIR__));
require_once PUGO_ROOT . '/core/bootstrap.php';

use Pugo\DataEditors\SimpleListEditor;

pugo_require_auth();
\$config = pugo_config();

\$editor = new SimpleListEditor([
    'title' => '{$name}',
    'subtitle' => 'Manage your {$name} data',
    'data_file' => '{$id}',
    'languages' => \$config['languages'],
    'icon' => 'edit',
    'cancel_url' => '{$id}.php',
    
    'fields' => [
        'title' => ['type' => 'text', 'label' => 'Title', 'required' => true],
        'description' => ['type' => 'textarea', 'label' => 'Description'],
    ],
    
    'empty_state_message' => 'No items yet.',
    'empty_state_subtext' => 'Click below to add your first item.',
]);

\$editor->handleRequest();

include PUGO_ROOT . '/core/includes/header.php';
\$editor->render();
include PUGO_ROOT . '/core/includes/footer.php';
PHP;
    }
    
    protected function getPluginTemplate(string $id, string $name): string
    {
        $className = str_replace(' ', '', ucwords(str_replace('-', ' ', $name))) . 'Plugin';
        return <<<PHP
<?php
/**
 * {$name} Plugin
 * Generated by Pugo CLI
 */

namespace Pugo\Plugins;

class {$className} extends Plugin
{
    public function getInfo(): array
    {
        return [
            'id' => '{$id}',
            'name' => '{$name}',
            'version' => '1.0.0',
            'author' => 'Your Name',
            'description' => 'Description of your plugin',
        ];
    }
    
    public function register(PluginManager \$manager): void
    {
        \$this->manager = \$manager;
        
        // Register hooks
        \$this->addAction('pugo_init', [\$this, 'onInit']);
        \$this->addFilter('pugo_menu', [\$this, 'addMenuItem']);
    }
    
    public function onInit(): void
    {
        // Plugin initialization
    }
    
    public function addMenuItem(array \$menu): array
    {
        // Add custom menu item
        // \$menu[] = ['name' => 'My Plugin', 'url' => 'my-plugin.php'];
        return \$menu;
    }
}

return new {$className}();
PHP;
    }
    
    // ========== Helpers ==========
    
    protected function getArgValue(array $args, string $name, string $default = ''): string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, $name . '=')) {
                return substr($arg, strlen($name) + 1);
            }
        }
        return $default;
    }
    
    protected function output(string $message): void
    {
        echo $message . "\n";
    }
    
    protected function error(string $message): void
    {
        echo "\033[31m  ✗ Error: {$message}\033[0m\n";
    }
}

