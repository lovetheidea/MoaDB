FROM tutum/apache-php:latest
MAINTAINER Christian Stewart <kidovate@gmail.com>

# ubdate first
RUN apt-get update --assume-yes --quiet && apt-get install --assume-yes --quiet curl git wget apache2 php5 php5-curl php5-gd php-pear php5-imap php5-cli php5-mongo libapache2-mod-php5

RUN rm -rf /app/
ADD moaDB /var/www/moadb
RUN cd /var/www/moadb/ && chown -R www-data:www-data /var/www/moadb/

ADD ./docker/docker-apache.conf /etc/apache2/sites-enabled/000-default.conf

CMD bash /run.sh
EXPOSE 80
