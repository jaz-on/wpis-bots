=== WordPress Is… Bots ===
Contributors: jaz_on
Tags: mastodon, bluesky, ingestion, moderation, quotes
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Companion ingestion bots (Mastodon and Bluesky) for WordPress Is… Core. Proposes "WordPress is…" quotes as drafts for human moderation.

== Description ==

**WordPress Is… Bots** adds two settings screens (Mastodon and Bluesky) to a site already running [**WordPress Is… Core**](https://github.com/jaz-on/wpis-core). The plugin watches a public Mastodon hashtag feed and a Bluesky search query for messages that look like "WordPress is…" quotes and copies them into WordPress as **pending drafts** for human moderation.

The plugin never posts anything on Mastodon or Bluesky. It only reads public content and hands candidates to the core function `wpis_submit_quote_candidate()` provided by wpis-core (validation, deduplication, `wpis_quote_submitted` hook).

**Key points**

* **Nothing is published automatically.** Every candidate lands as a `pending` post that a site editor must review.
* **Two independent bots** (Mastodon and Bluesky) with their own credentials, interval, keyword list and duplicate threshold.
* **Connection test** and **dry-run manual run** per bot, plus a shared run log (**WPIS Bots → Execution logs**).
* **Action Scheduler** is used when available; otherwise the plugin falls back to WP-Cron and shows an admin notice.
* Optional **Polylang** integration: map the source language reported by the API to one of your Polylang slugs, with a configurable fallback.
* **Source-language metadata** is stored on each draft so a separate translation workflow (manual or via the `wpis_bot_translate_to_english` filter) can produce an English version later.

**Full documentation (French, on GitHub):**

* [Administrator guide](https://github.com/jaz-on/wpis-bots/blob/main/docs/guide-admin.md)
* [API limits and good practice](https://github.com/jaz-on/wpis-bots/blob/main/docs/limites-api-et-bonnes-pratiques.md)
* [Mastodon and Bluesky resources](https://github.com/jaz-on/wpis-bots/blob/main/docs/ressources.md)

Override the documentation base URL in your site with the `wpis_bots_docs_base_url` filter (return a base URL that ends with `/`).

== Installation ==

1. Install and activate [**WordPress Is… Core**](https://github.com/jaz-on/wpis-core) (`wpis-core.php`). Without it, the admin shows a notice and ingestion does not run until core is active.
2. **Action Scheduler** is recommended for reliable background jobs; without it the plugin uses WP-Cron and may show an admin notice.
3. Install **WordPress Is… Bots**:
   * **GitHub ZIP:** on the [repo](https://github.com/jaz-on/wpis-bots) use **Code → Download ZIP**. Upload via **Plugins → Add New → Upload** (or copy the folder into `wp-content/plugins/`). Rename `wpis-bots-main` to `wpis-bots` if you like.
   * **Release asset:** the `wpis-bots.zip` attached to a [GitHub Release](https://github.com/jaz-on/wpis-bots/releases) contains the plugin subtree without `vendor/`.
   * **Git Updater:** the `GitHub Plugin URI` header matches what [Git Updater](https://github.com/afragen/github-updater) expects.
4. Activate **WordPress Is… Bots**.
5. Open **WPIS Bots → Mastodon** and **WPIS Bots → Bluesky**, fill in credentials (see FAQ below) and save once so schedules register. Re-save after every upgrade.

== Frequently Asked Questions ==

= Does this plugin post anything on Mastodon or Bluesky? =

No. It only reads public content (Mastodon hashtag timeline, Bluesky `searchPosts`) and creates **pending** drafts on your WordPress site. A human editor decides whether to publish.

= What credentials does each bot need? =

**Mastodon**

* Instance URL (e.g. `https://mastodon.social`).
* Hashtag (without `#`, e.g. `wordpress`).
* Access token — often **empty** if the public hashtag timeline is readable anonymously. If your instance requires auth, create an application in **Preferences → Development** with scope `read` (or `read:statuses`) and paste the token. The plugin never needs write scope.

**Bluesky**

* Service URL (usually `https://bsky.social`).
* Identifier (handle like `example.bsky.social` or account email).
* **App password** created in the Bluesky app (Settings → App passwords). Do **not** use your main account password.

= How often should a bot run? =

Leave the interval at **10–15 minutes or more**. A single read per interval stays well below Mastodon and Bluesky rate limits. If the run log starts showing `429` errors, raise the interval. See [API limits](https://github.com/jaz-on/wpis-bots/blob/main/docs/limites-api-et-bonnes-pratiques.md).

= How do I test without creating drafts? =

Each settings screen has a **"Try the API and run a pass"** box with:

* **Connection test** — one minimal request that never creates a draft or mutates bot state.
* **Manual run** with a **Dry run** checkbox — counts what would be ingested without creating drafts or advancing the "seen" cursor.

Executed passes (scheduled or manual, non-dry-run) are listed under **WPIS Bots → Execution logs**.

= What about Polylang and non-English posts? =

If Polylang is active, set a **fallback slug** per bot (e.g. `en` or `fr`) used when the API returns no clear language. Drafts keep the **source language** and, when the source is not English, the **original text** in post meta. You can hook the `wpis_bot_translate_to_english` filter from another plugin to fill the English body. Linking English and translated posts together is a manual Polylang step or a theme-level integration.

= The admin screens link to GitHub — can I host the docs elsewhere? =

Yes. Hook the `wpis_bots_docs_base_url` filter and return a base URL that ends with `/` (it is concatenated with the file name, e.g. `guide-admin.md`).

= Does deactivating the plugin clean up scheduled tasks? =

Deactivation clears the recurring actions for both bots. Settings in the database are left in place so reactivating restores the previous configuration.

== Changelog ==

= 0.1.0 =

* First tagged release.
* Mastodon ingestion (public hashtag timeline).
* Bluesky ingestion (`app.bsky.feed.searchPosts`) with cached session login.
* Shared Action Scheduler group `wpis-bots` with WP-Cron fallback.
* Execution log screen and per-bot connection test and dry-run manual pass.
* Polylang-aware language mapping with per-bot fallback slug.
* Documentation links surfaced in the admin via the `wpis_bots_docs_base_url` filter.

== Upgrade Notice ==

= 0.1.0 =

Initial release. After upgrading, re-save **WPIS Bots → Mastodon** and **WPIS Bots → Bluesky** once so schedules register.
