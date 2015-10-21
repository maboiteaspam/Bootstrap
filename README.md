# C - Bootstrap

Bootstrap module for applications based on a regular C framework setup.

C framework is a lightweight framework dedicated to frontend development for php applications.

Based on top of silex and symfony.

## Install

You can install it alone along your project with composer

```
php composer require maboiteaspam/Bootstrap
```

You can also let `c2-bin`, `C command&control binary` tool do it for you.

## Usage

The CLI interface comes with a number of pre defined tasks.

```
Silex - C Edition version 0.1

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  help           Displays help for a command
  list           Lists commands
 cache
  cache:init     Generate cached items
  cache:update   Update cached items given a relative file path and the related File System action
 db
  db:init        Initialize your database. Clear all, construct schema, insert fixtures.
  db:refresh     Refresh your database.
 fs-cache
  fs-cache:dump  Dumps all paths to watch for changes.
 http
  http:bridge    Generate an http bridge file for your webserver.

```
