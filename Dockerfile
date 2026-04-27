FROM php:8.2-apache AS base

# Install system dependencies and PHP extensions
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
    && docker-php-ext-install -j$(nproc) \
        curl \
        pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Configure Apache
RUN a2enmod headers rewrite && \
    echo 'ServerTokens Prod' >> /etc/apache2/conf-available/security.conf && \
    echo 'ServerSignature Off' >> /etc/apache2/conf-available/security.conf

# Production stage
FROM base AS production

WORKDIR /var/www/html

# Copy application files
COPY --chown=www-data:www-data . .

# Set proper permissions
RUN find . -type d -exec chmod 755 {} \; && \
    find . -type f -exec chmod 644 {} \;

# Switch to non-root user
USER www-data

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1
