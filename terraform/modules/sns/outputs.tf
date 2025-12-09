output "trade_alerts_topic_arn" {
  description = "ARN du topic SNS pour les alertes de trades"
  value       = aws_sns_topic.trade_alerts.arn
}

output "trade_alerts_topic_name" {
  description = "Nom du topic SNS pour les alertes de trades"
  value       = aws_sns_topic.trade_alerts.name
}

output "error_alerts_topic_arn" {
  description = "ARN du topic SNS pour les alertes d'erreurs"
  value       = aws_sns_topic.error_alerts.arn
}

output "error_alerts_topic_name" {
  description = "Nom du topic SNS pour les alertes d'erreurs"
  value       = aws_sns_topic.error_alerts.name
}

output "topic_arns" {
  description = "Liste de tous les ARNs des topics"
  value = [
    aws_sns_topic.trade_alerts.arn,
    aws_sns_topic.error_alerts.arn,
  ]
}
