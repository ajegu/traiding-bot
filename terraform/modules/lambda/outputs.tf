output "bot_executor_function_arn" {
  description = "ARN de la fonction Lambda bot executor"
  value       = aws_lambda_function.bot_executor.arn
}

output "bot_executor_function_name" {
  description = "Nom de la fonction Lambda bot executor"
  value       = aws_lambda_function.bot_executor.function_name
}

output "bot_executor_invoke_arn" {
  description = "ARN d'invocation de la fonction Lambda bot executor"
  value       = aws_lambda_function.bot_executor.invoke_arn
}

output "daily_report_function_arn" {
  description = "ARN de la fonction Lambda daily report"
  value       = aws_lambda_function.daily_report.arn
}

output "daily_report_function_name" {
  description = "Nom de la fonction Lambda daily report"
  value       = aws_lambda_function.daily_report.function_name
}

output "daily_report_invoke_arn" {
  description = "ARN d'invocation de la fonction Lambda daily report"
  value       = aws_lambda_function.daily_report.invoke_arn
}

output "function_arns" {
  description = "Liste de tous les ARNs des fonctions"
  value = [
    aws_lambda_function.bot_executor.arn,
    aws_lambda_function.daily_report.arn,
  ]
}
