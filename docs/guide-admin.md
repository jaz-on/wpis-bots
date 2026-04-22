# Guide administrateur — bots WordPress Is…

Ce texte s’adresse aux personnes qui configurent le site, **sans supposer de connaissances techniques**. Les détails sur les limites des réseaux sociaux sont dans [limites-api-et-bonnes-pratiques.md](limites-api-et-bonnes-pratiques.md).

## À quoi sert cette extension ?

Une seule extension, **WordPress Is… Bots**, ajoute **deux écrans de réglages** (Mastodon et Bluesky) pour **repérer sur les réseaux** des messages publics qui ressemblent à des citations du type « WordPress is… ». Elle **n’affiche rien sur Mastodon ou Bluesky** : elle copie seulement le texte vers votre site WordPress, en **brouillon à modérer** (statut « en attente »), comme une suggestion soumise par un contributeur.

Sans modération humaine, **rien ne doit être publié automatiquement** sur le site public.

## Comptes officiels du projet

- **Mastodon** : [https://mastodon.social/@wpis](https://mastodon.social/@wpis)
- **Bluesky** : [https://bsky.app/profile/wpis.bsky.social](https://bsky.app/profile/wpis.bsky.social)

Avatar commun **400×400** px (fond crème, « **WP** **is**… » aux couleurs du site) : [../assets/wpis-social-avatar.png](../assets/wpis-social-avatar.png) — adapté au recadrage circulaire Mastodon / Bluesky.

Ces comptes servent de **référence** (profil, transparence). Le site WordPress n’a pas besoin des mots de passe de ces comptes, seulement des valeurs listées ci‑dessous.

### Ce que WordPress doit recevoir

| Plateforme | À fournir | Détail |
|------------|-----------|--------|
| **Mastodon** | URL d’instance | `https://mastodon.social` pour ce compte. |
| **Mastodon** | Hashtag | Sans `#`, celui que vous suivez pour le projet (ex. `wordpress`). |
| **Mastodon** | Jeton d’accès | Souvent **vide** si le fil public du hashtag se lit sans compte. Si l’instance ou le réglage exige une authentification, connectez-vous avec **@wpis**, créez une **application** (Paramètres → Développement), droits **lecture**, et collez le **jeton d’accès** dans **WPIS Bots → Mastodon**. |
| **Bluesky** | Identifiant | `wpis.bsky.social` (le handle du compte, pas l’URL complète du profil). |
| **Bluesky** | Mot de passe d’application | Créé dans l’app Bluesky pour le compte **wpis** (Paramètres → mots de passe d’application). **Pas** le mot de passe principal du compte. |
| **Bluesky** | URL du service | `https://bsky.social` sauf hébergement ATproto personnalisé. |

## Avant d’activer les bots

1. **Le plugin principal « WordPress Is… Core »** (dossier `wpis-core`, fichier `wpis-core.php`) doit être installé et actif.
2. Il est recommandé d’avoir **déjà du contenu validé** sur le site (citations modérées). Cela aide le système à repérer les doublons et à garder une base saine.
3. **Planification** : l’extension [**Action Scheduler**](https://fr.wordpress.org/plugins/action-scheduler/) est **recommandée** pour des tâches en arrière-plan plus fiables. Sans elle, le site retombe sur le cron WordPress, un peu moins fiable sur les petits sites peu visités.

## Mastodon — réglages simples

1. Dans WordPress, menu latéral **WPIS Bots → Mastodon** (ou l’onglet Mastodon sous **WPIS Bots**).
2. **Activer le bot** seulement quand la configuration est prête.
3. **URL de l’instance** : l’adresse du serveur Mastodon (souvent `https://mastodon.social` ou celui de votre communauté). Respectez les règles de cette instance (certaines n’aiment pas les comptes ou scripts trop gourmands en requêtes).
4. **Jeton d’accès** : souvent **vide** si le fil **hashtag public** se lit sans compte. Sinon, sur Mastodon : **Préférences → Développement → Nouvelle application**. URI de redirection : `urn:ietf:wg:oauth:2.0:oob` si vous collez seulement le jeton dans WordPress. **Cases à cocher** : au minimum **`read`** (et **`read:statuses`** si proposé) — pas besoin d’**écriture** : le plugin ne fait que **lire** le fil du hashtag, il ne publie rien sur Mastodon. **Ne partagez jamais ce jeton.**
5. **Hashtag** : sans le caractère `#`, par exemple `wordpress`.
6. **Intervalle** : espacement entre deux vérifications. Laissez au moins **10–15 minutes** sauf besoin précis ; les serveurs imposent des plafonds (voir doc limites).
7. **Mots-clés** : une ligne = une expression que le message doit contenir (ex. `WordPress is`). Si la liste est vide, tout message récupéré peut être proposé (peu recommandé).
8. **Langue Polylang** : si votre site est multilingue avec Polylang, indiquez le **slug** de repli (ex. `en` ou `fr`) quand l’API ne donne pas de langue claire. Quand c’est le cas, Mastodon et Bluesky remontent une langue : le plugin la mappe sur vos slugs Polylang, sinon le repli s’applique. Tant que Polylang n’est **pas** actif, le champ est inactif côté WordPress.

### Langue d’origine, anglais pivot et traductions Polylang (aperçu)

- Les brouillons issus des bots peuvent stocker **la langue source** et, si le texte n’est pas en anglais, le **texte d’origine** dans des métas (voyez la colonne *Source lang.* dans le tableau d’administration des citations) pour préparer une version anglaise sur le site.
- Le cœur WordPress Is… n’inclut **pas** de service de traduction payant. Des extensions peuvent s’abonner au filtre `wpis_bot_translate_to_english` pour remplir le corps en anglais quand la source n’est pas en anglais.
- **Lier** une citation anglaise et sa traduction dans Polylang (deux billets, deux langues) relève d’un **flux manuel** (créer la traduction dans l’interface Polylang) ou d’un thème d’intégration spécifique (`pll_save_post_translations`, selon la version de Polylang). Les bots n’assemblent pas à eux seuls toutes les paires de traductions du site.

**Avant ou après avoir enregistré**, la zone **« Essayer l’API et lancer un passage »** (en haut de l’écran, sous l’intro) propose :

- **Test de connexion** : une requête minimale vers l’instance (un message du fil hashtag) **sans** créer de brouillon ni modifier l’état du bot.
- **Passage manuel** : comme la tâche planifiée, avec option **« Simulation à blanc »** (dry run) : compte les messages qui seraient ingérés **sans** créer de brouillon ni mettre à jour la liste des messages déjà vus ni la position dans le fil.

Le **journal détaillé** de tous les passages (planifiés ou manuels **hors** simulation) se trouve sous **WPIS Bots → Journaux d’exécution**. Chaque écran Mastodon ou Bluesky rappelle aussi la date du **dernier passage enregistré** dans le journal.

## Bluesky — réglages simples

1. Menu latéral **WPIS Bots → Bluesky**.
2. **Activer** quand tout est prêt.
3. **URL du service** : en général `https://bsky.social` sauf indication contraire (hébergement personnalisé).
4. **Identifiant** : votre **pseudo Bluesky** (format `exemple.bsky.social`) ou l’e-mail du compte.
5. **Mot de passe d’application** : à créer dans l’application Bluesky (paramètres du compte → mots de passe d’application). **Ne utilisez pas** votre mot de passe principal. Ce mot de passe sert uniquement à ouvrir une session API ; il reste stocké dans la base WordPress comme les autres options (protégez l’admin du site).
6. **Requête de recherche** : texte envoyé à la recherche Bluesky (ex. `WordPress is`). Les **mots-clés** en dessous filtrent encore le texte des messages trouvés.
7. Même logique que Mastodon pour **intervalle**, **seuil de doublons**, **Polylang**, **test de connexion**, **passage manuel**, **simulation à blanc** et **journal** (voir ci‑dessus et **WPIS Bots → Journaux d’exécution**).

## Bonnes habitudes

- Commencez avec un **intervalle large** et surveillez le journal. En cas d’erreur « trop de requêtes » (429), augmentez l’intervalle.
- Si une instance Mastodon ou Bluesky change ses règles, **adaptez-vous** ou désactivez le bot.
- Les bots **lisent** du contenu public ou accessible avec vos identifiants ; ils ne remplacent pas une politique éditoriale ni le respect du droit d’auteur sur les citations longues ou les captures.

## Besoin d’aide technique ?

Voir le [README principal](../README.md) du dépôt (installation pour développeurs, tests, structure des dossiers).

## Pour aller plus loin

- [Limites des API et bonnes pratiques](limites-api-et-bonnes-pratiques.md) (liens officiels, 429, intervalles).
- [Ressources Mastodon et Bluesky](ressources.md) (documentation officielle, tutoriels communautaires, autres langages).
