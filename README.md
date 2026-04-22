# WPIS Bots

Separate GitHub repository for **WordPress Is…** ingestion bots: Mastodon, Bluesky and future platforms.

Each platform is a **standalone WordPress plugin** in its own directory. Activate them only alongside [**wpis-plugin**](https://github.com/jaz-on/wpis-plugin) (core quotes, dedup via `wpis_find_potential_duplicates`, moderation).

## Layout

| Directory | Role |
|-----------|------|
| `wpis-bot-mastodon/` | Mastodon firehose polling and candidate submission |
| `wpis-bot-bluesky/` | Bluesky polling and candidate submission |

## Chantier 11 scope

See `wordpress-is-cursor-plan.md` in the main WPIS workspace: Action Scheduler cron jobs, `pending` quotes, `_wpis_submission_source` values `bot-mastodon` / `bot-bluesky`, admin settings and run logs.

**Do not ship polling logic until the core site has enough seeded quotes** (plan: Chantier 10 baseline, about 50) so matching rules have a reference corpus.

## Development

From each plugin directory:

```bash
composer install
composer lint
```

Deploy the plugin folder into `wp-content/plugins/` on the target site, or symlink for local work.
