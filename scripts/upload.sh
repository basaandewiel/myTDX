#!/bin/bash
set -e
cd /home/jeremie/workspace/www/td/
opt=
rsync $opt --progress -a --exclude .git --exclude scripts --exclude *.db * www-data@tecrd.com:/home/www/www.tecrd.com/td/
