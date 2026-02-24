# Use PHP Alpine image with built-in server
FROM php:8.1-alpine

# Install required extensions
RUN docker-php-ext-install mysqli

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Expose port (Railway sets PORT env var)
EXPOSE 8080

# Start PHP built-in server (shell form to expand PORT env var)
CMD php -S 0.0.0.0:${PORT:-8080} -t .
