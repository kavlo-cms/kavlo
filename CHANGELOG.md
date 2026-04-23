# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.2.0] - 2026-04-23

### Added
- Initial repository documentation, including the project README and release-facing docs.
- A reusable Monaco-based code editor under `@/block-kit` for shared HTML and JavaScript authoring surfaces.
- Builder-first Content block support so rich content can be placed as a draggable block in page layouts.
- Theme-defined text color presets and shared gradient tooling for core page-builder blocks.
- Detailed GitHub wiki authoring guides for plugins, themes, and blocks.
- Themed public error pages for 403, 404, 429, 500, and 503 responses with safe theme fallback behavior.
- Backend feature coverage for scripts, theme activation, maintenance mode, cache clearing, redirects, menus, form submissions, email templates, and media lifecycle flows.
- Page-level multilingual support with admin-managed site languages, per-locale page translations, default-locale routes at `/slug`, and translated routes at `/<locale>/slug`.

### Changed
- CI workflows now use check-only linting steps and build in non-Lando environments.
- Frontend linting and type-checking baselines were corrected for blocks, themes, and admin screens.
- Project metadata was updated from starter defaults to Kavlo CMS branding.
- The admin UI now uses Kavlo-first branding across titles, logos, navigation links, runtime labels, and shared defaults.
- The page editor now uses shared block-kit controls and a reusable Monaco editor instead of isolated code-editor implementations.
- Theme-aware controllers and providers now fall back to the default theme slug consistently when active theme state is missing or unavailable.
- General settings and the page editor now expose locale-aware language management and translation editing flows.
- Public menus, page metadata, canonical URLs, and `hreflang` alternates now resolve against the active locale.

### Fixed
- Removed redundant plugin activation UI.
- Restored scrolling behavior in the page editor revisions sidebar.
- Fixed multiple admin route-helper typing issues that blocked frontend type-checking.
- Corrected GitHub Actions test/build configuration for the current PHP and Wayfinder setup.
- Fixed admin/site branding drift that still surfaced Laravel-era defaults in browser titles, error pages, and fallback labels.
- Fixed theme-config fallback paths that could omit editor theme settings in fresh or incomplete environments.
- Fixed localized route resolution so normal default-language paths like `/about` do not get mistaken for locale-prefixed routes.
- Fixed site-language cache hydration so serialized locale data does not break Lando/web requests with `__PHP_Incomplete_Class` errors.

### Upgrade Notes
- Run the new database migrations introduced for multilingual pages: `site_languages`, `page_translations`, and revision locale support.
- Set `APP_VERSION=1.2.0` in the deployed environment so admin release detection reports the correct version.
- Clear caches after deploy so localized routes, settings, and page/menu payloads rebuild against the new schema.

## [1.0.0]

### Added
- Laravel CMS foundation with Inertia/Vue admin UI.
- Page builder, revisions, themes, plugins, forms, media library, redirects, menus, backups, cache tools, health checks, analytics, and GraphQL/DataHub support.
