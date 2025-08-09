#!/bin/bash
composer run-script cache:clear
service nginx start && php-fpm
