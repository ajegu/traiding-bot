output "lambda_exec_role_arn" {
  description = "ARN du rôle d'exécution Lambda"
  value       = aws_iam_role.lambda_exec.arn
}

output "lambda_exec_role_name" {
  description = "Nom du rôle d'exécution Lambda"
  value       = aws_iam_role.lambda_exec.name
}

output "lambda_exec_role_id" {
  description = "ID du rôle d'exécution Lambda"
  value       = aws_iam_role.lambda_exec.id
}
