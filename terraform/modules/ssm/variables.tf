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
