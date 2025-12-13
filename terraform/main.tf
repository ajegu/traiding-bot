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

  dynamodb_table_arns = module.dynamodb.all_table_arns
  sns_topic_arns      = module.sns.topic_arns
  sqs_queue_arns      = module.sqs.all_queue_arns

  depends_on = [module.dynamodb, module.sns, module.sqs]
}

# =============================================================================
# Lambda Functions
# =============================================================================
module "lambda" {
  source = "./modules/lambda"

  environment  = var.environment
  project_name = var.project_name
  common_tags  = local.common_tags

  lambda_exec_role_arn = module.iam.lambda_exec_role_arn
  lambda_zip_path      = "${path.module}/placeholder.zip"
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
