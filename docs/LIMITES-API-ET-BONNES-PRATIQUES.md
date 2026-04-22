# Limites API et bonnes pratiques (Mastodon, Bluesky)

Synthèse à jour de la documentation officielle **à titre d’aide**. Les plafonds **changent** : vérifiez toujours les liens ci-dessous avant une mise en production importante.

## Mastodon

**Documentation officielle**

- [Rate limits](https://docs.joinmastodon.org/api/rate-limits/)
- [API guidelines](https://docs.joinmastodon.org/api/guidelines/)

**Ce que dit la doc (valeurs par défaut côté logiciel Mastodon)**

- Environ **300 appels** aux endpoints REST **par compte** dans une fenêtre de **5 minutes**, et une limite **similaire par adresse IP** pour les requêtes non authentifiées (détails et exceptions sur la page « Rate limits »).
- Les réponses peuvent inclure des en-têtes du type `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`.
- Au-delà des limites, le serveur peut répondre **429** (« Too Many Requests »). Un en-tête `Retry-After` peut indiquer un délai avant de réessayer.

**Bonnes pratiques pour ce projet**

- Un bot qui interroge **une fois toutes les 10–15 minutes** une seule URL (fil hashtag) reste **largement** sous les plafonds par défaut, sauf si vous multipliez les extensions ou les sites sur la même IP.
- Chaque **instance** peut avoir des règles supplémentaires (conditions d’utilisation, modération). Vérifiez celles de **votre** instance.
- Le code des plugins ajoute un **User-Agent** identifiable pour respecter l’esprit des « guidelines » (reconnaître l’application).

## Bluesky (AT Protocol)

**Documentation officielle**

- [Rate limits (Bluesky docs)](https://docs.bsky.app/docs/advanced-guides/rate-limits)
- [Community Guidelines](https://bsky.social/about/support/community-guidelines) (comportements abusifs, spam, etc.)

**Ce que dit la doc (résumé)**

- Les services hébergés appliquent des **plafonds par IP** et par **compte** selon les endpoints. Exemples cités :
  - Ordre de grandeur **3000 requêtes / 5 minutes** pour l’ensemble des requêtes API sur le PDS (hébergement de compte), **par IP** (voir la page pour le détail et les évolutions).
  - `com.atproto.server.createSession` : limites **par compte** (par ex. **30 / 5 minutes** et un plafond **journalier** — voir la doc).
- Les réponses peuvent utiliser **429** et des en-têtes de rate limit (standards en cours d’harmonisation).
- Les opérations d’**écriture** massives (likes, follows, etc.) sont surtout concernées par des quotas en « points » ; **notre usage est en lecture** (`searchPosts`) et création de session, ce qui est plus léger, mais **createSession** ne doit pas être appelé en boucle (d’où la **mise en cache** de la session dans les plugins).

**Bonnes pratiques pour ce projet**

- Utiliser un **mot de passe d’application**, pas le mot de passe principal du compte.
- Garder un **intervalle de poll raisonnable** et augmenter si le journal affiche des erreurs 429.
- Respecter les **Community Guidelines** (pas d’automation « spammy » même si ici on ne poste pas sur Bluesky).

## Fair use et légal

- **Fair use** est surtout une notion **juridique (États-Unis)** et ne remplace pas le droit local ni les conditions des plateformes.
- Ce projet **copie du texte** vers votre base pour **modération** : gardez des extraits **courts**, citez la source (URL enregistrée en méta quand c’est possible) et conformez-vous à votre **propre politique éditoriale** et au RGPD si des données personnelles apparaissent dans les messages.

## Adaptations dans le code

Les clients HTTP des plugins :

- ajoutent un **User-Agent** décrivant « WPIS Bot » et le site ;
- transmettent dans les journaux d’erreur des **indices** issus des en-têtes (`Retry-After`, en-têtes Mastodon `X-RateLimit-*` quand présents) pour faciliter le diagnostic ;
- appliquent un **intervalle minimum** de **10 minutes** entre deux polls configurables côté admin (évite des réglages trop agressifs par mégarde).
