FROM fsiopen/docker-php-apache:alpine-8.0

ENV PROFILE="/home/www-data/.bashrc"
COPY ./.bashrc "$PROFILE"
RUN source "$PROFILE"
