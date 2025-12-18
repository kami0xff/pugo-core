# Pugo Core 2.0

A powerful, modular admin panel engine for Hugo static sites.

## 🚀 Features

- **Registry System** - Enable/disable sections, content types, and data editors
- **Pre-built Data Editors** - FAQs, Topics, Tutorials, Quick Access, and more
- **Reusable UI Components** - Cards, Tabs, Form Fields, Save Bar, Toast notifications
- **Multi-language Support** - Built-in translation management
- **Live Preview** - See changes in real-time
- **Git Submodule Ready** - Easy updates across projects

## 📦 Installation

### As Git Submodule (Recommended)

```bash
# In your Hugo project's admin folder
git submodule add https://git.example.com/pugo-core.git admin/core
```

### Manual Installation

Copy the `pugo-core` directory to `admin/core` in your project.

## 🏗️ Architecture

```
pugo-core/
├── Actions/              # Business logic (build, publish, etc.)
├── Components/           # Reusable UI components
│   ├── Card.php
│   ├── Tabs.php
│   ├── Toast.php
│   ├── SaveBar.php
│   ├── EmptyState.php
│   └── FormFields/
│       ├── TextField.php
│       ├── TextareaField.php
│       ├── SelectField.php
│       └── CheckboxField.php
├── DataEditors/          # Pre-built data editors
│   ├── BaseDataEditor.php
│   ├── SimpleListEditor.php    # For FAQs, quick access, etc.
│   └── GroupedListEditor.php   # For topics (with sections)
├── Registry/             # Configuration registry
│   └── Registry.php
├── includes/             # Core includes
├── pages/               # Example admin pages
├── assets/              # CSS/JS assets
├── autoload.php         # PSR-4 autoloader
└── bootstrap.php        # Main entry point
```

## 🎯 Quick Start

### 1. Create a Data Editor Page

**Before (800+ lines):**
```php
<?php
// Hundreds of lines of parsing, saving, UI code...
```

**After (35 lines!):**
```php
<?php
define('HUGO_ADMIN', true);
define('PUGO_ROOT', __DIR__);

$config = require 'config.php';
require 'core/bootstrap.php';
require_auth();

$editor = pugo_create_editor('faqs', [
    'title' => 'FAQ Editor',
    'languages' => $config['languages'],
]);

$editor->handleRequest();

require 'core/includes/header.php';
$editor->render();
require 'core/includes/footer.php';
```

### 2. Custom Data Editor

```php
<?php
use Pugo\DataEditors\SimpleListEditor;

$editor = new SimpleListEditor([
    'title' => 'Testimonials',
    'subtitle' => 'Manage customer testimonials',
    'data_file' => 'testimonials',
    
    'fields' => [
        'name' => ['type' => 'text', 'label' => 'Name', 'required' => true],
        'company' => ['type' => 'text', 'label' => 'Company'],
        'quote' => ['type' => 'textarea', 'label' => 'Quote', 'required' => true],
        'rating' => ['type' => 'select', 'label' => 'Rating', 'options' => [
            '5' => '⭐⭐⭐⭐⭐',
            '4' => '⭐⭐⭐⭐',
            '3' => '⭐⭐⭐',
        ]],
        'featured' => ['type' => 'checkbox', 'label' => 'Featured'],
    ],
    
    'item_name' => 'testimonial',
    'item_name_plural' => 'testimonials',
]);
```

### 3. Grouped Data Editor (with sections)

```php
<?php
use Pugo\DataEditors\GroupedListEditor;

$editor = new GroupedListEditor([
    'title' => 'Topics Editor',
    'data_file' => 'topics',
    
    'sections' => [
        'users' => ['name' => 'Users', 'color' => '#3b82f6'],
        'models' => ['name' => 'Models', 'color' => '#ec4899'],
        'studios' => ['name' => 'Studios', 'color' => '#8b5cf6'],
    ],
    
    'fields' => [
        'title' => ['type' => 'text', 'label' => 'Title', 'required' => true],
        'desc' => ['type' => 'text', 'label' => 'Description'],
        'url' => ['type' => 'text', 'label' => 'URL'],
    ],
]);
```

## 📋 Registry System

The Registry allows you to configure which features are available:

```php
<?php
use Pugo\Registry\Registry;

$registry = Registry::getInstance();

// Register a custom data editor
$registry->registerDataEditor('pricing', [
    'name' => 'Pricing Editor',
    'icon' => 'dollar-sign',
    'editor_class' => \Pugo\DataEditors\SimpleListEditor::class,
    'data_file' => 'pricing',
    'fields' => [
        'name' => ['type' => 'text', 'label' => 'Plan Name'],
        'price' => ['type' => 'number', 'label' => 'Price'],
        'features' => ['type' => 'textarea', 'label' => 'Features'],
    ],
]);

// Enable/disable features via config
$registry->configure([
    'data_editors' => [
        'faqs' => true,
        'topics' => true,
        'tutorials' => false,  // Disable for this project
    ],
    'sections' => [
        'users' => ['name' => 'Users', 'color' => '#3b82f6'],
        'models' => false,  // Disable this section
    ],
]);
```

## 🎨 UI Components

### Card

```php
use Pugo\Components\Card;

$card = new Card([
    'title' => 'My Card',
    'icon' => 'file-text',
    'content' => '<p>Card content here</p>',
    'scrollable' => true,
    'max_height' => '400px',
]);

echo $card;
```

### Tabs

```php
use Pugo\Components\Tabs;

// Language tabs
echo Tabs::languages($languages, 'en', '?');

// Section tabs  
echo Tabs::sections($sections, 'users', '?lang=en');

// Category tabs
echo Tabs::categories($categories, 'all', '?');
```

### Form Fields

```php
use Pugo\Components\FormFields\FieldFactory;

// Create fields from schema
$fields = FieldFactory::fromSchema([
    'title' => ['type' => 'text', 'label' => 'Title', 'required' => true],
    'description' => ['type' => 'textarea', 'label' => 'Description'],
], $values);

foreach ($fields as $field) {
    echo $field->render();
}
```

### Toast Notifications

```php
use Pugo\Components\Toast;

echo Toast::success('Saved successfully!');
echo Toast::error('Something went wrong');
echo Toast::warning('Please check your input');
```

## 📁 Field Types

| Type | Description |
|------|-------------|
| `text` | Single line text input |
| `textarea` | Multi-line text |
| `number` | Numeric input |
| `email` | Email input |
| `url` | URL input |
| `select` | Dropdown select |
| `checkbox` | Boolean checkbox |
| `date` | Date picker |

## 🔄 Updating Pugo Core

If using as a submodule:

```bash
cd admin/core
git pull origin main
cd ../..
git add admin/core
git commit -m "Update pugo-core"
```

## 🤝 Contributing

1. Make changes in your project's `admin/core` submodule
2. Commit and push to pugo-core repository
3. Update other projects by pulling the submodule

## 📄 License

MIT License
