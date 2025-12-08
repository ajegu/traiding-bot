# Bot Trading Binance - Laravel

## Contexte
Application web Laravel pour automatiser le trading de cryptomonnaies sur Binance.

## Stack Technique
- **Framework** : Laravel 12.x
- **Langage** : PHP 8.4+
- **Cloud Provider** : AWS
- **Compute** : AWS Lambda (via Bref)
- **Base de données** : AWS DynamoDB
- **Messaging** : AWS SNS + AWS SQS + Telegram Bot API
- **Scheduler** : AWS EventBridge (cron jobs)
- **IaC** : Terraform
- **CI/CD** : GitHub Actions
- **Frontend** : Blade + Tailwind CSS
- **API** : Binance API (`jaggedsoft/php-binance-api`)

## Documentation détaillée

### Suivi de Projet
@.claude/docs/TODO.md
@.claude/task/*

### Architecture et Fonctionnalités
@.claude/docs/tech/architecture.md
@.claude/docs/tech/security.md
@.claude/docs/tech/installation.md
@.claude/docs/specs/strategies.md
@.claude/docs/specs/bot-execution.md
@.claude/docs/specs/dashboard.md
@.claude/docs/specs/notifications.md
@.claude/docs/specs/order-management.md
@.claude/docs/specs/reporting.md
@.claude/docs/specs/trade-history.md

### Conventions et Bonnes Pratiques
@.claude/docs/tech/laravel.md
@.claude/docs/tech/aws.md
@.claude/docs/tech/aws-cli-setup.md
@.claude/docs/tech/aws-organizations.md
@.claude/docs/tech/terraform.md
@.claude/docs/tech/github-actions.md
@.claude/docs/tech/telegram.md

## Conventions de Code
- Standard PSR-12
- Typage strict (declare(strict_types=1))
- Nommage : camelCase pour méthodes, PascalCase pour classes
- Services dans `app/Services/`
- Commandes Artisan dans `app/Console/Commands/`

## Règles Importantes
- **AWS Free Tier obligatoire** : rester dans les limites gratuites (voir aws.md)
  - Utiliser SSM Parameter Store (pas Secrets Manager)
  - DynamoDB en mode PAY_PER_REQUEST
  - Lambda sans VPC
  - CloudWatch Logs avec rétention courte (7-14 jours)
- Ne jamais commiter les clés API (.env dans .gitignore)
- Try-catch sur tous les appels API Binance
- Logger toutes les actions critiques avec `Log::info()` / `Log::error()`
- Tester en testnet avant production

## Fonctionnalités Clés
- Dashboard web avec prix en temps réel 
- Stratégies RSI et Moyennes Mobiles
- Commande Artisan : `php artisan bot:run`
- Enregistrement automatique des trades en BDD
- **Reporting quotidien via Telegram** : envoi automatique d'un rapport journalier contenant :
  - Résumé des trades exécutés (achats/ventes)
  - Gains et pertes de la journée (P&L)
  - Solde actuel du compte Binance
  - Déclenché via EventBridge (cron quotidien)
  - Envoi via Telegram Bot API

## Améliorations Futures

### Court Terme
- Stop-loss automatique
- Notifications email/Telegram
- Graphiques de performance
- Multi-paires

### Moyen Terme
- Backtesting des stratégies
- Interface de création de stratégies
- API REST de contrôle
- Mode paper trading

### Long Terme
- Machine Learning
- Support multi-exchanges
- Application mobile
- Métriques avancées (Sharpe ratio, drawdown)

## Avertissement
Le trading de cryptomonnaies comporte des risques. Ne tradez qu'avec de l'argent que vous pouvez vous permettre de perdre. Commencez avec de petits montants et testez exhaustivement en testnet.
