locals {
  name_prefix = "${var.project_name}-${var.environment}"
}

# =============================================================================
# Rule: Bot Executor (toutes les 5 minutes)
# =============================================================================
resource "aws_cloudwatch_event_rule" "bot_executor" {
  name                = "${local.name_prefix}-rule-bot-executor"
  description         = "Exécute le bot de trading toutes les 5 minutes"
  schedule_expression = "rate(5 minutes)"
  state               = var.bot_executor_enabled ? "ENABLED" : "DISABLED"

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-rule-bot-executor"
  })
}

# Target vers Lambda (sera connecté dans task 1.8)
# Pour l'instant, on crée la rule sans target
# Le target sera ajouté quand Lambda sera créé

# =============================================================================
# Rule: Daily Report (tous les jours à 08h00 UTC)
# =============================================================================
resource "aws_cloudwatch_event_rule" "daily_report" {
  name                = "${local.name_prefix}-rule-daily-report"
  description         = "Génère et envoie le rapport quotidien à 08h00 UTC"
  schedule_expression = "cron(0 8 * * ? *)"
  state               = var.daily_report_enabled ? "ENABLED" : "DISABLED"

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-rule-daily-report"
  })
}

# =============================================================================
# Outputs pour les targets (à connecter avec Lambda plus tard)
# =============================================================================
# Les targets seront créés dans le module Lambda (task 1.8)
# car ils ont besoin de l'ARN de la fonction Lambda
