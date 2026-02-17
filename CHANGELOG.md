# Change log

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/).

The format of this change log follows the advice given at [Keep a CHANGELOG](https://keepachangelog.com).

## [Unreleased]

### Fixed

- Correct `moodle-plugin-` skipping to be only skip for `moodle-package-`

## [1.2.0] - 2026-02-17

### Added

- Support for `moodle-package-` prefix which is not a moodle plugintype.

### Fixed

- Fix linting issues.

### Removed

- Removed unit tests because they are incorrectly written and rely upon runtime
  Composer APIs which are not easily mocked during testing.

## [1.1.0] - 2025-12-23

### Fixed

- Respect root package `haspublicdir` and `install-path` correctly
- Respect the `haspublicdir` of the installed `moodle/moodle`, rather than the available `moodle/moodle` package.

## [1.0.0] - 2025-12-17

### Added

- Initial release
