#!/bin/bash
ROOT_DIR=$(pwd)
CONF_DIR="${ROOT_DIR}/conf"

if { [ -f "$CONF_DIR/config.php" ] ; } then
	echo "Config file '$CONF_DIR/config.php' already existing."
else
	echo "Copy config file from '$CONF_DIR/config-dist.php' to '$CONF_DIR/config.php'"
	cp -p -n "$CONF_DIR/config-dist.php" "$CONF_DIR/config.php"
fi