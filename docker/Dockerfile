FROM fsiopenpl/docker-php-apache:alpine-8.1

ENV PROFILE="/home/www-data/.bashrc"
COPY ./.bashrc "$PROFILE"
RUN source "$PROFILE"
