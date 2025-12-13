locals {
  name_prefix = "${var.project_name}-${var.environment}"
}

# =============================================================================
# Lambda Function: Bot Executor
# =============================================================================
resource "aws_lambda_function" "bot_executor" {
  function_name = "${local.name_prefix}-lambda-executor"
  description   = "Trading bot executor - runs every 5 minutes"

  role          = var.lambda_exec_role_arn
  handler       = "bootstrap"
  runtime       = "provided.al2"
  architectures = ["x86_64"]
  timeout       = 30
  memory_size   = 512

  # Placeholder - sera remplacé par le vrai code via CI/CD
  filename         = var.lambda_zip_path
  source_code_hash = filebase64sha256(var.lambda_zip_path)

  layers = var.bref_layers

  environment {
    variables = {
      APP_ENV              = var.environment
      LOG_CHANNEL          = "stderr"
      CACHE_DRIVER         = "array"
      SESSION_DRIVER       = "array"
      BOT_COMMAND          = "bot:run"
      SSM_PARAMETER_PREFIX = "/${var.project_name}/${var.environment}"

      # DynamoDB Tables
      DYNAMODB_TABLE_TRADES     = var.dynamodb_trades_table_name
      DYNAMODB_TABLE_BOT_CONFIG = var.dynamodb_bot_config_table_name
      DYNAMODB_TABLE_REPORTS    = var.dynamodb_reports_table_name

      # SNS Topics
      SNS_TOPIC_TRADE_ALERTS = var.sns_trade_alerts_topic_arn
      SNS_TOPIC_ERROR_ALERTS = var.sns_error_alerts_topic_arn

      # SQS Queues
      SQS_QUEUE_ORDERS = var.sqs_orders_queue_url
    }
  }

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-lambda-executor"
  })

  depends_on = [aws_cloudwatch_log_group.bot_executor]
}

# Log Group pour bot executor
resource "aws_cloudwatch_log_group" "bot_executor" {
  name              = "/aws/lambda/${local.name_prefix}-lambda-executor"
  retention_in_days = var.environment == "prod" ? 30 : 7

  tags = var.common_tags
}

# =============================================================================
# Lambda Function: Daily Report
# =============================================================================
resource "aws_lambda_function" "daily_report" {
  function_name = "${local.name_prefix}-lambda-report"
  description   = "Daily report generator - runs once per day"

  role          = var.lambda_exec_role_arn
  handler       = "bootstrap"
  runtime       = "provided.al2"
  architectures = ["x86_64"]
  timeout       = 60 # Plus de temps pour générer le rapport
  memory_size   = 512

  filename         = var.lambda_zip_path
  source_code_hash = filebase64sha256(var.lambda_zip_path)

  layers = var.bref_layers

  environment {
    variables = {
      APP_ENV              = var.environment
      LOG_CHANNEL          = "stderr"
      CACHE_DRIVER         = "array"
      SESSION_DRIVER       = "array"
      BOT_COMMAND          = "report:daily"
      SSM_PARAMETER_PREFIX = "/${var.project_name}/${var.environment}"

      # DynamoDB Tables
      DYNAMODB_TABLE_TRADES     = var.dynamodb_trades_table_name
      DYNAMODB_TABLE_BOT_CONFIG = var.dynamodb_bot_config_table_name
      DYNAMODB_TABLE_REPORTS    = var.dynamodb_reports_table_name

      # Telegram (via SSM)
      TELEGRAM_ENABLED = "true"
    }
  }

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-lambda-report"
  })

  depends_on = [aws_cloudwatch_log_group.daily_report]
}

# Log Group pour daily report
resource "aws_cloudwatch_log_group" "daily_report" {
  name              = "/aws/lambda/${local.name_prefix}-lambda-report"
  retention_in_days = var.environment == "prod" ? 30 : 7

  tags = var.common_tags
}

# =============================================================================
# EventBridge Targets
# =============================================================================

# Target: Bot Executor
resource "aws_cloudwatch_event_target" "bot_executor" {
  rule      = var.eventbridge_bot_rule_name
  target_id = "BotExecutorLambda"
  arn       = aws_lambda_function.bot_executor.arn

  input = jsonencode({
    command = "bot:run"
  })
}

# Permission pour EventBridge -> Bot Executor
resource "aws_lambda_permission" "eventbridge_bot_executor" {
  statement_id  = "AllowEventBridgeInvoke"
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.bot_executor.function_name
  principal     = "events.amazonaws.com"
  source_arn    = var.eventbridge_bot_rule_arn
}

# Target: Daily Report
resource "aws_cloudwatch_event_target" "daily_report" {
  rule      = var.eventbridge_report_rule_name
  target_id = "DailyReportLambda"
  arn       = aws_lambda_function.daily_report.arn

  input = jsonencode({
    command = "report:daily"
  })
}

# Permission pour EventBridge -> Daily Report
resource "aws_lambda_permission" "eventbridge_daily_report" {
  statement_id  = "AllowEventBridgeInvoke"
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.daily_report.function_name
  principal     = "events.amazonaws.com"
  source_arn    = var.eventbridge_report_rule_arn
}
