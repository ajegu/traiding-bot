locals {
  name_prefix = "${var.project_name}-${var.environment}"
}

# =============================================================================
# Table: Trades
# =============================================================================
resource "aws_dynamodb_table" "trades" {
  name         = "${local.name_prefix}-trades"
  billing_mode = "PAY_PER_REQUEST" # Free Tier compatible
  hash_key     = "pk"
  range_key    = "sk"

  attribute {
    name = "pk"
    type = "S"
  }

  attribute {
    name = "sk"
    type = "S"
  }

  attribute {
    name = "gsi1pk"
    type = "S"
  }

  attribute {
    name = "gsi1sk"
    type = "S"
  }

  attribute {
    name = "gsi2pk"
    type = "S"
  }

  attribute {
    name = "gsi2sk"
    type = "S"
  }

  attribute {
    name = "gsi3pk"
    type = "S"
  }

  attribute {
    name = "gsi3sk"
    type = "S"
  }

  # GSI1: Trades par symbole
  global_secondary_index {
    name            = "gsi1-symbol-date"
    hash_key        = "gsi1pk"
    range_key       = "gsi1sk"
    projection_type = "ALL"
  }

  # GSI2: Trades par date
  global_secondary_index {
    name            = "gsi2-date"
    hash_key        = "gsi2pk"
    range_key       = "gsi2sk"
    projection_type = "ALL"
  }

  # GSI3: Trades par statut
  global_secondary_index {
    name            = "gsi3-status"
    hash_key        = "gsi3pk"
    range_key       = "gsi3sk"
    projection_type = "ALL"
  }

  point_in_time_recovery {
    enabled = var.environment == "prod"
  }

  server_side_encryption {
    enabled = true
  }

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-trades"
  })
}

# =============================================================================
# Table: Bot Config
# =============================================================================
resource "aws_dynamodb_table" "bot_config" {
  name         = "${local.name_prefix}-bot-config"
  billing_mode = "PAY_PER_REQUEST"
  hash_key     = "pk"
  range_key    = "sk"

  attribute {
    name = "pk"
    type = "S"
  }

  attribute {
    name = "sk"
    type = "S"
  }

  point_in_time_recovery {
    enabled = var.environment == "prod"
  }

  server_side_encryption {
    enabled = true
  }

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-bot-config"
  })
}

# =============================================================================
# Table: Reports
# =============================================================================
resource "aws_dynamodb_table" "reports" {
  name         = "${local.name_prefix}-reports"
  billing_mode = "PAY_PER_REQUEST"
  hash_key     = "pk"
  range_key    = "sk"

  attribute {
    name = "pk"
    type = "S"
  }

  attribute {
    name = "sk"
    type = "S"
  }

  attribute {
    name = "gsi1pk"
    type = "S"
  }

  attribute {
    name = "gsi1sk"
    type = "S"
  }

  # GSI1: Rapports par mois
  global_secondary_index {
    name            = "gsi1-month"
    hash_key        = "gsi1pk"
    range_key       = "gsi1sk"
    projection_type = "ALL"
  }

  # TTL pour expiration automatique des anciens rapports
  ttl {
    attribute_name = "ttl"
    enabled        = true
  }

  point_in_time_recovery {
    enabled = var.environment == "prod"
  }

  server_side_encryption {
    enabled = true
  }

  tags = merge(var.common_tags, {
    Name = "${local.name_prefix}-reports"
  })
}
