# WPIS Bots

One **WordPress plugin** (Mastodon + Bluesky ingestion) for **WordPress Is…**. Ships with shared `lib/`, **Action Scheduler** (vendored in `vendor/`), and two settings screens. Requires [**wpis-plugin**](https://github.com/jaz-on/wpis-plugin) (core quotes, dedup, moderation).

**Docs (French):** [Guide administrateur](docs/GUIDE-ADMIN.md), [API limits](docs/LIMITES-API-ET-BONNES-PRATIQUES.md), [resources](docs/RESSOURCES.md). Override doc URLs with the `wpis_bots_docs_base_url` filter.

Ingestion calls **`wpis_submit_quote_candidate()`** from wpis-plugin.

## Repository layout

| Path | Role |
|------|------|
| `wpis-bots.php`, `lib/`, `src/`, `vendor/` | **Plugin** (this is what WordPress loads) |
| `docs/` | Administrator guide (optional on the server) |
| `tests/`, `phpunit.xml` | PHPUnit for shared `lib` helpers |

## Requirements

- WordPress 6.9+ and PHP 8.2+
- **wpis-plugin** active
- **Action Scheduler** is already in `vendor/` for zip installs. Without those files, use the standalone [Action Scheduler](https://wordpress.org/plugins/action-scheduler/) plugin or WP-Cron fallback
- Optional: **Polylang** (language slug in bot settings)

## Install (classic upload)

1. **GitHub “Download ZIP”:** on the repo page use **Code → Download ZIP**. Unzip, upload the folder via **Plugins → Add New → Upload** (or copy it into `wp-content/plugins/`), then activate **WordPress Is… Bots**. The archive may be named `wpis-bots-main`; you can rename the folder to `wpis-bots` if you like.

2. **Release asset:** the **`wpis-bots.zip`** attached to a [GitHub Release](https://github.com/jaz-on/wpis-bots/releases) contains only the plugin subtree (`wpis-bots/wpis-bots.php` …) — same WordPress upload flow, slightly smaller.

3. After an upgrade, re-save **Settings → WPIS Mastodon Bot** and **WPIS Bluesky Bot** once so schedules register.

## Git Updater

`GitHub Plugin URI: https://github.com/jaz-on/wpis-bots` matches what [Git Updater](https://github.com/afragen/github-updater) expects (`wpis-bots.php` at the repository root).

## Development

`vendor/` in git contains **production** dependencies only (~Action Scheduler). For **PHPCS / PHPUnit**, run once after clone:

```bash
composer install && composer lint && composer test
```

That adds `require-dev` packages on top of the lockfile. When you **change** dependencies, run `composer update`, then `composer install --no-dev`, commit `composer.json`, `composer.lock`, and `vendor/`, and run `composer install` again locally for dev tools.

## Action Scheduler vs WP-Cron

With Action Scheduler available (bundled, standalone plugin or e.g. WooCommerce), bots use the `wpis-bots` group. Otherwise WP-Cron runs the polls. Deactivating **WordPress Is… Bots** clears both bots’ schedules.
