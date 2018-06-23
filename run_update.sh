#!/bin/bash
BASEDIR=$(dirname $0)

if [ -z "$1" ]
	then
		echo "No channel argument supplied"
		exit 1
fi

php -f ${BASEDIR}/get_follower_as_list.php $1

cd ${BASEDIR} && git add $1.txt && git commit -m "Update $1" && git push
