# Installation et Configuration

## Prérequis

- PHP 8.4+
- Composer 2.8+
- Node.js 22+ (pour les assets)
- Compte AWS avec accès à DynamoDB, SNS, SQS, EventBridge
- AWS CLI configuré
- Compte Binance avec clés API

## Étapes d'Installation

1. Créer le projet Laravel :
   ```bash
   composer create-project laravel/laravel binance-bot
   ```

2. Installer la bibliothèque Binance :
   ```bash
   composer require jaggedsoft/php-binance-api
   ```

3. Configurer les clés API dans `.env`

4. Créer la migration :
   ```bash
   php artisan make:migration create_trades_table
   ```

5. Exécuter les migrations :
   ```bash
   php artisan migrate
   ```

6. Créer les contrôleurs, modèles et services

7. Configurer les routes web

8. Créer les vues Blade

9. Tester en mode manuel avant d'activer l'automatisation

## Variables d'Environnement (.env)

```env
# AWS Configuration
AWS_ACCESS_KEY_ID=votre_access_key
AWS_SECRET_ACCESS_KEY=votre_secret_key
AWS_DEFAULT_REGION=eu-west-3

# DynamoDB
DYNAMODB_TABLE_PREFIX=trading_bot_

# SNS
AWS_SNS_TOPIC_TRADES=arn:aws:sns:eu-west-3:xxxx:trading-alerts
AWS_SNS_TOPIC_ERRORS=arn:aws:sns:eu-west-3:xxxx:trading-errors

# SQS
AWS_SQS_QUEUE_ORDERS=https://sqs.eu-west-3.amazonaws.com/xxxx/orders
AWS_SQS_QUEUE_PRICES=https://sqs.eu-west-3.amazonaws.com/xxxx/prices

# Binance
BINANCE_API_KEY=votre_cle_api
BINANCE_API_SECRET=votre_secret_api

# Bot Configuration
BOT_ENABLED=false
BOT_STRATEGY=rsi
BOT_SYMBOL=BTCUSDT
BOT_AMOUNT=100
```

## Commandes Utiles

```bash
# Lancer le serveur de développement
php artisan serve

# Exécuter le bot manuellement
php artisan bot:run

# Vider le cache
php artisan cache:clear
php artisan config:clear

# Voir les logs
tail -f storage/logs/laravel.log
```

## Tests avec Binance Testnet

Binance propose un environnement de test :
- URL : https://testnet.binance.vision/
- Permet de tester sans risque avec de faux fonds

### Checklist de Test

- [ ] Connexion API réussie
- [ ] Récupération du prix
- [ ] Consultation du solde
- [ ] Passage d'ordre en testnet
- [ ] Calcul correct du RSI
- [ ] Enregistrement des trades en BDD
- [ ] Interface web fonctionnelle
- [ ] Cron job exécuté correctement

## Déploiement AWS Lambda avec Bref

### Installation de Bref
```bash
composer require bref/bref bref/laravel-bridge
php artisan vendor:publish --tag=bref-config
```

### Infrastructure Terraform
L'infrastructure est gérée via Terraform dans le dossier `terraform/` :
- AWS Lambda (via Bref)
- DynamoDB tables
- SNS topics
- SQS queues
- EventBridge rules
- IAM roles et policies

### CI/CD avec GitHub Actions
Le déploiement est automatisé via GitHub Actions (`.github/workflows/deploy.yml`) :
- Déclenché sur push vers `main`
- Exécute les tests
- Terraform plan/apply
- Déploiement Lambda via Bref

#### Secrets GitHub requis
- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `AWS_REGION`

## Documentation

- Laravel 12 : https://laravel.com/docs/12.x
- PHP 8.4 : https://www.php.net/releases/8.4/en.php
- Bref (PHP on Lambda) : https://bref.sh/docs/
- Terraform AWS : https://registry.terraform.io/providers/hashicorp/aws/latest/docs
- GitHub Actions : https://docs.github.com/en/actions
- AWS SDK PHP : https://docs.aws.amazon.com/sdk-for-php/
- DynamoDB : https://docs.aws.amazon.com/dynamodb/
- EventBridge : https://docs.aws.amazon.com/eventbridge/
- Binance API : https://binance-docs.github.io/apidocs/spot/en/
- PHP Binance API : https://github.com/jaggedsoft/php-binance-api
