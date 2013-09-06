GeoIP Updater
==========

## Description
GeoIP Updater is a PHP tool that helps updating the GeoIPLite databases. It will retrieve DB files from MaxMind (based on a list of URLs stored as CSV), archive older DB files, validate
and then load the new DB files in the GeoIP DB directory.

IMPORTANT : GeoIP Updater will attempt to write DB files in the GeoIP DB directory (/usr/share/GeoIP by default) which belongs to user root. Therefore, GeoIP requires to be executed as root.

## Requirements

  - PHP 5.x
  - GeoIp

## Installation
In the directory of your choice, e.g. `~/geoip-updater`:

```bash
$ git clone git@gitlab.prod.twenga.lan:aa/geoip-updater.git
$ cd geoip-updater
$ ./install.sh
```

## Configuring GeoIP Updater

### IP addresses

When validating a new set of DB files, GeopIP Updater will use a list of known IP addresses and check that GeoIP returns expected country codes. IP addresses are stored in `inc/ip_list.csv` which you can populate with your own IP addresses to check. If the file is empty, no validation will be performed. IP addresses must be listed as follows :
`[IP_ADDRESS],[COUNTRY_CODE]
[IP_ADDRESS],[COUNTRY_CODE]
...`

### DB files URLs

The file `inc/db_url_list.csv` contains a list of URLs to MaxMind GeoIPLite DB files. GeoIP Updater comes with a default list, which you can update as needed.

Note : Default URLs come from http://dev.maxmind.com/geoip/legacy/geolite

## Usage

### Update
```bash
$ sudo php geoip-updater -v -m update
```

When a set of DB files is retrieved, GeoIP Updater computes a 'version' hash (sha1 of all DB files contents) and will archive these files in a directory for that version. When a set of DB files are not valid, the version is blacklisted and will never be loaded again.

### Rollback
```bash
$ sudo php geoip-updater -v -m rollback
```

A rollback will attempt to load the previous set of DB files from the archives. If there are no older archived version, rollback will stop. If the loaded archive is not valid, the rollback will attempt to load the next older version and so on.

## Working with GeoIP Updater

GeoIP Updater can be used manually but it can also be executed automatically by Crontab. If several servers are to be updated, it may be a good practice to run GeoIP Updater on a "master" server which will validate the DB files and then deploy them to other servers.
