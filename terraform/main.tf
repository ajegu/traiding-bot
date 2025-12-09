# Trading Bot - Infrastructure principale
#
# Ce fichier sert de point d'entrée pour l'infrastructure.

# =============================================================================
# DynamoDB Tables
# =============================================================================
module "dynamodb" {
  source = "./modules/dynamodb"

  environment  = var.environment
  project_name = var.project_name
  common_tags  = local.common_tags
}

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
