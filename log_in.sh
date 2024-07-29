#!/bin/sh
sudo docker run --rm -it --init -v $PWD/app:/app hub.madelineproto.xyz/danog/madelineproto php /app/bot.php