# Guide administrateur — bots WordPress Is…

Ce texte s’adresse aux personnes qui configurent le site, **sans supposer de connaissances techniques**. Les détails sur les limites des réseaux sociaux sont dans [LIMITES-API-ET-BONNES-PRATIQUES.md](LIMITES-API-ET-BONNES-PRATIQUES.md).

## À quoi servent ces plugins ?

Deux extensions séparées permettent de **repérer sur les réseaux** des messages publics qui ressemblent à des citations du type « WordPress is… ». Elles **n’affichent rien sur Mastodon ou Bluesky** : elles copient seulement le texte vers votre site WordPress, en **brouillon à modérer** (statut « en attente »), comme une suggestion soumise par un contributeur.

Sans modération humaine, **rien ne doit être publié automatiquement** sur le site public.

## Avant d’activer les bots

1. **Le plugin principal « WordPress Is… Core »** (`wpis-plugin`) doit être installé et actif.
2. Il est recommandé d’avoir **déjà du contenu validé** sur le site (citations modérées). Cela aide le système à repérer les doublons et à garder une base saine.
3. Installez de préférence **Action Scheduler** (extension WordPress). Sinon le site utilisera la planification WordPress classique, un peu moins fiable sur les petits sites peu visités.

## Mastodon — réglages simples

1. Dans WordPress, menu **Réglages → WPIS Mastodon Bot**.
2. **Activer le bot** seulement quand la configuration est prête.
3. **URL de l’instance** : l’adresse du serveur Mastodon (souvent `https://mastodon.social` ou celui de votre communauté). Respectez les règles de cette instance (certaines n’aiment pas les comptes ou scripts trop gourmands en requêtes).
4. **Jeton d’accès** : souvent vide si la ligne de temps du **hashtag public** est lisible sans compte. Sinon créez un compte applicatif sur l’instance et collez le jeton ici. **Ne partagez jamais ce jeton** (mail, capture d’écran publique, etc.).
5. **Hashtag** : sans le caractère `#`, par exemple `wordpress`.
6. **Intervalle** : espacement entre deux vérifications. Laissez au moins **10–15 minutes** sauf besoin précis ; les serveurs imposent des plafonds (voir doc limites).
7. **Mots-clés** : une ligne = une expression que le message doit contenir (ex. `WordPress is`). Si la liste est vide, tout message récupéré peut être proposé (peu recommandé).
8. **Langue Polylang** : si votre site est multilingue avec Polylang, indiquez le **slug** de la langue (ex. `en` ou `fr`) pour classer les nouvelles citations.

En bas de page, le **journal des exécutions** résume chaque passage : combien de messages vus, combien créés, combien fusionnés avec une citation existante, erreurs éventuelles.

## Bluesky — réglages simples

1. Menu **Réglages → WPIS Bluesky Bot**.
2. **Activer** quand tout est prêt.
3. **URL du service** : en général `https://bsky.social` sauf indication contraire (hébergement personnalisé).
4. **Identifiant** : votre **pseudo Bluesky** (format `exemple.bsky.social`) ou l’e-mail du compte.
5. **Mot de passe d’application** : à créer dans l’application Bluesky (paramètres du compte → mots de passe d’application). **Ne utilisez pas** votre mot de passe principal. Ce mot de passe sert uniquement à ouvrir une session API ; il reste stocké dans la base WordPress comme les autres options (protégez l’admin du site).
6. **Requête de recherche** : texte envoyé à la recherche Bluesky (ex. `WordPress is`). Les **mots-clés** en dessous filtrent encore le texte des messages trouvés.
7. Même logique que Mastodon pour **intervalle**, **seuil de doublons**, **Polylang** et le **journal**.

## Bonnes habitudes

- Commencez avec un **intervalle large** et surveillez le journal. En cas d’erreur « trop de requêtes » (429), augmentez l’intervalle.
- Si une instance Mastodon ou Bluesky change ses règles, **adaptez-vous** ou désactivez le bot.
- Les bots **lisent** du contenu public ou accessible avec vos identifiants ; ils ne remplacent pas une politique éditoriale ni le respect du droit d’auteur sur les citations longues ou les captures.

## Besoin d’aide technique ?

Voir le [README principal](../README.md) du dépôt (installation pour développeurs, tests, structure des dossiers).
