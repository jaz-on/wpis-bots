# WPIS Bots

One **WordPress plugin** (Mastodon + Bluesky ingestion) for **WordPress Is…**. Ships with shared `lib/`, PSR-4 `src/`, and two settings screens. Requires [**WordPress Is… Core**](https://github.com/jaz-on/wpis-core) (folder `wpis-core/`, `wpis-core.php`) and the [**Action Scheduler**](https://wordpress.org/plugins/action-scheduler/) plugin (WordPress lists both under **Plugins →** requirements with WordPress 6.5+).

**Docs (French, in `docs/`):** [guide administrateur](docs/guide-admin.md), [limites API](docs/limites-api-et-bonnes-pratiques.md), [ressources](docs/ressources.md). Override doc URLs with the `wpis_bots_docs_base_url` filter (value must be a base URL that ends with `/`, default `https://github.com/jaz-on/wpis-bots/blob/main/docs/`). A WordPress.org-style [`readme.txt`](readme.txt) (English) also sits at the repo root.

Ingestion calls **`wpis_submit_quote_candidate()`** from **WordPress Is… Core** (wpis-core).

## Repository layout

| Path | Role |
|------|------|
| `wpis-bots.php`, `lib/`, `src/` | **Plugin** (this is what WordPress loads) |
| `readme.txt` | WordPress.org-style plugin readme (English); user-facing summary, install, FAQ |
| `docs/`, `assets/` | French admin documentation and shared images; local notes in `.doc/` (gitignored) |
| `tests/`, `phpunit.xml` | PHPUnit for shared `lib` helpers |
| `composer.json` | Dev tools (PHPCS, PHPUnit); `vendor/` is gitignored |

## Requirements

- WordPress 6.9+ and PHP 8.2+
- **wpis-core** and **Action Scheduler** active (see plugin headers)
- Optional: **Polylang** (language slug in bot settings)

## Install (classic upload)

1. **GitHub “Download ZIP”:** on the repo page use **Code → Download ZIP**. Unzip, upload the folder via **Plugins → Add New → Upload** (or copy it into `wp-content/plugins/`), then activate **WordPress Is… Bots**. The archive may be named `wpis-bots-main`; you can rename the folder to `wpis-bots` if you like.

2. **Release asset:** the **`wpis-bots.zip`** attached to a [GitHub Release](https://github.com/jaz-on/wpis-bots/releases) contains the plugin subtree (`wpis-bots.php`, `lib/`, `src/`, …) without Composer **vendor** — same upload flow as above.

3. After an upgrade, re-save **WPIS Bots → Mastodon** and **WPIS Bots → Bluesky** once so schedules register.

## Git Updater

`GitHub Plugin URI: https://github.com/jaz-on/wpis-bots` matches what [Git Updater](https://github.com/afragen/github-updater) expects (`wpis-bots.php` at the repository root).

## Development

After clone, autoload is handled by `lib/autoload-runtime.php` (no `composer install` required to run the plugin in WordPress). For **PHPCS** and **PHPUnit**:

```bash
composer install
composer lint && composer test
```

`vendor/` stays local and is not committed. When you change `composer.json`, update the lockfile and commit `composer.json` and `composer.lock` only.

## Action Scheduler and WP-Cron

With Action Scheduler (standalone plugin, or loaded by e.g. WooCommerce), bots use the `wpis-bots` group. The UI expects the official Action Scheduler plugin to stay installed. If `as_schedule_recurring_action` is missing, the site can fall back to **WP-Cron** (an admin notice explains this on bot settings screens). Deactivating **WordPress Is… Bots** clears both bots’ schedules.
