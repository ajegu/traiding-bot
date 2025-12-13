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

variable "aws_region" {
  description = "AWS Region"
  type        = string
}

variable "aws_account_id" {
  description = "AWS Account ID"
  type        = string
}

variable "dynamodb_table_arns" {
  description = "Liste des ARNs des tables DynamoDB"
  type        = list(string)
}

variable "sns_topic_arns" {
  description = "Liste des ARNs des topics SNS"
  type        = list(string)
}

variable "sqs_queue_arns" {
  description = "Liste des ARNs des queues SQS"
  type        = list(string)
}
