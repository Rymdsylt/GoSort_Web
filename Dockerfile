# Use official PHP with Apache (pre-configured)
FROM php:8.1-apache

# Install required PHP extensions
RUN docker-php-ext-install mysqli

# Create a custom Apache config to prevent MPM conflicts
RUN echo "# Disable all MPMs except prefork" > /etc/apache2/conf-available/mpm-single.conf && \
    echo "<IfModule mpm_event_module>" >> /etc/apache2/conf-available/mpm-single.conf && \
    echo "  # event is disabled" >> /etc/apache2/conf-available/mpm-single.conf && \
    echo "</IfModule>" >> /etc/apache2/conf-available/mpm-single.conf && \
    echo "<IfModule mpm_worker_module>" >> /etc/apache2/conf-available/mpm-single.conf && \
    echo "  # worker is disabled" >> /etc/apache2/conf-available/mpm-single.conf && \
    echo "</IfModule>" >> /etc/apache2/conf-available/mpm-single.conf && \
    a2enconf mpm-single && \
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
