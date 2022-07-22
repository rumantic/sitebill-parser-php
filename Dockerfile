FROM ubuntu:latest
FROM php:8.1-rc-fpm
MAINTAINER kondin@etown.ru

RUN mkdir app
RUN apt-get update -y && apt-get upgrade -y
RUN apt-get -y install cron
RUN apt-get -y install less
RUN apt-get -y install vim
RUN apt-get -y install mc
RUN apt-get -y install git

RUN apt-get install git libssl-dev -y
RUN pecl install mongodb && docker-php-ext-enable mongodb

RUN touch /var/log/cron.log

COPY . /app

#RUN crontab crontab
RUN crontab -l | { cat; echo "* * * * * /usr/local/bin/php /app/add_product_details.php  >> /var/log/cron.log 2>&1"; } | crontab -

# Run the command on container startup
CMD cron && tail -f /var/log/cron.log


