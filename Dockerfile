# Use Ubuntu with Apache and PHP
FROM ubuntu:22.04

# Set non-interactive mode to prevent prompts during build
ENV DEBIAN_FRONTEND=noninteractive

# Install Apache, PHP, and required extensions
RUN apt-get update && apt-get install -y \
    apache2 \
    php \
    php-mysqli \
    && rm -rf /var/lib/apt/lists/*

# Disable all MPMs and enable only mpm_prefork (required for mod_php)
RUN a2dismod mpm_event mpm_worker mpm_async 2>/dev/null || true && \
    a2enmod mpm_prefork && \
    a2enmod php && \
    a2enmod rewrite

# Configure Apache for PHP with proper VirtualHost settings
RUN cat > /etc/apache2/sites-available/000-default.conf << 'EOF'
<VirtualHost *:80>
    ServerName localhost
    ServerAdmin admin@localhost
    DocumentRoot /var/www/html
    
    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php index.html
    </Directory>
    
    <FilesMatch \.php$>
        SetHandler application/x-httpd-php
    </FilesMatch>
    
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Copy project files
COPY . /var/www/html/

# Set proper permissions for Apache
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    find /var/www/html -type f -name "*.php" -exec chmod 644 {} \;

# Set working directory
WORKDIR /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]
