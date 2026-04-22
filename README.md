# WPIS Bots

Separate GitHub repository for **WordPress Is…** ingestion bots: Mastodon, Bluesky and future platforms.

Each platform is a **standalone WordPress plugin** in its own directory. Activate them only alongside [**wpis-plugin**](https://github.com/jaz-on/wpis-plugin) (core quotes, dedup via `wpis_find_potential_duplicates`, moderation).

**Human-friendly docs (French):** [Guide administrateur](docs/GUIDE-ADMIN.md) and [Limites des API et bonnes pratiques](docs/LIMITES-API-ET-BONNES-PRATIQUES.md). The WordPress settings screens link to these files on GitHub once the repo is published.

## Layout

| Directory | Role |
|-----------|------|
| `docs/` | Administrator guide and API limits (non-developer friendly) |
| `lib/` | Shared ingestion helpers (`QuoteIngest`, idempotence, run logs, text helpers) |
| `wpis-bot-mastodon/` | Mastodon hashtag timeline polling |
| `wpis-bot-bluesky/` | Bluesky `searchPosts` polling |

## Requirements

- WordPress 6.9+ and PHP 8.2+
- **wpis-plugin** active (quotes CPT, meta, counter sync)
- **[Action Scheduler](https://wordpress.org/plugins/action-scheduler/)** recommended; without it the bots fall back to WP-Cron
- Optional: **Polylang** if you set a default language slug in bot settings

## Behaviour

- Polls run on a configurable interval (**10–120 minutes** by default safeguards) when the bot is **enabled** in Settings.
- New candidates become `pending` quotes with `_wpis_submission_source` `bot-mastodon` or `bot-bluesky`, `_wpis_source_platform` set and optional `_wpis_source_domain` from the post URL.
- Near-duplicates (score ≥ threshold) **bump** `_wpis_counter` on the existing quote instead of creating a post (Polylang siblings sync via core `CounterSync`).
- Successful creates fire `do_action( 'wpis_quote_submitted', $post_id )` like the public form.
- Remote post IDs are tracked in a bounded ring buffer to limit double-processing when jobs replay.

**Wait until the site has enough seeded quotes** (Chantier 10 baseline, about 50) before enabling bots in production so dedup and filters behave sensibly.

## Install

1. Copy or symlink `wpis-bot-mastodon` and/or `wpis-bot-bluesky` into `wp-content/plugins/`.
2. Run `composer install --no-dev` inside each plugin you deploy (or commit `vendor` only if your deploy pipeline requires it).
3. Activate the plugin(s). Re-save **Settings → WPIS … Bot** once to register schedules after upgrades.
4. Store **Bluesky app passwords** and **Mastodon tokens** only in the WordPress options UI; never commit them. Logs intentionally omit secrets.

## Configuration notes

- **Mastodon:** public tag timeline; optional bearer token if your instance requires auth for that endpoint.
- **Bluesky:** handle (or email) + app password; JWT cached in a transient between polls with refresh on 401/403 when a refresh token is available.
- **Keywords:** one substring per line; empty list means “accept all fetched posts that pass other checks”.
- **Dedup threshold:** passed to `wpis_find_potential_duplicates` (0–100).

## Development

Per plugin:

```bash
cd wpis-bot-mastodon && composer install && composer lint
```

Repo root (shared lib unit tests):

```bash
composer install && composer test
```

## Action Scheduler vs WP-Cron

If Action Scheduler is present, bots register recurring actions in the `wpis-bots` group. Otherwise WordPress cron schedules a custom interval. Deactivating a bot plugin clears its Action Scheduler hooks and WP-Cron events.
