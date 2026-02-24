# Use the official PHP image
FROM php:8.2-cli

# Install mysqli extension
RUN docker-php-ext-install mysqli

# Copy all project files to the container
COPY . /app/

# Set working directory
WORKDIR /app

# Expose the port that Railway assigns via $PORT
EXPOSE 8080

# Start PHP built-in server
CMD php -S 0.0.0.0:${PORT:-8080}
