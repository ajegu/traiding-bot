# Task 1.3 - SSM Parameter Store (clés API Binance, token Telegram)

## Objectif

Créer les paramètres SSM Parameter Store pour stocker de manière sécurisée les secrets de l'application (clés API Binance et token Telegram).

## Pourquoi SSM Parameter Store ?

- **Free Tier** : 10 000 paramètres standard gratuits (vs Secrets Manager qui est payant)
- **SecureString** : Chiffrement avec KMS inclus
- **Intégration native** : AWS SDK et Lambda

## Paramètres à créer

| Paramètre | Type | Description |
|-----------|------|-------------|
| `/trading-bot/{env}/binance/api_key` | SecureString | Clé API Binance |
| `/trading-bot/{env}/binance/api_secret` | SecureString | Secret API Binance |
| `/trading-bot/{env}/telegram/bot_token` | SecureString | Token du bot Telegram |
| `/trading-bot/{env}/telegram/chat_id` | String | Chat ID destinataire |

## Fichiers à créer/modifier

### 1. Créer le module SSM

**Fichier** : `terraform/modules/ssm/main.tf`

```hcl
locals {
  name_prefix = "/${var.project_name}/${var.environment}"
}

# =============================================================================
# Binance API Parameters
# =============================================================================
resource "aws_ssm_parameter" "binance_api_key" {
  name        = "${local.name_prefix}/binance/api_key"
  description = "Binance API Key"
  type        = "SecureString"
  value       = var.binance_api_key
  tier        = "Standard"

  tags = var.common_tags

  lifecycle {
    ignore_changes = [value]
  }
}

resource "aws_ssm_parameter" "binance_api_secret" {
  name        = "${local.name_prefix}/binance/api_secret"
  description = "Binance API Secret"
  type        = "SecureString"
  value       = var.binance_api_secret
  tier        = "Standard"

  tags = var.common_tags

  lifecycle {
    ignore_changes = [value]
  }
}

# =============================================================================
# Telegram Parameters
# =============================================================================
resource "aws_ssm_parameter" "telegram_bot_token" {
  name        = "${local.name_prefix}/telegram/bot_token"
  description = "Telegram Bot Token"
  type        = "SecureString"
  value       = var.telegram_bot_token
  tier        = "Standard"

  tags = var.common_tags

  lifecycle {
    ignore_changes = [value]
  }
}

resource "aws_ssm_parameter" "telegram_chat_id" {
  name        = "${local.name_prefix}/telegram/chat_id"
  description = "Telegram Chat ID"
  type        = "String"
  value       = var.telegram_chat_id
  tier        = "Standard"

  tags = var.common_tags

  lifecycle {
    ignore_changes = [value]
  }
}
```

### 2. Variables du module

**Fichier** : `terraform/modules/ssm/variables.tf`

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

variable "binance_api_key" {
  description = "Binance API Key"
  type        = string
  sensitive   = true
  default     = "PLACEHOLDER_TO_UPDATE"
}

variable "binance_api_secret" {
  description = "Binance API Secret"
  type        = string
  sensitive   = true
  default     = "PLACEHOLDER_TO_UPDATE"
}

variable "telegram_bot_token" {
  description = "Telegram Bot Token"
  type        = string
  sensitive   = true
  default     = "PLACEHOLDER_TO_UPDATE"
}

variable "telegram_chat_id" {
  description = "Telegram Chat ID"
  type        = string
  default     = "PLACEHOLDER_TO_UPDATE"
}
```

### 3. Outputs du module

**Fichier** : `terraform/modules/ssm/outputs.tf`

```hcl
output "binance_api_key_arn" {
  description = "ARN du paramètre Binance API Key"
  value       = aws_ssm_parameter.binance_api_key.arn
}

output "binance_api_secret_arn" {
  description = "ARN du paramètre Binance API Secret"
  value       = aws_ssm_parameter.binance_api_secret.arn
}

output "telegram_bot_token_arn" {
  description = "ARN du paramètre Telegram Bot Token"
  value       = aws_ssm_parameter.telegram_bot_token.arn
}

output "telegram_chat_id_arn" {
  description = "ARN du paramètre Telegram Chat ID"
  value       = aws_ssm_parameter.telegram_chat_id.arn
}

output "parameter_arns" {
  description = "Liste de tous les ARNs des paramètres"
  value = [
    aws_ssm_parameter.binance_api_key.arn,
    aws_ssm_parameter.binance_api_secret.arn,
    aws_ssm_parameter.telegram_bot_token.arn,
    aws_ssm_parameter.telegram_chat_id.arn,
  ]
}

output "parameter_name_prefix" {
  description = "Préfixe des noms de paramètres"
  value       = local.name_prefix
}
```

### 4. Intégrer dans main.tf

**Modifier** : `terraform/main.tf`

```hcl
# =============================================================================
# SSM Parameters
# =============================================================================
module "ssm" {
  source = "./modules/ssm"

  environment  = var.environment
  project_name = var.project_name
  common_tags  = local.common_tags

  # Valeurs à mettre à jour manuellement après déploiement
  # ou via terraform.tfvars (non commité)
  binance_api_key    = var.binance_api_key
  binance_api_secret = var.binance_api_secret
  telegram_bot_token = var.telegram_bot_token
  telegram_chat_id   = var.telegram_chat_id
}
```

### 5. Ajouter les variables au projet

**Modifier** : `terraform/variables.tf`

```hcl
# SSM Parameters (secrets)
variable "binance_api_key" {
  description = "Binance API Key"
  type        = string
  sensitive   = true
  default     = "PLACEHOLDER_TO_UPDATE"
}

variable "binance_api_secret" {
  description = "Binance API Secret"
  type        = string
  sensitive   = true
  default     = "PLACEHOLDER_TO_UPDATE"
}

variable "telegram_bot_token" {
  description = "Telegram Bot Token"
  type        = string
  sensitive   = true
  default     = "PLACEHOLDER_TO_UPDATE"
}

variable "telegram_chat_id" {
  description = "Telegram Chat ID"
  type        = string
  default     = "PLACEHOLDER_TO_UPDATE"
}
```

### 6. Ajouter les outputs

**Modifier** : `terraform/outputs.tf`

```hcl
# SSM Parameters
output "ssm_parameter_arns" {
  description = "ARNs des paramètres SSM"
  value       = module.ssm.parameter_arns
}

output "ssm_parameter_prefix" {
  description = "Préfixe des paramètres SSM"
  value       = module.ssm.parameter_name_prefix
}
```

## Instructions de déploiement

### Étape 1 : Créer les fichiers du module

```bash
mkdir -p terraform/modules/ssm
# Créer main.tf, variables.tf, outputs.tf
```

### Étape 2 : Initialiser Terraform

```bash
cd terraform
terraform init
```

### Étape 3 : Déployer avec des placeholders

```bash
terraform plan
terraform apply
```

### Étape 4 : Mettre à jour les vraies valeurs

Après le déploiement initial, mettre à jour les valeurs manuellement via AWS CLI :

```bash
# Binance API (utiliser les clés du testnet pour dev)
aws ssm put-parameter \
  --name "/trading-bot/dev/binance/api_key" \
  --value "VOTRE_CLE_API" \
  --type SecureString \
  --overwrite

aws ssm put-parameter \
  --name "/trading-bot/dev/binance/api_secret" \
  --value "VOTRE_SECRET_API" \
  --type SecureString \
  --overwrite

# Telegram
aws ssm put-parameter \
  --name "/trading-bot/dev/telegram/bot_token" \
  --value "123456789:ABCdefGHI..." \
  --type SecureString \
  --overwrite

aws ssm put-parameter \
  --name "/trading-bot/dev/telegram/chat_id" \
  --value "987654321" \
  --type String \
  --overwrite
```

## Vérification

```bash
# Lister les paramètres créés
aws ssm describe-parameters \
  --parameter-filters "Key=Name,Option=Contains,Values=/trading-bot/"

# Lire un paramètre (valeur déchiffrée)
aws ssm get-parameter \
  --name "/trading-bot/dev/binance/api_key" \
  --with-decryption
```

## Sécurité

- Les valeurs `sensitive = true` ne sont jamais affichées dans les logs Terraform
- Le `lifecycle { ignore_changes = [value] }` évite d'écraser les vraies valeurs
- Les paramètres SecureString sont chiffrés avec la clé KMS par défaut d'AWS

## Dépendances

- **Prérequis** : Tâches 1.1 et 1.2 complétées
- **Utilisé par** : Tâche 1.7 (IAM), Tâche 1.8 (Lambda)

## Checklist

- [ ] Créer le dossier `terraform/modules/ssm/`
- [ ] Créer `main.tf` avec les 4 paramètres
- [ ] Créer `variables.tf` avec les variables sensibles
- [ ] Créer `outputs.tf` avec les ARNs
- [ ] Modifier `terraform/main.tf` pour inclure le module
- [ ] Modifier `terraform/variables.tf` pour ajouter les variables
- [ ] Modifier `terraform/outputs.tf` pour ajouter les outputs
- [ ] `terraform init` et `terraform apply`
- [ ] Mettre à jour les vraies valeurs via AWS CLI
- [ ] Vérifier avec `aws ssm describe-parameters`
