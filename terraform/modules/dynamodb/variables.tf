variable "environment" {
  description = "Environnement de déploiement"
  type        = string
}

variable "project_name" {
  description = "Nom du projet"
  type        = string
}

variable "common_tags" {
  description = "Tags communs à appliquer"
  type        = map(string)
  default     = {}
}
