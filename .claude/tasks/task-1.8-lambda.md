# Task 1.8 - Lambda Functions (Bref PHP runtime)

## Objectif

Créer les fonctions Lambda avec le runtime Bref pour PHP. Cette tâche prépare l'infrastructure Lambda, le code applicatif sera développé dans la Phase 2.

## Fonctions à créer

| Fonction | Nom | Description | Trigger |
|----------|-----|-------------|---------|
| Bot Executor | `trading-bot-{env}-lambda-executor` | Exécute la stratégie de trading | EventBridge (5 min) |
| Daily Report | `trading-bot-{env}-lambda-report` | Génère le rapport quotidien | EventBridge (daily) |

## Architecture Bref

Bref permet d'exécuter PHP sur AWS Lambda :
- Runtime : `provided.al2` (Amazon Linux 2)
- Layer Bref : PHP 8.4 avec extensions
- Handler : Commande Artisan via `artisan` handler

## Fichiers à créer/modifier

### 1. Créer le module Lambda

**Fichier** : `terraform/modules/lambda/main.tf`

```hcl
locals {
  name_prefix = "${var.project_name}-${var.environment}"
}

# =============================================================================
# Lambda Function: Bot Executor
# =============================================================================
resource "aws_lambda_function" "bot_executor" {
  function_name = "${local.name_prefix}-lambda-executor"
  description   = "Trading bot executor - runs every 5 minutes"

  role          = var.lambda_exec_role_arn
  handler       = "artisan"
  runtime       = "provided.al2"
  architectures = ["x86_64"]
  timeout       = 30
  memory_size   = 512

  # Le code sera déployé via CI/CD ou serverless
  # Pour l'instant, on utilise un placeholder
  filename         = var.lambda_zip_path
  source_code_hash = var.lambda_zip_path != "" ? filebase64sha256(var.lambda_zip_path) : null

  # S3 comme alternative (si le zip est trop gros)
  # s3_bucket = var.lambda_s3_bucket
  # s3_key    = var.lambda_s3_key

  layers = var.bref_layers

  environment {
    variables = {
      APP_ENV              = var.environment
      LOG_CHANNEL          = "stderr"
      CACHE_DRIVER         = "array"
      SESSION_DRIVER       = "array"
      BOT_COMMAND          = "bot:run"
      SSM_PARAMETER_PREFIX = "/${var.project_name}/${var.environment}"

      # DynamoDB Tables
      DYNAMODB_TABLE_TRADES     = var.dynamodb_trades_table_name
      DYNAMODB_TABLE_BOT_CONFIG = var.dynamodb_bot_config_table_name
      DYNAMODB_TABLE_REPORTS    = var.dynamodb_reports_table_name

      # SNS Topics
      SNS_TOPIC_TRADE_ALERTS = var.sns_trade_alerts_topic_arn
      SNS_TOPIC_ERROR_ALERTS = var.sns_error_alerts_topic_arn

      # SQS Queues
      SQS_QUEUE_ORDERS = var.sqs_orders_queue_url
    }
  }

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-lambda-executor"
  })

  depends_on = [aws_cloudwatch_log_group.bot_executor]
}

# Log Group pour bot executor
resource "aws_cloudwatch_log_group" "bot_executor" {
  name              = "/aws/lambda/${local.name_prefix}-lambda-executor"
  retention_in_days = var.environment == "prod" ? 30 : 7

  tags = var.common_tags
}

# =============================================================================
# Lambda Function: Daily Report
# =============================================================================
resource "aws_lambda_function" "daily_report" {
  function_name = "${local.name_prefix}-lambda-report"
  description   = "Daily report generator - runs once per day"

  role          = var.lambda_exec_role_arn
  handler       = "artisan"
  runtime       = "provided.al2"
  architectures = ["x86_64"]
  timeout       = 60 # Plus de temps pour générer le rapport
  memory_size   = 512

  filename         = var.lambda_zip_path
  source_code_hash = var.lambda_zip_path != "" ? filebase64sha256(var.lambda_zip_path) : null

  layers = var.bref_layers

  environment {
    variables = {
      APP_ENV              = var.environment
      LOG_CHANNEL          = "stderr"
      CACHE_DRIVER         = "array"
      SESSION_DRIVER       = "array"
      BOT_COMMAND          = "report:daily"
      SSM_PARAMETER_PREFIX = "/${var.project_name}/${var.environment}"

      # DynamoDB Tables
      DYNAMODB_TABLE_TRADES     = var.dynamodb_trades_table_name
      DYNAMODB_TABLE_BOT_CONFIG = var.dynamodb_bot_config_table_name
      DYNAMODB_TABLE_REPORTS    = var.dynamodb_reports_table_name

      # Telegram (via SSM)
      TELEGRAM_ENABLED = "true"
    }
  }

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-lambda-report"
  })

  depends_on = [aws_cloudwatch_log_group.daily_report]
}

# Log Group pour daily report
resource "aws_cloudwatch_log_group" "daily_report" {
  name              = "/aws/lambda/${local.name_prefix}-lambda-report"
  retention_in_days = var.environment == "prod" ? 30 : 7

  tags = var.common_tags
}

# =============================================================================
# EventBridge Targets
# =============================================================================

# Target: Bot Executor
resource "aws_cloudwatch_event_target" "bot_executor" {
  rule      = var.eventbridge_bot_rule_name
  target_id = "BotExecutorLambda"
  arn       = aws_lambda_function.bot_executor.arn

  input = jsonencode({
    command = "bot:run"
  })
}

# Permission pour EventBridge -> Bot Executor
resource "aws_lambda_permission" "eventbridge_bot_executor" {
  statement_id  = "AllowEventBridgeInvoke"
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.bot_executor.function_name
  principal     = "events.amazonaws.com"
  source_arn    = var.eventbridge_bot_rule_arn
}

# Target: Daily Report
resource "aws_cloudwatch_event_target" "daily_report" {
  rule      = var.eventbridge_report_rule_name
  target_id = "DailyReportLambda"
  arn       = aws_lambda_function.daily_report.arn

  input = jsonencode({
    command = "report:daily"
  })
}

# Permission pour EventBridge -> Daily Report
resource "aws_lambda_permission" "eventbridge_daily_report" {
  statement_id  = "AllowEventBridgeInvoke"
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.daily_report.function_name
  principal     = "events.amazonaws.com"
  source_arn    = var.eventbridge_report_rule_arn
}
```

### 2. Variables du module

**Fichier** : `terraform/modules/lambda/variables.tf`

```hcl
variable "environment" {
  description = "Environnement (dev, staging, prod)"
  type        = string
}

variable "project_name" {
  description = "Nom du projet"
  type        = string
}

variable "common_tags" {
  description = "Tags communs"
  type        = map(string)
}

variable "lambda_exec_role_arn" {
  description = "ARN du rôle d'exécution Lambda"
  type        = string
}

variable "lambda_zip_path" {
  description = "Chemin vers le fichier ZIP du code Lambda (placeholder)"
  type        = string
  default     = ""
}

variable "bref_layers" {
  description = "Liste des ARNs des layers Bref"
  type        = list(string)
  default     = []
}

# DynamoDB
variable "dynamodb_trades_table_name" {
  description = "Nom de la table DynamoDB trades"
  type        = string
}

variable "dynamodb_bot_config_table_name" {
  description = "Nom de la table DynamoDB bot_config"
  type        = string
}

variable "dynamodb_reports_table_name" {
  description = "Nom de la table DynamoDB reports"
  type        = string
}

# SNS
variable "sns_trade_alerts_topic_arn" {
  description = "ARN du topic SNS trade alerts"
  type        = string
}

variable "sns_error_alerts_topic_arn" {
  description = "ARN du topic SNS error alerts"
  type        = string
}

# SQS
variable "sqs_orders_queue_url" {
  description = "URL de la queue SQS orders"
  type        = string
}

# EventBridge
variable "eventbridge_bot_rule_name" {
  description = "Nom de la règle EventBridge bot executor"
  type        = string
}

variable "eventbridge_bot_rule_arn" {
  description = "ARN de la règle EventBridge bot executor"
  type        = string
}

variable "eventbridge_report_rule_name" {
  description = "Nom de la règle EventBridge daily report"
  type        = string
}

variable "eventbridge_report_rule_arn" {
  description = "ARN de la règle EventBridge daily report"
  type        = string
}
```

### 3. Outputs du module

**Fichier** : `terraform/modules/lambda/outputs.tf`

```hcl
output "bot_executor_function_arn" {
  description = "ARN de la fonction Lambda bot executor"
  value       = aws_lambda_function.bot_executor.arn
}

output "bot_executor_function_name" {
  description = "Nom de la fonction Lambda bot executor"
  value       = aws_lambda_function.bot_executor.function_name
}

output "bot_executor_invoke_arn" {
  description = "ARN d'invocation de la fonction Lambda bot executor"
  value       = aws_lambda_function.bot_executor.invoke_arn
}

output "daily_report_function_arn" {
  description = "ARN de la fonction Lambda daily report"
  value       = aws_lambda_function.daily_report.arn
}

output "daily_report_function_name" {
  description = "Nom de la fonction Lambda daily report"
  value       = aws_lambda_function.daily_report.function_name
}

output "daily_report_invoke_arn" {
  description = "ARN d'invocation de la fonction Lambda daily report"
  value       = aws_lambda_function.daily_report.invoke_arn
}

output "function_arns" {
  description = "Liste de tous les ARNs des fonctions"
  value = [
    aws_lambda_function.bot_executor.arn,
    aws_lambda_function.daily_report.arn,
  ]
}
```

### 4. Ajouter les outputs DynamoDB (noms des tables)

**Modifier** : `terraform/modules/dynamodb/outputs.tf` (ajouter)

```hcl
output "trades_table_name" {
  description = "Nom de la table trades"
  value       = aws_dynamodb_table.trades.name
}

output "bot_config_table_name" {
  description = "Nom de la table bot_config"
  value       = aws_dynamodb_table.bot_config.name
}

output "reports_table_name" {
  description = "Nom de la table reports"
  value       = aws_dynamodb_table.reports.name
}
```

### 5. Créer un placeholder Lambda ZIP

**Créer** : `terraform/lambda-placeholder/index.php`

```php
<?php
// Placeholder - sera remplacé par le vrai code lors du déploiement
echo "Placeholder Lambda - Deploy real code via CI/CD";
```

**Créer** : `terraform/lambda-placeholder/build.sh`

```bash
#!/bin/bash
cd "$(dirname "$0")"
zip -r ../placeholder.zip index.php
```

### 6. Intégrer dans main.tf

**Modifier** : `terraform/main.tf`

```hcl
# =============================================================================
# Lambda Functions
# =============================================================================
module "lambda" {
  source = "./modules/lambda"

  environment  = var.environment
  project_name = var.project_name
  common_tags  = local.common_tags

  lambda_exec_role_arn = module.iam.lambda_exec_role_arn
  lambda_zip_path      = var.lambda_zip_path
  bref_layers          = var.bref_layers

  # DynamoDB
  dynamodb_trades_table_name     = module.dynamodb.trades_table_name
  dynamodb_bot_config_table_name = module.dynamodb.bot_config_table_name
  dynamodb_reports_table_name    = module.dynamodb.reports_table_name

  # SNS
  sns_trade_alerts_topic_arn = module.sns.trade_alerts_topic_arn
  sns_error_alerts_topic_arn = module.sns.error_alerts_topic_arn

  # SQS
  sqs_orders_queue_url = module.sqs.orders_queue_url

  # EventBridge
  eventbridge_bot_rule_name    = module.eventbridge.bot_executor_rule_name
  eventbridge_bot_rule_arn     = module.eventbridge.bot_executor_rule_arn
  eventbridge_report_rule_name = module.eventbridge.daily_report_rule_name
  eventbridge_report_rule_arn  = module.eventbridge.daily_report_rule_arn

  depends_on = [module.iam, module.dynamodb, module.sns, module.sqs, module.eventbridge]
}
```

### 7. Ajouter les variables

**Modifier** : `terraform/variables.tf`

```hcl
variable "lambda_zip_path" {
  description = "Chemin vers le fichier ZIP du code Lambda"
  type        = string
  default     = "placeholder.zip"
}

variable "bref_layers" {
  description = "ARNs des layers Bref pour PHP"
  type        = list(string)
  default = [
    # Bref PHP 8.4 layer pour eu-west-3
    # https://bref.sh/docs/runtimes/
    "arn:aws:lambda:eu-west-3:534081306603:layer:php-84:1"
  ]
}
```

### 8. Ajouter les outputs

**Modifier** : `terraform/outputs.tf`

```hcl
# Lambda Functions
output "lambda_bot_executor_arn" {
  description = "ARN de la fonction Lambda bot executor"
  value       = module.lambda.bot_executor_function_arn
}

output "lambda_bot_executor_name" {
  description = "Nom de la fonction Lambda bot executor"
  value       = module.lambda.bot_executor_function_name
}

output "lambda_daily_report_arn" {
  description = "ARN de la fonction Lambda daily report"
  value       = module.lambda.daily_report_function_arn
}

output "lambda_daily_report_name" {
  description = "Nom de la fonction Lambda daily report"
  value       = module.lambda.daily_report_function_name
}
```

## Instructions de déploiement

### Étape 1 : Créer le placeholder ZIP

```bash
mkdir -p terraform/lambda-placeholder
echo '<?php echo "Placeholder";' > terraform/lambda-placeholder/index.php
cd terraform
zip placeholder.zip lambda-placeholder/index.php
```

### Étape 2 : Créer les fichiers du module

```bash
mkdir -p terraform/modules/lambda
# Créer main.tf, variables.tf, outputs.tf
```

### Étape 3 : Déployer

```bash
cd terraform
terraform init
terraform plan
terraform apply
```

### Étape 4 : Vérifier les layers Bref

Les ARNs des layers Bref sont disponibles sur : https://bref.sh/docs/runtimes/

Pour eu-west-3 (Paris), vérifier la version actuelle du layer PHP 8.4.

## Vérification

```bash
# Lister les fonctions Lambda
aws lambda list-functions

# Voir les détails d'une fonction
aws lambda get-function --function-name trading-bot-dev-lambda-executor

# Invoquer manuellement (test)
aws lambda invoke \
  --function-name trading-bot-dev-lambda-executor \
  --payload '{"command":"bot:run"}' \
  output.json

# Voir les logs récents
aws logs tail /aws/lambda/trading-bot-dev-lambda-executor --follow
```

## Free Tier

- **1 million de requêtes Lambda/mois** gratuites
- **400 000 GB-seconds/mois** gratuits

Usage estimé :
- Bot executor : ~8 640 invocations/mois × 512 MB × 30s = ~132 480 GB-sec
- Daily report : ~30 invocations/mois × 512 MB × 60s = ~921 GB-sec
- **Total** : ~133 400 GB-sec/mois (33% du Free Tier)

## Bref Layers

| Runtime | Layer ARN (eu-west-3) |
|---------|----------------------|
| PHP 8.4 | `arn:aws:lambda:eu-west-3:534081306603:layer:php-84:XX` |
| PHP 8.4 + FPM | `arn:aws:lambda:eu-west-3:534081306603:layer:php-84-fpm:XX` |
| Console | `arn:aws:lambda:eu-west-3:534081306603:layer:console:XX` |

Note : Vérifier les versions actuelles sur https://runtimes.bref.sh/

## Dépendances

- **Prérequis** : Tâches 1.2-1.7 (toute l'infrastructure)
- **Utilisé par** : Phase 2 (Application Laravel)

## Checklist

- [ ] Ajouter les outputs `table_name` dans `terraform/modules/dynamodb/outputs.tf`
- [ ] Créer le placeholder ZIP
- [ ] Créer le dossier `terraform/modules/lambda/`
- [ ] Créer `main.tf` avec les 2 fonctions et targets EventBridge
- [ ] Créer `variables.tf`
- [ ] Créer `outputs.tf`
- [ ] Modifier `terraform/main.tf` pour inclure le module
- [ ] Modifier `terraform/variables.tf` pour ajouter les variables
- [ ] Modifier `terraform/outputs.tf` pour ajouter les ARNs
- [ ] Vérifier les ARNs des layers Bref
- [ ] `terraform init` et `terraform apply`
- [ ] Vérifier avec `aws lambda list-functions`
- [ ] Tester l'invocation manuelle
