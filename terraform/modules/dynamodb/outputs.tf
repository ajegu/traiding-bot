# =============================================================================
# Trades Table Outputs
# =============================================================================
output "trades_table_name" {
  description = "Nom de la table trades"
  value       = aws_dynamodb_table.trades.name
}

output "trades_table_arn" {
  description = "ARN de la table trades"
  value       = aws_dynamodb_table.trades.arn
}

# =============================================================================
# Bot Config Table Outputs
# =============================================================================
output "bot_config_table_name" {
  description = "Nom de la table bot_config"
  value       = aws_dynamodb_table.bot_config.name
}

output "bot_config_table_arn" {
  description = "ARN de la table bot_config"
  value       = aws_dynamodb_table.bot_config.arn
}

# =============================================================================
# Reports Table Outputs
# =============================================================================
output "reports_table_name" {
  description = "Nom de la table reports"
  value       = aws_dynamodb_table.reports.name
}

output "reports_table_arn" {
  description = "ARN de la table reports"
  value       = aws_dynamodb_table.reports.arn
}

# =============================================================================
# Combined Outputs
# =============================================================================
output "all_table_arns" {
  description = "Liste de tous les ARNs des tables"
  value = [
    aws_dynamodb_table.trades.arn,
    aws_dynamodb_table.bot_config.arn,
    aws_dynamodb_table.reports.arn
  ]
}

output "all_table_names" {
  description = "Map de tous les noms des tables"
  value = {
    trades     = aws_dynamodb_table.trades.name
    bot_config = aws_dynamodb_table.bot_config.name
    reports    = aws_dynamodb_table.reports.name
  }
}
