#From PHP8.3-cli
FROM php:8.3-cli-alpine

RUN adduser -h /home/admin -s /home/admin/vendor/bin/phpterminal -D admin
RUN echo -n 'admin:admin' | chpasswd

WORKDIR /home/admin

RUN apk update
RUN apk add --update --no-cache git
RUN apk add --update --no-cache zip

RUN docker-php-ext-configure pcntl --enable-pcntl && docker-php-ext-install pcntl

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php
RUN php -r "unlink('composer-setup.php');"
RUN mv composer.phar /home/admin/composer
RUN chmod +x /home/admin/composer
RUN /home/admin/composer require phpterminal/phpterminal
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN mkdir /home/admin/terminaldata

RUN apk add --update --no-cache openssh
RUN echo 'PasswordAuthentication yes' >> /etc/ssh/sshd_config
RUN echo 'Port 2233' >> /etc/ssh/sshd_config
ENTRYPOINT ["/entrypoint.sh"]
COPY entrypoint.sh /
RUN chown -R admin:admin /home/admin/

# Running
# docker run -d --name phpterminal -h phpterminal oyeaussie/phpterminal
# Grab IP
# docker exec phpterminal cat /etc/hosts | grep phpterminal
# {container IP}    phpterminal
# Connect via ssh
# ssh -l admin -p 2233 {container IP}