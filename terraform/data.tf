# Récupérer l'ID du compte AWS courant
data "aws_caller_identity" "current" {}

# Récupérer la région courante
data "aws_region" "current" {}
