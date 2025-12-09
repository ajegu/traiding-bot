# Task 1.6 - EventBridge Rules (cron 5min bot, cron daily report)

## Objectif

Créer les règles EventBridge pour déclencher automatiquement le bot de trading (toutes les 5 minutes) et le rapport quotidien (une fois par jour).

## Rules à créer

| Rule | Nom | Schedule | Description |
|------|-----|----------|-------------|
| Bot Executor | `trading-bot-{env}-rule-bot-executor` | rate(5 minutes) | Exécute le bot toutes les 5 min |
| Daily Report | `trading-bot-{env}-rule-daily-report` | cron(0 8 * * ? *) | Rapport quotidien à 08h00 UTC |

## Fichiers à créer/modifier

### 1. Créer le module EventBridge

**Fichier** : `terraform/modules/eventbridge/main.tf`

```hcl
locals {
  name_prefix = "${var.project_name}-${var.environment}"
}

# =============================================================================
# Rule: Bot Executor (toutes les 5 minutes)
# =============================================================================
resource "aws_cloudwatch_event_rule" "bot_executor" {
  name                = "${local.name_prefix}-rule-bot-executor"
  description         = "Exécute le bot de trading toutes les 5 minutes"
  schedule_expression = "rate(5 minutes)"
  state               = var.bot_executor_enabled ? "ENABLED" : "DISABLED"

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-rule-bot-executor"
  })
}

# Target vers Lambda (sera connecté dans task 1.8)
# Pour l'instant, on crée la rule sans target
# Le target sera ajouté quand Lambda sera créé

# =============================================================================
# Rule: Daily Report (tous les jours à 08h00 UTC)
# =============================================================================
resource "aws_cloudwatch_event_rule" "daily_report" {
  name                = "${local.name_prefix}-rule-daily-report"
  description         = "Génère et envoie le rapport quotidien à 08h00 UTC"
  schedule_expression = "cron(0 8 * * ? *)"
  state               = var.daily_report_enabled ? "ENABLED" : "DISABLED"

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-rule-daily-report"
  })
}

# =============================================================================
# Outputs pour les targets (à connecter avec Lambda plus tard)
# =============================================================================
# Les targets seront créés dans le module Lambda (task 1.8)
# car ils ont besoin de l'ARN de la fonction Lambda
```

### 2. Variables du module

**Fichier** : `terraform/modules/eventbridge/variables.tf`

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

variable "bot_executor_enabled" {
  description = "Activer la règle bot executor"
  type        = bool
  default     = false # Désactivé par défaut pour éviter les exécutions accidentelles
}

variable "daily_report_enabled" {
  description = "Activer la règle daily report"
  type        = bool
  default     = false # Désactivé par défaut
}
```

### 3. Outputs du module

**Fichier** : `terraform/modules/eventbridge/outputs.tf`

```hcl
output "bot_executor_rule_arn" {
  description = "ARN de la règle bot executor"
  value       = aws_cloudwatch_event_rule.bot_executor.arn
}

output "bot_executor_rule_name" {
  description = "Nom de la règle bot executor"
  value       = aws_cloudwatch_event_rule.bot_executor.name
}

output "daily_report_rule_arn" {
  description = "ARN de la règle daily report"
  value       = aws_cloudwatch_event_rule.daily_report.arn
}

output "daily_report_rule_name" {
  description = "Nom de la règle daily report"
  value       = aws_cloudwatch_event_rule.daily_report.name
}

output "rule_arns" {
  description = "Liste de tous les ARNs des règles"
  value = [
    aws_cloudwatch_event_rule.bot_executor.arn,
    aws_cloudwatch_event_rule.daily_report.arn,
  ]
}
```

### 4. Intégrer dans main.tf

**Modifier** : `terraform/main.tf`

```hcl
# =============================================================================
# EventBridge Rules
# =============================================================================
module "eventbridge" {
  source = "./modules/eventbridge"

  environment  = var.environment
  project_name = var.project_name
  common_tags  = local.common_tags

  # Désactivé par défaut, activer manuellement quand prêt
  bot_executor_enabled = var.eventbridge_bot_enabled
  daily_report_enabled = var.eventbridge_report_enabled
}
```

### 5. Ajouter les variables

**Modifier** : `terraform/variables.tf`

```hcl
variable "eventbridge_bot_enabled" {
  description = "Activer la règle EventBridge pour le bot (désactivé par défaut)"
  type        = bool
  default     = false
}

variable "eventbridge_report_enabled" {
  description = "Activer la règle EventBridge pour le rapport quotidien (désactivé par défaut)"
  type        = bool
  default     = false
}
```

### 6. Ajouter les outputs

**Modifier** : `terraform/outputs.tf`

```hcl
# EventBridge Rules
output "eventbridge_bot_executor_rule_arn" {
  description = "ARN de la règle EventBridge bot executor"
  value       = module.eventbridge.bot_executor_rule_arn
}

output "eventbridge_daily_report_rule_arn" {
  description = "ARN de la règle EventBridge daily report"
  value       = module.eventbridge.daily_report_rule_arn
}
```

## Instructions de déploiement

### Étape 1 : Créer les fichiers du module

```bash
mkdir -p terraform/modules/eventbridge
# Créer main.tf, variables.tf, outputs.tf
```

### Étape 2 : Déployer (règles désactivées par défaut)

```bash
cd terraform
terraform init
terraform plan
terraform apply
```

### Étape 3 : Activer les règles (quand Lambda est prêt)

Après avoir déployé Lambda (tâche 1.8), activer les règles :

```bash
# Via Terraform
terraform apply -var="eventbridge_bot_enabled=true" -var="eventbridge_report_enabled=true"

# Ou via AWS CLI
aws events enable-rule --name trading-bot-dev-rule-bot-executor
aws events enable-rule --name trading-bot-dev-rule-daily-report
```

## Vérification

```bash
# Lister les règles EventBridge
aws events list-rules

# Voir les détails d'une règle
aws events describe-rule --name trading-bot-dev-rule-bot-executor

# Voir les targets d'une règle
aws events list-targets-by-rule --rule trading-bot-dev-rule-bot-executor

# Désactiver temporairement une règle
aws events disable-rule --name trading-bot-dev-rule-bot-executor

# Réactiver une règle
aws events enable-rule --name trading-bot-dev-rule-bot-executor
```

## Free Tier

- **Événements du default bus** : 1 million/mois gratuit
- Usage estimé :
  - Bot executor : ~8 640 événements/mois (5min × 24h × 30j)
  - Daily report : ~30 événements/mois
  - **Total** : ~9 000 événements/mois (< 1% du Free Tier)

## Schedules

### Bot Executor : `rate(5 minutes)`

- Exécution toutes les 5 minutes
- ~288 exécutions par jour
- Idéal pour le trading de crypto (marché 24/7)

### Daily Report : `cron(0 8 * * ? *)`

- Exécution à 08h00 UTC chaque jour
- Format cron AWS : `cron(minutes hours day-of-month month day-of-week year)`
- `?` = pas de valeur spécifique (utilisé quand l'autre champ jour est spécifié)

### Conversion des heures

| UTC | Paris (CET/CEST) |
|-----|------------------|
| 08:00 | 09:00 (hiver) / 10:00 (été) |

## Connection avec Lambda (Tâche 1.8)

Les targets EventBridge → Lambda seront créés dans le module Lambda :

```hcl
# Dans le module Lambda (tâche 1.8)
resource "aws_cloudwatch_event_target" "bot_executor" {
  rule      = var.eventbridge_bot_rule_name
  target_id = "TradingBotExecutor"
  arn       = aws_lambda_function.bot_executor.arn
}

resource "aws_lambda_permission" "eventbridge_bot" {
  statement_id  = "AllowEventBridgeInvoke"
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.bot_executor.function_name
  principal     = "events.amazonaws.com"
  source_arn    = var.eventbridge_bot_rule_arn
}
```

## Dépendances

- **Prérequis** : Tâches 1.1, 1.2
- **Utilisé par** : Tâche 1.8 (Lambda targets)
- **Note** : Les targets seront ajoutés dans la tâche 1.8 (Lambda)

## Checklist

- [ ] Créer le dossier `terraform/modules/eventbridge/`
- [ ] Créer `main.tf` avec les 2 règles (désactivées)
- [ ] Créer `variables.tf` avec les flags d'activation
- [ ] Créer `outputs.tf`
- [ ] Modifier `terraform/main.tf` pour inclure le module
- [ ] Modifier `terraform/variables.tf` pour ajouter les variables
- [ ] Modifier `terraform/outputs.tf` pour ajouter les ARNs
- [ ] `terraform init` et `terraform apply`
- [ ] Vérifier avec `aws events list-rules`
- [ ] Noter que les règles sont DISABLED jusqu'à la connexion avec Lambda
