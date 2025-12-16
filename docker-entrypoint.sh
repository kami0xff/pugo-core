#!/bin/sh
set -e

# Fix permissions on shared public volume (www-data = uid 82 in Alpine)
echo "Fixing public folder permissions..."
chown -R 82:82 /var/www/hugo/public 2>/dev/null || true
chmod -R 775 /var/www/hugo/public 2>/dev/null || true

# Check if public folder is empty (first run or volume reset)
if [ ! -f "/var/www/hugo/public/index.html" ]; then
    echo "Public folder empty - running initial Hugo build..."
    cd /var/www/hugo
    
    # Run Hugo build
    hugo --minify --gc
    
    # Run Pagefind for search indexing
    pagefind --site public || echo "Pagefind warning (non-critical)"
    
    echo "Initial build complete!"
else
    echo "Public folder ready."
fi

# Start PHP-FPM
exec php-fpm

