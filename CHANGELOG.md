ChangeLog
=========

## Version 1.3.2 (2015-07-29)

Fix :

  - Fixing message when there is no validation item for GeoIP2.

## Version 1.3.1 (2015-07-10)

Features:

  - New 'type' option that takes 3 possible values : lite, legacy or geoip2. This option allows updating any of the 3 DB types.

## Version 1.2.7 (2013-11-18)

Features:

  - PHP errors on failures. Shell can now catch errors if PHP return code is not 0.
  - Dependency injection

## Version 1.2.6 (2013-11-13)

Features:

  - Extended validation (matches function to IP and expected result)
  - Checks for valid validation list
  - First archives the current db files before retrieving the new ones
  - README updated

Fixes:

  - Malformed validation CSV throws exception with instructions on how to format the CSV file

## Version 1.2.2 (2013-06-19)

Fixes:

  - Using exec to validate geoip API
  - Fixed constants for CSV files
  - Using full date times for archives folders
  - Using hashes only to compare DB versions
  - Moved maximum number of archives conatnt to config
  - Not writing DB contents to a file for sha1 generation, simply adding to a variable
  - Checking an archive before loading it during rollback
  - Removed a few useless logs

## Version 1.2.0 (2013-06-18)

Features:

  - New version system using sha1 of DB files
  - Blacklist

## Version 1.1.0 (2013-06-07)

Features:

  - Update mode
  - Rollback mode

## Version 1.0.0 (2013-06-05)

First release.