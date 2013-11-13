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

### Validation items

When validating a new set of DB files, GeopIP Updater will use a list of validation items. An item is made of GeoIp function name, a host/IP and the expected result. Validation items are stored in a CSV file inc/validation_list.csv which you can populate with your own validation items as follow :

```
[GEOIP_FUNCTION],[IP_HOST],[EXPECTED_RESULT]
[GEOIP_FUNCTION],[IP_HOST],[EXPECTED_RESULT]
```

For now, the expected result can only be specified as a string, that means we can't validate that geoip_record_by_name() works as it returns an array.

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

## Principles

### DB versions

geoip_database_info() already returns the DB files versions but it has to be called for every DB file and it would be too much of a hassle to keep track of each file's version.

GeoIP Updater builds an 'overall' version for a given set of db files. It's basically a SHA-1 of the files contents.

The version is then written to a hash file, stored along with the db files. Newt time we need this DB set version, we can just read the hash file.

### Archives

Every set of DB files is archived in a directory specified in the conf/config.php file. When updating the DB files, the current files are archived, the newly retrieved files are also archived.

If a version is already archived, GeoIp Updater will just tell you about it.

The maximum number of archives can be configured in the conf/config.php file.

### Validation

To make sure that the loaded DB files actually work, we simply call GeoIp functions and check that they return expected results for given parameters. The functions, IP/hosts and expected results are to be listed in the inc/validation_list.csv file.

The GeoIp functions are executed through exec() to prevent GeoIP Updater from crashing if the DB files are corrupted.

## Limitations

It is not currently possible to validate geoip_record_by_name(). It returns an array and would not match a string as specified in the CSV validation items list.

GeoIP Updater MUST be executed by 'root' as it needs to write in /usr/share/

### To do

Dependencies injection + Unit tests