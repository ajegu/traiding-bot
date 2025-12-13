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

variable "bot_executor_enabled" {
  description = "Activer la règle bot executor"
  type        = bool
  default     = false # Désactivé par défaut pour éviter les exécutions accidentelles
}

variable "daily_report_enabled" {
  description = "Activer la règle daily report"
  type        = bool
  default     = false # Désactivé par défaut
}
