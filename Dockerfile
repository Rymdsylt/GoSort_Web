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
    a2enmod php8.1

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]
