#!/bin/sh
# version="$(git describe --tags --abbrev=0)"
version=$1
echo "Set version $version"

echo "./appinfo/info.xml"
sed -i "s/<version>.*<\/version>/<version>$version<\/version>/g" ./appinfo/info.xml

echo "./composer.json"
sed -i "s/^\t\"version\": \".*\",/\t\"version\": \"$version\",/g" ./composer.json

echo "./package.json"
sed -i "s/^  \"version\": \".*\",/  \"version\": \"$version\",/g" ./package.json

echo "Commit to git"
git add ./appinfo/info.xml
git add ./composer.json
git add ./package.json
git commit -m "Version $version"
git tag "v$version"
