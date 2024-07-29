#!/bin/sh

if [ "$1" = "-f" ]; then
    git pull -f origin main
else
    git pull origin main
fi