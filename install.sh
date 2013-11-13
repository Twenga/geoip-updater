#!/bin/bash
ROOT_DIR=$(pwd)
CONF_DIR="${ROOT_DIR}/conf"
INC_DIR="${ROOT_DIR}/inc"

if { [ -f "$CONF_DIR/config.php" ] ; } then
	echo "Config file '$CONF_DIR/config.php' already exists."
else
	echo "Copy config file from '$CONF_DIR/config-dist.php' to '$CONF_DIR/config.php'"
	cp -p -n "$CONF_DIR/config-dist.php" "$CONF_DIR/config.php"
fi

if { [ -f "$INC_DIR/db_url_list.csv" ] ; } then
	echo "Config file '$INC_DIR/db_url_list.csv' already exists."
else
	echo "Copy config file from '$INC_DIR/db_url_list-dist.csv' to '$INC_DIR/db_url_list.csv'"
	cp -p -n "$INC_DIR/db_url_list-dist.csv" "$INC_DIR/db_url_list.csv"
fi

if { [ -f "$INC_DIR/validation_list.csv" ] ; } then
	echo "Config file '$INC_DIR/validation_list.csv' already exists."
else
	echo "Copy config file from '$INC_DIR/validation_list-dist.csv' to '$INC_DIR/validation_list.csv'"
	cp -p -n "$INC_DIR/validation_list-dist.csv" "$INC_DIR/validation_list.csv"
fi