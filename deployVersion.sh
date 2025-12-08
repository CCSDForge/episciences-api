#!/bin/bash
PHP_BIN="/usr/bin/php8.2"
deployDate=$(date "+%Y-%m-%d %X %z")
git fetch --all
git fetch --tags

#########################################
## If no parameters are specified, the code is deployed from the main branch by default [$1=main].
currentEnv=${1:-main}
#########################################
encoreEnv="production"
composerInstallOptions="-o --no-dev --ignore-platform-reqs"
currentTag=$(git describe --tags --abbrev=0) ## get the most recent tag

case "$currentEnv" in
    "main")
        # nothing to do for main
        ;;
    "develop"|"dev")
        composerInstallOptions=""
        currentEnv="develop"
        encoreEnv="dev"
        currentTag="$currentEnv"
        ;;
    *)
        currentTag="$currentEnv"
        ;;
esac

echo '<?php' > 'version.php';
echo "\$appVersion='$currentTag ($deployDate)';" >>  'version.php';

git checkout $currentEnv
git pull

$PHP_BIN composer.phar dump-env $currentEnv
$PHP_BIN composer.phar install $composerInstallOptions

yarn ## Running yarn with no command will run yarn install
yarn encore  $encoreEnv
