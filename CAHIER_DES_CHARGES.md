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
- **Comptes personnels** *(ajout en cours de projet)* : chaque personne crée son compte et n'accède qu'à ses propres sessions. Une page publique `/demo` montre un exemple de dashboard en lecture seule, avec des données fictives.
- **Persistance et cohérence** : enregistrer les sessions dans une base de données. Valider les entrées côté serveur et centraliser les calculs afin d'éviter des résultats différents entre les pages.

### Périmètre volontairement limité

- Une bankroll par compte. Pas de rôles ni de partage entre comptes.
- Comptes simples : inscription, connexion, déconnexion. Pas encore de réinitialisation du mot de passe par e-mail.
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
- **MySQL 8.4** (version LTS) pour la base de données, en développement comme en production.
- Chart.js pour le graphique.
- Tailwind CSS ou le système de styles habituel.
- PHPUnit ou Pest pour quelques tests ciblés.

### Base de données (MySQL)

Le projet a démarré sur SQLite (un simple fichier), puis est passé sur **MySQL** : c'est le moteur proposé par la plupart des hébergeurs PHP, la mise en production n'aura donc pas à changer de base, et les tests vérifient le même SQL que celui de l'application.

| Élément | Choix |
| --- | --- |
| Serveur | MySQL Server 8.4 LTS, service Windows `MySQL84`, démarrage automatique, écoute uniquement en local (`127.0.0.1:3306`) |
| Base de l'application | `riverlog` |
| Base des tests | `riverlog_test` : vidée et recréée par les tests (`RefreshDatabase`), jamais la base de l'application |
| Utilisateur | `riverlog`, droits limités à ces deux bases (pas de connexion de l'application en `root`) |
| Encodage | `utf8mb4` / `utf8mb4_unicode_ci` : accents et symboles (pseudos comme `Néné9`, `€`) stockés sans perte |
| Configuration | Variables `DB_*` du fichier `.env` (le mot de passe n'est jamais versionné ; `.env.example` le laisse vide) |

**Règles d'utilisation**

- **Toute la structure passe par les migrations** : aucune table créée ou modifiée à la main. Une base neuve se construit avec `php artisan migrate --seed`.
- **Montants en `DECIMAL`**, jamais en `FLOAT` : `decimal(10,2)` pour les euros, entiers pour les jetons. Les sommes sont faites en centimes pour éviter toute erreur d'arrondi.
- **Intégrité gérée par la base** : clés étrangères avec suppression en cascade (supprimer un compte supprime ses sessions, imports, tournois et mains), index uniques pour empêcher les doublons (un fichier par empreinte SHA-256, un tournoi par Game ID, une main par Hand ID, pour chaque compte).
- **Transactions** : un import est enregistré en entier ou pas du tout ; le recalcul des tournois et des sessions se fait dans la même transaction.
- **Index** sur les colonnes filtrées et triées : (`user_id`, `played_at`), (`user_id`, `format`), (`user_id`, `started_at`), (`tournament_id`, `played_at`).
- **Heures** : colonnes `DATETIME` en heure de Paris (fuseau de l'application) ; les heures UTC des exports sont converties à l'import.
- **SQL portable** : les requêtes passent par Eloquent ou le Query Builder ; le seul SQL brut (`SUM(ROUND(net_result * 100))`) est standard. SQLite reste donc utilisable en secours en changeant `DB_CONNECTION`.

### Modèle de données

La V1 ne demandait qu'une table `poker_sessions` ; les comptes puis l'import d'historiques (section 8.4) l'ont complétée.

| Colonne de `poker_sessions` | Type MySQL                         |
| --------------------------- | ---------------------------------- |
| `id`                        | `BIGINT UNSIGNED`, auto-incrément  |
| `user_id`                   | `BIGINT UNSIGNED`, clé étrangère vers `users` (cascade) |
| `source`                    | `VARCHAR(10)` : `manual` ou `import` |
| `played_at`                 | `DATETIME`                         |
| `format`                    | `VARCHAR(10)` : `cash`, `mtt`, `sng`, `spin` |
| `duration_minutes`          | `SMALLINT UNSIGNED`                |
| `net_result`                | `DECIMAL(10,2)`                    |
| `notes`                     | `TEXT`, nullable                   |
| `created_at`, `updated_at`  | `TIMESTAMP`                        |

Tables complémentaires : `users` (comptes), `hand_imports`, `tournaments`, `hands`, `hand_players`, `hand_actions` (détail en section 8.4), plus les tables techniques de Laravel (`sessions`, `cache`, `jobs`).

### Architecture Laravel suggérée

- **Routes web** : dashboard, liste filtrée, création, modification, suppression.
- **Modèle `PokerSession`** : cast de date, validation des données et accès aux sessions.
- **Service `BankrollStatsService`** : applique les filtres, agrège les statistiques et prépare les points du graphique.
- **Vue Blade + Chart.js** : affiche les cartes, les filtres, le tableau et la courbe.

> **Le petit plus technique :** construire une requête filtrée réutilisable, puis utiliser cette même base pour le tableau et les statistiques (scope Eloquent ou objet dédié aux filtres). Éviter de disperser les calculs dans les vues Blade.

### Hébergement et mise en production

- **En ligne sur Alwaysdata** : https://riverlog.alwaysdata.net (PHP 8.4, base MySQL de l'hébergeur). Le site du défi reste sur Netlify, qui ne peut pas exécuter de PHP : sa carte renvoie vers cette adresse.
- **Déploiement continu** : le workflow GitHub Actions `.github/workflows/main.yml` lance les tests (PHP 8.3 + MySQL 8.4) à chaque push ; sur `master`, s'ils passent, il met le serveur à jour en SSH avec `deploy.sh` (code, `composer install --no-dev`, migrations, caches). Clé SSH et empreinte du serveur sont des secrets GitHub.
- **En local** : `php artisan serve` et un serveur MySQL local, pour développer et tester avant de pousser.
- **Base en production :** une base MySQL 8 (ou MariaDB compatible) fournie par l'hébergeur, avec un utilisateur dédié aux droits limités. Le déploiement se résume à renseigner les variables `DB_*` puis lancer `php artisan migrate --force`.

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

Si l'installation bloque : se fixer une limite de 30 à 60 minutes supplémentaires. Pour le reste, réduire le périmètre graphique, ou basculer temporairement sur SQLite si le serveur MySQL résiste, plutôt que de sacrifier les calculs métier.

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

---

## 8. Évolution V2 — Import d'historiques et analyse

> **Statut : phase 1 terminée** (import, tournois, sessions automatiques, page Tournois, démo). Phases 2 à 4 à venir. Cette partie dépasse largement le format 2–3 h du défi : elle est construite par phases, comme un fil rouge du projet.

### 8.1 Objectif et principes

Importer les historiques de mains exportés par les sites (Betclic pour commencer) pour calculer automatiquement des statistiques de rentabilité, de volume et de jeu, sans saisie manuelle.

| Principe | Conséquence |
| --- | --- |
| **Un tournoi = un Game ID, une main = un Hand ID** | Le buy-in et les gains d'un tournoi ne sont comptés qu'une fois, même si l'en-tête est répété dans chaque main. |
| **Import idempotent** | Réimporter le même fichier (ou un fichier qui chevauche le précédent) ne crée aucun doublon et ne change aucun chiffre. |
| **Texte brut conservé** | Chaque main garde son texte d'origine : quand le parser s'améliore, tout peut être recalculé sans réimporter. Le texte servira aussi au rejeu des mains. |
| **Résultats ≠ mouvements de bankroll** | Un dépôt augmente le solde mais n'est pas un gain ; un retrait n'est pas une perte. Ils sont stockés à part. |
| **Échantillon toujours visible** | Chaque statistique affiche son nombre de tournois ou de mains. En dessous d'un seuil, elle est grisée avec la mention « échantillon trop petit ». |
| **Données privées** | Les mains, tournois et pseudos adverses ne sont visibles que par le compte qui les a importés. |
| **Sessions automatiques** | Les tournois importés sont regroupés en sessions (format « Spin ») : le dashboard actuel (profit, taux horaire, courbe, filtres) les inclut sans modification. Les sessions manuelles restent possibles. |

### 8.2 Le format Betclic (analyse de l'export fourni)

Fichier analysé : `<compte>_ExportHH_2026-1-29.txt`, Spin & Rush à 1 €.

**Structure générale**

- Un fichier texte, une main par bloc, blocs séparés par une ligne `------------`.
- Les mains sont dans l'**ordre antichronologique** (la plus récente en premier).
- Chaque bloc est découpé en sections : `*** HEADER ***`, `*** PLAYERS ***`, `*** HOLE CARDS ***`, `*** PRE-FLOP ***`, `*** FLOP ***`, `*** TURN ***`, `*** RIVER ***`, `*** SHOWDOWN ***`, `*** SUMMARY ***` (les sections de street n'existent que si la main y arrive).

**En-tête (répété dans chaque main)**

| Champ | Exemple | Usage |
| --- | --- | --- |
| `Site` | `Betclic.fr` | Choix du parser |
| `Game Mode` / `Game Type` | `Spin` / `NL Texas Hold'em` | Format |
| `Game Name` | `Spin & Rush 1€` | Libellé |
| `Game ID` | `01KG54P5VX…` (ULID) | **Identifiant du tournoi** |
| `Prize pool` / `Multiplier` | `10.00€` / `x10` | Stats par multiplicateur |
| `Buy In` | `1.00€` | Coût du tournoi (frais inclus) |
| `Hand ID` | `01KG54Z6SP…` (ULID) | **Identifiant de la main** |
| `Date & Time` | `2026-01-29 15:11:55 (UTC)` | Heure **UTC** → convertie en Europe/Paris (UTC+1 l'hiver, UTC+2 l'été) |
| `Table ID` | `01KG54P5WA…` | Information |
| `Blinds` | `20/40` | Niveau, stack en big blinds |
| `Total Pot` / `Rake` | `1300` / `0` | Contrôle de cohérence |

**Rake et ROI** : chaque main indique `Rake: 0`. Les frais sont intégrés au buy-in de 1 € (la structure des multiplicateurs les finance). Le ROI se calcule donc simplement : `(gains − buy-ins) ÷ buy-ins`, sans risque de compter les frais deux fois.

**Joueurs** : `Seat 2: PierreWZK (850) [BB Hero]`

- Tags possibles : `BTN`, `SB`, `BB`, `Hero`. En heads-up, le bouton est aussi petite blinde : `[BTN SB]`.
- Le tag **`Hero` identifie automatiquement le joueur qui importe** : aucun pseudo à configurer.
- Les pseudos peuvent contenir des accents (`numéro9`) : la reconnaissance se fait sur la structure de la ligne, pas sur un alphabet restreint.

**Actions** : `15:11:58 - PierreWZK: Raises to 850 and is all-in`

| Action | Sens du montant |
| --- | --- |
| `Posts SB 20` / `Posts BB 40` | Mise obligatoire |
| `Calls 570` | Montant **ajouté** |
| `Bets 160` | Montant **ajouté** |
| `Raises to 850` | **Total** misé sur la street (pas l'ajout !) |
| `Checks`, `Folds` | — |
| `… and is all-in` | Marqueur de tapis |

Chaque action est horodatée à la seconde : le temps de décision est calculable.

**Cartes, board et abattage**

- `HOLE CARDS` : toujours les cartes du Hero ; celles des adversaires seulement s'ils les montrent.
- Board : `*** FLOP *** [Jh 8c Qh]`, puis turn et river avec le board complet.
- `PierreWZK shows [Th 9h] (Straight) [Ks Qh Jh Th 9h]` : cartes, nom de la combinaison, 5 meilleures cartes.

**Résumé**

- `X wins main pot of 1300`, `X wins 1st side pot of 610` (pots secondaires).
- `PierreWZK finished 1st and wins 10.00 EUR` : place finale et gain.
- `PierreWZK finished 2nd` : place finale sans gain.
- La place du Hero apparaît dans la main où il est éliminé ou gagne. Les mains jouées après son élimination ne sont pas dans son export.

**Pièges à gérer**

- `Raises to` est un total de street, `Calls`/`Bets` un ajout : il faut suivre ce que chaque joueur a déjà misé sur la street.
- Mise non suivie, deux cas vérifiés sur l'export :
  - **tout le monde se couche** : la mise reste dans `Total Pot` et revient au gagnant avec le pot (tapis de 390, les autres se couchent : pot de 430 gagné, gain net +40) ;
  - **tapis payé par un joueur plus court** : l'excédent est rendu et **n'est pas** compté dans `Total Pot` (tapis de 850 payé par un stack de 650 : pot de 1300, pas 1500).
- Un tournoi peut être coupé entre deux exports : les agrégats sont recalculés à partir de **toutes** les mains en base, pas seulement du fichier.
- Un tournoi dont la dernière main n'est pas présente est **incomplet** : pas de place finale, exclu du ROI et signalé.
- Fichiers avec BOM UTF-8, fins de ligne Windows (`\r\n`), ZIP contenant plusieurs fichiers.
- En Spin & Rush x2, seul le 1er est payé. Les gros multiplicateurs peuvent payer plusieurs places : le parser lit tout `wins X EUR` plutôt que de supposer « 1er = tout ».

### 8.3 Résultats attendus sur le fichier fourni (jeu de test)

Ces valeurs, vérifiées à la main, serviront d'assertions dans les tests du parser.

| Tournoi (heure UTC) | Multiplicateur | Mains | Place | Gain | Net |
| --- | --- | --- | --- | --- | --- |
| 10:04 → 10:08 | x4 | 12 | 1re | 4,00 € | +3,00 € |
| 10:09 → 10:12 | x2 | 11 | 2e | 0 | −1,00 € |
| 15:04 → 15:05 | x2 | 4 | 3e | 0 | −1,00 € |
| 15:05 → 15:06 | x2 | 3 | 3e | 0 | −1,00 € |
| 15:07 → 15:11 | x10 | 11 | 1re | 10,00 € | +9,00 € |
| **Total** | | **41** | | **14,00 €** | **+9,00 €** |

- 5 tournois, 5,00 € de buy-ins, ROI **+180 %**, taux de victoire 40 %.
- 2 sessions automatiques : environ 11:04–11:12 et 16:04–16:12 heure de Paris (≈ 16 min de jeu au total).
- Heads-up atteint 3 fois, gagné 2 fois (conversion 67 %).
- Exemple de temps de décision : 7 s pour se coucher avec Ks 7h face à un tapis (15:11:31 → 15:11:38).
- Contrôle de cohérence : pour chaque main, la somme des mises de tous les joueurs doit être égale à `Total Pot`.

> Avant d'être ajouté au dépôt public, le fichier de test sera **anonymisé** (pseudos remplacés, identifiant de compte retiré du nom du fichier).

### 8.4 Modèle de données

Montants en euros en `decimal(10,2)`, jetons en entiers.

| Table | Colonnes principales | Rôle |
| --- | --- | --- |
| `imports` | `user_id`, `filename`, `sha256`, `site`, `status`, `hands_found`, `hands_imported`, `duplicates`, `errors` (JSON), `created_at` | Historique et résumé de chaque import |
| `tournaments` | `user_id`, `site`, `external_id` (Game ID), `game_name`, `game_mode`, `buy_in`, `prize_pool`, `multiplier`, `players_count`, `started_at`, `ended_at`, `hands_count`, `finish_position`, `winnings`, `net_result`, `is_complete`, `poker_session_id` | Un tournoi ; unique sur (`user_id`, `site`, `external_id`) |
| `hands` | `user_id`, `tournament_id`, `import_id`, `external_id` (Hand ID), `played_at`, `small_blind`, `big_blind`, `players_count`, `hero_position`, `hero_cards`, `hero_stack`, `hero_stack_bb`, `board`, `total_pot`, `hero_invested`, `hero_won`, `hero_net`, `went_to_showdown`, `won_at_showdown`, `hero_all_in`, `raw_text`, `parser_version` | Une main ; unique sur (`user_id`, `external_id`) |
| `hand_players` | `hand_id`, `seat`, `name`, `stack`, `position`, `is_hero`, `cards`, `shown_rank`, `finish_position`, `winnings` | Joueurs d'une main (profils adverses en phase 4) |
| `hand_actions` | `hand_id`, `sequence`, `street`, `seat`, `type`, `amount`, `amount_added`, `street_total`, `is_all_in`, `acted_at`, `decision_seconds` | Déroulé de la main, normalisé |
| `poker_sessions` *(existante)* | + `source` (`manual` / `import`) | Les sessions importées sont générées, pas saisies |
| `bankroll_movements` *(phase 2)* | `user_id`, `type` (dépôt, retrait, bonus, rakeback), `amount`, `occurred_at`, `site`, `note` | Mouvements hors résultats de jeu |

L'énumération `PokerFormat` gagne un cas `Spin`. Supprimer un import supprime ses mains ; les tournois et sessions concernés sont recalculés.

### 8.5 Pipeline d'import

1. **Envoi** : un ou plusieurs fichiers `.txt` ou `.zip` (10 Mo max par fichier), stockés en privé dans `storage/app/private/imports/{user}`.
2. **Empreinte** : SHA-256 du fichier. Déjà importé par ce compte → message « fichier déjà importé », rien n'est refait.
3. **ZIP** : extraction contrôlée (`.txt` uniquement, nombre de fichiers et taille décompressée limités, chemins `../` refusés).
4. **Parsing** : découpage en blocs, puis chaque bloc devient un objet `ParsedHand`. Une main illisible est notée dans les erreurs (numéro de bloc, raison) sans bloquer les autres.
5. **Enregistrement** (transaction) : création ou mise à jour des tournois par Game ID, insertion des nouvelles mains (doublons ignorés par Hand ID), joueurs et actions.
6. **Recalcul** des tournois touchés : nombre de mains, début, fin, place, gains, net, complet ou non.
7. **Sessions automatiques** : régénération des sessions importées des jours touchés (voir 8.6).
8. **Résumé** : nouvelles mains, doublons, tournois créés ou complétés, erreurs. Conservé dans l'historique des imports.

L'import est d'abord synchrone (les fichiers sont petits). Il passera dans une file d'attente (`queue`) si les fichiers deviennent volumineux.

**Architecture** : une interface `HandHistoryParser` (`supports(string $content): bool`, `parse(string $content): iterable<ParsedHand>`) avec une première implémentation `BetclicParser`. D'autres sites (Winamax, PokerStars…) pourront être ajoutés sans toucher au reste. Services : `HandImporter`, `TournamentAggregator`, `SessionBuilder`, `TournamentStatsService`, `HandStatsService`.

### 8.6 Règles de calcul

**Tournois et rentabilité**

| Statistique | Calcul |
| --- | --- |
| Net d'un tournoi | `gains − buy-in` |
| Profit net | `Σ nets` des tournois complets |
| ROI | `profit net ÷ Σ buy-ins × 100` |
| Taux de victoire | `1res places ÷ tournois complets × 100` |
| ITM | `tournois avec gain > 0 ÷ tournois complets × 100` (= taux de victoire en Spin & Rush x2) |
| Profit moyen par tournoi | `profit net ÷ tournois complets` |
| Conversion heads-up | `heads-up gagnés ÷ heads-up atteints` (heads-up = une main du tournoi à 2 joueurs) |
| ROI hors jackpots | ROI recalculé sans les tournois au multiplicateur ≥ seuil (par défaut x10) : montre la dépendance aux gros multiplicateurs |
| Part des gains due aux jackpots | `gains des tournois ≥ seuil ÷ gains totaux` |
| Répartition des multiplicateurs | nombre et pourcentage de x2, x4, x10… rencontrés |

**Temps et volume**

| Statistique | Calcul |
| --- | --- |
| Durée d'un tournoi | dernière action de la dernière main − première main |
| Session automatique | tournois triés par heure de début ; une nouvelle session commence si l'écart avec la fin du tournoi précédent dépasse **30 min** |
| Durée d'une session | fin du dernier tournoi − début du premier (les tournois en parallèle ne sont pas comptés deux fois) |
| Tournois par heure / mains par heure | `nombre ÷ heures de session` |
| Profit horaire | `profit net ÷ heures de session` |
| Temps de décision | `heure de l'action − heure de l'action précédente` dans la main (résolution 1 s, inclut les animations) |

**Jetons d'une main** (par joueur et par street)

- `Posts`, `Calls N`, `Bets N` : ajoutent leur montant.
- `Raises to N` : ajoute `N − déjà misé sur cette street`.
- Si au moins deux joueurs restent en fin de main, le plus gros contributeur récupère ce qui dépasse la mise du deuxième (excédent non suivi).
- `hero_invested = Σ ajouts du Hero`, `hero_won = Σ pots gagnés par le Hero`, `hero_net = won − invested`.
- Contrôles : `Σ ajouts de tous les joueurs = Total Pot`, `Σ pots gagnés = Total Pot`, et aucun joueur ne mise plus que son tapis ; sinon la main est signalée en erreur de parsing.

**Statistiques de jeu (phase 3)**

| Statistique | Calcul |
| --- | --- |
| VPIP | mains où le Hero met volontairement des jetons préflop (call, bet, raise ; les blindes ne comptent pas) ÷ mains jouées hors « walks » (tout le monde se couche avant la grosse blinde) |
| PFR | mains avec relance préflop du Hero ÷ mêmes mains |
| 3-bet | relances du Hero face à une relance ÷ occasions |
| Fold to 3-bet | abandons du Hero après avoir été sur-relancé ÷ occasions |
| Push préflop | mains où le Hero part à tapis préflop ÷ mains jouées, ventilé par stack en BB |
| C-bet | le Hero, dernier relanceur préflop, mise en premier au flop ÷ occasions |
| WTSD / W$SD | mains allant à l'abattage ÷ mains vues au flop ; abattages gagnés ÷ abattages |
| Stack en BB | `stack ÷ big blind` ; zone push/fold sous 15 BB |
| Positions | BTN, SB, BB ; en heads-up, BTN = SB |

**Analyses avancées (phase 4)**

- **EV all-in** : quand toutes les cartes sont connues au moment du tapis, l'équité est calculée (énumération exacte du board restant, ou Monte-Carlo si trop coûteux). `EV = équité × pot − mise`. Affichée avec ses hypothèses, jamais comme une prédiction.
- **Drawdown** : plus forte baisse depuis un sommet du profit cumulé, durée sous le sommet, temps de récupération.
- **Variance** : simulation de scénarios à partir du ROI et du volume, avec hypothèses explicites.

### 8.7 Pages

| Page | Contenu |
| --- | --- |
| **Import** (`/imports`) | Zone de dépôt des fichiers, résumé du dernier import, historique (date, fichier, mains, doublons, erreurs), suppression d'un import |
| **Tournois** (`/tournaments`) | Cartes : tournois, profit, ROI, victoires. Tableaux par multiplicateur (nombre, %, profit, ROI) et par buy-in. Répartition des places. Liste filtrable (période, buy-in, multiplicateur, place). |
| **Détail d'un tournoi** | Mains jouées, courbe du stack en BB main par main, place finale, durée |
| **Dashboard** *(existant)* | Inclut les sessions importées ; nouveau format « Spin » dans les filtres ; badge « importée » dans le tableau |
| **Mains** (`/hands`, phase 3) | Liste filtrable, rejeu action par action avec le board |
| **Analyse** (`/analysis`, phase 3) | VPIP, PFR, 3-bet, résultats par position, temps de décision |
| **Démo** | Le compte de démonstration reçoit des Spins fictifs générés (multiplicateurs, places, mains) : la page Tournois est visible sans compte |

### 8.8 Phases

| Phase | Contenu | Livrable vérifiable |
| --- | --- | --- |
| **P1 — Import et tournois** | Tables, parser Betclic, import TXT/ZIP, doublons, agrégats des tournois, sessions automatiques, page Import, page Tournois (ROI, victoires, multiplicateurs, buy-ins, places), Spins fictifs pour la démo | Les valeurs du 8.3 sont retrouvées par les tests ; réimporter le fichier ne change rien |
| **P2 — Périodes et bankroll** | Résultats par jour, semaine, mois, année ; calendrier coloré ; meilleure et pire session ; séries ; drawdown ; conversion heads-up ; résultats par heure de la journée ; dépôts, retraits, bonus séparés ; champs de session facultatifs (fatigue, concentration, tilt, objectif) | Un dépôt ne modifie pas le profit ; le calendrier correspond aux sessions |
| **P3 — Analyse du jeu** | Liste et rejeu des mains, VPIP, PFR, 3-bet, c-bet, WTSD, résultats par position et par stack en BB, temps de décision | Stats vérifiées à la main sur le fichier de test |
| **P4 — Avancé** | EV all-in, courbe réel / EV, simulation de variance, profils adverses avec taille d'échantillon, comparaison de périodes, alertes | Hypothèses affichées à côté de chaque estimation |

### 8.9 Sécurité et confidentialité

- Fichiers stockés hors du dossier public, rattachés au compte, jamais servis directement.
- Taille, type et contenu des fichiers contrôlés (y compris dans les ZIP).
- Chaque requête filtre par compte ; les accès à un import, un tournoi ou une main d'un autre compte renvoient 404 (policies, comme pour les sessions).
- Les pseudos adverses restent privés au compte qui les a importés : aucune donnée n'est partagée entre comptes.
- Supprimer son compte supprime ses imports, fichiers, mains et tournois.

### 8.10 Tests prévus

- **Parser** : le fichier de test anonymisé donne exactement les valeurs du 8.3 (tournois, mains, places, gains, nets).
- **Jetons** : pour chaque main, `Σ mises = Total Pot` ; cas de la mise non suivie et des pots secondaires.
- **Idempotence** : importer deux fois le même fichier, ou deux fichiers qui se chevauchent, donne les mêmes totaux.
- **Tournoi incomplet** : exclu du ROI, signalé comme tel, complété par un import ultérieur.
- **ZIP** : plusieurs fichiers, fichier non `.txt` ignoré, chemin `../` refusé.
- **Sessions automatiques** : deux sessions détectées dans le fichier de test, durées correctes.
- **Isolation** : un compte ne voit ni les imports, ni les tournois, ni les mains d'un autre.
- **Même moteur que l'application** : les tests tournent sur MySQL (base `riverlog_test`), pour vérifier le SQL réellement utilisé.

### 8.11 Points à vérifier sur de vrais exports

- Format des exports Betclic pour les MTT et le Cash Game (seuls des Spins ont été analysés).
- Structure de paiement des gros multiplicateurs (2e et 3e places payées ?).
- Répartition officielle des multiplicateurs annoncée par Betclic, pour comparer la chance réelle à l'attendu.
- Les heures sont-elles toujours en UTC, quel que soit le réglage du compte ?
- Format des exports des autres sites visés (Winamax, PokerStars).
