FROM fsiopen/docker-php-apache:alpine-7.4

ENV PROFILE="/home/www-data/.bashrc"
COPY ./.bashrc "$PROFILE"
RUN source "$PROFILE"
