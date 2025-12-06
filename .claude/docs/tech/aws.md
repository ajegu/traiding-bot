# AWS - Conventions et Bonnes Pratiques

## Free Tier - Règle Obligatoire

Ce projet doit rester dans les limites du **AWS Free Tier**. Tous les choix d'architecture doivent respecter cette contrainte.

### Limites Free Tier (Always Free)

| Service | Limite Gratuite | Utilisation Projet |
|---------|-----------------|-------------------|
| Lambda | 1M requêtes/mois + 400K GB-sec | ~9K requêtes/mois |
| DynamoDB | 25 GB + 25 RCU/WCU | < 1 GB |
| SNS | 1M publications/mois | < 1K/mois |
| SQS | 1M requêtes/mois | < 10K/mois |
| EventBridge | 1M events/mois (default bus) | ~9K/mois |
| SSM Parameter Store | 10K paramètres (standard) | < 10 paramètres |
| CloudWatch Logs | 5 GB ingestion + 5 GB stockage | Variable |

### Services à Éviter (Payants)

| Service | Alternative Gratuite |
|---------|---------------------|
| Secrets Manager | SSM Parameter Store (SecureString) |
| NAT Gateway | Pas de VPC (Lambda public) |
| RDS | DynamoDB |
| ElastiCache | DynamoDB / Cache applicatif |

### Bonnes Pratiques Free Tier

1. **Utiliser SSM Parameter Store** au lieu de Secrets Manager pour les secrets
2. **DynamoDB en mode PAY_PER_REQUEST** (on-demand) pour rester dans les 25 RCU/WCU
3. **CloudWatch Logs** : rétention courte (7-14 jours) pour limiter le stockage
4. **Lambda** : optimiser la mémoire (128-512 MB) pour réduire les GB-seconds
5. **Pas de VPC** : Lambda en mode public pour éviter NAT Gateway

## Convention de Nommage Globale

### Format Standard
```
{projet}-{environnement}-{service}-{description}
```

| Composant | Description | Exemple |
|-----------|-------------|---------|
| `projet` | Nom du projet | `trading-bot` |
| `environnement` | dev, staging, prod | `prod` |
| `service` | Type de service AWS | `lambda`, `sqs`, `sns` |
| `description` | Fonction spécifique | `executor`, `orders` |

## Services AWS

### Lambda

#### Nommage
```
trading-bot-{env}-lambda-{fonction}
```
Exemples :
- `trading-bot-prod-lambda-executor`
- `trading-bot-prod-lambda-price-fetcher`
- `trading-bot-prod-lambda-order-processor`

#### Bonnes Pratiques
- Timeout adapté à la fonction (défaut : 30s, max recommandé : 60s)
- Memory : commencer à 512MB, ajuster selon les besoins
- Variables d'environnement pour la configuration
- Layers pour les dépendances partagées (Bref PHP runtime)
- Dead Letter Queue (DLQ) pour les erreurs

```hcl
resource "aws_lambda_function" "executor" {
  function_name = "${local.name_prefix}-lambda-executor"
  runtime       = "provided.al2"
  timeout       = 30
  memory_size   = 512

  dead_letter_config {
    target_arn = aws_sqs_queue.dlq.arn
  }

  environment {
    variables = {
      APP_ENV     = var.environment
      LOG_CHANNEL = "stderr"
    }
  }
}
```

### DynamoDB

#### Nommage des Tables
```
trading-bot-{env}-{entité}
```
Exemples :
- `trading-bot-prod-trades`
- `trading-bot-prod-bot-config`
- `trading-bot-prod-price-history`

#### Nommage des Index (GSI/LSI)
```
{attribut}-index
```
Exemples :
- `status-index`
- `created-at-index`
- `symbol-timestamp-index`

#### Bonnes Pratiques
- Utiliser le mode PAY_PER_REQUEST pour le développement
- Mode PROVISIONED avec auto-scaling pour la production
- Point-in-time recovery activé en production
- TTL pour les données temporaires

```hcl
resource "aws_dynamodb_table" "trades" {
  name         = "${local.name_prefix}-trades"
  billing_mode = var.environment == "prod" ? "PROVISIONED" : "PAY_PER_REQUEST"
  hash_key     = "pk"
  range_key    = "sk"

  attribute {
    name = "pk"
    type = "S"
  }

  attribute {
    name = "sk"
    type = "S"
  }

  point_in_time_recovery {
    enabled = var.environment == "prod"
  }

  ttl {
    attribute_name = "ttl"
    enabled        = true
  }
}
```

#### Single Table Design
```
PK                    | SK                      | Données
----------------------|-------------------------|------------------
TRADE#123             | METADATA                | {symbol, type...}
TRADE#123             | STATUS#2024-01-15       | {status, updated}
CONFIG#bot            | SETTINGS                | {enabled, strategy}
PRICE#BTCUSDT         | 2024-01-15T10:00:00     | {price, volume}
```

### SQS

#### Nommage
```
trading-bot-{env}-{fonction}
trading-bot-{env}-{fonction}-dlq  (Dead Letter Queue)
```
Exemples :
- `trading-bot-prod-orders`
- `trading-bot-prod-orders-dlq`
- `trading-bot-prod-price-updates`

#### Bonnes Pratiques
- Toujours créer une DLQ associée
- Visibility timeout > Lambda timeout
- Message retention adapté (défaut : 4 jours)
- Batch size optimal pour Lambda (10 messages)

```hcl
resource "aws_sqs_queue" "orders" {
  name                       = "${local.name_prefix}-orders"
  visibility_timeout_seconds = 60
  message_retention_seconds  = 345600  # 4 jours

  redrive_policy = jsonencode({
    deadLetterTargetArn = aws_sqs_queue.orders_dlq.arn
    maxReceiveCount     = 3
  })
}

resource "aws_sqs_queue" "orders_dlq" {
  name                      = "${local.name_prefix}-orders-dlq"
  message_retention_seconds = 1209600  # 14 jours
}
```

### SNS

#### Nommage des Topics
```
trading-bot-{env}-{type-notification}
```
Exemples :
- `trading-bot-prod-trade-alerts`
- `trading-bot-prod-error-notifications`
- `trading-bot-prod-daily-reports`

#### Bonnes Pratiques
- Utiliser des filter policies pour les subscriptions
- Activer le logging pour le debugging
- Dead Letter Queue pour les messages non délivrés

```hcl
resource "aws_sns_topic" "trade_alerts" {
  name = "${local.name_prefix}-trade-alerts"
}

resource "aws_sns_topic_subscription" "email" {
  topic_arn = aws_sns_topic.trade_alerts.arn
  protocol  = "email"
  endpoint  = var.alert_email

  filter_policy = jsonencode({
    type = ["BUY", "SELL"]
  })
}
```

### EventBridge

#### Nommage des Rules
```
trading-bot-{env}-rule-{description}
```
Exemples :
- `trading-bot-prod-rule-bot-executor-5min`
- `trading-bot-prod-rule-daily-report`
- `trading-bot-prod-rule-price-check`

#### Bonnes Pratiques
- Descriptions claires pour chaque règle
- Utiliser des expressions cron pour les schedules complexes
- Tags pour l'organisation

```hcl
resource "aws_cloudwatch_event_rule" "bot_executor" {
  name                = "${local.name_prefix}-rule-bot-executor-5min"
  description         = "Exécute le bot de trading toutes les 5 minutes"
  schedule_expression = "rate(5 minutes)"

  tags = local.common_tags
}

resource "aws_cloudwatch_event_target" "lambda" {
  rule      = aws_cloudwatch_event_rule.bot_executor.name
  target_id = "TradingBotLambda"
  arn       = aws_lambda_function.executor.arn
}
```

### IAM

#### Nommage des Rôles
```
trading-bot-{env}-role-{service}-{fonction}
```
Exemples :
- `trading-bot-prod-role-lambda-executor`
- `trading-bot-prod-role-eventbridge-invoke`

#### Nommage des Policies
```
trading-bot-{env}-policy-{description}
```
Exemples :
- `trading-bot-prod-policy-dynamodb-access`
- `trading-bot-prod-policy-sqs-send`
- `trading-bot-prod-policy-sns-publish`

#### Bonnes Pratiques
- Principe du moindre privilège
- Policies spécifiques par ressource
- Éviter les wildcards (`*`) sauf nécessité

```hcl
data "aws_iam_policy_document" "lambda_dynamodb" {
  statement {
    effect = "Allow"
    actions = [
      "dynamodb:GetItem",
      "dynamodb:PutItem",
      "dynamodb:UpdateItem",
      "dynamodb:Query"
    ]
    resources = [
      aws_dynamodb_table.trades.arn,
      "${aws_dynamodb_table.trades.arn}/index/*"
    ]
  }
}
```

### CloudWatch

#### Nommage des Log Groups
```
/aws/lambda/trading-bot-{env}-{fonction}
```
(Créés automatiquement par Lambda)

#### Nommage des Alarmes
```
trading-bot-{env}-alarm-{métrique}-{condition}
```
Exemples :
- `trading-bot-prod-alarm-lambda-errors-high`
- `trading-bot-prod-alarm-sqs-dlq-not-empty`
- `trading-bot-prod-alarm-api-latency-high`

#### Bonnes Pratiques
- Retention des logs adaptée (30 jours dev, 90 jours prod)
- Alarmes sur les métriques critiques
- Dashboards pour la visualisation

```hcl
resource "aws_cloudwatch_log_group" "lambda" {
  name              = "/aws/lambda/${aws_lambda_function.executor.function_name}"
  retention_in_days = var.environment == "prod" ? 90 : 30
}

resource "aws_cloudwatch_metric_alarm" "lambda_errors" {
  alarm_name          = "${local.name_prefix}-alarm-lambda-errors-high"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 2
  metric_name         = "Errors"
  namespace           = "AWS/Lambda"
  period              = 300
  statistic           = "Sum"
  threshold           = 5
  alarm_description   = "Lambda function error rate too high"

  dimensions = {
    FunctionName = aws_lambda_function.executor.function_name
  }

  alarm_actions = [aws_sns_topic.error_notifications.arn]
}
```

## Tags Standards

Tous les ressources AWS doivent avoir ces tags :

```hcl
locals {
  common_tags = {
    Project     = "trading-bot"
    Environment = var.environment
    ManagedBy   = "terraform"
    Owner       = "trading-team"
    CostCenter  = "trading-bot"
  }
}
```

## Sécurité

### Secrets (SSM Parameter Store)

Utiliser **SSM Parameter Store** (gratuit) au lieu de Secrets Manager pour respecter le Free Tier.

#### Nommage des Paramètres
```
/{projet}/{environnement}/{service}/{nom}
```

Exemples :
- `/trading-bot/prod/binance/api_key`
- `/trading-bot/prod/binance/api_secret`
- `/trading-bot/prod/telegram/bot_token`
- `/trading-bot/prod/telegram/chat_id`

#### Types de Paramètres

| Type | Usage | Chiffrement |
|------|-------|-------------|
| String | Configuration non sensible | Non |
| SecureString | Clés API, tokens | Oui (KMS) |

### Encryption
- Chiffrement au repos activé par défaut (DynamoDB, SQS, SNS)
- Utiliser des KMS keys personnalisées en production

### Réseau
- VPC uniquement si nécessaire (connexion à des ressources privées)
- Security Groups restrictifs
