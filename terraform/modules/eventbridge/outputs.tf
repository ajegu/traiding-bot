output "bot_executor_rule_arn" {
  description = "ARN de la règle bot executor"
  value       = aws_cloudwatch_event_rule.bot_executor.arn
}

output "bot_executor_rule_name" {
  description = "Nom de la règle bot executor"
  value       = aws_cloudwatch_event_rule.bot_executor.name
}

output "daily_report_rule_arn" {
  description = "ARN de la règle daily report"
  value       = aws_cloudwatch_event_rule.daily_report.arn
}

output "daily_report_rule_name" {
  description = "Nom de la règle daily report"
  value       = aws_cloudwatch_event_rule.daily_report.name
}

output "rule_arns" {
  description = "Liste de tous les ARNs des règles"
  value = [
    aws_cloudwatch_event_rule.bot_executor.arn,
    aws_cloudwatch_event_rule.daily_report.arn,
  ]
}
