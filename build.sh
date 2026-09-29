#!/usr/bin/env bash
# Builds an installable Joomla package: dist/mod_cblistmodule-<version>.zip
set -euo pipefail

cd "$(dirname "$0")"

VERSION=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' mod_cblistmodule.xml | head -n 1)
NAME="mod_cblistmodule-${VERSION}"

rm -rf dist
mkdir -p "dist/${NAME}"

cp -r mod_cblistmodule.xml script.php services src tmpl language LICENSE "dist/${NAME}/"

(cd "dist/${NAME}" && zip -qr "../${NAME}.zip" .)
rm -rf "dist/${NAME}"

echo "Built dist/${NAME}.zip"
