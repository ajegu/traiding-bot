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
