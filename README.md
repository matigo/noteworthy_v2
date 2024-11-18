# Noteworthy

### A Simple Note Management Platform

The Following is a list of notes regarding the use and configuration of Noteworthy.

## General Requirements

You will need:

* a web server running Apache 2.4.x and PHP 8.0 or newer (Earlier versions *may* work, but it has not been tested)
* PostgreSQL 14 or above (Earlier versions *may* work, but it has not been tested)

## LAPP Configuration Notes

### Linux Notes

This code has been tested to run on Ubuntu Server 24.04 LTS. That said, it should run on any version of Linux released in the last 5 years. Your mileage may very. Test often. Test well.

### Apache Notes

The following modules must be loaded:

* mod-php
* mod-rewrite
* mod-headers

### Database Notes

PostgreSQL 14 is the database engine used for all testing, development, and deployment.

### PHP Notes

The following modules are required:

* mbstring
* dev
* xml
* zip
* json
* pgsql
* gd
* curl
* pear
* redis

### Other Setup Requirements

In addition to the basic LAPP stack, the following items need to be taken into account.

* Apache must be configured to honour the `.htaccess` overrides
* Noteworthy can use Amazon S3 storage for files, but is off by default
* Noteworthy can enforce HTTPS redirects (and ideally should use it)
* Noteworthy is designed to run on servers with as little as 512MB RAM

### Basic Web Server -- Minimum Recommended

* Ubuntu Server 20.04 LTS
* Dual-Core CPU (x86/x64/ARM)
* 2GB RAM
* 10GB Storage

### Windows Configuration Notes

It is not recommended that Noteworthy run on Windows in a WAMP-like fashion. It has not been tested and, as of this writing, will not be supported.

### MAPP Configuration Notes

Do not do this. Please.

### XAMPP Configuration Notes

If you're running Linux, *RUN LINUX*. There is no need for XAMPP when Apache, PostgreSQL, and PHP are already well-supported.

### Optional Components

There are some optional pieces to the puzzle that might make things a little better. These things include:

* something to drink
* good music
* a faithful dog
