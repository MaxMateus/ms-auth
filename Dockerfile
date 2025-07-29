FROM php:8.2-fpm

# Argumentos de configuração do usuário
ARG user=laravel
ARG uid=1000

# Instala as dependências do sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Limpa cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Instala extensões PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Obtém o Composer mais recente
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Cria usuário do sistema para executar comandos Composer e Artisan
RUN useradd -G www-data,root -u $uid -d /home/$user $user
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Define o diretório de trabalho
WORKDIR /var/www

# Copia os arquivos do projeto
COPY . /var/www

# Define as permissões do diretório de armazenamento
RUN chown -R $user:$user /var/www/storage /var/www/bootstrap/cache

# Muda para o usuário não-root
USER $user

# Expõe a porta 9000
EXPOSE 9000
CMD ["php-fpm"] 