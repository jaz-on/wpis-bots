# WPIS Bots

One **WordPress plugin** (Mastodon + Bluesky ingestion) for **WordPress Is…**. Ships with shared `lib/`, **Action Scheduler** via Composer, and two settings screens. Requires [**wpis-plugin**](https://github.com/jaz-on/wpis-plugin) (core quotes, dedup, moderation).

**Docs (French):** [Guide administrateur](docs/GUIDE-ADMIN.md), [API limits](docs/LIMITES-API-ET-BONNES-PRATIQUES.md), [resources](docs/RESSOURCES.md). Override doc URLs with the `wpis_bots_docs_base_url` filter.

Ingestion calls **`wpis_submit_quote_candidate()`** from wpis-plugin.

## Repository layout

| Path | Role |
|------|------|
| `wpis-bots.php`, `lib/`, `src/`, `vendor/` | **Plugin** (this is what WordPress loads) |
| `docs/` | Administrator guide (GitHub only; not required on the server) |
| `tests/`, `phpunit.xml` | PHPUnit for shared `lib` helpers |

## Requirements

- WordPress 6.9+ and PHP 8.2+
- **wpis-plugin** active
- After `composer install --no-dev`, **Action Scheduler** is in `vendor/`. Without it, use the standalone [Action Scheduler](https://wordpress.org/plugins/action-scheduler/) plugin or WP-Cron fallback
- Optional: **Polylang** (language slug in bot settings)

## Install (classic upload)

1. **Production zip (recommended):** use the **`wpis-bots.zip`** asset attached to a [GitHub Release](https://github.com/jaz-on/wpis-bots/releases) (built by CI with `composer install --no-dev`). In WordPress: **Plugins → Add New → Upload** → activate **WordPress Is… Bots**.

2. **From a git clone:** run `composer install --no-dev`, then zip a folder named `wpis-bots` containing only: `wpis-bots.php`, `composer.json`, `composer.lock`, `lib/`, `src/`, `vendor/`. Upload that zip (same as the release asset layout).

3. Re-save **Settings → WPIS Mastodon Bot** and **WPIS Bluesky Bot** once after upgrades so schedules register.

## Git Updater

The main file **`wpis-bots.php` is at the repository root**, so `GitHub Plugin URI: https://github.com/jaz-on/wpis-bots` matches what [Git Updater](https://github.com/afragen/github-updater) expects. Prefer **release ZIPs** for updates if you want a clean tree without `docs/` or `tests/` on the server.

## Development

```bash
composer install && composer lint && composer test
```

## Action Scheduler vs WP-Cron

With Action Scheduler available (bundled, standalone plugin or e.g. WooCommerce), bots use the `wpis-bots` group. Otherwise WP-Cron runs the polls. Deactivating **WordPress Is… Bots** clears both bots’ schedules.
