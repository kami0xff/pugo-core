# Pugo Core

A lightweight PHP admin panel for Hugo static sites.

## Features

- 📝 **Content Management** - Create, edit, delete Hugo content
- 📊 **Data Editors** - Manage YAML data files (FAQs, components, etc.)
- 🖼️ **Media Library** - Upload and manage images/files
- 🌐 **Multi-language** - Built-in i18n support
- 🚀 **Git Deploy** - Commit and push to trigger CI/CD
- 🔒 **Security** - CSRF protection, input validation, logging

## Installation

### As a Git Submodule (Recommended)

```bash
# In your Hugo project
git submodule add https://github.com/kami0xff/pugo-core.git admin/core
```

### Update Submodule

```bash
git submodule update --remote admin/core
```

## Project Structure

```
your-hugo-site/
├── admin/
│   ├── core/              ← Pugo Core (submodule)
│   ├── config.php         ← Your site config
│   ├── index.php          ← Entry point
│   └── custom/            ← Your custom pages (optional)
├── content/
├── data/
├── layouts/
├── static/
└── config.toml
```

## Configuration

Create `admin/config.php`:

```php
<?php
define('HUGO_ROOT', dirname(__DIR__));
define('ADMIN_ROOT', __DIR__);

return [
    'site_name' => 'My Site',
    'languages' => [
        'en' => ['name' => 'English', 'flag' => '🇬🇧'],
    ],
    'default_language' => 'en',
    'sections' => [
        'posts' => ['name' => 'Blog Posts', 'path' => 'content/posts'],
    ],
    'auth' => [
        'username' => 'admin',
        'password_hash' => password_hash('changeme', PASSWORD_DEFAULT),
    ],
];
```

## Core Components

### Data Editors

Pre-built editors for common patterns:

- **SimpleListEditor** - Flat list of items (FAQs, links)
- **GroupedListEditor** - Items grouped by sections (tutorials by topic)

### UI Components

- **Card** - Content containers
- **Tabs** - Language/category switching
- **FormFields** - Text, textarea, select, checkbox
- **SaveBar** - Fixed save button
- **Toast** - Notifications

### Security

- **CSRF** - Token-based form protection
- **Validator** - Input validation rules
- **Logger** - PSR-3 style logging

## Docker

```yaml
services:
  pugo:
    build: .
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/hugo
```

## License

MIT
