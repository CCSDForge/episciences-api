#!/bin/bash
PHP_BIN="/usr/bin/php8.2"
deployDate=$(date "+%Y-%m-%d %X %z")
git fetch --all
git fetch --tags

git checkout ${1:-main} ## default $1=main
currentTag=$(git describe --tags --abbrev=0) ## get the most recent tag

if test $1 != 'main'; then
  currentTag=$1
fi

echo '<?php' > 'version.php';
echo "\$appVersion='$currentTag ($deployDate)';" >>  'version.php';

$PHP_BIN composer.phar install -o --no-dev --ignore-platform-reqs
$PHP_BIN composer.phar dump-env production
yarn ## Running yarn with no command will run yarn install
yarn encore production