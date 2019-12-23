#!/bin/bash

ENVFILE="$PWD/.env"
LOCAL_PATH="$(pwd)"

if [ "$(whoami)" != "root" ] ; then
    echo "Please run as root"
    exit
fi

if [ ! -f "$ENVFILE" ]; then
   echo "\e[31m/!\ NO ENV FILE FOUND IN $PWD\e[0m"
   echo "run \`cp .env.dist .env\`"
   echo "and edit it"
   exit;
fi

set -a
. $ENVFILE
set +a

if [ $# -lt 1 ]; then
  echo 1>&2 "$0: not enough arguments."
  echo "Please specify the tag you want to deploy."
  exit 2
fi

echo "\e[34mPHP_USER : \e[95m$PHP_USER\e[0m";
echo "\e[34mRESTART_PHP : \e[95m$RESTART_PHP\e[0m";
echo "\e[34mTAG TO DEPLOY : \e[95m$1\e[0m";

git fetch --all --tags --prune

echo "\e[93m1)\e[35m Git checkout $1\e[39m"
echo "\e[36m"
git checkout master
git pull
git checkout $1
git pull
git status
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

echo "\e[93m2)\e[35m composer install\e[39m"
export SYMFONY_ENV=prod
sudo -u $PHP_USER composer install --no-dev --optimize-autoloader

echo "\e[93m3)\e[35m Clear symfony cache\e[39m"
sudo -u $PHP_USER php bin/console cache:clear --env=prod --no-debug

echo "\e[93m4)\e[35m Restart php sevices\e[39m"
exec $RESTART_PHP