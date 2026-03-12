FROM webdevops/php-apache:8.4

ENV WEB_DOCUMENT_ROOT=/app/public
ENV WEB_ALIAS_DOMAIN=localhost

WORKDIR /app

COPY docker/entrypoint.sh /opt/docker/provision/entrypoint.d/99-laravel.sh
RUN chmod +x /opt/docker/provision/entrypoint.d/99-laravel.sh
