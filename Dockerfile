FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update || apt-get update --fix-missing
RUN apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libpq-dev \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required for Laravel & Intervention Image / PhpSpreadsheet / PostgreSQL
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip

# Security Hardening: Disable session upload progress & hide PHP version (CVE & fingerprinting mitigation)
RUN { \
        echo "session.upload_progress.enabled = Off"; \
        echo "expose_php = Off"; \
    } > /usr/local/etc/php/conf.d/security.ini

# Install Python 3 & pip for mapping-tool (GeoBound)
RUN apt-get update && apt-get install -y --no-install-recommends \
    python3 \
    python3-pip \
    && rm -rf /var/lib/apt/lists/*

RUN pip install pandas shapely openpyxl xlsxwriter requests --break-system-packages

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

EXPOSE 9000
CMD ["php-fpm"]
