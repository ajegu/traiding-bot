# Orders Queue
output "orders_queue_arn" {
  description = "ARN de la queue orders"
  value       = aws_sqs_queue.orders.arn
}

output "orders_queue_url" {
  description = "URL de la queue orders"
  value       = aws_sqs_queue.orders.url
}

output "orders_queue_name" {
  description = "Nom de la queue orders"
  value       = aws_sqs_queue.orders.name
}

output "orders_dlq_arn" {
  description = "ARN de la DLQ orders"
  value       = aws_sqs_queue.orders_dlq.arn
}

# Notifications Queue
output "notifications_queue_arn" {
  description = "ARN de la queue notifications"
  value       = aws_sqs_queue.notifications.arn
}

output "notifications_queue_url" {
  description = "URL de la queue notifications"
  value       = aws_sqs_queue.notifications.url
}

output "notifications_queue_name" {
  description = "Nom de la queue notifications"
  value       = aws_sqs_queue.notifications.name
}

output "notifications_dlq_arn" {
  description = "ARN de la DLQ notifications"
  value       = aws_sqs_queue.notifications_dlq.arn
}

# All queues
output "all_queue_arns" {
  description = "Liste de tous les ARNs des queues"
  value = [
    aws_sqs_queue.orders.arn,
    aws_sqs_queue.orders_dlq.arn,
    aws_sqs_queue.notifications.arn,
    aws_sqs_queue.notifications_dlq.arn,
  ]
}
