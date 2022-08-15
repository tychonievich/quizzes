#!/bin/bash

echo ======== Ensuring www-data write access to directories ========

chmod 777 log cache && echo 'Succcess!' || echo 'Failure! Results not recorded until fixed'

echo ======== Getting Parsedown and ParsedownExtra ========

wget -nc "https://raw.githubusercontent.com/erusev/parsedown/master/Parsedown.php"
wget -nc "https://raw.githubusercontent.com/erusev/parsedown-extra/master/ParsedownExtra.php"


