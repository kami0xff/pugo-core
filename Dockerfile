# PHP Admin for Hugo - with Hugo binary for rebuilding
FROM php:8.2-fpm-alpine

# Install extensions and dependencies
RUN docker-php-ext-install opcache

# Install Hugo extended (Alpine-compatible binary)
# Using musl-compatible binary and necessary libs
RUN apk add --no-cache \
    curl \
    git \
    libc6-compat \
    libstdc++ \
    nodejs \
    npm \
    && curl -L https://github.com/gohugoio/hugo/releases/download/v0.139.4/hugo_extended_0.139.4_Linux-64bit.tar.gz -o /tmp/hugo.tar.gz \
    && tar -xzf /tmp/hugo.tar.gz -C /usr/local/bin hugo \
    && rm /tmp/hugo.tar.gz \
    && chmod +x /usr/local/bin/hugo

# Install pagefind for search indexing
RUN npm install -g pagefind

# Copy PHP config
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini

# Set working directory
WORKDIR /var/www/html/admin

# Copy admin files
COPY . .

# Copy and setup entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/html/admin

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
