locals {
  name_prefix = "/${var.project_name}/${var.environment}"
}

# =============================================================================
# Binance API Parameters
# =============================================================================
resource "aws_ssm_parameter" "binance_api_key" {
  name        = "${local.name_prefix}/binance/api_key"
  description = "Binance API Key"
  type        = "SecureString"
  value       = var.binance_api_key
  tier        = "Standard"

  tags = var.common_tags

  lifecycle {
    ignore_changes = [value]
  }
}

resource "aws_ssm_parameter" "binance_api_secret" {
  name        = "${local.name_prefix}/binance/api_secret"
  description = "Binance API Secret"
  type        = "SecureString"
  value       = var.binance_api_secret
  tier        = "Standard"

  tags = var.common_tags

  lifecycle {
    ignore_changes = [value]
  }
}

# =============================================================================
# Telegram Parameters
# =============================================================================
resource "aws_ssm_parameter" "telegram_bot_token" {
  name        = "${local.name_prefix}/telegram/bot_token"
  description = "Telegram Bot Token"
  type        = "SecureString"
  value       = var.telegram_bot_token
  tier        = "Standard"

  tags = var.common_tags

  lifecycle {
    ignore_changes = [value]
  }
}

resource "aws_ssm_parameter" "telegram_chat_id" {
  name        = "${local.name_prefix}/telegram/chat_id"
  description = "Telegram Chat ID"
  type        = "String"
  value       = var.telegram_chat_id
  tier        = "Standard"

  tags = var.common_tags

  lifecycle {
    ignore_changes = [value]
  }
}
