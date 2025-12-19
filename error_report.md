# Rapport d'Analyse - Commandes Artisan Trading Bot

**Date** : 19 décembre 2025
**Commandes analysées** : `bot:run`, `report:daily`

---

## Erreurs identifiées et corrigées

| # | Erreur | Fichier | Status |
|---|--------|---------|--------|
| 1 | Noms d'index GSI incorrects (`-index` en trop) | `DynamoDbTradeRepository.php` | ✅ Corrigé |
| 2 | Nom de table construit avec `app.env` (retourne `local` au lieu de `dev`) | Tous les repositories | ✅ Corrigé |
| 3 | Query `BETWEEN` sur partition key (non supporté par DynamoDB) | `DynamoDbTradeRepository.php` | ✅ Corrigé |
| 4 | Type mismatch Strategy enum vs string | `RunBot.php` | ✅ Corrigé |
| 5 | `price()` retourne string, pas array | `BinanceClient.php` | ✅ Corrigé |
| 6 | `Undefined array key 0` dans les indicateurs | À investiguer | ⏳ En cours |

---

## Détail des corrections

### 1. Noms d'index GSI incorrects

**Problème** : Les index GSI dans le code avaient un suffixe `-index` qui n'existe pas dans DynamoDB.

**Fichier** : `app/Repositories/DynamoDbTradeRepository.php`

**Avant** :
```php
'IndexName' => 'gsi2-date-index',
'IndexName' => 'gsi1-symbol-date-index',
'IndexName' => 'gsi3-status-index',
```

**Après** :
```php
'IndexName' => 'gsi2-date',
'IndexName' => 'gsi1-symbol-date',
'IndexName' => 'gsi3-status',
```

---

### 2. Nom de table construit incorrectement

**Problème** : Les repositories utilisaient `config('app.env')` qui retourne `local` en développement au lieu de `dev`.

**Fichiers** :
- `app/Repositories/DynamoDbTradeRepository.php`
- `app/Repositories/DynamoDbBotConfigRepository.php`
- `app/Repositories/DynamoDbReportRepository.php`

**Avant** :
```php
$environment = config('app.env', 'dev');
$this->tableName = self::TABLE_NAME_PREFIX."-{$environment}-trades";
```

**Après** :
```php
$this->tableName = (string) config('services.dynamodb.tables.trades', 'trading-bot-dev-trades');
```

---

### 3. Query DynamoDB invalide

**Problème** : `BETWEEN` sur une partition key n'est pas supporté par DynamoDB.

**Fichier** : `app/Repositories/DynamoDbTradeRepository.php` - méthode `findByDateRange()`

**Solution** : Itérer sur chaque jour de la période au lieu d'utiliser `BETWEEN`.

---

### 4. Type mismatch Strategy

**Problème** : `$config->strategy` est de type `string` mais le code attendait un enum `Strategy`.

**Fichier** : `app/Console/Commands/RunBot.php` ligne 97

**Avant** :
```php
$strategy = $strategyOption ? Strategy::from($strategyOption) : ($config->strategy ?? Strategy::from(config('bot.trading.strategy')));
```

**Après** :
```php
$strategyString = $strategyOption ?? $config->strategy ?? config('bot.trading.strategy');
$strategy = Strategy::from($strategyString);
```

---

### 5. Format de retour `price()`

**Problème** : La méthode `price('BTCUSDT')` de la lib Binance retourne directement une string, pas un array.

**Fichier** : `app/Services/Binance/BinanceClient.php`

**Solution** : Gérer les deux cas (string et array).

---

## Erreur actuelle : `Undefined array key 0`

### Contexte
Se produit lors de l'analyse du marché (calcul des indicateurs techniques).

### Cause probable
La méthode `getKlines()` de `BinanceClient` retourne des données dans un format différent de celui attendu par les indicateurs.

### Fichiers à vérifier
- `app/Services/Binance/BinanceClient.php` - méthode `getKlines()`
- `app/Services/Trading/Indicators/RsiIndicator.php`
- `app/Services/Trading/Indicators/MovingAverageIndicator.php`

---

## Solutions de résolution

### Solution 1 : Débugger `getKlines()`

```bash
eval "$(aws configure export-credentials --profile tb-dev --format env)" && php -r "
require 'vendor/autoload.php';
\$api = new Binance\API('', '', true);
\$klines = \$api->candlesticks('BTCUSDT', '5m', 10);
echo 'Type: ' . gettype(\$klines) . PHP_EOL;
echo 'Keys: ' . implode(', ', array_keys(\$klines)) . PHP_EOL;
print_r(\$klines[array_key_first(\$klines)]);
"
```

### Solution 2 : Vérifier les clés API Binance Testnet

Les clés actuelles peuvent être invalides ou pour le mauvais environnement.

**Action** : Créer de nouvelles clés sur https://testnet.binance.vision/
1. Se connecter avec GitHub
2. Générer de nouvelles clés API
3. Mettre à jour `.env` :
   ```
   BINANCE_API_KEY=nouvelle_cle
   BINANCE_API_SECRET=nouveau_secret
   ```

### Solution 3 : Exécuter les tests unitaires

```bash
eval "$(aws configure export-credentials --profile tb-dev --format env)" && php artisan test --filter=Binance
```

---

## Commandes de diagnostic

```bash
# 1. Tester la connexion AWS
AWS_PROFILE=tb-dev aws sts get-caller-identity

# 2. Tester DynamoDB
AWS_PROFILE=tb-dev aws dynamodb scan --table-name trading-bot-dev-trades --limit 1

# 3. Tester Binance (prix public)
curl -s "https://testnet.binance.vision/api/v3/ticker/price?symbol=BTCUSDT"

# 4. Exécuter le rapport avec AWS
eval "$(aws configure export-credentials --profile tb-dev --format env)" && php artisan report:daily --dry-run

# 5. Exécuter le bot avec AWS
eval "$(aws configure export-credentials --profile tb-dev --format env)" && php artisan bot:run --dry-run --force
```

---

## Configuration requise

### Variables d'environnement `.env`

```env
# AWS (utilisé via SSO, pas besoin de clés dans .env)
AWS_DEFAULT_REGION=eu-west-3

# DynamoDB Tables
DYNAMODB_TABLE_TRADES=trading-bot-dev-trades
DYNAMODB_TABLE_BOT_CONFIG=trading-bot-dev-bot-config
DYNAMODB_TABLE_REPORTS=trading-bot-dev-reports

# Binance Testnet
BINANCE_API_KEY=<clé_testnet>
BINANCE_API_SECRET=<secret_testnet>
BINANCE_TESTNET=true
```

### Alias recommandés (`~/.zshrc`)

```bash
# Fonction pour artisan avec credentials AWS SSO
artisan-aws() {
    eval "$(aws configure export-credentials --profile tb-dev --format env)" && php artisan "$@"
}

# Raccourcis
alias bot-run='artisan-aws bot:run'
alias bot-dry='artisan-aws bot:run --dry-run --force'
alias report-daily='artisan-aws report:daily'
alias report-dry='artisan-aws report:daily --dry-run'
```

---

## Fichiers modifiés

| Fichier | Modifications |
|---------|---------------|
| `app/Providers/AppServiceProvider.php` | Ajout SnsClient et SnsNotificationService |
| `app/Repositories/DynamoDbTradeRepository.php` | Correction noms d'index et table |
| `app/Repositories/DynamoDbBotConfigRepository.php` | Correction nom de table |
| `app/Repositories/DynamoDbReportRepository.php` | Correction nom de table |
| `app/Console/Commands/RunBot.php` | Correction type Strategy, suppression option verbose |
| `app/Console/Commands/DailyReport.php` | Suppression option verbose |
| `app/Services/Binance/BinanceClient.php` | Correction format retour price() |
| `config/aws.php` | Ajout support profil SSO |
| `config/services.php` | Ajout sns.enabled |
| `.claude/docs/tech/aws-cli-setup.md` | Documentation utilisation Laravel avec SSO |
| `.gitignore` | Ajout ca.pem |
