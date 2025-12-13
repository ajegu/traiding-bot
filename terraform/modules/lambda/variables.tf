variable "environment" {
  description = "Environnement (dev, staging, prod)"
  type        = string
}

variable "project_name" {
  description = "Nom du projet"
  type        = string
}

variable "common_tags" {
  description = "Tags communs"
  type        = map(string)
}

variable "lambda_exec_role_arn" {
  description = "ARN du rôle d'exécution Lambda"
  type        = string
}

variable "lambda_zip_path" {
  description = "Chemin vers le fichier ZIP du code Lambda"
  type        = string
}

variable "bref_layers" {
  description = "Liste des ARNs des layers Bref"
  type        = list(string)
  default     = []
}

# DynamoDB
variable "dynamodb_trades_table_name" {
  description = "Nom de la table DynamoDB trades"
  type        = string
}

variable "dynamodb_bot_config_table_name" {
  description = "Nom de la table DynamoDB bot_config"
  type        = string
}

variable "dynamodb_reports_table_name" {
  description = "Nom de la table DynamoDB reports"
  type        = string
}

# SNS
variable "sns_trade_alerts_topic_arn" {
  description = "ARN du topic SNS trade alerts"
  type        = string
}

variable "sns_error_alerts_topic_arn" {
  description = "ARN du topic SNS error alerts"
  type        = string
}

# SQS
variable "sqs_orders_queue_url" {
  description = "URL de la queue SQS orders"
  type        = string
}

# EventBridge
variable "eventbridge_bot_rule_name" {
  description = "Nom de la règle EventBridge bot executor"
  type        = string
}

variable "eventbridge_bot_rule_arn" {
  description = "ARN de la règle EventBridge bot executor"
  type        = string
}

variable "eventbridge_report_rule_name" {
  description = "Nom de la règle EventBridge daily report"
  type        = string
}

variable "eventbridge_report_rule_arn" {
  description = "ARN de la règle EventBridge daily report"
  type        = string
}
