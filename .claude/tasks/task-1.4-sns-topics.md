# Task 1.4 - SNS Topics (trade-alerts, error-alerts)

## Objectif

Créer les topics SNS pour les notifications du bot de trading : alertes de trades et alertes d'erreurs.

## Topics à créer

| Topic | Nom | Description |
|-------|-----|-------------|
| Trade Alerts | `trading-bot-{env}-sns-trade-alerts` | Notifications des trades exécutés |
| Error Alerts | `trading-bot-{env}-sns-error-alerts` | Alertes d'erreurs critiques |

## Fichiers à créer/modifier

### 1. Créer le module SNS

**Fichier** : `terraform/modules/sns/main.tf`

```hcl
locals {
  name_prefix = "${var.project_name}-${var.environment}"
}

# =============================================================================
# Topic: Trade Alerts
# =============================================================================
resource "aws_sns_topic" "trade_alerts" {
  name = "${local.name_prefix}-sns-trade-alerts"

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-sns-trade-alerts"
  })
}

# Policy pour permettre la publication depuis Lambda
resource "aws_sns_topic_policy" "trade_alerts" {
  arn    = aws_sns_topic.trade_alerts.arn
  policy = data.aws_iam_policy_document.trade_alerts_policy.json
}

data "aws_iam_policy_document" "trade_alerts_policy" {
  statement {
    sid    = "AllowLambdaPublish"
    effect = "Allow"

    principals {
      type        = "Service"
      identifiers = ["lambda.amazonaws.com"]
    }

    actions   = ["sns:Publish"]
    resources = [aws_sns_topic.trade_alerts.arn]

    condition {
      test     = "ArnLike"
      variable = "aws:SourceArn"
      values   = ["arn:aws:lambda:${var.aws_region}:${var.aws_account_id}:function:${local.name_prefix}-*"]
    }
  }

  statement {
    sid    = "AllowAccountAccess"
    effect = "Allow"

    principals {
      type        = "AWS"
      identifiers = ["arn:aws:iam::${var.aws_account_id}:root"]
    }

    actions = [
      "sns:Publish",
      "sns:Subscribe",
      "sns:GetTopicAttributes",
    ]
    resources = [aws_sns_topic.trade_alerts.arn]
  }
}

# =============================================================================
# Topic: Error Alerts
# =============================================================================
resource "aws_sns_topic" "error_alerts" {
  name = "${local.name_prefix}-sns-error-alerts"

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-sns-error-alerts"
  })
}

resource "aws_sns_topic_policy" "error_alerts" {
  arn    = aws_sns_topic.error_alerts.arn
  policy = data.aws_iam_policy_document.error_alerts_policy.json
}

data "aws_iam_policy_document" "error_alerts_policy" {
  statement {
    sid    = "AllowLambdaPublish"
    effect = "Allow"

    principals {
      type        = "Service"
      identifiers = ["lambda.amazonaws.com"]
    }

    actions   = ["sns:Publish"]
    resources = [aws_sns_topic.error_alerts.arn]

    condition {
      test     = "ArnLike"
      variable = "aws:SourceArn"
      values   = ["arn:aws:lambda:${var.aws_region}:${var.aws_account_id}:function:${local.name_prefix}-*"]
    }
  }

  statement {
    sid    = "AllowAccountAccess"
    effect = "Allow"

    principals {
      type        = "AWS"
      identifiers = ["arn:aws:iam::${var.aws_account_id}:root"]
    }

    actions = [
      "sns:Publish",
      "sns:Subscribe",
      "sns:GetTopicAttributes",
    ]
    resources = [aws_sns_topic.error_alerts.arn]
  }
}

# =============================================================================
# Email Subscription (optionnel, pour les alertes d'erreurs)
# =============================================================================
resource "aws_sns_topic_subscription" "error_alerts_email" {
  count = var.alert_email != "" ? 1 : 0

  topic_arn = aws_sns_topic.error_alerts.arn
  protocol  = "email"
  endpoint  = var.alert_email
}
```

### 2. Variables du module

**Fichier** : `terraform/modules/sns/variables.tf`

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

variable "aws_region" {
  description = "AWS Region"
  type        = string
}

variable "aws_account_id" {
  description = "AWS Account ID"
  type        = string
}

variable "alert_email" {
  description = "Email pour les alertes d'erreurs (optionnel)"
  type        = string
  default     = ""
}
```

### 3. Outputs du module

**Fichier** : `terraform/modules/sns/outputs.tf`

```hcl
output "trade_alerts_topic_arn" {
  description = "ARN du topic SNS pour les alertes de trades"
  value       = aws_sns_topic.trade_alerts.arn
}

output "trade_alerts_topic_name" {
  description = "Nom du topic SNS pour les alertes de trades"
  value       = aws_sns_topic.trade_alerts.name
}

output "error_alerts_topic_arn" {
  description = "ARN du topic SNS pour les alertes d'erreurs"
  value       = aws_sns_topic.error_alerts.arn
}

output "error_alerts_topic_name" {
  description = "Nom du topic SNS pour les alertes d'erreurs"
  value       = aws_sns_topic.error_alerts.name
}

output "topic_arns" {
  description = "Liste de tous les ARNs des topics"
  value = [
    aws_sns_topic.trade_alerts.arn,
    aws_sns_topic.error_alerts.arn,
  ]
}
```

### 4. Data source pour l'Account ID

**Créer** : `terraform/data.tf`

```hcl
# Récupérer l'ID du compte AWS courant
data "aws_caller_identity" "current" {}

# Récupérer la région courante
data "aws_region" "current" {}
```

### 5. Intégrer dans main.tf

**Modifier** : `terraform/main.tf`

```hcl
# =============================================================================
# SNS Topics
# =============================================================================
module "sns" {
  source = "./modules/sns"

  environment    = var.environment
  project_name   = var.project_name
  common_tags    = local.common_tags
  aws_region     = data.aws_region.current.name
  aws_account_id = data.aws_caller_identity.current.account_id
  alert_email    = var.alert_email
}
```

### 6. Ajouter la variable alert_email

**Modifier** : `terraform/variables.tf`

```hcl
variable "alert_email" {
  description = "Email pour recevoir les alertes d'erreurs (optionnel)"
  type        = string
  default     = ""
}
```

### 7. Ajouter les outputs

**Modifier** : `terraform/outputs.tf`

```hcl
# SNS Topics
output "sns_trade_alerts_topic_arn" {
  description = "ARN du topic SNS pour les alertes de trades"
  value       = module.sns.trade_alerts_topic_arn
}

output "sns_error_alerts_topic_arn" {
  description = "ARN du topic SNS pour les alertes d'erreurs"
  value       = module.sns.error_alerts_topic_arn
}
```

## Instructions de déploiement

### Étape 1 : Créer les fichiers du module

```bash
mkdir -p terraform/modules/sns
# Créer main.tf, variables.tf, outputs.tf
```

### Étape 2 : Créer data.tf

```bash
# Créer terraform/data.tf
```

### Étape 3 : Déployer

```bash
cd terraform
terraform init
terraform plan
terraform apply
```

### Étape 4 : (Optionnel) Confirmer l'abonnement email

Si vous avez configuré un email d'alerte, vérifiez votre boîte mail et confirmez l'abonnement SNS.

## Vérification

```bash
# Lister les topics SNS
aws sns list-topics

# Voir les détails d'un topic
aws sns get-topic-attributes \
  --topic-arn arn:aws:sns:eu-west-3:ACCOUNT_ID:trading-bot-dev-sns-trade-alerts

# Tester l'envoi d'un message
aws sns publish \
  --topic-arn arn:aws:sns:eu-west-3:ACCOUNT_ID:trading-bot-dev-sns-trade-alerts \
  --message '{"type":"TEST","data":"Hello from CLI"}'
```

## Free Tier

- **1 million de publications SNS/mois** gratuites
- **1 000 notifications email/mois** gratuites (si abonnement email)
- Usage estimé du projet : < 1 000 publications/mois

## Dépendances

- **Prérequis** : Tâches 1.1, 1.2
- **Utilisé par** : Tâche 1.5 (SQS), Tâche 1.7 (IAM), Tâche 2.9 (NotificationService)

## Checklist

- [ ] Créer le dossier `terraform/modules/sns/`
- [ ] Créer `main.tf` avec les 2 topics et policies
- [ ] Créer `variables.tf`
- [ ] Créer `outputs.tf`
- [ ] Créer `terraform/data.tf` pour account ID et region
- [ ] Modifier `terraform/main.tf` pour inclure le module
- [ ] Modifier `terraform/variables.tf` pour ajouter `alert_email`
- [ ] Modifier `terraform/outputs.tf` pour ajouter les ARNs
- [ ] `terraform init` et `terraform apply`
- [ ] Vérifier avec `aws sns list-topics`
- [ ] (Optionnel) Confirmer l'abonnement email
