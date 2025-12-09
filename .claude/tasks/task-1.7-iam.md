# Task 1.7 - IAM Roles et Policies (Lambda execution)

## Objectif

Créer les rôles IAM et policies nécessaires pour l'exécution des fonctions Lambda avec le principe du moindre privilège.

## Rôles à créer

| Rôle | Nom | Description |
|------|-----|-------------|
| Lambda Execution | `trading-bot-{env}-role-lambda-exec` | Rôle principal pour les fonctions Lambda |

## Policies à créer

| Policy | Description | Permissions |
|--------|-------------|-------------|
| DynamoDB Access | Accès aux tables DynamoDB | GetItem, PutItem, Query, UpdateItem |
| SSM Access | Lecture des paramètres SSM | GetParameter, GetParameters |
| SNS Publish | Publication SNS | Publish |
| SQS Access | Accès aux queues SQS | SendMessage, ReceiveMessage, DeleteMessage |
| CloudWatch Logs | Écriture des logs | CreateLogGroup, CreateLogStream, PutLogEvents |

## Fichiers à créer/modifier

### 1. Créer le module IAM

**Fichier** : `terraform/modules/iam/main.tf`

```hcl
locals {
  name_prefix = "${var.project_name}-${var.environment}"
}

# =============================================================================
# Lambda Execution Role
# =============================================================================
resource "aws_iam_role" "lambda_exec" {
  name = "${local.name_prefix}-role-lambda-exec"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "lambda.amazonaws.com"
        }
      }
    ]
  })

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-role-lambda-exec"
  })
}

# =============================================================================
# Policy: CloudWatch Logs
# =============================================================================
resource "aws_iam_role_policy" "lambda_logs" {
  name = "${local.name_prefix}-policy-lambda-logs"
  role = aws_iam_role.lambda_exec.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "logs:CreateLogGroup",
          "logs:CreateLogStream",
          "logs:PutLogEvents"
        ]
        Resource = "arn:aws:logs:${var.aws_region}:${var.aws_account_id}:log-group:/aws/lambda/${local.name_prefix}-*:*"
      }
    ]
  })
}

# =============================================================================
# Policy: DynamoDB Access
# =============================================================================
resource "aws_iam_role_policy" "lambda_dynamodb" {
  name = "${local.name_prefix}-policy-lambda-dynamodb"
  role = aws_iam_role.lambda_exec.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "dynamodb:GetItem",
          "dynamodb:PutItem",
          "dynamodb:UpdateItem",
          "dynamodb:DeleteItem",
          "dynamodb:Query",
          "dynamodb:Scan",
          "dynamodb:BatchGetItem",
          "dynamodb:BatchWriteItem"
        ]
        Resource = concat(
          var.dynamodb_table_arns,
          [for arn in var.dynamodb_table_arns : "${arn}/index/*"]
        )
      }
    ]
  })
}

# =============================================================================
# Policy: SSM Parameter Store (Read)
# =============================================================================
resource "aws_iam_role_policy" "lambda_ssm" {
  name = "${local.name_prefix}-policy-lambda-ssm"
  role = aws_iam_role.lambda_exec.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "ssm:GetParameter",
          "ssm:GetParameters",
          "ssm:GetParametersByPath"
        ]
        Resource = "arn:aws:ssm:${var.aws_region}:${var.aws_account_id}:parameter/${var.project_name}/${var.environment}/*"
      },
      {
        Effect = "Allow"
        Action = [
          "kms:Decrypt"
        ]
        Resource = "*"
        Condition = {
          StringEquals = {
            "kms:ViaService" = "ssm.${var.aws_region}.amazonaws.com"
          }
        }
      }
    ]
  })
}

# =============================================================================
# Policy: SNS Publish
# =============================================================================
resource "aws_iam_role_policy" "lambda_sns" {
  name = "${local.name_prefix}-policy-lambda-sns"
  role = aws_iam_role.lambda_exec.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "sns:Publish"
        ]
        Resource = var.sns_topic_arns
      }
    ]
  })
}

# =============================================================================
# Policy: SQS Access
# =============================================================================
resource "aws_iam_role_policy" "lambda_sqs" {
  name = "${local.name_prefix}-policy-lambda-sqs"
  role = aws_iam_role.lambda_exec.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "sqs:SendMessage",
          "sqs:ReceiveMessage",
          "sqs:DeleteMessage",
          "sqs:GetQueueAttributes",
          "sqs:GetQueueUrl"
        ]
        Resource = var.sqs_queue_arns
      }
    ]
  })
}
```

### 2. Variables du module

**Fichier** : `terraform/modules/iam/variables.tf`

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

variable "dynamodb_table_arns" {
  description = "Liste des ARNs des tables DynamoDB"
  type        = list(string)
}

variable "sns_topic_arns" {
  description = "Liste des ARNs des topics SNS"
  type        = list(string)
}

variable "sqs_queue_arns" {
  description = "Liste des ARNs des queues SQS"
  type        = list(string)
}
```

### 3. Outputs du module

**Fichier** : `terraform/modules/iam/outputs.tf`

```hcl
output "lambda_exec_role_arn" {
  description = "ARN du rôle d'exécution Lambda"
  value       = aws_iam_role.lambda_exec.arn
}

output "lambda_exec_role_name" {
  description = "Nom du rôle d'exécution Lambda"
  value       = aws_iam_role.lambda_exec.name
}

output "lambda_exec_role_id" {
  description = "ID du rôle d'exécution Lambda"
  value       = aws_iam_role.lambda_exec.id
}
```

### 4. Ajouter les outputs DynamoDB

**Modifier** : `terraform/modules/dynamodb/outputs.tf`

```hcl
output "trades_table_arn" {
  description = "ARN de la table trades"
  value       = aws_dynamodb_table.trades.arn
}

output "bot_config_table_arn" {
  description = "ARN de la table bot_config"
  value       = aws_dynamodb_table.bot_config.arn
}

output "reports_table_arn" {
  description = "ARN de la table reports"
  value       = aws_dynamodb_table.reports.arn
}

output "table_arns" {
  description = "Liste de tous les ARNs des tables"
  value = [
    aws_dynamodb_table.trades.arn,
    aws_dynamodb_table.bot_config.arn,
    aws_dynamodb_table.reports.arn,
  ]
}
```

### 5. Intégrer dans main.tf

**Modifier** : `terraform/main.tf`

```hcl
# =============================================================================
# IAM Roles & Policies
# =============================================================================
module "iam" {
  source = "./modules/iam"

  environment    = var.environment
  project_name   = var.project_name
  common_tags    = local.common_tags
  aws_region     = data.aws_region.current.name
  aws_account_id = data.aws_caller_identity.current.account_id

  dynamodb_table_arns = module.dynamodb.table_arns
  sns_topic_arns      = module.sns.topic_arns
  sqs_queue_arns      = module.sqs.all_queue_arns

  depends_on = [module.dynamodb, module.sns, module.sqs]
}
```

### 6. Ajouter les outputs

**Modifier** : `terraform/outputs.tf`

```hcl
# IAM
output "lambda_execution_role_arn" {
  description = "ARN du rôle d'exécution Lambda"
  value       = module.iam.lambda_exec_role_arn
}

output "lambda_execution_role_name" {
  description = "Nom du rôle d'exécution Lambda"
  value       = module.iam.lambda_exec_role_name
}
```

## Instructions de déploiement

### Étape 1 : Mettre à jour les outputs DynamoDB

```bash
# Ajouter les outputs dans terraform/modules/dynamodb/outputs.tf
```

### Étape 2 : Créer les fichiers du module IAM

```bash
mkdir -p terraform/modules/iam
# Créer main.tf, variables.tf, outputs.tf
```

### Étape 3 : Déployer

```bash
cd terraform
terraform init
terraform plan
terraform apply
```

## Vérification

```bash
# Voir le rôle créé
aws iam get-role --role-name trading-bot-dev-role-lambda-exec

# Lister les policies attachées
aws iam list-role-policies --role-name trading-bot-dev-role-lambda-exec

# Voir le contenu d'une policy
aws iam get-role-policy \
  --role-name trading-bot-dev-role-lambda-exec \
  --policy-name trading-bot-dev-policy-lambda-dynamodb
```

## Principe du Moindre Privilège

Les policies sont restrictives :

| Resource | Scope |
|----------|-------|
| CloudWatch Logs | Uniquement les log groups `/aws/lambda/trading-bot-{env}-*` |
| DynamoDB | Uniquement les 3 tables du projet + leurs index |
| SSM | Uniquement les paramètres sous `/{project}/{env}/*` |
| SNS | Uniquement les 2 topics du projet |
| SQS | Uniquement les 4 queues du projet |

## Free Tier

IAM est **gratuit** - pas de limite d'utilisation.

## Dépendances

- **Prérequis** : Tâches 1.2 (DynamoDB), 1.3 (SSM), 1.4 (SNS), 1.5 (SQS)
- **Utilisé par** : Tâche 1.8 (Lambda)

## Checklist

- [ ] Ajouter les outputs `table_arns` dans `terraform/modules/dynamodb/outputs.tf`
- [ ] Créer le dossier `terraform/modules/iam/`
- [ ] Créer `main.tf` avec le rôle et les 5 policies
- [ ] Créer `variables.tf`
- [ ] Créer `outputs.tf`
- [ ] Modifier `terraform/main.tf` pour inclure le module (avec depends_on)
- [ ] Modifier `terraform/outputs.tf` pour ajouter le rôle ARN
- [ ] `terraform init` et `terraform apply`
- [ ] Vérifier avec `aws iam get-role`
- [ ] Vérifier les policies avec `aws iam list-role-policies`
