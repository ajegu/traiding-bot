variable "aws_region" {
  description = "AWS region"
  type        = string
  default     = "eu-west-3"
}

variable "environment" {
  description = "Environnement de déploiement"
  type        = string
  default     = "dev"

  validation {
    condition     = contains(["dev", "staging", "prod"], var.environment)
    error_message = "Environment must be dev, staging, or prod."
  }
}

variable "project_name" {
  description = "Nom du projet"
  type        = string
  default     = "trading-bot"
}

# =============================================================================
# SSM Parameters (secrets)
# =============================================================================
variable "binance_api_key" {
  description = "Binance API Key"
  type        = string
  sensitive   = true
  default     = "PLACEHOLDER_TO_UPDATE"
}

variable "binance_api_secret" {
  description = "Binance API Secret"
  type        = string
  sensitive   = true
  default     = "PLACEHOLDER_TO_UPDATE"
}

variable "telegram_bot_token" {
  description = "Telegram Bot Token"
  type        = string
  sensitive   = true
  default     = "PLACEHOLDER_TO_UPDATE"
}

variable "telegram_chat_id" {
  description = "Telegram Chat ID"
  type        = string
  default     = "PLACEHOLDER_TO_UPDATE"
}

# =============================================================================
# SNS Topics
# =============================================================================
variable "alert_email" {
  description = "Email pour recevoir les alertes d'erreurs (optionnel)"
  type        = string
  default     = ""
}

# =============================================================================
# EventBridge Rules
# =============================================================================
variable "eventbridge_bot_enabled" {
  description = "Activer la règle EventBridge pour le bot (désactivé par défaut)"
  type        = bool
  default     = false
}

variable "eventbridge_report_enabled" {
  description = "Activer la règle EventBridge pour le rapport quotidien (désactivé par défaut)"
  type        = bool
  default     = false
}
