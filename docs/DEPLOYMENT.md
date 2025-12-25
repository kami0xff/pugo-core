# Pugo Deployment Guide

## Option 1: VPS Deployment (Full Control)

### 1. Server Setup (Ubuntu 22.04)

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y nginx php8.1-fpm php8.1-yaml php8.1-mbstring \
    php8.1-xml php8.1-curl git hugo

# Create directory structure
sudo mkdir -p /var/www/mysite
sudo chown -R $USER:www-data /var/www/mysite
```

### 2. Clone/Upload Your Project

```bash
cd /var/www/mysite

# Clone pugo-core (or copy from your dev machine)
git clone https://github.com/your-repo/pugo-core.git core

# Create your Hugo site structure
mkdir -p content data static/images layouts public admin

# Create admin entry point
cat > admin/index.php << 'EOF'
<?php
define('HUGO_ADMIN', true);
define('PUGO_CORE_ROOT', dirname(__DIR__) . '/core');
require PUGO_CORE_ROOT . '/router.php';
EOF

# Create config.php
cat > admin/config.php << 'EOF'
<?php
if (!defined('HUGO_ADMIN')) define('HUGO_ADMIN', true);

$hugo_root = dirname(__DIR__);
define('HUGO_ROOT', $hugo_root);
define('ADMIN_ROOT', __DIR__);
define('CONTENT_DIR', HUGO_ROOT . '/content');
define('STATIC_DIR', HUGO_ROOT . '/static');
define('DATA_DIR', HUGO_ROOT . '/data');
define('IMAGES_DIR', STATIC_DIR . '/images');

// Discover sections dynamically
if (!function_exists('discover_sections')) {
    function discover_sections() {
        $sections = [];
        if (is_dir(CONTENT_DIR)) {
            foreach (scandir(CONTENT_DIR) as $item) {
                if ($item[0] === '.') continue;
                $path = CONTENT_DIR . '/' . $item;
                if (is_dir($path)) {
                    $sections[$item] = [
                        'name' => ucfirst(str_replace('-', ' ', $item)),
                        'color' => '#3b82f6',
                        'path' => $path
                    ];
                }
            }
        }
        return $sections;
    }
}

return [
    'site_name' => 'My Site',
    'site_url' => 'https://yourdomain.com',
    
    'languages' => [
        'en' => ['name' => 'English', 'flag' => '🇬🇧', 'content_dir' => 'content', 'suffix' => '', 'data_suffix' => ''],
    ],
    'default_language' => 'en',
    
    'auth' => [
        'enabled' => true,
        'username' => 'admin',
        // Generate with: php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"
        'password_hash' => '$2y$10$YOUR_HASH_HERE',
        'session_lifetime' => 86400,
    ],
    
    'hugo_command' => 'cd ' . HUGO_ROOT . ' && hugo --minify',
    
    'git' => [
        'enabled' => true,
        'auto_commit' => false,
    ],
];
EOF
```

### 3. Nginx Configuration

```bash
sudo nano /etc/nginx/sites-available/mysite
```

```nginx
# /etc/nginx/sites-available/mysite

# Main site (static Hugo output)
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    
    root /var/www/mysite/public;
    index index.html;
    
    # Static site
    location / {
        try_files $uri $uri/ =404;
    }
    
    # Cache static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}

# Admin panel (PHP)
server {
    listen 80;
    server_name admin.yourdomain.com;
    
    root /var/www/mysite/admin;
    index index.php;
    
    # Security: Only allow from specific IPs (optional)
    # allow 1.2.3.4;
    # deny all;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ config\.php$ {
        deny all;
    }
}
```

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/mysite /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 4. SSL with Let's Encrypt

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com -d admin.yourdomain.com
```

### 5. Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/mysite/content
sudo chown -R www-data:www-data /var/www/mysite/data
sudo chown -R www-data:www-data /var/www/mysite/static
sudo chown -R www-data:www-data /var/www/mysite/public
sudo chmod -R 775 /var/www/mysite/content
sudo chmod -R 775 /var/www/mysite/data
sudo chmod -R 775 /var/www/mysite/static
sudo chmod -R 775 /var/www/mysite/public
```

---

## Option 2: Docker Deployment

### docker-compose.yml

```yaml
version: '3.8'

services:
  pugo:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./content:/var/www/hugo/content
      - ./data:/var/www/hugo/data
      - ./static:/var/www/hugo/static
      - ./public:/var/www/hugo/public
      - ./layouts:/var/www/hugo/layouts
    environment:
      - HUGO_ROOT=/var/www/hugo
    restart: unless-stopped
```

### Dockerfile

```dockerfile
FROM php:8.1-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    git curl wget libyaml-dev \
    && pecl install yaml \
    && docker-php-ext-enable yaml \
    && a2enmod rewrite

# Install Hugo
RUN wget https://github.com/gohugoio/hugo/releases/download/v0.140.2/hugo_extended_0.140.2_linux-amd64.deb \
    && dpkg -i hugo_extended_0.140.2_linux-amd64.deb \
    && rm hugo_extended_0.140.2_linux-amd64.deb

# Copy Pugo core
COPY ./core /var/www/pugo-core
COPY ./admin /var/www/hugo/admin

# Apache config
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/hugo/public\n\
    <Directory /var/www/hugo/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    Alias /admin /var/www/hugo/admin\n\
    <Directory /var/www/hugo/admin>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Set permissions
RUN chown -R www-data:www-data /var/www

WORKDIR /var/www/hugo
CMD ["apache2-foreground"]
```

---

## Option 3: Hybrid (Admin on VPS, Static on CDN)

This is the most performant setup:
1. Admin runs on a small VPS
2. Hugo output is pushed to a CDN (Netlify/Vercel/Cloudflare Pages)

### Setup Git-based deployment

```bash
# In your Hugo root
cd /var/www/mysite

# Initialize git repo for public folder only
cd public
git init
git remote add origin git@github.com:youruser/mysite-public.git

# After each Hugo build, commit and push
hugo --minify
cd public
git add .
git commit -m "content: Update $(date)"
git push origin main
```

### Netlify Setup
1. Connect your `mysite-public` repo to Netlify
2. Set publish directory to `/` (root)
3. Leave build command empty (pre-built)
4. Deploy!

---

## Option 4: Cloudflare Pages (Free & Fast)

1. Push your Hugo site to GitHub
2. Connect to Cloudflare Pages
3. Build settings:
   - Build command: `hugo --minify`
   - Build output: `public`
4. Deploy!

For the admin, you'll still need a PHP server.

---

## Security Checklist

- [ ] Change default admin password
- [ ] Use HTTPS for admin panel
- [ ] Restrict admin access by IP if possible
- [ ] Set proper file permissions
- [ ] Disable directory listing
- [ ] Keep PHP and dependencies updated
- [ ] Set up firewall (ufw)
- [ ] Enable fail2ban for SSH

---

## Backup Strategy

```bash
# Backup script: /usr/local/bin/backup-pugo.sh
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/pugo"

mkdir -p $BACKUP_DIR

# Backup content and data
tar -czf $BACKUP_DIR/pugo-content-$DATE.tar.gz \
    /var/www/mysite/content \
    /var/www/mysite/data \
    /var/www/mysite/static

# Keep only last 7 days
find $BACKUP_DIR -type f -mtime +7 -delete

# Add to crontab: 0 3 * * * /usr/local/bin/backup-pugo.sh
```

---

## Quick Start Commands

```bash
# Build site
hugo --minify

# Deploy to Netlify via CLI
netlify deploy --prod

# Restart services
sudo systemctl restart nginx php8.1-fpm

# Check logs
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/php8.1-fpm.log
```

