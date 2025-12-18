# 🚀 Pugo Core 3.0

**The Ultimate Hugo Admin Panel** - A powerful, extensible admin system for Hugo static sites.

```
██████╗ ██╗   ██╗ ██████╗  ██████╗ 
██╔══██╗██║   ██║██╔════╝ ██╔═══██╗
██████╔╝██║   ██║██║  ███╗██║   ██║
██╔═══╝ ██║   ██║██║   ██║██║   ██║
██║     ╚██████╔╝╚██████╔╝╚██████╔╝
╚═╝      ╚═════╝  ╚═════╝  ╚═════╝  v3.0
```

## ✨ What's New in 3.0

- **📦 Block System** - Visual page builder with drag-and-drop blocks
- **🚀 Multi-Deployment** - Deploy to Git, Netlify, Vercel, Cloudflare, S3, or your own server
- **🔌 Plugin Architecture** - Extend Pugo with custom plugins
- **⚡ CLI Tool** - Scaffold blocks, data types, and plugins from command line
- **📊 Dashboard Widgets** - Customizable dashboard with live stats
- **📝 Single Config** - All settings in one `pugo.yaml` file

## 📖 Quick Start

### Installation

```bash
# Clone into your Hugo project
git clone https://github.com/your-org/pugo-core.git admin/core

# Or use as submodule (recommended)
git submodule add https://github.com/your-org/pugo-core.git admin/core
```

### Initialize a New Project

```bash
./admin/core/bin/pugo init my-site
cd my-site
./admin/core/bin/pugo serve
```

## 🎯 Features

### 📝 Content Management
- Multi-language support with flag indicators
- Section-based content organization
- Custom content types with configurable fields
- WYSIWYG and Markdown editors
- Media library with drag-and-drop upload

### 📦 Visual Page Builder
- 15+ built-in blocks (Hero, Features, Testimonials, FAQ, Pricing, etc.)
- Custom block creation via Hugo partials
- Drag-and-drop section ordering
- Live preview

### 🚀 Multi-Platform Deployment

| Platform | Method | Best For |
|----------|--------|----------|
| Git CI/CD | Push → Pipeline | Production, full control |
| Netlify | API / Hook | JAMstack, instant previews |
| Vercel | API | Edge functions, previews |
| Cloudflare | API | Global CDN, Workers |
| AWS S3 | CLI sync | Enterprise, CloudFront |
| Rsync/SSH | Direct upload | Traditional VPS |

### 🔌 Plugin System
- Event-driven architecture
- Custom hooks and filters
- WordPress-like API
- Easy plugin creation

### 📊 Dashboard
- Quick stats overview
- Git status integration
- Recent activity feed
- Site health checks
- Deployment status
- Customizable widget layout

## 📁 Architecture

```
pugo-core/
├── bin/
│   └── pugo              # CLI tool
├── Blocks/
│   └── BlockRegistry.php # Visual blocks for page builder
├── CLI/
│   └── PugoCLI.php      # Command-line interface
├── Components/
│   ├── Card.php
│   ├── Tabs.php
│   ├── Toast.php
│   ├── SaveBar.php
│   └── FormFields/      # Text, Textarea, Select, Checkbox
├── Config/
│   └── PugoConfig.php   # pugo.yaml parser
├── Dashboard/
│   ├── DashboardManager.php
│   ├── Widget.php
│   └── Widgets/         # Built-in widgets
├── DataEditors/
│   ├── BaseDataEditor.php
│   ├── SimpleListEditor.php
│   └── GroupedListEditor.php
├── Deployment/
│   ├── DeploymentManager.php
│   ├── DeploymentAdapter.php
│   └── Adapters/        # Git, Netlify, Vercel, etc.
├── PageBuilder/
│   ├── PageBuilder.php
│   └── PageLayout.php
├── Plugins/
│   ├── PluginManager.php
│   └── Plugin.php
├── autoload.php
├── bootstrap.php
└── pugo.example.yaml
```

## ⚡ CLI Commands

```bash
# Project
pugo init <name>          # Initialize new project
pugo build                # Build Hugo site
pugo serve                # Start dev server
pugo deploy               # Deploy to production

# Scaffolding
pugo make:block <name>    # Create new block
pugo make:data-type <name> # Create data type editor
pugo make:plugin <name>   # Create new plugin
pugo make:page <name>     # Create page layout

# Information
pugo list:blocks          # List available blocks
pugo list:adapters        # List deployment adapters
pugo config:show          # Show configuration
pugo help                 # Show help
```

## 📝 Configuration (pugo.yaml)

```yaml
site:
  name: "My Site"
  url: "https://example.com"
  default_language: en

languages:
  en:
    name: English
    flag: 🇬🇧
  fr:
    name: Français
    flag: 🇫🇷
    suffix: "_fr"

sections:
  blog:
    name: Blog
    color: "#3b82f6"
    content_type: article

content_types:
  article:
    fields:
      title:
        type: text
        required: true
      description:
        type: textarea

data_types:
  faqs:
    name: FAQs
    editor: simple-list
    fields:
      question:
        type: text
        required: true
      answer:
        type: textarea
        required: true

deployment:
  method: git
  git:
    branch: main
    trigger_pipeline: true

plugins:
  seo:
    enabled: true
    class: Pugo\Plugins\SEOPlugin
```

## 🔌 Creating Plugins

```php
<?php
// admin/plugins/my-plugin/plugin.php

namespace Pugo\Plugins;

class MyPlugin extends Plugin
{
    public function getInfo(): array
    {
        return [
            'id' => 'my-plugin',
            'name' => 'My Plugin',
            'version' => '1.0.0',
        ];
    }
    
    public function register(PluginManager $manager): void
    {
        $this->manager = $manager;
        
        // Add hooks
        $this->addAction('pugo_init', [$this, 'onInit']);
        $this->addFilter('pugo_menu', [$this, 'addMenuItem']);
    }
}

return new MyPlugin();
```

## 📦 Creating Blocks

1. Create Hugo partial:

```html
{{/* layouts/blocks/my-block.html */}}
{{ $title := .title | default "" }}

<section class="my-block">
    <h2>{{ $title }}</h2>
    {{ .content | markdownify }}
</section>
```

2. Register in pugo.yaml:

```yaml
blocks:
  my-block:
    name: My Block
    icon: box
    category: content
    partial: blocks/my-block.html
    fields:
      title:
        type: text
        label: Title
      content:
        type: markdown
        label: Content
```

## 🤝 Contributing

Contributions are welcome! Please read our contributing guidelines before submitting PRs.

## 📄 License

MIT License - see LICENSE file for details.

---

Built with ❤️ for the Hugo community
