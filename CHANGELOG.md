ChangeLog
=========

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