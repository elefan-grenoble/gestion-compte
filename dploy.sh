#!/bin/bash

#todo mode maintenance NGINX
#todo Appliquer les migrations avec un dump de base juste avant

set -e

ENVFILE="$PWD/.env"
LOCAL_PATH="$(pwd)"

if [ "$(whoami)" != "root" ] ; then
    echo "Please run as root"
    exit
fi

if [ ! -f "$ENVFILE" ]; then
   echo "\e[31m⚠ NO ENV FILE FOUND IN $PWD\e[0m"
   echo "run \`cp .env.dist .env\`"
   echo "and edit it"
   exit;
fi

set -a
. "$ENVFILE"
set +a

if [ $# -lt 1 ]; then
  echo 1>&2 "\e[31m$0: not enough arguments.\e[0m"
  echo "\e[93mPlease specify the tag you want to deploy !\e[0m"
  exit 2
fi

echo "\e[34m\"PHP_USER\" : \e[95m${PHP_USER}\e[0m";
echo "\e[34m\"PHP_SERVICE_NAME\" : \e[95m$PHP_SERVICE_NAME\e[0m";
echo "\e[34m\"TAG TO DEPLOY\" : \e[95m$1\e[0m";

echo "\e[93m1a)\e[35m Fetch \e[39m"
git fetch --all --tags --prune

echo "\e[93m1b)\e[35m Check if tag \e[34m$1\e[35m exist\e[0m"
if git tag --list | grep -E -q "^${1}$"
then
    echo "\e[92mYes, Tag found ! 👌\e[0m"
else
    echo "\e[31mOups, Tag not found ! 😬\e[0m"
    exit;
fi
echo "\e[39m"

echo "====================="
while true; do
    read -p "Continue and deploy app ? " c
    case $c in
        y ) break;;
        yes ) break;;
        no ) exit; break;;
        n ) exit; break;;
        * ) echo "Please answer yes or no.";;
    esac
done

git checkout "${1}"

echo "\e[93m2)\e[35m composer install\e[39m"
export SYMFONY_ENV=prod
sudo -u "${PHP_USER}" composer install --no-dev --optimize-autoloader

echo "\e[93m3)\e[35m assetic:dump \e[39m"
sudo -u "${PHP_USER}" php bin/console assetic:dump

echo "\e[93m4)\e[35m Restart php sevice\e[39m"
systemctl restart "${PHP_SERVICE_NAME}"

# Appliquer les migrations avec un dump de base juste avant.
#echo "\e[93m5)\e[35m Backup DB\e[39m"
#Afficher aussi le nombre de migrations à appliquer dans le texte de confirmation.
