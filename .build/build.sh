#!/bin/sh
tag="$(git describe --tags --abbrev=0)"
output="../dist/churchtools_integration_$tag.tar.gz"
cd .build/tmp

echo "Update tmp git"
git reset --hard HEAD
git checkout master
git pull --rebase --force

echo "composer install"
composer install --no-ansi --no-dev --no-interaction --no-plugins --no-progress --no-scripts --optimize-autoloader

echo "npm install"
npm install --production 

echo "npm run build"
npm run build --no-interaction --no-progress

echo "Start packing"
rm $output
tar -czvf $output appinfo css img js l10n lib src templates
echo "Output: $output"
