# Trading Bot - Infrastructure principale
#
# Ce fichier sert de point d'entrée pour l'infrastructure.

# =============================================================================
# DynamoDB Tables
# =============================================================================
module "dynamodb" {
  source = "./modules/dynamodb"

  environment  = var.environment
  project_name = var.project_name
  common_tags  = local.common_tags
}
