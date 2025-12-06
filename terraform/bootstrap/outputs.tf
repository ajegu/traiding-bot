output "s3_bucket_name" {
  description = "Nom du bucket S3 pour l'état Terraform"
  value       = aws_s3_bucket.terraform_state.id
}

output "s3_bucket_arn" {
  description = "ARN du bucket S3"
  value       = aws_s3_bucket.terraform_state.arn
}

output "dynamodb_table_name" {
  description = "Nom de la table DynamoDB pour le verrouillage"
  value       = aws_dynamodb_table.terraform_lock.name
}

output "dynamodb_table_arn" {
  description = "ARN de la table DynamoDB"
  value       = aws_dynamodb_table.terraform_lock.arn
}

output "backend_config" {
  description = "Configuration à utiliser pour le backend"
  value       = <<-EOT
    terraform {
      backend "s3" {
        bucket         = "${aws_s3_bucket.terraform_state.id}"
        key            = "state/terraform.tfstate"
        region         = "${var.aws_region}"
        encrypt        = true
        dynamodb_table = "${aws_dynamodb_table.terraform_lock.name}"
      }
    }
  EOT
}
