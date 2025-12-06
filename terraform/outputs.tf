output "environment" {
  description = "Environnement actuel"
  value       = var.environment
}

output "name_prefix" {
  description = "Préfixe de nommage des ressources"
  value       = local.name_prefix
}
