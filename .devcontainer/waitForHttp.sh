#!/bin/bash

printf "⌛️ Waiting for occ command..."
while true;
do
	# wait for occ
	if [[ -f "/var/www/html/occ" && $(occ 2>&1|grep maintenance:install|grep -v "grep"|grep -v defunct|wc -c) -eq 0 ]]; then
		echo ""
		break
	fi
	printf '.'
	sleep 1
done
echo "✅ occ command found"

printf "⌛️ Waiting for apps-extra..."
while true;
do
	if [[ -d "/var/www/html/apps-extra" ]]; then
		echo ""
		break
	fi
	printf '.'
	sleep 1
done
echo "✅ apps-extra found"

printf "⌛️ Waiting for php-fpm"
while true;
do
	if [[ $(ps aux|grep "pool www"|grep -v "grep"|grep -v defunct|wc -c) -ne 0 ]]; then
		echo ""
		break
	fi
	printf '.'
	sleep 1
done
echo "✅ php-fpm started"
