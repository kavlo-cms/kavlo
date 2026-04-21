# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Initial repository documentation, including the project README and release-facing docs.

### Changed
- CI workflows now use check-only linting steps and build in non-Lando environments.
- Frontend linting and type-checking baselines were corrected for blocks, themes, and admin screens.
- Project metadata was updated from starter defaults to Kavlo CMS branding.

### Fixed
- Removed redundant plugin activation UI.
- Restored scrolling behavior in the page editor revisions sidebar.
- Fixed multiple admin route-helper typing issues that blocked frontend type-checking.
- Corrected GitHub Actions test/build configuration for the current PHP and Wayfinder setup.

## [1.0.0]

### Added
- Laravel CMS foundation with Inertia/Vue admin UI.
- Page builder, revisions, themes, plugins, forms, media library, redirects, menus, backups, cache tools, health checks, analytics, and GraphQL/DataHub support.
