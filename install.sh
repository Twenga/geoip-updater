#!/bin/bash
ROOT_DIR=$(pwd)
CONF_DIR="${ROOT_DIR}/conf"
RESOURCES_DIR="${ROOT_DIR}/resources"

# Configuration
if { [ -f "$CONF_DIR/config.php" ] ; } then
	echo "Config file '$CONF_DIR/config.php' already exists. Please remove it before running install script again."
else
	echo "Copy config file from '$CONF_DIR/config-dist.php' to '$CONF_DIR/config.php'"
	cp -p -n "$CONF_DIR/config-dist.php" "$CONF_DIR/config.php"
fi

# DB lists
# Lite
if { [ -f "$RESOURCES_DIR/db_url_list_lite.csv" ] ; } then
	echo "Config file '$RESOURCES_DIR/db_url_list_lite.csv' already exists. Please remove it before running install script again."
else
	echo "Copy config file from '$RESOURCES_DIR/db_url_list_lite-dist.csv' to '$RESOURCES_DIR/db_url_list_lite.csv'"
	cp -p -n "$RESOURCES_DIR/db_url_list_lite-dist.csv" "$RESOURCES_DIR/db_url_list_lite.csv"
fi

# Legacy
if { [ -f "$RESOURCES_DIR/db_url_list_legacy.csv" ] ; } then
	echo "Config file '$RESOURCES_DIR/db_url_list_legacy.csv' already exists. Please remove it before running install script again."
else
	echo "Copy config file from '$RESOURCES_DIR/db_url_list_legacy-dist.csv' to '$RESOURCES_DIR/db_url_list_legacy.csv'"
	cp -p -n "$RESOURCES_DIR/db_url_list_legacy-dist.csv" "$RESOURCES_DIR/db_url_list_legacy.csv"
fi

# GeoIP2
if { [ -f "$RESOURCES_DIR/db_url_list_geoip2.csv" ] ; } then
	echo "Config file '$RESOURCES_DIR/db_url_list_geoip2.csv' already exists. Please remove it before running install script again."
else
	echo "Copy config file from '$RESOURCES_DIR/db_url_list_geoip2-dist.csv' to '$RESOURCES_DIR/db_url_list_geoip2.csv'"
	cp -p -n "$RESOURCES_DIR/db_url_list_geoip2-dist.csv" "$RESOURCES_DIR/db_url_list_geoip2.csv"
fi

# Validation

#GeoIP (Lite and Legacy)
if { [ -f "$RESOURCES_DIR/validation_list.csv" ] ; } then
	echo "Config file '$RESOURCES_DIR/validation_list.csv' already exists. Please remove it before running install script again."
else
	echo "Copy config file from '$RESOURCES_DIR/validation_list-dist.csv' to '$RESOURCES_DIR/validation_list.csv'"
	cp -p -n "$RESOURCES_DIR/validation_list-dist.csv" "$RESOURCES_DIR/validation_list.csv"
fi

#GeoIP2
if { [ -f "$RESOURCES_DIR/validation_list.csv" ] ; } then
	echo "Config file '$RESOURCES_DIR/validation_list_geoip2.csv' already exists. Please remove it before running install script again."
else
	echo "Copy config file from '$RESOURCES_DIR/validation_list_geoip2-dist.csv' to '$RESOURCES_DIR/validation_list_geoip2.csv'"
	cp -p -n "$RESOURCES_DIR/validation_list_geoip2-dist.csv" "$RESOURCES_DIR/validation_list_geoip2.csv"
fi