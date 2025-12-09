# Task 1.5 - SQS Queues + DLQ (orders, notifications)

## Objectif

Créer les queues SQS pour le traitement asynchrone des ordres et notifications, avec leurs Dead Letter Queues (DLQ) respectives.

## Queues à créer

| Queue | Nom | Description |
|-------|-----|-------------|
| Orders | `trading-bot-{env}-sqs-orders` | Traitement des ordres de trading |
| Orders DLQ | `trading-bot-{env}-sqs-orders-dlq` | Messages en erreur (orders) |
| Notifications | `trading-bot-{env}-sqs-notifications` | Envoi des notifications |
| Notifications DLQ | `trading-bot-{env}-sqs-notifications-dlq` | Messages en erreur (notifications) |

## Fichiers à créer/modifier

### 1. Créer le module SQS

**Fichier** : `terraform/modules/sqs/main.tf`

```hcl
locals {
  name_prefix = "${var.project_name}-${var.environment}"
}

# =============================================================================
# Orders Queue + DLQ
# =============================================================================

# Dead Letter Queue pour les orders
resource "aws_sqs_queue" "orders_dlq" {
  name                      = "${local.name_prefix}-sqs-orders-dlq"
  message_retention_seconds = 1209600 # 14 jours

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-sqs-orders-dlq"
  })
}

# Queue principale pour les orders
resource "aws_sqs_queue" "orders" {
  name                       = "${local.name_prefix}-sqs-orders"
  delay_seconds              = 0
  max_message_size           = 262144 # 256 KB
  message_retention_seconds  = 345600 # 4 jours
  receive_wait_time_seconds  = 10     # Long polling
  visibility_timeout_seconds = 60     # Doit être > Lambda timeout

  redrive_policy = jsonencode({
    deadLetterTargetArn = aws_sqs_queue.orders_dlq.arn
    maxReceiveCount     = 3
  })

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-sqs-orders"
  })
}

# Policy pour la queue orders
resource "aws_sqs_queue_policy" "orders" {
  queue_url = aws_sqs_queue.orders.id
  policy    = data.aws_iam_policy_document.orders_queue_policy.json
}

data "aws_iam_policy_document" "orders_queue_policy" {
  statement {
    sid    = "AllowLambdaAccess"
    effect = "Allow"

    principals {
      type        = "Service"
      identifiers = ["lambda.amazonaws.com"]
    }

    actions = [
      "sqs:SendMessage",
      "sqs:ReceiveMessage",
      "sqs:DeleteMessage",
      "sqs:GetQueueAttributes",
    ]

    resources = [aws_sqs_queue.orders.arn]

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
      "sqs:*",
    ]

    resources = [aws_sqs_queue.orders.arn]
  }
}

# =============================================================================
# Notifications Queue + DLQ
# =============================================================================

# Dead Letter Queue pour les notifications
resource "aws_sqs_queue" "notifications_dlq" {
  name                      = "${local.name_prefix}-sqs-notifications-dlq"
  message_retention_seconds = 1209600 # 14 jours

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-sqs-notifications-dlq"
  })
}

# Queue principale pour les notifications
resource "aws_sqs_queue" "notifications" {
  name                       = "${local.name_prefix}-sqs-notifications"
  delay_seconds              = 0
  max_message_size           = 262144 # 256 KB
  message_retention_seconds  = 345600 # 4 jours
  receive_wait_time_seconds  = 10     # Long polling
  visibility_timeout_seconds = 60     # Doit être > Lambda timeout

  redrive_policy = jsonencode({
    deadLetterTargetArn = aws_sqs_queue.notifications_dlq.arn
    maxReceiveCount     = 3
  })

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-sqs-notifications"
  })
}

# Policy pour la queue notifications
resource "aws_sqs_queue_policy" "notifications" {
  queue_url = aws_sqs_queue.notifications.id
  policy    = data.aws_iam_policy_document.notifications_queue_policy.json
}

data "aws_iam_policy_document" "notifications_queue_policy" {
  # Permettre à SNS de publier dans cette queue
  statement {
    sid    = "AllowSNSPublish"
    effect = "Allow"

    principals {
      type        = "Service"
      identifiers = ["sns.amazonaws.com"]
    }

    actions   = ["sqs:SendMessage"]
    resources = [aws_sqs_queue.notifications.arn]

    condition {
      test     = "ArnEquals"
      variable = "aws:SourceArn"
      values   = var.sns_topic_arns
    }
  }

  statement {
    sid    = "AllowLambdaAccess"
    effect = "Allow"

    principals {
      type        = "Service"
      identifiers = ["lambda.amazonaws.com"]
    }

    actions = [
      "sqs:ReceiveMessage",
      "sqs:DeleteMessage",
      "sqs:GetQueueAttributes",
    ]

    resources = [aws_sqs_queue.notifications.arn]

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
      "sqs:*",
    ]

    resources = [aws_sqs_queue.notifications.arn]
  }
}

# =============================================================================
# SNS Subscription vers SQS (notifications)
# =============================================================================

# Abonner la queue notifications aux topics SNS
resource "aws_sns_topic_subscription" "trade_alerts_to_sqs" {
  count = length(var.sns_topic_arns) > 0 ? 1 : 0

  topic_arn = var.sns_topic_arns[0] # trade-alerts
  protocol  = "sqs"
  endpoint  = aws_sqs_queue.notifications.arn

  raw_message_delivery = true
}
```

### 2. Variables du module

**Fichier** : `terraform/modules/sqs/variables.tf`

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

variable "sns_topic_arns" {
  description = "Liste des ARNs des topics SNS à connecter"
  type        = list(string)
  default     = []
}
```

### 3. Outputs du module

**Fichier** : `terraform/modules/sqs/outputs.tf`

```hcl
# Orders Queue
output "orders_queue_arn" {
  description = "ARN de la queue orders"
  value       = aws_sqs_queue.orders.arn
}

output "orders_queue_url" {
  description = "URL de la queue orders"
  value       = aws_sqs_queue.orders.url
}

output "orders_queue_name" {
  description = "Nom de la queue orders"
  value       = aws_sqs_queue.orders.name
}

output "orders_dlq_arn" {
  description = "ARN de la DLQ orders"
  value       = aws_sqs_queue.orders_dlq.arn
}

# Notifications Queue
output "notifications_queue_arn" {
  description = "ARN de la queue notifications"
  value       = aws_sqs_queue.notifications.arn
}

output "notifications_queue_url" {
  description = "URL de la queue notifications"
  value       = aws_sqs_queue.notifications.url
}

output "notifications_queue_name" {
  description = "Nom de la queue notifications"
  value       = aws_sqs_queue.notifications.name
}

output "notifications_dlq_arn" {
  description = "ARN de la DLQ notifications"
  value       = aws_sqs_queue.notifications_dlq.arn
}

# All queues
output "all_queue_arns" {
  description = "Liste de tous les ARNs des queues"
  value = [
    aws_sqs_queue.orders.arn,
    aws_sqs_queue.orders_dlq.arn,
    aws_sqs_queue.notifications.arn,
    aws_sqs_queue.notifications_dlq.arn,
  ]
}
```

### 4. Intégrer dans main.tf

**Modifier** : `terraform/main.tf`

```hcl
# =============================================================================
# SQS Queues
# =============================================================================
module "sqs" {
  source = "./modules/sqs"

  environment    = var.environment
  project_name   = var.project_name
  common_tags    = local.common_tags
  aws_region     = data.aws_region.current.name
  aws_account_id = data.aws_caller_identity.current.account_id

  sns_topic_arns = module.sns.topic_arns

  depends_on = [module.sns]
}
```

### 5. Ajouter les outputs

**Modifier** : `terraform/outputs.tf`

```hcl
# SQS Queues
output "sqs_orders_queue_url" {
  description = "URL de la queue SQS orders"
  value       = module.sqs.orders_queue_url
}

output "sqs_orders_queue_arn" {
  description = "ARN de la queue SQS orders"
  value       = module.sqs.orders_queue_arn
}

output "sqs_notifications_queue_url" {
  description = "URL de la queue SQS notifications"
  value       = module.sqs.notifications_queue_url
}

output "sqs_notifications_queue_arn" {
  description = "ARN de la queue SQS notifications"
  value       = module.sqs.notifications_queue_arn
}
```

## Instructions de déploiement

### Étape 1 : Créer les fichiers du module

```bash
mkdir -p terraform/modules/sqs
# Créer main.tf, variables.tf, outputs.tf
```

### Étape 2 : Déployer

```bash
cd terraform
terraform init
terraform plan
terraform apply
```

## Vérification

```bash
# Lister les queues SQS
aws sqs list-queues

# Voir les attributs d'une queue
aws sqs get-queue-attributes \
  --queue-url https://sqs.eu-west-3.amazonaws.com/ACCOUNT_ID/trading-bot-dev-sqs-orders \
  --attribute-names All

# Envoyer un message de test
aws sqs send-message \
  --queue-url https://sqs.eu-west-3.amazonaws.com/ACCOUNT_ID/trading-bot-dev-sqs-orders \
  --message-body '{"type":"TEST","data":"Hello"}'

# Recevoir un message
aws sqs receive-message \
  --queue-url https://sqs.eu-west-3.amazonaws.com/ACCOUNT_ID/trading-bot-dev-sqs-orders
```

## Free Tier

- **1 million de requêtes SQS/mois** gratuites
- Usage estimé du projet : < 10 000 requêtes/mois

## Configuration des Queues

| Paramètre | Valeur | Raison |
|-----------|--------|--------|
| `visibility_timeout_seconds` | 60s | > Lambda timeout (30s) |
| `receive_wait_time_seconds` | 10s | Long polling (économise les appels) |
| `message_retention_seconds` | 4 jours | Temps suffisant pour le traitement |
| `maxReceiveCount` (DLQ) | 3 | 3 tentatives avant DLQ |
| DLQ `retention` | 14 jours | Temps pour investiguer les erreurs |

## Dépendances

- **Prérequis** : Tâches 1.1, 1.2, 1.4 (SNS)
- **Utilisé par** : Tâche 1.7 (IAM), Tâche 1.8 (Lambda)

## Checklist

- [ ] Créer le dossier `terraform/modules/sqs/`
- [ ] Créer `main.tf` avec les 4 queues et policies
- [ ] Créer `variables.tf`
- [ ] Créer `outputs.tf`
- [ ] Modifier `terraform/main.tf` pour inclure le module (avec depends_on SNS)
- [ ] Modifier `terraform/outputs.tf` pour ajouter les URLs et ARNs
- [ ] `terraform init` et `terraform apply`
- [ ] Vérifier avec `aws sqs list-queues`
- [ ] Tester l'envoi/réception de messages
