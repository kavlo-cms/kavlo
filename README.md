# CMS

A Laravel 13 CMS with an Inertia/Vue 3 admin, page builder, themes, plugins, forms, media management, and revision history.

## Highlights

- Page management with drafts, publishing, duplication, trash, previews, and revision restore
- Visual page builder with reusable blocks
- Theme and plugin management from the admin
- Media library, menus, redirects, forms, and email template management
- Role and permission-based admin access
- Backups, cache tools, maintenance mode, search, sitemap, and health endpoint

## Stack

- PHP 8.4+
- Laravel 13
- Inertia.js + Vue 3 + TypeScript
- Vite
- SQLite by default

## Getting started

1. Install dependencies:

   ```bash
   composer install
   npm install
   ```

2. Prepare the environment:

   ```bash
   cp .env.example .env
   php artisan key:generate
   php artisan migrate
   ```

3. Seed the default admin account:

   ```bash
   php artisan db:seed
   ```

4. Start the app:

   ```bash
   composer dev
   ```

The default seeded admin credentials are:

| Email | Password |
| --- | --- |
| `admin@example.com` | `password` |

Change that password after first login.

## Useful commands

| Command | Purpose |
| --- | --- |
| `composer dev` | Run Laravel, queue worker, logs, and Vite together |
| `npm run dev` | Run the frontend dev server only |
| `npm run build` | Build frontend assets |
| `composer test` | Run Pint and the test suite |
| `composer ci:check` | Run frontend checks plus tests |

## Key routes

| Path | Purpose |
| --- | --- |
| `/` | Frontend page rendering |
| `/admin` | Admin dashboard |
| `/admin/login` | Admin login redirect |
| `/search` | Frontend search |
| `/sitemap.xml` | Sitemap |
| `/health` | Health check |

## Project structure

| Path | Purpose |
| --- | --- |
| `app/` | Laravel application code |
| `resources/js/` | Inertia/Vue admin UI |
| `resources/js/block-kit/` | Public block authoring components and helpers |
| `routes/` | Web and settings routes |
| `plugins/` | Local plugins |
| `themes/` | Frontend themes |
| `blocks/` | Reusable content blocks |
| `tests/` | Unit and feature tests |

## Block kit

Custom blocks can import a supported authoring surface from `@/block-kit` instead of depending on private admin internals.

```ts
import {
    BlockFieldInput,
    BlockInlineText,
    commonTextColorField,
    commonTextGradientField,
    commonWidthField,
    gradientField,
    pageLinkField,
    selectField,
} from '@/block-kit';
```

- **Components:** `BlockFieldInput`, `BlockColorInput`, `BlockGradientInput`, `BlockMediaInput`, `BlockPageLinkInput`, `BlockInlineText`
- **Schema helpers:** `textField`, `textareaField`, `urlField`, `numberField`, `selectField`, `toggleField`, `mediaField`, `pageLinkField`, `colorField`, `gradientField`
- **Shared presets:** `commonWidthField`, `commonTextColorField`, `commonButtonColorField`, `commonTextGradientField`, `commonButtonGradientField`, `commonHeroHeadlineGradientField`
- **Preview helpers:** exported from `@/block-kit` for block width, text tone, gradient text/background styles, button styles, and shared presets

## Notes

- The repository includes `database/database.sqlite` and the default environment uses SQLite.
- Uploaded plugins can be enabled from the admin and may run installers or migrations when activated.
- Set `APP_VERSION` in production if you want the admin dashboard to detect and notify about newer Kavlo CMS releases.
