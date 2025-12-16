# Pugo Core

**The complete admin engine for Pugo - PHP Admin Panel for Hugo Static Sites**

## What's Included

```
src/
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
│   ├── core/              ← Copy of pugo-core/src/ (updateable)
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

## Updating Projects

When you release a new version:

1. Tag a release on GitHub/GitLab
2. Users run `./admin/core/pugo update`
3. The entire `core/` folder is replaced
4. Their `config.php`, `content_types/`, and `custom/` are untouched

## What Survives Updates

| Path | Updated? | Description |
|------|----------|-------------|
| `admin/core/` | ✅ YES | Replaced entirely |
| `admin/config.php` | ❌ NO | Site configuration |
| `admin/content_types/` | ❌ NO | Custom content types |
| `admin/custom/` | ❌ NO | View overrides |

## Releasing a New Version

1. Update `PUGO_VERSION` in `src/bootstrap.php`
2. Update `VERSION` file
3. Create a GitHub/GitLab release with tag `vX.Y.Z`
4. The CLI will fetch the latest release automatically

## Development

```bash
# Clone this repo
git clone https://github.com/your-org/pugo-core.git

# Make changes to src/

# Test in a project by copying
cp -r src/* /path/to/project/admin/core/

# Commit and tag
git add .
git commit -m "feat: add new feature"
git tag v1.1.0
git push origin main --tags
```

## License

MIT
