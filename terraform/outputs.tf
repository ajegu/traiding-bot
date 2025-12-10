output "environment" {
  description = "Environnement actuel"
  value       = var.environment
}

output "name_prefix" {
  description = "Préfixe de nommage des ressources"
  value       = local.name_prefix
}

# =============================================================================
# DynamoDB Outputs
# =============================================================================
output "dynamodb_trades_table_name" {
  description = "Nom de la table trades"
  value       = module.dynamodb.trades_table_name
}

output "dynamodb_trades_table_arn" {
  description = "ARN de la table trades"
  value       = module.dynamodb.trades_table_arn
}

output "dynamodb_bot_config_table_name" {
  description = "Nom de la table bot_config"
  value       = module.dynamodb.bot_config_table_name
}

output "dynamodb_bot_config_table_arn" {
  description = "ARN de la table bot_config"
  value       = module.dynamodb.bot_config_table_arn
}

output "dynamodb_reports_table_name" {
  description = "Nom de la table reports"
  value       = module.dynamodb.reports_table_name
}

output "dynamodb_reports_table_arn" {
  description = "ARN de la table reports"
  value       = module.dynamodb.reports_table_arn
}

output "dynamodb_all_table_arns" {
  description = "Liste de tous les ARNs des tables DynamoDB"
  value       = module.dynamodb.all_table_arns
}

# =============================================================================
# SSM Parameters Outputs
# =============================================================================
output "ssm_parameter_arns" {
  description = "ARNs des paramètres SSM"
  value       = module.ssm.parameter_arns
}

output "ssm_parameter_prefix" {
  description = "Préfixe des paramètres SSM"
  value       = module.ssm.parameter_name_prefix
}

# =============================================================================
# SNS Topics Outputs
# =============================================================================
output "sns_trade_alerts_topic_arn" {
  description = "ARN du topic SNS pour les alertes de trades"
  value       = module.sns.trade_alerts_topic_arn
}

output "sns_error_alerts_topic_arn" {
  description = "ARN du topic SNS pour les alertes d'erreurs"
  value       = module.sns.error_alerts_topic_arn
}

# =============================================================================
# SQS Queues Outputs
# =============================================================================
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
