# Use official PHP with Apache (pre-configured)
FROM php:8.1-apache

# Install required PHP extensions
RUN docker-php-ext-install mysqli && \
    # Ensure only mpm_prefork is enabled, disable all others
    a2dismod mpm_event mpm_worker mpm_async 2>/dev/null || true && \
    a2enmod mpm_prefork

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]
