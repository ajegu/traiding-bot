# Laravel - Conventions et Bonnes Pratiques

## Structure du Projet

```
app/
├── Console/Commands/       # Commandes Artisan
├── Enums/                  # Énumérations PHP 8.4
├── Events/                 # Événements applicatifs
├── Exceptions/             # Exceptions personnalisées
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Requests/           # Form Requests (validation)
├── Jobs/                   # Jobs asynchrones (SQS)
├── Listeners/              # Listeners d'événements
├── Models/                 # Modèles Eloquent/DynamoDB
├── Providers/              # Service Providers
└── Services/               # Logique métier
    ├── Binance/
    └── Trading/
        ├── Strategies/
        └── Indicators/
```

## Conventions de Nommage

### Classes

| Type | Convention | Exemple |
|------|------------|---------|
| Controller | PascalCase + `Controller` | `BotController` |
| Model | PascalCase, singulier | `Trade` |
| Service | PascalCase + `Service` | `BinanceService` |
| Job | PascalCase, verbe d'action | `ProcessOrder` |
| Event | PascalCase, passé composé | `TradeExecuted` |
| Listener | PascalCase, verbe d'action | `SendTradeNotification` |
| Request | PascalCase + `Request` | `ExecuteTradeRequest` |
| Exception | PascalCase + `Exception` | `BinanceApiException` |
| Enum | PascalCase | `OrderStatus` |
| Command | PascalCase + `Command` | `RunBotCommand` |

### Méthodes et Variables

| Type | Convention | Exemple |
|------|------------|---------|
| Méthodes | camelCase, verbes d'action | `executeOrder()`, `calculateRsi()` |
| Variables | camelCase | `$currentPrice`, `$tradeAmount` |
| Constantes | SCREAMING_SNAKE_CASE | `MAX_RETRY_ATTEMPTS` |
| Propriétés | camelCase | `$binanceClient` |

### Fichiers

| Type | Convention | Exemple |
|------|------------|---------|
| Classes | PascalCase.php | `BinanceService.php` |
| Config | kebab-case.php | `trading-bot.php` |
| Views | kebab-case.blade.php | `trade-history.blade.php` |
| Migrations | snake_case avec timestamp | `2024_01_15_create_trades_table.php` |

## Bonnes Pratiques

### 1. Typage Strict
- Toujours utiliser `declare(strict_types=1)` en début de fichier
- Typer tous les paramètres et retours de méthodes
- Utiliser les types union PHP 8.4 quand approprié

### 2. Architecture
- **Services** : Logique métier isolée, injectable
- **DTOs** : Data Transfer Objects pour les données structurées
- **Enums** : Pour toutes les constantes avec valeurs fixes
- **Form Requests** : Validation centralisée
- **Events/Listeners** : Découplage des actions

### 3. Injection de Dépendances
- Utiliser le constructor injection
- Déclarer les dépendances comme `private readonly`
- Enregistrer les bindings dans les Service Providers

### 4. Gestion des Erreurs
- Try-catch sur tous les appels externes (API Binance)
- Exceptions personnalisées par domaine
- Logger toutes les erreurs avec contexte

### 5. Logging
- `Log::info()` pour les actions normales
- `Log::warning()` pour les situations inhabituelles
- `Log::error()` pour les erreurs avec contexte complet
- Toujours inclure les données pertinentes dans le contexte

### 6. Configuration
- Variables d'environnement pour les valeurs sensibles
- Fichiers config/ pour les valeurs par défaut
- Ne jamais hardcoder de valeurs

### 7. Tests
- Tests unitaires pour les Services et Indicators
- Tests feature pour les Controllers et Commands
- Mocking des appels API externes

## Configuration AWS Lambda (Bref)

### Packages Requis
- `bref/bref` : Runtime PHP pour Lambda
- `bref/laravel-bridge` : Intégration Laravel

### Optimisations Lambda
- Logging vers stderr (CloudWatch)
- Cache/Session via DynamoDB
- Queues via SQS
- Pas de stockage local persistant

### Variables d'Environnement Essentielles
```env
LOG_CHANNEL=stderr
CACHE_DRIVER=dynamodb
SESSION_DRIVER=dynamodb
QUEUE_CONNECTION=sqs
```

## Commandes Artisan Utiles

```bash
# Développement
php artisan serve
php artisan tinker

# Cache (production)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Tests
php artisan test
php artisan test --filter=NomDuTest

# Génération
php artisan make:model Trade
php artisan make:controller BotController
php artisan make:command RunBotCommand
php artisan make:job ProcessOrder
php artisan make:event TradeExecuted

# Bot
php artisan bot:run
php artisan bot:run --dry-run
```

## Règles Importantes

1. **Sécurité API** : Ne jamais exposer les clés Binance
2. **Validation** : Toujours valider les entrées utilisateur via Form Requests
3. **Transactions** : Utiliser les transactions DB pour les opérations critiques
4. **Idempotence** : Les Jobs doivent être idempotents (réexécutables)
5. **Rate Limiting** : Respecter les limites de l'API Binance
6. **Retry Logic** : Implémenter des retries avec backoff exponentiel
