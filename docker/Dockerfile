#Docker File for SP using oyeaussie/docker-phalcon
FROM oyeaussie/docker-phalcon

WORKDIR /var/www/html/

RUN git clone https://github.com/oyeaussie/PHPTerminal.git
RUN mkdir /var/www/html/PHPTerminal/terminaldata
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php
RUN php -r "unlink('composer-setup.php');"
RUN mv composer.phar /var/www/html/PHPTerminal/composer
ENV COMPOSER_ALLOW_SUPERUSER=1