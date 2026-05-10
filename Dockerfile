FROM php:8.2-apache

# Enable Apache modules
RUN a2enmod rewrite headers

# Install system dependencies + Python
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    python3 \
    python3-pip \
    python3-venv \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    zip \
    mbstring \
    curl

# Install Python data mining libraries
RUN pip3 install --break-system-packages \
    pandas \
    numpy \
    scikit-learn \
    mlxtend

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Add ServerName to suppress warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Enable .htaccess override in Apache config
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Copy source code
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Fix permissions
RUN chown -R www-data:www-data /var/www/html

# Make Python scripts executable
RUN find /var/www/html/scripts -name "*.py" -exec chmod +x {} \; 2>/dev/null || true

EXPOSE 80