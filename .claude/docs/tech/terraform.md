# Terraform - Conventions et Bonnes Pratiques

## Structure des Fichiers

```
terraform/
├── environments/
│   ├── dev/
│   │   ├── main.tf
│   │   ├── variables.tf
│   │   └── terraform.tfvars
│   ├── staging/
│   └── prod/
├── modules/
│   ├── lambda/
│   ├── dynamodb/
│   ├── sqs/
│   └── sns/
├── main.tf
├── variables.tf
├── outputs.tf
├── providers.tf
├── versions.tf
└── backend.tf
```

## Convention de Nommage

### Fichiers
| Fichier | Contenu |
|---------|---------|
| `main.tf` | Ressources principales |
| `variables.tf` | Déclaration des variables |
| `outputs.tf` | Valeurs de sortie |
| `providers.tf` | Configuration des providers |
| `versions.tf` | Contraintes de version Terraform/providers |
| `backend.tf` | Configuration du backend (S3) |
| `locals.tf` | Variables locales |
| `data.tf` | Data sources |

### Variables et Locals
- snake_case uniquement
- Noms descriptifs et explicites
- Préfixer par le contexte si nécessaire

```hcl
# Bon
variable "lambda_memory_size" {}
variable "dynamodb_read_capacity" {}

# Mauvais
variable "memSize" {}
variable "rc" {}
```

### Ressources et Data Sources
- snake_case pour le nom logique
- Nom descriptif de la fonction

```hcl
# Bon
resource "aws_lambda_function" "trading_bot_executor" {}
resource "aws_dynamodb_table" "trades" {}
data "aws_iam_policy_document" "lambda_assume_role" {}

# Mauvais
resource "aws_lambda_function" "func1" {}
resource "aws_dynamodb_table" "table" {}
```

### Modules
- snake_case
- Nom basé sur la fonctionnalité

```hcl
module "trading_lambda" {
  source = "./modules/lambda"
}

module "trades_table" {
  source = "./modules/dynamodb"
}
```

### Outputs
- snake_case
- Préfixer par le type de ressource

```hcl
output "lambda_function_arn" {}
output "lambda_function_name" {}
output "dynamodb_table_arn" {}
output "sqs_queue_url" {}
```

## Nommage des Ressources AWS

### Format Standard
```
{projet}-{environnement}-{composant}-{description}
```

### Exemples
| Ressource | Nom |
|-----------|-----|
| Lambda | `trading-bot-prod-lambda-executor` |
| DynamoDB | `trading-bot-prod-dynamodb-trades` |
| SQS | `trading-bot-prod-sqs-orders` |
| SNS | `trading-bot-prod-sns-alerts` |
| IAM Role | `trading-bot-prod-role-lambda-exec` |
| EventBridge | `trading-bot-prod-rule-cron-5min` |

### Implémentation avec Locals
```hcl
locals {
  project     = "trading-bot"
  environment = terraform.workspace

  name_prefix = "${local.project}-${local.environment}"

  common_tags = {
    Project     = local.project
    Environment = local.environment
    ManagedBy   = "terraform"
  }
}

resource "aws_lambda_function" "executor" {
  function_name = "${local.name_prefix}-lambda-executor"
  tags          = local.common_tags
}
```

## Bonnes Pratiques

### 1. État Distant (Remote State)
Toujours utiliser un backend S3 avec verrouillage DynamoDB :
```hcl
terraform {
  backend "s3" {
    bucket         = "trading-bot-terraform-state"
    key            = "state/terraform.tfstate"
    region         = "eu-west-3"
    encrypt        = true
    dynamodb_table = "terraform-state-lock"
  }
}
```

### 2. Workspaces pour les Environnements
```bash
terraform workspace new dev
terraform workspace new staging
terraform workspace new prod
terraform workspace select prod
```

### 3. Variables avec Validation
```hcl
variable "environment" {
  type        = string
  description = "Environnement de déploiement"

  validation {
    condition     = contains(["dev", "staging", "prod"], var.environment)
    error_message = "Environment must be dev, staging, or prod."
  }
}
```

### 4. Versions Épinglées
```hcl
terraform {
  required_version = ">= 1.6.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}
```

### 5. Formatage et Validation
```bash
# Avant chaque commit
terraform fmt -recursive
terraform validate
```

### 6. Documentation des Variables
```hcl
variable "lambda_timeout" {
  type        = number
  description = "Timeout en secondes pour la fonction Lambda"
  default     = 30

  validation {
    condition     = var.lambda_timeout >= 1 && var.lambda_timeout <= 900
    error_message = "Lambda timeout must be between 1 and 900 seconds."
  }
}
```

### 7. Utiliser count/for_each avec Parcimonie
```hcl
# Préférer for_each à count pour les ressources nommées
resource "aws_sqs_queue" "queues" {
  for_each = toset(["orders", "prices", "alerts"])

  name = "${local.name_prefix}-sqs-${each.key}"
  tags = local.common_tags
}
```

### 8. Sécurité
- Ne jamais commiter de secrets dans les fichiers `.tf`
- Utiliser **SSM Parameter Store** (gratuit, Free Tier) pour les secrets
- Utiliser `sensitive = true` pour les outputs sensibles

### 9. Free Tier
- Privilégier les services inclus dans le Free Tier AWS
- Éviter Secrets Manager (payant) → utiliser SSM Parameter Store
- DynamoDB en mode PAY_PER_REQUEST
- CloudWatch Logs avec rétention courte (7-14 jours)
- Lambda sans VPC (éviter NAT Gateway)

## Commandes Utiles

```bash
# Initialiser
terraform init

# Valider la syntaxe
terraform validate

# Formater le code
terraform fmt -recursive

# Planifier les changements
terraform plan -out=tfplan

# Appliquer les changements
terraform apply tfplan

# Détruire l'infrastructure
terraform destroy

# Lister les workspaces
terraform workspace list

# Importer une ressource existante
terraform import aws_lambda_function.example function_name
```
