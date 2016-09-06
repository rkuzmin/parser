#!/usr/bin/env bash
composer install
bin/console do:da:cr
bin/console do:sc:up --force
bin/console do:mi:mi
