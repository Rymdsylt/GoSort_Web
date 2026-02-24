# Use the official PHP image with Apache
FROM php:8.2-apache

# Install mysqli extension
RUN docker-php-ext-install mysqli

# Copy all project files to the container
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Expose port 8080 for Railway
EXPOSE 8080

# Use Apache to serve the app
CMD ["apache2-foreground"]
