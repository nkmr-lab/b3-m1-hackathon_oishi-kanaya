# PHP + Apacheをベースにしたイメージを使用
FROM php:8.1-apache

# 作業ディレクトリの設定
WORKDIR /var/www/html

# 必要なPHPモジュールをインストール
RUN docker-php-ext-install mysqli pdo pdo_mysql

# 権限の設定（必要に応じて追加）
RUN chown -R www-data:www-data /var/www/html
