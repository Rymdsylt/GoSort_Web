# Use Ubuntu with Apache and PHP
FROM ubuntu:22.04

# Install Apache, PHP, and required extensions
RUN apt-get update && apt-get install -y \
    apache2 \
    php \
    php-mysqli \
    && rm -rf /var/lib/apt/lists/*

# Disable mpm_event and enable mpm_prefork to avoid MPM conflict
RUN a2dismod mpm_event && a2enmod mpm_prefork

# Enable mod_php
RUN a2enmod php8.1

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]
