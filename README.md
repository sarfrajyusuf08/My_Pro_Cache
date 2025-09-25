# My Pro Cache

My Pro Cache delivers full-page caching, automated purging, preloading, optimization toggles, and CDN helpers for WordPress 6.1+.

## Highlights

- Disk/Redis/Memcached storage drivers via a pluggable interface
- Granular TTL rules, exclusions, purge triggers, and admin bar shortcuts
- REST API and WP-CLI commands for automating purge/preload/status
- Dashboard with hit/miss ratio, cache size, quick actions, and debug log viewer
- 16-tab settings UI using the WordPress Settings API with tooltips and safe defaults reset
- Preload queue with configurable concurrency, interval, and sitemap sources
- Configurable drop-in (`advanced-cache.php`) served directly from `wp-content/cache/my-pro-cache`

## Requirements

- WordPress 6.1+
- PHP 8.0+

## Getting Started

1. Activate the plugin from the WordPress dashboard.
2. Visit **My Pro Cache → Dashboard** to enable caching, purge, or preload.
3. Configure the remaining modules from their respective tabs.
4. Use `wp my-pro-cache status` / `purge` / `preload` for scripted workflows.

## Support

Open the **Help** tab inside the plugin for documentation links and to generate a system report.

