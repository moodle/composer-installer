# A Composer installer for Moodle

This Composer plugin allows Moodle plugins to be installed into the correct locations within the Moodle directory.

## Usage

## As a package maintainer

Your plugin should have a `composer.json` which defines the following fields:

- `name` in the format `[githubuser]/moodle-[plugintype]_[pluginname]`
- `type` of `moodle-[plugintype]`.

## As a site administrator

You should depend upon this plugin:

```sh
composer require moodle/composer-installer
```
