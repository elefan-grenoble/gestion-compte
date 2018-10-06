#!/bin/bash
echo "truncate db"
sudo mysql -uroot -e "DROP DATABASE membres_new_schema;"
sudo mysql -uroot -e "CREATE DATABASE membres_new_schema;"
echo "import dump"
mysql -ubabar -ppassword membres_new_schema < dump-espace-membres.sql
echo "migrate data"
mysql -ubabar -ppassword membres_new_schema < migrate-to-multi-user.sql