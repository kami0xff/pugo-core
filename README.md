# Pugo Core

**The complete admin engine for Pugo - PHP Admin Panel for Hugo Static Sites**

## What's Included

```
pugo-core/
├── pages/                 # All admin pages (dashboard, editor, media, etc.)
│   ├── index.php         # Dashboard
│   ├── articles.php      # Article listing
│   ├── edit.php          # Article editor
│   ├── new.php           # New article
│   ├── media.php         # Media manager
│   ├── components.php    # Site components (YAML-driven sections)
│   ├── scanner.php       # Project validator
│   ├── taxonomy.php      # Tags & categories
│   ├── settings.php      # Settings
│   ├── help.php          # Help documentation
│   ├── data.php          # Data file editor
│   ├── login.php         # Login page
│   ├── logout.php        # Logout handler
│   └── api.php           # API endpoints
│
├── Actions/               # Business logic
│   ├── Content/          # CRUD for articles
│   ├── Media/            # Image/video management
│   ├── Tags/             # Taxonomy operations
│   └── Build/            # Hugo build & Git publish
│
├── includes/              # Core utilities
│   ├── functions.php     # Helper functions
│   ├── ContentType.php   # Content type engine
│   ├── auth.php          # Authentication
│   ├── header.php        # Admin UI header
│   └── footer.php        # Admin UI footer
│
├── assets/                # CSS & JavaScript
├── router.php             # Request router
├── bootstrap.php          # Core initialization
├── pugo                   # CLI tool
├── Dockerfile             # Admin container
└── docker-entrypoint.sh
```

## How Projects Use This

Each Pugo site has this structure:

```
my-site/
├── admin/
│   ├── core/              ← Git submodule pointing to pugo-core
│   ├── content_types/     ← Site-specific (never touched by updates)
│   │   └── article.php
│   ├── custom/            ← Site-specific overrides
│   │   ├── views/         ← Custom admin views
│   │   └── components_registry.php ← Site component definitions
│   ├── config.php         ← Site-specific config
│   └── index.php          ← Entry point (see below)
├── content/
├── layouts/
└── ...
```

### Entry Point (admin/index.php)

Each project needs a simple entry point:

```php
<?php
// admin/index.php
define('PUGO_ADMIN_ROOT', __DIR__);
require __DIR__ . '/core/router.php';
```

Or for direct page access (backward compatible):

```php
<?php
// admin/articles.php (if you want /admin/articles.php URLs)
require __DIR__ . '/core/pages/articles.php';
```

## Using as Git Submodule

Projects include pugo-core as a git submodule at `admin/core/`:

```bash
# Clone a project with submodules
git clone --recursive https://github.com/your-org/my-site.git

# Or if already cloned, initialize submodules
git submodule update --init --recursive
```

## Updating Projects

When pugo-core has updates:

```bash
cd my-site
git submodule update --remote admin/core
git add admin/core
git commit -m "chore: update pugo-core"
git push
```

## What Survives Updates

| Path | Updated? | Description |
|------|----------|-------------|
| `admin/core/` | ✅ YES | Submodule, tracks pugo-core |
| `admin/config.php` | ❌ NO | Site configuration |
| `admin/content_types/` | ❌ NO | Custom content types |
| `admin/custom/` | ❌ NO | View overrides |

## Contributing Changes Back

When you fix/improve core functionality from a project:

```bash
# Changes in admin/core/ are tracked by pugo-core
cd admin/core
git add .
git commit -m "fix: your improvement"
git push origin main

# Then update the submodule reference in your project
cd ../..
git add admin/core
git commit -m "chore: update pugo-core submodule"
git push
```

## Releasing a New Version

1. Update `PUGO_VERSION` in `bootstrap.php`
2. Update `VERSION` file
3. Commit and tag:

```bash
git add .
git commit -m "feat: add new feature"
git tag v1.1.0
git push origin main --tags
```

## License

MIT
