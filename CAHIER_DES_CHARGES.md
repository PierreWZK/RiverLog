# Jour 3/30 — Suivi de bankroll poker (V1)

> Cahier des charges de la V1. MVP réalisable en 2 à 3 h.
> (Le document d'origine était titré « Jour 6/30 » ; le projet correspond au jour 3 du défi.)

**Stack :** Laravel, Blade, JavaScript, Chart.js

**Objectif :** créer un mini-outil d'analyse de sessions de poker qui apporte une vraie valeur au joueur : suivre les résultats, comparer les formats et calculer son taux horaire.

Le défi n'est pas de refaire un CRUD Laravel classique. Le cœur du projet est **le calcul fiable des statistiques, la conception des données et la visualisation des résultats**.

---

## 1. MVP — ce qui doit fonctionner à la fin

- **Historique des sessions** : ajouter une session avec date, format de jeu, durée en minutes, résultat net et notes facultatives. Afficher les sessions dans un tableau trié de la plus récente à la plus ancienne.
- **Tableau de bord statistique** : afficher le gain net cumulé, le nombre de sessions, le temps joué et le taux horaire. Ajouter une courbe d'évolution du résultat cumulé.
- **Filtres utiles** : filtrer par format (Cash Game, MTT, Sit & Go), période et résultat. Les indicateurs et le graphique doivent se recalculer sur les sessions filtrées.
- **Persistance et cohérence** : enregistrer les sessions dans une base de données. Valider les entrées côté serveur et centraliser les calculs afin d'éviter des résultats différents entre les pages.

### Périmètre volontairement limité

- Une seule bankroll, sans gestion multi-utilisateur.
- Pas d'inscription, d'authentification ou de rôles.
- Pas d'import de fichiers obligatoire.
- Pas de calcul de rakeback, de variance avancée ou d'équité poker.
- Pas d'API externe : les données viennent des sessions saisies.

> **Règle de réussite :** les filtres doivent agir sur les données réellement persistées, pas uniquement masquer des lignes côté navigateur.

---

## 2. Cahier des charges fonctionnel

### A. Gestion des sessions

Chaque session contient les champs suivants :

| Champ         | Type                     | Règle                                            |
| ------------- | ------------------------ | ------------------------------------------------ |
| Date et heure | DateTime                 | Obligatoire                                      |
| Format        | Enum ou chaîne contrôlée | Cash Game, MTT, Sit & Go                         |
| Durée         | Minutes entières         | Strictement supérieure à 0                       |
| Résultat net  | Décimal signé            | Gains positifs, pertes négatives, break-even à 0 |
| Notes         | Texte                    | Facultatif                                       |

Le résultat net représente le profit ou la perte **après** les coûts de participation. Une session perdante de 50 € vaut donc `-50.00`, et non `50.00`.

Fonctionnalités requises :

- Ajouter une session.
- Modifier une session existante.
- Supprimer une session avec confirmation.
- Consulter l'historique trié par date décroissante.
- Afficher un état vide explicite lorsqu'aucune session ne correspond aux filtres.

### B. Indicateurs et règles de calcul

Les statistiques sont calculées **côté serveur**, sur l'ensemble des sessions correspondant aux filtres actifs.

| Indicateur | Formule |
| --- | --- |
| Profit net | `Profit net = Σ résultatᵢ` (i de 1 à n) |
| Temps total joué | `Heures = (Σ duréeᵢ) / 60` |
| Taux horaire | `€/h = Profit net / Heures jouées` |
| Résultat cumulé (graphique) | `Cₖ = Σ résultatᵢ` (i de 1 à k), sessions triées par ordre chronologique **croissant** |

Cas à gérer correctement :

- **Aucune session** : les gains et le nombre de sessions valent zéro ; le taux horaire affiche `—` s'il n'existe aucune heure jouée.
- **Durée totale nulle** : ne jamais diviser par zéro.
- **Résultats négatifs** : le taux horaire peut être négatif.
- **Plusieurs sessions le même jour** : conserver l'ordre chronologique, sans fusionner les sessions dans le tableau.
- **Filtrage** : les indicateurs, la courbe et l'historique doivent utiliser **le même ensemble de sessions**.

### C. Filtres

Filtres minimum :

- **Format** : tous, Cash Game, MTT, Sit & Go.
- **Période** : date de début et date de fin.
- **Résultat** : toutes les sessions, gagnantes, perdantes ou nulles.

Les filtres peuvent être transmis par query string (`?format=mtt&from=...&to=...&result=win`) afin que l'URL soit partageable et que la page reste actualisable.

### D. Interface attendue

Maquette indicative (valeurs fictives, pas besoin de reproduire exactement le design) :

```
Bankroll Overview
┌──────────────┬──────────────┬──────────────┬──────────────┐
│ Profit net   │ Taux horaire │ Sessions     │ Temps joué   │
│ +€342.50     │ €8.56/h      │ 24           │ 40 h         │
└──────────────┴──────────────┴──────────────┴──────────────┘
Évolution du profit cumulé
[ courbe ]  début de période ─────────────── fin de période
```

L'interface finale doit comporter :

1. Une rangée de cartes statistiques.
2. Une courbe de profit cumulé.
3. Une zone de filtres compacte.
4. Un tableau des sessions avec date, format, durée, résultat et actions.
5. Un formulaire d'ajout/modification, sous forme de page ou de modal.

---

## 3. Cahier des charges technique

### Stack recommandée

- Laravel et Blade pour l'application.
- SQLite pour gagner du temps en développement (ou MySQL si l'environnement existe déjà).
- Chart.js pour le graphique.
- Tailwind CSS ou le système de styles habituel.
- PHPUnit ou Pest pour quelques tests ciblés.

### Modèle de données

Une seule table `poker_sessions` suffit pour le MVP.

| Colonne                    | Type suggéré           |
| -------------------------- | ---------------------- |
| `id`                       | Identifiant            |
| `played_at`                | `dateTime`             |
| `format`                   | `string`               |
| `duration_minutes`         | `unsignedSmallInteger` |
| `net_result`               | `decimal(10, 2)`       |
| `notes`                    | `text`, nullable       |
| `created_at`, `updated_at` | Timestamps             |

Garder le modèle simple : pas besoin d'un système générique de transactions ni de plusieurs entités métier.

### Architecture Laravel suggérée

- **Routes web** : dashboard, liste filtrée, création, modification, suppression.
- **Modèle `PokerSession`** : cast de date, validation des données et accès aux sessions.
- **Service `BankrollStatsService`** : applique les filtres, agrège les statistiques et prépare les points du graphique.
- **Vue Blade + Chart.js** : affiche les cartes, les filtres, le tableau et la courbe.

> **Le petit plus technique :** construire une requête filtrée réutilisable, puis utiliser cette même base pour le tableau et les statistiques (scope Eloquent ou objet dédié aux filtres). Éviter de disperser les calculs dans les vues Blade.

### Hébergement et mise en production

- **Pour le moment, le projet est maintenu en local** (`php artisan serve` et base SQLite). Le site du défi est hébergé sur Netlify, qui ne peut pas exécuter de PHP : RiverLog n'y est donc pas publié, et sa carte renvoie vers le code sur GitHub.
- **Mise en production plus tard :** si une solution d'hébergement simple et pratique se présente, le projet sera mis en ligne **avec une base de données fonctionnelle**, pour que l'outil soit utilisable directement depuis le navigateur.
- Ce point ne bloque pas la V1 : le code doit simplement rester facile à déployer (configuration dans `.env`, migrations et seeders à jour).

---

## 4. Planning minuté — 2 h 45 maximum

| Durée | Créneau | Étape | Livrable |
| --- | --- | --- | --- |
| 15 min | 0:00–0:15 | **1. Cadrage et squelette** : créer le projet, configurer la base, définir les champs, créer la migration et générer quelques sessions fictives. | Application démarrable et données de test disponibles. |
| 30 min | 0:15–0:45 | **2. Domaine et persistance** : modèle, validation, création, modification et suppression d'une session. Tester les résultats négatifs et les durées invalides. | Les sessions sont correctement enregistrées et modifiables. |
| 35 min | 0:45–1:20 | **3. Calculs métier et filtres** : créer `BankrollStatsService`, appliquer les filtres de format et de période, calculer profit net, temps joué, nombre de sessions et taux horaire. | Statistiques correctes même avec une période vide ou des résultats négatifs. |
| 35 min | 1:20–1:55 | **4. Dashboard et graphique** : cartes statistiques, tableau et courbe Chart.js, avec des données déjà préparées côté serveur. | Tableau de bord utilisable sur ordinateur et mobile. |
| 25 min | 1:55–2:20 | **5. Filtres réactifs et finitions** : relier les filtres à l'URL, vérifier leur combinaison, états vides, confirmations de suppression et formats monétaires. | Parcours utilisateur complet, sans incohérence visible. |
| 25 min | 2:20–2:45 | **6. Tests, README et publication** : vérifier les cas limites, documenter les fonctionnalités, ajouter une capture d'écran, préparer le dépôt pour le portfolio. | Projet documenté, reproductible et présentable. |

Si l'installation bloque : se fixer une limite de 30 à 60 minutes supplémentaires. Pour le reste, réduire le périmètre graphique ou utiliser SQLite plutôt que de sacrifier les calculs métier.

---

## 5. Critères de réussite

Le projet est terminé lorsque tous les critères obligatoires sont validés.

**Fonctionnel**

- [ ] Ajouter, modifier et supprimer une session.
- [ ] Afficher l'historique dans le bon ordre.
- [ ] Filtrer par format, période et résultat.
- [ ] Recalculer toutes les statistiques après filtrage.

**Calculs**

- [ ] Calculer correctement le profit net et le temps total.
- [ ] Calculer le taux horaire sans division par zéro.
- [ ] Tracer le profit cumulé en ordre chronologique.
- [ ] Gérer les résultats négatifs et les filtres sans résultat.

**Qualité**

- [ ] Valider les données côté serveur.
- [ ] Avoir une interface lisible sur mobile et ordinateur.
- [ ] Fournir un README avec installation et données de démonstration.
- [ ] Publier le code et une capture d'écran exploitable dans le portfolio.

---

## 6. Bonus facultatifs

À ne commencer que si le MVP est fini.

| Bonus | Durée | Description |
| --- | --- | --- |
| A — Export CSV | 10–15 min | Exporter les sessions filtrées pour les analyser dans un tableur. |
| B — Objectif de bankroll | 10–15 min | Définir un objectif de profit et afficher la progression en pourcentage. |
| C — Comparaison des formats | 15–20 min | Graphique comparant le profit net ou le taux horaire du Cash Game, des MTT et des Sit & Go. |
| D — Import CSV | 20–30 min | Importer des sessions avec validation des colonnes, aperçu avant import et rapport d'erreurs. |

---

## 7. Définition du « terminé »

Le dépôt GitHub doit inclure :

- Un README avec le but du projet, les fonctionnalités et les instructions de lancement.
- Une capture du dashboard et, idéalement, une seconde du tableau filtré.
- Un jeu de données de démonstration ou un seeder.
- Une liste claire des technologies utilisées.
- Un lien vers la version déployée si l'hébergement est possible (voir « Hébergement et mise en production » : en local pour le moment).

> **Conseil :** privilégier un dashboard qui calcule correctement ses statistiques plutôt qu'un dashboard très décoré. La valeur démontrable est **la cohérence entre les sessions, les filtres, les calculs et le graphique** : c'est ce qui distinguera le projet d'un simple exercice CRUD Laravel.
