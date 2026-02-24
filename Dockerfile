# Use the official PHP Apache image
FROM php:8.2-apache

# Install mysqli extension
RUN docker-php-ext-install mysqli

# Copy all project files to the container
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Expose the port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
