GeoIP Updater
==========

## Description

GeoIP Updater is a PHP program that helps updating the GeoIP databases :
  - Retrieve DB files from MaxMind (based on a list of URLs stored as CSV)
  - Archive older DB files
  - Validate DB files
  - Load the new DB files in the GeoIP DB directory

This program works with the 3 types of DB files :
  - Lite (Free version)
  - Legacy (Paid version, old .dat format)
  - GeoIP2 (Paid version, new .mmdb format)

## Requirements

  - PHP 5.x
  - Lite and Legacy (for validating DB's) : GeoIp => http://us2.php.net/manual/en/geoip.setup.php

## Installation

In the directory of your choice :

```bash
$ git clone https://github.com/Twenga/geoip-updater.git
$ cd geoip-updater
$ ./install.sh
```

The install script will simply create working directories such as /tmp/GeoIP, /usr/share/GeoIP_Archives. These paths can be edited in conf/config.php

## Configuration

### Folders

GeoIP needs to know 3 paths : 
- Directory to copy the final DB files
- Directory to archive DB files
- A temporary directory that will be used for intermediate tasks

These configuration can be edited for each type of DB with the following constants (see conf/config.php) : 
- XXX_DB_PATH
- XXX_DB_ARCHIVE_PATH
- XXX_DB_TMP_PATH

Where XXX can be GEOIP_LITE, GEOIP_LEGACY or GEOIP2.

Notes : 
- When using Lite and Legacy through the native PHP API will, by default, use the folder /usr/share/GeoIP.
- For GeoIP2, there is no default folder, Paths to GeoIP DB files must be specified when using the GeoIP PHP API.

### License key

**Legacy** and **GeoIP2** are paid versions of GeoIP. Accessing the available DB files through HTTP requires sending a license key as part of the request.
 
You can configure the **MAXMIND_LICENSE_KEY** constant in conf/config.php to specify a valid MaxMind license key.

### Validation

For DB files validation, GeoIP Updater will use PHP. There are 2 PHP API's depending on the DB type : 
  - Lite and Legacy : PHP API  => http://php.net/manual/en/ref.geoip.php
  - GeoIP2 : MaxMind PHP API for GeoIP2 => https://github.com/maxmind/GeoIP2-php

These API's should be available in order to enable DB files validation.

#### Validation items (Lite and Legacy)

For validating a set of DB files, GeoIP Updater will use a list of validation items. An item is made of : 
  - A GeoIp function name (PHP API)
  - A host/IP 
  - An expected result. 

Validation items are stored in a CSV file inc/validation_list.csv which you can populate with your own validation items as follows :

```
"geoip_country_code_by_name","xx.xx.xx.xx","Country code"
"geoip_country_code_by_name","xx.xx.xx.xx","Country code"
```

NOTE : Use commas as separators and double quotes as enclosure.

For now, the expected result can only be specified as a string, that means we can't validate that geoip_record_by_name() works as it returns an array.

#### Validation items (GeoIP2)

Validation items for GeoIP2 are stored in a CSV file inc/validation_list_geoip2.csv and are formatted as follows : 

```
"city->name","xx.xx.xx.xx","City name","GeoIP2-City.mmdb"
"country->name","xx.xx.xx.xx","Country name","GeoIP2-City.mmdb"
```

NOTES :
- You only specify the NAME of the DB file, the path to the file is specified by GEOIP2_DB_PATH in conf/config.php

### DB files URLs

The files `inc/db_url_list_XXX.csv` contain lists of URLs to MaxMind GeoIP DB files. GeoIP Updater comes with default lists for each type, which you can update as needed.

Default URLs come from :
  - http://dev.maxmind.com/geoip/legacy/geolite (Lite)
  - https://www.maxmind.com/en/download_files (Legacy and GeoIP2)

## Usage

### Update

```bash
$ sudo php geoip-updater.php -v -m update
```

When a set of DB files is retrieved, GeoIP Updater computes a 'version' hash (sha1 of all DB files contents) and will archive these files in a directory for that version. When a set of DB files is not valid, the version is blacklisted and will never be loaded again.

### Rollback

```bash
$ sudo php geoip-updater.php -v -m rollback
```

A rollback will attempt to load the previous set of DB files from the archives. If there are no older archived version, rollback will stop. If the loaded archive is not valid, the rollback will attempt to load the next older version and so on.

## Options

### Mode : -m \[mode\]

Specifies GeoIP Updater mode.

Values :
  - update
  - rollback

### Type : -t \[type\] (optional)

Specifies the type of DB files to update.

Values :
  - Lite (default)
  - Legacy
  - GeoIP2
  
### Verbose : -v (optional)
  
Specifies whether GeoIP should output logs to the console.

#### Notes
Files are packaged differently from a type to another :

<table>
    <tr>
        <td>Type</td>
        <td>Package</td>
        <td>City DB (=> path/to/destination)</td>
        <td>Country DB (=> path/to/destination</td>
    </tr>
     <tr>
        <td>Lite</td>
        <td>Gzip</td>
        <td>GeoIPLiteCity.dat => /usr/share/GeoIP/GeoIPCity.dat</td>
        <td>GeoIP.dat => /usr/share/GeoIP/GeoIP.dat</td>
    </tr>
    <tr>
        <td>Legacy</td>
        <td>Tar.gz</td>
        <td>/GeoIP-133_XXX/GeoIPCity.dat => /usr/share/GeoIP/GeoIPCity.dat</td>
        <td>/GeoIP-106_XXX/GeoIP-106_XXX.dat => /usr/share/GeoIP/GeoIP.dat</td>
    </tr>
     <tr>
        <td>GeoIP2</td>
        <td>Tar.gz</td>
        <td>/GeoIP2-City_XXX/GeoIP2-City.mmdb</td>
        <td>/GeoIP2-Country_XXX/GeoIP2-Country.mmdb</td>
    </tr>
</table>

XXX = DB file timestamp

Notes :
- For Lite and Legacy types, GeoIP Updater renames .dat files to GeoIP.dat and GeoIPCity.dat
- For GeoIP2, it's up to the application to load the appropriate GeoIP2 MMDB.

## Principles

### DB versions

When using Lite or Legacy with the PHP API, geoip_database_info() returns the DB files versions but it has to be called for every DB file and it would be too much of a hassle to keep track of each file's version.

GeoIP Updater builds an 'overall' version for a given set of db files. It's basically a SHA-1 of the files contents.

The version is then written to a hash file, stored along with the db files. Next time we need this DB set version, we can just read the hash file.

### Archives

Every set of DB files is archived in a directory specified in the conf/config.php file. When updating the DB files, the current files are archived, the newly retrieved files are also archived.

If a version is already archived, GeoIp Updater will just tell you about it.

The maximum number of archives can be configured in the conf/config.php file.

### Validation

**For Lite and Legacy only.**

To make sure that the loaded DB files actually work, we simply call GeoIp functions and check that they return expected results for given parameters. The functions, IP/hosts and expected results are to be listed in the inc/validation_list.csv file.

The GeoIp functions are executed through exec() to prevent GeoIP Updater from crashing if the DB files are corrupted.

## Limitations

It is not currently possible to validate geoip_record_by_name(). It returns an array and would not match a string as specified in the CSV validation items list.

GeoIP Updater MUST be executed by 'root' as it needs to write in /usr/share/

### To do

Unit tests