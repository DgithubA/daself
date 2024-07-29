#!/bin/sh
bash ./up_vendor.sh
echo "=====login====="
sudo docker run --rm -it --init -v $PWD:/app hub.madelineproto.xyz/danog/madelineproto php /app/bot.php