#!/bin/bash

# Script to install WordPress test environment.
# Usage: bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]

DB_NAME=${1-wordpress_test}
DB_USER=${2-root}
DB_PASS=${3-}
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}

WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-/tmp/wordpress}

# Avoid accidental deletion of production data.
if [[ $WP_TESTS_DIR == /* ]]; then
	TEST_DIR=$WP_TESTS_DIR
else
	TEST_DIR="$PWD/$WP_TESTS_DIR"
fi

if [[ $WP_CORE_DIR == /* ]]; then
	CORE_DIR=$WP_CORE_DIR
else
	CORE_DIR="$PWD/$WP_CORE_DIR"
fi

set -ex

download() {
	if [ `which curl` ]; then
		curl -s "$1" -o "$2";
	elif [ `which wget` ]; then
		wget -nv -O "$2" "$1"
	fi
}

mkdir -p "$TEST_DIR" "$CORE_DIR"

if [ $WP_VERSION == 'latest' ]; then
	LATEST=$(find . -maxdepth 1 -type f -name "wp-*.zip" | head -1)
	if [ -z "$LATEST" ]; then
		download https://wordpress.org/wordpress-latest.zip /tmp/wordpress-latest.zip
		unzip -q /tmp/wordpress-latest.zip -d "$CORE_DIR"
	fi
else
	if [ ! -f "/tmp/wordpress-$WP_VERSION.zip" ]; then
		download https://wordpress.org/wordpress-${WP_VERSION}.zip /tmp/wordpress-${WP_VERSION}.zip
	fi
	unzip -q /tmp/wordpress-${WP_VERSION}.zip -d "$CORE_DIR"
fi

if [ ! -f "$CORE_DIR/wp-settings.php" ]; then
	echo "WordPress failed to download. Exiting."
	exit 1
fi

if [ ! -d "$TEST_DIR/includes" ]; then
	download https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/includes/ "$TEST_DIR/includes" || {
		echo "Failed to download test includes"
		exit 1
	}
fi

cd "$CORE_DIR"

if [ ! -f wp-config-test.php ]; then
	download https://develop.svn.wordpress.org/tags/${WP_VERSION}/wp-tests-config-sample.php "$CORE_DIR/wp-config-test.php"
	sed -i "s/youremptytestdbnamehere/$DB_NAME/g" "$CORE_DIR/wp-config-test.php"
	sed -i "s/yourusernamehere/$DB_USER/g" "$CORE_DIR/wp-config-test.php"
	sed -i "s/yourpasswordhere/$DB_PASS/g" "$CORE_DIR/wp-config-test.php"
	sed -i "s|localhost|${DB_HOST}|g" "$CORE_DIR/wp-config-test.php"
fi

mkdir -p "$CORE_DIR/wp-content/plugins/wordpress-tests-lib"
ln -s "$TEST_DIR/includes" "$CORE_DIR/wp-content/plugins/wordpress-tests-lib/includes"

echo "WordPress test environment ready!"
