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
