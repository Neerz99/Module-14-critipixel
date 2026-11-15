<img src="assets/images/logo.png" alt="CritiPixel" width="200" />

# CritiPixel

## Pré-requis
* PHP >= 8.2
* Composer
* Extension PHP Xdebug
* Symfony (binaire)

## Installation

### Composer
Dans un premier temps, installer les dépendances :
```bash
composer install
```

### Docker (optionnel)
Si vous souhaitez utiliser Docker Compose, il vous suffit de lancer la commande suivante :
```bash
docker compose up -d
```

## Configuration

### Base de données
Actuellement, le fichier `.env` est configuré pour la base de données PostgreSQL mise en place dans `docker-compose.yml`.
Cependant, vous pouvez créer un fichier `.env.local` si nécessaire pour configurer l'accès à la base de données.
Exemple :
```dotenv
DATABASE_URL=mysql://root:Password123!@host:3306/criti-pixel
```

### PHP (optionnel)
Vous pouvez surcharger la configuration PHP en créant un fichier `php.local.ini`.

De même pour la version de PHP que vous pouvez spécifier dans un fichier `.php-version`.

## Usage

### Base de données

#### Supprimer la base de données
```bash
symfony console doctrine:database:drop --force --if-exists
```

#### Créer la base de données
```bash
symfony console doctrine:database:create
```

#### Exécuter les migrations
```bash
symfony console doctrine:migrations:migrate -n
```

#### Charger les fixtures
```bash
symfony console doctrine:fixtures:load -n --purge-with-truncate
```

*Note : Vous pouvez exécuter ces commandes avec l'option `--env=test` pour les exécuter dans l'environnement de test.*

### SASS

#### Compiler les fichiers SASS
```bash
symfony console sass:build
```
*Note : le fichier `.symfony.local.yaml` est configuré pour surveiller les fichiers SASS et les compiler automatiquement quand vous lancez le serveur web de Symfony.*

### Tests
```bash
symfony console sass:build --env=test
composer test
```

`composer test` recrée la base de test, charge les fixtures puis lance PHPUnit.
Chaque test est exécuté dans une transaction annulée à la fin (DAMADoctrineTestBundle) :
les fixtures n'ont donc besoin d'être chargées qu'une seule fois.

* Tests unitaires : `tests/Unit` (calcul de la moyenne et du nombre de notes par valeur)
* Tests fonctionnels : `tests/Functional` (inscription, connexion, ajout d'un avis, filtrage par recherche et par tags)

### Qualité du code
```bash
composer phpstan    # PHPStan niveau 6 (extensions Symfony et Doctrine)
composer cs-check   # PHP CS Fixer (règles PSR-12 et Symfony), sans modifier les fichiers
composer cs-fix     # applique les corrections de style
```

*Note : PHPStan s'appuie sur le conteneur du cache `dev`, lancer `symfony console cache:warmup` au préalable.*

### Intégration continue
Le workflow GitHub Actions `.github/workflows/ci.yml` est exécuté à chaque push sur `main` et à chaque pull request :

* analyse statique : PHP CS Fixer puis PHPStan ;
* tests : PostgreSQL 16, compilation Sass, migrations, fixtures puis PHPUnit avec couverture, sur PHP 8.2 et 8.3.

### Serveur web
```bash
symfony serve
```