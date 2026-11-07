# Symcord : une messagerie inspirée de Discord, avec Symfony

Projet réalisé pour le module « Symfony, le framework PHP » : une application de messagerie
façon Discord (**Sym**fony + Dis**cord**), développée avec **Symfony 7.4 (LTS)**, Doctrine et Twig.

## Fonctionnalités

**Gestion des utilisateurs**
- Inscription : adresse e-mail valide et unique, nom d'utilisateur unique (2 à 32 caractères),
  mot de passe de 8 caractères minimum saisi deux fois. Le mot de passe est haché, jamais stocké en clair.
- Connexion avec l'e-mail **ou** le nom d'utilisateur, case « Se souvenir de moi ».
- Déconnexion.
- Toutes les pages, sauf l'inscription et la connexion, sont réservées aux membres connectés.

**Gestion des salons**
- Création d'un salon (nom unique de 2 à 50 caractères, sujet facultatif).
- Modification et suppression, réservées au créateur du salon ou à un administrateur.
- La suppression demande une confirmation et efface aussi tous les messages du salon (cascade).

**Messagerie**
- Envoi et consultation des messages dans chaque salon.
- Formulaire de saisie avec contrôles de validation : message non vide (les espaces seuls ne comptent pas)
  et 2 000 caractères maximum, avec un compteur quand on approche de la limite.
- Affichage façon Discord : avatars, messages consécutifs regroupés, séparateurs de date,
  « Aujourd'hui à 14:32 ».
- Envoi sans rechargement (`Entrée` pour envoyer, `Maj + Entrée` pour aller à la ligne).
- Rafraîchissement automatique : les messages des autres membres apparaissent en quelques secondes.
- Chargement des messages plus anciens à la demande (50 messages affichés à l'ouverture).
- Suppression d'un message par son auteur, le créateur du salon ou un administrateur.

Tout fonctionne aussi sans JavaScript (formulaires classiques avec rechargement de la page).

## Prérequis

- **PHP 8.2 ou plus**, avec les extensions `ctype`, `iconv` et `pdo_sqlite`
  (ou `pdo_mysql` / `pdo_pgsql` si vous utilisez MySQL, MariaDB ou PostgreSQL)
- **Composer 2**
- [Symfony CLI](https://symfony.com/download), recommandé mais facultatif

Aucun outil front-end n'est nécessaire : pas de Node.js, pas de npm, pas d'étape de build.
Le CSS et le JavaScript sont de simples fichiers dans `public/`.

## Installation rapide (SQLite, sans configuration)

```bash
git clone <URL-du-depot> symcord
cd symcord
composer install
composer setup
symfony serve
```

Puis ouvrir <http://127.0.0.1:8000>.

- `composer install` installe les dépendances (les dossiers `vendor/` et `var/` ne sont pas versionnés).
- `composer setup` crée les tables avec les **migrations Doctrine** puis charge les **données de
  démonstration**. C'est un raccourci pour :
  ```bash
  php bin/console doctrine:migrations:migrate --no-interaction
  php bin/console doctrine:fixtures:load --no-interaction
  ```
- Sans Symfony CLI : `php -S 127.0.0.1:8000 -t public`

Par défaut la base de données est un fichier **SQLite** créé automatiquement (`var/data_dev.db`) :
il n'y a rien à configurer. Avec SQLite, la commande `doctrine:database:create` n'est pas nécessaire
(elle n'est d'ailleurs pas prise en charge par Doctrine pour SQLite).

## Utiliser MySQL, MariaDB ou PostgreSQL (facultatif)

1. Créer un fichier **`.env.local`** à la racine du projet (il n'est pas versionné) avec **une** des
   lignes suivantes, en adaptant l'utilisateur, le mot de passe, le port et la version du serveur :

   ```dotenv
   # XAMPP / WAMP (MariaDB, utilisateur root sans mot de passe)
   DATABASE_URL="mysql://root:@127.0.0.1:3306/symcord?serverVersion=10.4.32-MariaDB&charset=utf8mb4"

   # MAMP (utilisateur root, mot de passe root, port 8889)
   DATABASE_URL="mysql://root:root@127.0.0.1:8889/symcord?serverVersion=8.0.32&charset=utf8mb4"

   # MySQL 8
   DATABASE_URL="mysql://root:motdepasse@127.0.0.1:3306/symcord?serverVersion=8.0.32&charset=utf8mb4"

   # PostgreSQL
   DATABASE_URL="postgresql://app:motdepasse@127.0.0.1:5432/symcord?serverVersion=16&charset=utf8"
   ```

2. Créer la base, puis les tables et les données de démonstration :

   ```bash
   php bin/console doctrine:database:create
   composer setup
   ```

La migration du projet (`migrations/Version20261107140000.php`) contient le SQL généré par Doctrine
pour chaque SGBD. Elle a été testée avec SQLite 3, MySQL 8.4, MariaDB 10.4 et PostgreSQL 16, et
`php bin/console doctrine:schema:validate` confirme que la base correspond aux entités.

## Comptes de démonstration

Créés par `composer setup` (ou `php bin/console doctrine:fixtures:load`), avec trois salons
(`#général`, `#aide-symfony`, `#détente`) et quelques messages.

| E-mail              | Nom d'utilisateur | Mot de passe | Rôle                                         |
|---------------------|-------------------|--------------|----------------------------------------------|
| `admin@example.com` | `admin`           | `password`   | Administrateur : gère tous les salons/messages |
| `alice@example.com` | `alice`           | `password`   | Membre, créatrice de `#aide-symfony`          |
| `bob@example.com`   | `bob`             | `password`   | Membre, créateur de `#détente`                |
| `chloe@example.com` | `chloe`           | `password`   | Membre                                       |

Vous pouvez aussi créer votre propre compte avec le lien « S'inscrire ».
Recharger les fixtures remet la base dans son état de départ.

## Tests automatisés

```bash
composer test
```

45 tests fonctionnels (PHPUnit + navigateur de test de Symfony) couvrent l'inscription, la connexion,
la déconnexion, la création/modification/suppression des salons, l'envoi et la suppression des
messages, les règles de validation, les droits d'accès et la protection CSRF. Ils utilisent leur propre
base SQLite (`var/data_test.db`), recréée à chaque lancement à partir des migrations.

## Organisation du code

```
config/packages/security.yaml   Sécurité : formulaire de connexion, déconnexion, accès réservé aux membres
migrations/                     Migration Doctrine (SQLite, MySQL, MariaDB, PostgreSQL)
public/css/app.css              Thème sombre inspiré de Discord
public/js/app.js                Notifications, confirmations, menu mobile
public/js/chat.js               Chat : envoi en AJAX, rafraîchissement, historique, suppression
src/Controller/                 Accueil, inscription, connexion, salons, messages
src/DataFixtures/               Données de démonstration
src/Entity/                     User, Channel (salon) et Message, avec leurs contraintes de validation
src/Form/                       Formulaires d'inscription, de salon et de message
src/Repository/                 Requêtes Doctrine (connexion par e-mail ou pseudo, pagination des messages…)
src/Security/Voter/             Qui peut modifier/supprimer un salon ou un message
templates/                      Vues Twig (mise en page, formulaires, salons, messages, erreurs)
tests/                          Tests fonctionnels PHPUnit
```

### Routes

| Route                | Méthode  | URL                       | Rôle                                               |
|----------------------|----------|---------------------------|----------------------------------------------------|
| `app_register`       | GET/POST | `/register`               | Inscription                                        |
| `app_login`          | GET/POST | `/login`                  | Connexion                                          |
| `app_logout`         | GET      | `/logout`                 | Déconnexion                                        |
| `app_home`           | GET      | `/`                       | Accueil : liste des salons                         |
| `app_channel_new`    | GET/POST | `/channels/new`           | Créer un salon                                     |
| `app_channel_show`   | GET/POST | `/channels/{id}`          | Afficher un salon et envoyer un message            |
| `app_channel_edit`   | GET/POST | `/channels/{id}/edit`     | Modifier un salon (créateur ou admin)              |
| `app_channel_delete` | POST     | `/channels/{id}/delete`   | Supprimer un salon (créateur ou admin)             |
| `app_message_list`   | GET      | `/channels/{id}/messages` | Nouveaux messages / historique, en JSON (AJAX)     |
| `app_message_delete` | POST     | `/messages/{id}/delete`   | Supprimer un message (auteur, créateur ou admin)   |

## Choix techniques

- **Sécurité** : authentification `form_login` protégée par jeton CSRF, mots de passe hachés
  (`password_hashers: auto`), « Se souvenir de moi », `access_control` pour réserver l'application aux
  membres, hiérarchie de rôles (`ROLE_ADMIN` hérite de `ROLE_USER`). Les droits sur les salons et les
  messages sont centralisés dans deux **Voters** (`ChannelVoter`, `MessageVoter`), utilisés dans les
  contrôleurs (`#[IsGranted]`) et dans Twig (`is_granted()`). Les suppressions sont protégées par jeton CSRF.
- **Validation** : contraintes sur les entités (`NotBlank`, `Length`, `Email`, `Regex`, `UniqueEntity`)
  et sur le mot de passe dans le formulaire d'inscription. Les formulaires ont l'attribut `novalidate` pour
  que ce soient les messages de Symfony (en français) qui s'affichent ; un formulaire invalide renvoie le
  code HTTP 422.
- **Doctrine** : trois entités reliées par des `ManyToOne` avec `ON DELETE CASCADE`. Un middleware
  (`src/Doctrine/SqliteForeignKeysMiddleware.php`) active les clés étrangères de SQLite, désactivées par
  défaut, pour que la cascade fonctionne aussi avec SQLite.
- **Front-end** : Twig, un thème de formulaire personnalisé, du CSS et du JavaScript natifs sans
  dépendance. Les URL des fichiers CSS/JS contiennent leur date de modification (`?v=…`) pour éviter les
  problèmes de cache. Le « temps réel » repose sur un rafraîchissement toutes les 3 secondes, qui ne
  demande aucun serveur supplémentaire (contrairement à Mercure ou aux WebSockets).

## Dépannage

- **`could not find driver`** : l'extension PHP de la base choisie n'est pas activée. Dans le `php.ini`,
  décommenter `extension=pdo_sqlite` (ou `extension=pdo_mysql` / `extension=pdo_pgsql`), puis relancer le serveur.
- **`doctrine:database:create` échoue avec SQLite** : c'est normal, cette étape est inutile avec SQLite.
  Lancer directement `composer setup`.
- **Repartir d'une base vide** : `composer setup` vide la base et recharge les données de démonstration.
- **Le port 8000 est déjà utilisé** : `symfony serve --port=8001` ou `php -S 127.0.0.1:8001 -t public`.
- **Apache (XAMPP, WAMP…)** : le fichier `public/.htaccess` gère la réécriture d'URL (module
  `mod_rewrite` requis). Faire pointer le VirtualHost sur le dossier `public/`, ou ouvrir
  `http://localhost/<dossier-du-projet>/public/`.
- La barre de debug de Symfony, en bas de l'écran, n'apparaît qu'en environnement de développement.
