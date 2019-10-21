FROM fsiopen/docker-php-apache:alpine-7.3

ENV PROFILE="/home/www-data/.bashrc"
COPY ./.bashrc "$PROFILE"
RUN source "$PROFILE"
