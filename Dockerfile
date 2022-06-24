FROM php:7.4-fpm

ARG GITHUB_TOKEN

# Set working directory
WORKDIR /var/www/html

# Install dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    libicu-dev \
    libsodium-dev \
    libxml2-dev \
    libssl-dev \
    libldap2-dev \
    libonig-dev \
    libzip-dev \
    openssl \
    libcurl4-openssl-dev \
    unzip \
    git \
    curl \
    nodejs \
    npm \
    cron \
    supervisor \
    nginx

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install extensions
RUN docker-php-ext-configure intl
RUN docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/
RUN docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/
RUN docker-php-ext-install gd \
    pdo_mysql \
    mbstring \zip \
    sodium \
    exif \
    intl \
    pcntl \
    json \
    tokenizer \
    simplexml \
    phar \
    curl \
    mysqli \
    session \
    ctype \
    xml \
    dom \
    iconv \
    gettext \
    ldap
RUN docker-php-ext-enable mysqli

COPY docker/exclude.list /tmp/exclude.list

# Copy existing application directory contents
COPY . /var/www/html

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer.phar \
	&& composer.phar config -g github-oauth.github.com $GITHUB_TOKEN \
	# Install Composer Deps:
 	&& composer.phar --working-dir=lib/pkp install --no-dev \
 	&& composer.phar --working-dir=plugins/paymethod/paypal install --no-dev \
	&& composer.phar --working-dir=plugins/generic/citationStyleLanguage install --no-dev \
	# Node joins to the party:
	&& npm install -y && npm run build \
# Create directories
 	&& mkdir -p /var/www/files /run/nginx  /run/supervisord /etc/ssl/nginx \
	&& cp config.TEMPLATE.inc.php config.inc.php \
    && chown -R www-data:www-data /var/www/* \
    && rm -rf  /etc/nginx/sites-available/default \
    && rm -rf  /etc/nginx/sites-enabled/default \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log \
# Prepare freefont for captcha
	&& ln -s /usr/share/fonts/TTF/FreeSerif.ttf /usr/share/fonts/FreeSerif.ttf \
# Prepare crontab
	&& echo "0 * * * *   ojs-run-scheduled" | crontab - \
# Clear the image (list of files to be deleted in exclude.list).
	&& cd /var/www/html \
 	&& rm -rf $(cat /tmp/exclude.list) \
	&& rm -rf /tmp/* \
	&& rm -rf /root/.cache/* \
# Some folders are not required (as .git .travis.yml test .gitignore .gitmodules ...)
	&& find . -name ".git" -exec rm -Rf '{}' \; \
	&& find . -name ".travis.yml" -exec rm -Rf '{}' \; \
	&& find . -name "test" -exec rm -Rf '{}' \; \
	&& find . \( -name .gitignore -o -name .gitmodules -o -name .keepme \) -exec rm -Rf '{}' \;

COPY docker/root /

VOLUME [ "/var/www/files", "/var/www/html/public" ]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

