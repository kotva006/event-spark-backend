#!/bin/sh
# Upgrades/Downloads the latest release of the Slim Framework.
# http://www.slimframework.com/

echo "Downloading latest release of Slim..."
mkdir slimtemp
cd slimtemp
curl -kL# http://github.com/codeguy/Slim/zipball/master > slim_latest.zip
unzip -q slim_latest.zip *Slim/*
mkdir -p ../Slim
rsync -ah codeguy-Slim-*/Slim/ ../Slim/
rm -rf ../slimtemp
echo "Slim Framework Updated."