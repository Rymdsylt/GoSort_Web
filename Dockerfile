# Use Alpine-based PHP
FROM php:8.1-alpine

# Install required packages
RUN apk add --no-cache apache2 apache2-mod-php81 && \
    docker-php-ext-install mysqli && \
    # Enable PHP module
    sed -i 's/^#LoadModule mpm_prefork_module/LoadModule mpm_prefork_module/' /etc/apache2/httpd.conf && \
    sed -i 's/^LoadModule mpm_event_module/#LoadModule mpm_event_module/' /etc/apache2/httpd.conf && \
    sed -i 's/^LoadModule mpm_worker_module/#LoadModule mpm_worker_module/' /etc/apache2/httpd.conf

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD ["httpd", "-D", "FOREGROUND"]
