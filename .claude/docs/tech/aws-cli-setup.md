# Configuration AWS CLI

## Vue d'ensemble

Ce document décrit la configuration AWS CLI avec **AWS IAM Identity Center (SSO)** pour le développement, le déploiement Terraform et le debug de l'application Trading Bot.

## Prérequis

- AWS CLI v2 installé : `brew install awscli`
- Compte AWS avec AWS IAM Identity Center (SSO) configuré
- Accès au portail SSO de votre organisation

## Avantages du SSO vs Access Keys

| Critère | SSO | Access Keys |
|---------|-----|-------------|
| Sécurité | Tokens temporaires (1-12h) | Clés permanentes |
| Rotation | Automatique | Manuelle (90 jours) |
| MFA | Intégré au portail | Configuration séparée |
| Gestion | Centralisée (Identity Center) | Par utilisateur IAM |
| Révocation | Instantanée | Nécessite suppression clé |

## Structure des Profils

```
~/.aws/
├── config      # Configuration des profils SSO (région, sso-session, rôles)
└── sso/        # Cache des tokens SSO (géré automatiquement)
```

**Note** : Pas besoin de fichier `~/.aws/credentials` avec SSO.

## Profils Recommandés

| Profil | Usage | Compte | Permission Set |
|--------|-------|--------|----------------|
| `trading-bot-dev` | Développement et tests | Compte Dev | AdministratorAccess |
| `trading-bot-prod` | Production (lecture) | Compte Prod | ReadOnlyAccess |
| `trading-bot-terraform` | Déploiement infrastructure | Compte Dev/Prod | AdministratorAccess |

## Configuration

### Étape 1 : Configurer la session SSO

```bash
# Configuration initiale (une seule fois)
aws configure sso

# Répondre aux questions :
# SSO session name: trading-bot
# SSO start URL: https://your-org.awsapps.com/start
# SSO region: eu-west-3
# SSO registration scopes: sso:account:access
```

### Étape 2 : Fichier `~/.aws/config`

```ini
# =============================================================================
# Trading Bot - AWS Config avec SSO
# =============================================================================

# -----------------------------------------------------------------------------
# Session SSO (partagée entre les profils)
# -----------------------------------------------------------------------------
[sso-session trading-bot]
sso_start_url = https://your-org.awsapps.com/start
sso_region = eu-west-3
sso_registration_scopes = sso:account:access

# -----------------------------------------------------------------------------
# Environnement Développement
# -----------------------------------------------------------------------------
[profile trading-bot-dev]
sso_session = trading-bot
sso_account_id = 111111111111
sso_role_name = AdministratorAccess
region = eu-west-3
output = json
cli_pager =

# Alias court pour le dev
[profile tb-dev]
sso_session = trading-bot
sso_account_id = 111111111111
sso_role_name = AdministratorAccess
region = eu-west-3
output = json
cli_pager =

# -----------------------------------------------------------------------------
# Environnement Production
# -----------------------------------------------------------------------------
[profile trading-bot-prod]
sso_session = trading-bot
sso_account_id = 222222222222
sso_role_name = ReadOnlyAccess
region = eu-west-3
output = json
cli_pager =

# Profil Production avec accès admin (déploiement)
[profile trading-bot-prod-admin]
sso_session = trading-bot
sso_account_id = 222222222222
sso_role_name = AdministratorAccess
region = eu-west-3
output = json
cli_pager =

# Alias court pour la prod
[profile tb-prod]
sso_session = trading-bot
sso_account_id = 222222222222
sso_role_name = ReadOnlyAccess
region = eu-west-3
output = json
cli_pager =

# -----------------------------------------------------------------------------
# Terraform (Infrastructure as Code)
# -----------------------------------------------------------------------------
[profile trading-bot-terraform]
sso_session = trading-bot
sso_account_id = 111111111111
sso_role_name = AdministratorAccess
region = eu-west-3
output = json
cli_pager =

# Alias court pour Terraform
[profile tb-tf]
sso_session = trading-bot
sso_account_id = 111111111111
sso_role_name = AdministratorAccess
region = eu-west-3
output = json
cli_pager =

# -----------------------------------------------------------------------------
# Assume Role (si AWS Organizations - cross-account)
# -----------------------------------------------------------------------------
# Permet d'assumer un rôle dans un autre compte depuis le profil SSO

[profile trading-bot-prod-deploy]
role_arn = arn:aws:iam::222222222222:role/TerraformDeployRole
source_profile = trading-bot-dev
region = eu-west-3
output = json
```

## Connexion SSO

### Connexion initiale

```bash
# Se connecter à la session SSO (ouvre le navigateur)
aws sso login --profile trading-bot-dev

# Ou avec la session nommée
aws sso login --sso-session trading-bot
```

### Vérification de la connexion

```bash
# Vérifier l'identité
aws sts get-caller-identity --profile trading-bot-dev

# Réponse attendue :
# {
#     "UserId": "AROAXXXXXXXXXXXXXXXXX:user@example.com",
#     "Account": "111111111111",
#     "Arn": "arn:aws:sts::111111111111:assumed-role/AWSReservedSSO_AdministratorAccess_.../user@example.com"
# }
```

### Déconnexion

```bash
# Se déconnecter (invalide tous les tokens)
aws sso logout
```

### Renouvellement automatique

Les tokens SSO expirent après 1-12h (selon la configuration de votre organisation). Pour renouveler :

```bash
# Renouveler la session
aws sso login --sso-session trading-bot
```

## Variables d'Environnement

### Pour le développement local

```bash
# Ajouter dans ~/.zshrc ou ~/.bashrc

# Profil par défaut pour ce projet
export AWS_PROFILE=trading-bot-dev
export AWS_REGION=eu-west-3

# Alias pratiques
alias aws-dev='export AWS_PROFILE=trading-bot-dev'
alias aws-prod='export AWS_PROFILE=trading-bot-prod'
alias aws-tf='export AWS_PROFILE=trading-bot-terraform'
alias aws-whoami='aws sts get-caller-identity'
alias aws-login='aws sso login --sso-session trading-bot'
alias aws-logout='aws sso logout'
```

### Pour Terraform

```bash
# Option 1 : Variable d'environnement
export AWS_PROFILE=trading-bot-terraform

# Option 2 : Se connecter avant terraform
aws sso login --sso-session trading-bot
terraform plan

# Option 3 : Dans le provider Terraform
# Voir section Terraform ci-dessous
```

## Utilisation avec Terraform

### Provider avec profil

```hcl
# terraform/providers.tf

provider "aws" {
  region  = var.aws_region
  profile = var.aws_profile

  default_tags {
    tags = {
      Project     = "trading-bot"
      Environment = var.environment
      ManagedBy   = "terraform"
    }
  }
}
```

### Variables Terraform

```hcl
# terraform/variables.tf

variable "aws_profile" {
  description = "AWS CLI profile to use"
  type        = string
  default     = "trading-bot-terraform"
}

variable "aws_region" {
  description = "AWS region"
  type        = string
  default     = "eu-west-3"
}
```

### Commandes Terraform

```bash
# Avec profil par défaut (AWS_PROFILE)
terraform init
terraform plan
terraform apply

# Avec profil spécifique
AWS_PROFILE=trading-bot-dev terraform plan

# Ou via variable
terraform plan -var="aws_profile=trading-bot-prod"
```

## Commandes de Debug

### Vérification de l'identité

```bash
# Vérifier le profil actif
aws sts get-caller-identity

# Réponse attendue :
# {
#     "UserId": "AIDAXXXXXXXXXXXXXXXXX",
#     "Account": "123456789012",
#     "Arn": "arn:aws:iam::123456789012:user/trading-bot-deployer"
# }
```

### Debug DynamoDB

```bash
# Lister les tables
aws dynamodb list-tables --profile trading-bot-dev

# Scanner une table (limité à 10 items)
aws dynamodb scan \
  --table-name trading-bot-dev-trades \
  --limit 10 \
  --profile trading-bot-dev

# Requête sur un item spécifique
aws dynamodb get-item \
  --table-name trading-bot-dev-bot-config \
  --key '{"pk": {"S": "CONFIG#bot"}, "sk": {"S": "SETTINGS"}}' \
  --profile trading-bot-dev

# Mettre à jour la config du bot
aws dynamodb update-item \
  --table-name trading-bot-dev-bot-config \
  --key '{"pk": {"S": "CONFIG#bot"}, "sk": {"S": "SETTINGS"}}' \
  --update-expression "SET enabled = :val" \
  --expression-attribute-values '{":val": {"BOOL": true}}' \
  --profile trading-bot-dev
```

### Debug Lambda

```bash
# Lister les fonctions
aws lambda list-functions --profile trading-bot-dev | jq '.Functions[].FunctionName'

# Invoquer une fonction manuellement
aws lambda invoke \
  --function-name trading-bot-dev-lambda-executor \
  --payload '{}' \
  --cli-binary-format raw-in-base64-out \
  output.json \
  --profile trading-bot-dev

# Voir les logs récents
aws logs tail /aws/lambda/trading-bot-dev-lambda-executor \
  --follow \
  --profile trading-bot-dev

# Logs des 30 dernières minutes
aws logs filter-log-events \
  --log-group-name /aws/lambda/trading-bot-dev-lambda-executor \
  --start-time $(date -v-30M +%s000) \
  --profile trading-bot-dev
```

### Debug SSM Parameter Store

```bash
# Lister les paramètres du projet
aws ssm describe-parameters \
  --parameter-filters "Key=Name,Option=Contains,Values=/trading-bot/" \
  --profile trading-bot-dev

# Lire un paramètre (valeur déchiffrée)
aws ssm get-parameter \
  --name "/trading-bot/dev/binance/api_key" \
  --with-decryption \
  --profile trading-bot-dev

# Créer/mettre à jour un paramètre SecureString
aws ssm put-parameter \
  --name "/trading-bot/dev/binance/api_key" \
  --value "your-api-key-here" \
  --type SecureString \
  --overwrite \
  --profile trading-bot-dev
```

### Debug SNS

```bash
# Lister les topics
aws sns list-topics --profile trading-bot-dev

# Publier un message de test
aws sns publish \
  --topic-arn arn:aws:sns:eu-west-3:123456789012:trading-bot-dev-sns-trade-alerts \
  --message '{"type":"TEST","data":"Hello from CLI"}' \
  --profile trading-bot-dev
```

### Debug SQS

```bash
# Lister les queues
aws sqs list-queues --profile trading-bot-dev

# Voir le nombre de messages en attente
aws sqs get-queue-attributes \
  --queue-url https://sqs.eu-west-3.amazonaws.com/123456789012/trading-bot-dev-orders \
  --attribute-names ApproximateNumberOfMessages \
  --profile trading-bot-dev

# Recevoir des messages (debug)
aws sqs receive-message \
  --queue-url https://sqs.eu-west-3.amazonaws.com/123456789012/trading-bot-dev-orders \
  --max-number-of-messages 1 \
  --profile trading-bot-dev

# Purger une queue (ATTENTION: supprime tous les messages)
aws sqs purge-queue \
  --queue-url https://sqs.eu-west-3.amazonaws.com/123456789012/trading-bot-dev-orders \
  --profile trading-bot-dev
```

### Debug EventBridge

```bash
# Lister les règles
aws events list-rules --profile trading-bot-dev

# Voir les détails d'une règle
aws events describe-rule \
  --name trading-bot-dev-rule-bot-executor-5min \
  --profile trading-bot-dev

# Désactiver temporairement une règle
aws events disable-rule \
  --name trading-bot-dev-rule-bot-executor-5min \
  --profile trading-bot-dev

# Réactiver une règle
aws events enable-rule \
  --name trading-bot-dev-rule-bot-executor-5min \
  --profile trading-bot-dev
```

## Scripts Utiles

### Script de vérification de l'environnement

Créer `scripts/check-aws-env.sh` :

```bash
#!/bin/bash
# Vérifie que l'environnement AWS est correctement configuré

set -e

PROFILE=${1:-trading-bot-dev}

echo "🔍 Vérification du profil AWS: $PROFILE"
echo "================================================"

# Vérifier l'identité
echo -n "✓ Identité: "
aws sts get-caller-identity --profile $PROFILE --query 'Arn' --output text

# Vérifier la région
echo -n "✓ Région: "
aws configure get region --profile $PROFILE

# Vérifier l'accès DynamoDB
echo -n "✓ DynamoDB: "
aws dynamodb list-tables --profile $PROFILE --query 'TableNames | length(@)' --output text
echo " table(s)"

# Vérifier l'accès Lambda
echo -n "✓ Lambda: "
aws lambda list-functions --profile $PROFILE --query 'Functions | length(@)' --output text
echo " fonction(s)"

# Vérifier SSM
echo -n "✓ SSM Parameters: "
aws ssm describe-parameters --profile $PROFILE \
  --parameter-filters "Key=Name,Option=Contains,Values=/trading-bot/" \
  --query 'Parameters | length(@)' --output text
echo " paramètre(s)"

echo "================================================"
echo "✅ Environnement AWS OK"
```

### Script de debug rapide

Créer `scripts/aws-debug.sh` :

```bash
#!/bin/bash
# Debug rapide des services AWS

PROFILE=${1:-trading-bot-dev}
ENV=${2:-dev}

echo "🔧 Debug Trading Bot - $ENV"
echo "================================================"

echo ""
echo "📊 État du Bot (DynamoDB):"
aws dynamodb get-item \
  --table-name trading-bot-$ENV-bot-config \
  --key '{"pk": {"S": "CONFIG#bot"}, "sk": {"S": "SETTINGS"}}' \
  --profile $PROFILE \
  --query 'Item.{enabled: enabled.BOOL, strategy: strategy.S, symbol: symbol.S}' \
  --output table 2>/dev/null || echo "  ⚠️  Table non trouvée"

echo ""
echo "📈 Derniers trades (5):"
aws dynamodb scan \
  --table-name trading-bot-$ENV-trades \
  --limit 5 \
  --profile $PROFILE \
  --query 'Items[*].{symbol: symbol.S, side: side.S, price: price.N, created_at: created_at.S}' \
  --output table 2>/dev/null || echo "  ⚠️  Table non trouvée"

echo ""
echo "📝 Logs Lambda récents:"
aws logs filter-log-events \
  --log-group-name /aws/lambda/trading-bot-$ENV-lambda-executor \
  --start-time $(date -v-1H +%s000 2>/dev/null || date -d '1 hour ago' +%s000) \
  --limit 5 \
  --profile $PROFILE \
  --query 'events[*].message' \
  --output text 2>/dev/null || echo "  ⚠️  Log group non trouvé"

echo ""
echo "================================================"
```

## Sécurité

### Bonnes Pratiques avec SSO

1. **Pas de credentials locaux** : SSO gère tout automatiquement
2. **Tokens temporaires** : Expiration automatique (1-12h)
3. **MFA intégré** : Configuré dans IAM Identity Center
4. **Permissions via Permission Sets** : Gestion centralisée
5. **Audit centralisé** : CloudTrail au niveau de l'organisation

### Permission Sets Recommandés

Configurer dans AWS IAM Identity Center :

| Permission Set | Usage | Politique |
|----------------|-------|-----------|
| AdministratorAccess | Déploiement complet | AWS managed |
| ReadOnlyAccess | Debug et monitoring | AWS managed |
| TerraformDeployer | CI/CD (custom) | Voir ci-dessous |

### Permission Set Custom : TerraformDeployer

Pour un déploiement Terraform avec permissions minimales :

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "TerraformCore",
      "Effect": "Allow",
      "Action": [
        "dynamodb:*",
        "lambda:*",
        "events:*",
        "sns:*",
        "sqs:*",
        "ssm:*",
        "iam:*",
        "logs:*",
        "s3:*"
      ],
      "Resource": "*",
      "Condition": {
        "StringEquals": {
          "aws:RequestedRegion": "eu-west-3"
        }
      }
    }
  ]
}
```

**Note** : En production, restreindre davantage aux ARN spécifiques.

## Troubleshooting

### Erreur "Token has expired"

```bash
# Le token SSO a expiré, se reconnecter
aws sso login --sso-session trading-bot

# Vérifier la connexion
aws sts get-caller-identity --profile trading-bot-dev
```

### Erreur "Unable to locate credentials"

```bash
# Vérifier que le profil existe
aws configure list --profile trading-bot-dev

# Se connecter au SSO
aws sso login --sso-session trading-bot

# Vérifier le cache SSO
ls -la ~/.aws/sso/cache/
```

### Erreur "Access Denied"

```bash
# Vérifier les permissions du rôle SSO
aws sts get-caller-identity --profile trading-bot-dev

# Vérifier le Permission Set assigné dans IAM Identity Center
# Le rôle affiché doit correspondre au Permission Set configuré

# Tester une action spécifique
aws dynamodb list-tables --profile trading-bot-dev
```

### Erreur "Region not specified"

```bash
# Spécifier la région explicitement
aws dynamodb list-tables --region eu-west-3 --profile trading-bot-dev

# Vérifier la config du profil
aws configure list --profile trading-bot-dev
```

### Erreur "The SSO session associated with this profile has expired"

```bash
# Renouveler la session SSO
aws sso login --sso-session trading-bot

# Si le problème persiste, vider le cache
rm -rf ~/.aws/sso/cache/*
aws sso login --sso-session trading-bot
```

## Ressources

- [AWS CLI Documentation](https://docs.aws.amazon.com/cli/latest/userguide/)
- [AWS IAM Identity Center (SSO)](https://docs.aws.amazon.com/singlesignon/latest/userguide/)
- [Configuring IAM Identity Center with AWS CLI](https://docs.aws.amazon.com/cli/latest/userguide/cli-configure-sso.html)
- [SSO Token Provider](https://docs.aws.amazon.com/cli/latest/userguide/sso-configure-profile-token.html)
- [Permission Sets](https://docs.aws.amazon.com/singlesignon/latest/userguide/permissionsetsconcept.html)
