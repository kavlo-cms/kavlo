<?php

/**
 * Midnight Blue theme — hook registrations.
 *
 * This file is loaded automatically by the CMS after the theme is activated.
 * Use Hook::addFilter / Hook::addAction to extend the CMS.
 *
 * Admin nav example (commented out):
 *
 *   use App\Facades\Hook;
 *
 *   Hook::addFilter('admin.nav', function (array $items): array {
 *       $items[] = [
 *           'group' => 'Content',          // 'Content' | 'Structure' | 'Settings' | any custom label
 *           'title' => 'Theme Options',
 *           'href'  => '/admin/theme-options',
 *           'icon'  => 'paintbrush',       // optional — kebab-case lucide icon name
 *       ];
 *       return $items;
 *   });
 */
