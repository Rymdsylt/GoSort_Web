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
    a2enmod php8.1 && \
    a2enmod rewrite

# Set ServerName and create Apache configuration for PHP
RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf && \
    echo '' >> /etc/apache2/apache2.conf && \
    echo '<Directory /var/www/html>' >> /etc/apache2/apache2.conf && \
    echo '    Options Indexes FollowSymLinks' >> /etc/apache2/apache2.conf && \
    echo '    AllowOverride All' >> /etc/apache2/apache2.conf && \
    echo '    Require all granted' >> /etc/apache2/apache2.conf && \
    echo '    DirectoryIndex index.php index.html' >> /etc/apache2/apache2.conf && \
    echo '</Directory>' >> /etc/apache2/apache2.conf && \
    echo '' >> /etc/apache2/apache2.conf && \
    echo '<FilesMatch \.php$>' >> /etc/apache2/apache2.conf && \
    echo '    SetHandler application/x-httpd-php' >> /etc/apache2/apache2.conf && \
    echo '</FilesMatch>' >> /etc/apache2/apache2.conf

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
