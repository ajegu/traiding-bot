locals {
  name_prefix = "${var.project_name}-${var.environment}"
}

# =============================================================================
# Orders Queue + DLQ
# =============================================================================

# Dead Letter Queue pour les orders
resource "aws_sqs_queue" "orders_dlq" {
  name                      = "${local.name_prefix}-sqs-orders-dlq"
  message_retention_seconds = 1209600 # 14 jours

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-sqs-orders-dlq"
  })
}

# Queue principale pour les orders
resource "aws_sqs_queue" "orders" {
  name                       = "${local.name_prefix}-sqs-orders"
  delay_seconds              = 0
  max_message_size           = 262144 # 256 KB
  message_retention_seconds  = 345600 # 4 jours
  receive_wait_time_seconds  = 10     # Long polling
  visibility_timeout_seconds = 60     # Doit être > Lambda timeout

  redrive_policy = jsonencode({
    deadLetterTargetArn = aws_sqs_queue.orders_dlq.arn
    maxReceiveCount     = 3
  })

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-sqs-orders"
  })
}

# Policy pour la queue orders
resource "aws_sqs_queue_policy" "orders" {
  queue_url = aws_sqs_queue.orders.id
  policy    = data.aws_iam_policy_document.orders_queue_policy.json
}

data "aws_iam_policy_document" "orders_queue_policy" {
  statement {
    sid    = "AllowLambdaAccess"
    effect = "Allow"

    principals {
      type        = "Service"
      identifiers = ["lambda.amazonaws.com"]
    }

    actions = [
      "sqs:SendMessage",
      "sqs:ReceiveMessage",
      "sqs:DeleteMessage",
      "sqs:GetQueueAttributes",
    ]

    resources = [aws_sqs_queue.orders.arn]

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
      "sqs:*",
    ]

    resources = [aws_sqs_queue.orders.arn]
  }
}

# =============================================================================
# Notifications Queue + DLQ
# =============================================================================

# Dead Letter Queue pour les notifications
resource "aws_sqs_queue" "notifications_dlq" {
  name                      = "${local.name_prefix}-sqs-notifications-dlq"
  message_retention_seconds = 1209600 # 14 jours

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-sqs-notifications-dlq"
  })
}

# Queue principale pour les notifications
resource "aws_sqs_queue" "notifications" {
  name                       = "${local.name_prefix}-sqs-notifications"
  delay_seconds              = 0
  max_message_size           = 262144 # 256 KB
  message_retention_seconds  = 345600 # 4 jours
  receive_wait_time_seconds  = 10     # Long polling
  visibility_timeout_seconds = 60     # Doit être > Lambda timeout

  redrive_policy = jsonencode({
    deadLetterTargetArn = aws_sqs_queue.notifications_dlq.arn
    maxReceiveCount     = 3
  })

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-sqs-notifications"
  })
}

# Policy pour la queue notifications
resource "aws_sqs_queue_policy" "notifications" {
  queue_url = aws_sqs_queue.notifications.id
  policy    = data.aws_iam_policy_document.notifications_queue_policy.json
}

data "aws_iam_policy_document" "notifications_queue_policy" {
  # Permettre à SNS de publier dans cette queue
  statement {
    sid    = "AllowSNSPublish"
    effect = "Allow"

    principals {
      type        = "Service"
      identifiers = ["sns.amazonaws.com"]
    }

    actions   = ["sqs:SendMessage"]
    resources = [aws_sqs_queue.notifications.arn]

    condition {
      test     = "ArnEquals"
      variable = "aws:SourceArn"
      values   = var.sns_topic_arns
    }
  }

  statement {
    sid    = "AllowLambdaAccess"
    effect = "Allow"

    principals {
      type        = "Service"
      identifiers = ["lambda.amazonaws.com"]
    }

    actions = [
      "sqs:ReceiveMessage",
      "sqs:DeleteMessage",
      "sqs:GetQueueAttributes",
    ]

    resources = [aws_sqs_queue.notifications.arn]

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
      "sqs:*",
    ]

    resources = [aws_sqs_queue.notifications.arn]
  }
}

# =============================================================================
# SNS Subscription vers SQS (notifications)
# =============================================================================

# Abonner la queue notifications aux topics SNS
resource "aws_sns_topic_subscription" "trade_alerts_to_sqs" {
  count = length(var.sns_topic_arns) > 0 ? 1 : 0

  topic_arn = var.sns_topic_arns[0] # trade-alerts
  protocol  = "sqs"
  endpoint  = aws_sqs_queue.notifications.arn

  raw_message_delivery = true
}
