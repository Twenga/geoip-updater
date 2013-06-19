GeoIP Updater
==========

## Description
GeoIP Updater helps updating the GeoIP databases. It will retrieve DB files from MaxMind, archive older versions, validate
and then load the new DB files.

## Requirements

  - GeoIp

## Installing GeoIP Updater
In the directory of your choice, e.g. `~/geoip-updater`:

```bash
$ git clone git@gitlab.prod.twenga.lan:aa/geoip-updater.git .
$ cd geoip-updater
$ ./install.sh
```

More [Installation instructions](http://wiki.office.twenga.com/doku.php?id=aa:geoip_updater#installation)

## Documentation
[French documentation](http://wiki.office.twenga.com/doku.php?id=aa:geoip_updater)

## Usage

### Update

```bash
$ sudo php geoip-updater -v -m update
```

### Rollback

```bash
$ sudo php geoip-updater -v -m rollback
```