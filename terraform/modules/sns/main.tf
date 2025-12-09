locals {
  name_prefix = "${var.project_name}-${var.environment}"
}

# =============================================================================
# Topic: Trade Alerts
# =============================================================================
resource "aws_sns_topic" "trade_alerts" {
  name = "${local.name_prefix}-sns-trade-alerts"

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-sns-trade-alerts"
  })
}

# Policy pour permettre la publication depuis Lambda
resource "aws_sns_topic_policy" "trade_alerts" {
  arn    = aws_sns_topic.trade_alerts.arn
  policy = data.aws_iam_policy_document.trade_alerts_policy.json
}

data "aws_iam_policy_document" "trade_alerts_policy" {
  statement {
    sid    = "AllowLambdaPublish"
    effect = "Allow"

    principals {
      type        = "Service"
      identifiers = ["lambda.amazonaws.com"]
    }

    actions   = ["sns:Publish"]
    resources = [aws_sns_topic.trade_alerts.arn]

    condition {
      test     = "ArnLike"
      variable = "aws:SourceArn"
      values   = ["arn:aws:lambda:${var.aws_region}:${var.aws_account_id}:function:${local.name_prefix}-*"]
    }
  }

  statement {
    sid    = "AllowAccountAccess"
    effect = "Allow"

    principals {
      type        = "AWS"
      identifiers = ["arn:aws:iam::${var.aws_account_id}:root"]
    }

    actions = [
      "sns:Publish",
      "sns:Subscribe",
      "sns:GetTopicAttributes",
    ]
    resources = [aws_sns_topic.trade_alerts.arn]
  }
}

# =============================================================================
# Topic: Error Alerts
# =============================================================================
resource "aws_sns_topic" "error_alerts" {
  name = "${local.name_prefix}-sns-error-alerts"

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-sns-error-alerts"
  })
}

resource "aws_sns_topic_policy" "error_alerts" {
  arn    = aws_sns_topic.error_alerts.arn
  policy = data.aws_iam_policy_document.error_alerts_policy.json
}

data "aws_iam_policy_document" "error_alerts_policy" {
  statement {
    sid    = "AllowLambdaPublish"
    effect = "Allow"

    principals {
      type        = "Service"
      identifiers = ["lambda.amazonaws.com"]
    }

    actions   = ["sns:Publish"]
    resources = [aws_sns_topic.error_alerts.arn]

    condition {
      test     = "ArnLike"
      variable = "aws:SourceArn"
      values   = ["arn:aws:lambda:${var.aws_region}:${var.aws_account_id}:function:${local.name_prefix}-*"]
    }
  }

  statement {
    sid    = "AllowAccountAccess"
    effect = "Allow"

    principals {
      type        = "AWS"
      identifiers = ["arn:aws:iam::${var.aws_account_id}:root"]
    }

    actions = [
      "sns:Publish",
      "sns:Subscribe",
      "sns:GetTopicAttributes",
    ]
    resources = [aws_sns_topic.error_alerts.arn]
  }
}

# =============================================================================
# Email Subscription (optionnel, pour les alertes d'erreurs)
# =============================================================================
resource "aws_sns_topic_subscription" "error_alerts_email" {
  count = var.alert_email != "" ? 1 : 0

  topic_arn = aws_sns_topic.error_alerts.arn
  protocol  = "email"
  endpoint  = var.alert_email
}
