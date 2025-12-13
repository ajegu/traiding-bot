locals {
  name_prefix = "${var.project_name}-${var.environment}"
}

# =============================================================================
# Lambda Execution Role
# =============================================================================
resource "aws_iam_role" "lambda_exec" {
  name = "${local.name_prefix}-role-lambda-exec"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "lambda.amazonaws.com"
        }
      }
    ]
  })

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-role-lambda-exec"
  })
}

# =============================================================================
# Policy: CloudWatch Logs
# =============================================================================
resource "aws_iam_role_policy" "lambda_logs" {
  name = "${local.name_prefix}-policy-lambda-logs"
  role = aws_iam_role.lambda_exec.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "logs:CreateLogGroup",
          "logs:CreateLogStream",
          "logs:PutLogEvents"
        ]
        Resource = "arn:aws:logs:${var.aws_region}:${var.aws_account_id}:log-group:/aws/lambda/${local.name_prefix}-*:*"
      }
    ]
  })
}

# =============================================================================
# Policy: DynamoDB Access
# =============================================================================
resource "aws_iam_role_policy" "lambda_dynamodb" {
  name = "${local.name_prefix}-policy-lambda-dynamodb"
  role = aws_iam_role.lambda_exec.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "dynamodb:GetItem",
          "dynamodb:PutItem",
          "dynamodb:UpdateItem",
          "dynamodb:DeleteItem",
          "dynamodb:Query",
          "dynamodb:Scan",
          "dynamodb:BatchGetItem",
          "dynamodb:BatchWriteItem"
        ]
        Resource = concat(
          var.dynamodb_table_arns,
          [for arn in var.dynamodb_table_arns : "${arn}/index/*"]
        )
      }
    ]
  })
}

# =============================================================================
# Policy: SSM Parameter Store (Read)
# =============================================================================
resource "aws_iam_role_policy" "lambda_ssm" {
  name = "${local.name_prefix}-policy-lambda-ssm"
  role = aws_iam_role.lambda_exec.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "ssm:GetParameter",
          "ssm:GetParameters",
          "ssm:GetParametersByPath"
        ]
        Resource = "arn:aws:ssm:${var.aws_region}:${var.aws_account_id}:parameter/${var.project_name}/${var.environment}/*"
      },
      {
        Effect = "Allow"
        Action = [
          "kms:Decrypt"
        ]
        Resource = "*"
        Condition = {
          StringEquals = {
            "kms:ViaService" = "ssm.${var.aws_region}.amazonaws.com"
          }
        }
      }
    ]
  })
}

# =============================================================================
# Policy: SNS Publish
# =============================================================================
resource "aws_iam_role_policy" "lambda_sns" {
  name = "${local.name_prefix}-policy-lambda-sns"
  role = aws_iam_role.lambda_exec.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "sns:Publish"
        ]
        Resource = var.sns_topic_arns
      }
    ]
  })
}

# =============================================================================
# Policy: SQS Access
# =============================================================================
resource "aws_iam_role_policy" "lambda_sqs" {
  name = "${local.name_prefix}-policy-lambda-sqs"
  role = aws_iam_role.lambda_exec.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "sqs:SendMessage",
          "sqs:ReceiveMessage",
          "sqs:DeleteMessage",
          "sqs:GetQueueAttributes",
          "sqs:GetQueueUrl"
        ]
        Resource = var.sqs_queue_arns
      }
    ]
  })
}
