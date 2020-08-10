#!/bin/bash

php /elefan/bin/console doctrine:migration:migrate
php /elefan/bin/console cache:warmup
if [ -n "$SUPER_ADMIN_USERNAME" ];
then
  php /elefan/bin/console fos:user:change-password "$SUPER_ADMIN_USERNAME" "$SUPER_ADMIN_PASSWORD"
fi