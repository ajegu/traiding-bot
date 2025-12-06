terraform {
  backend "s3" {
    bucket         = "trading-bot-terraform-state-eu-west-3"
    key            = "state/terraform.tfstate"
    region         = "eu-west-3"
    encrypt        = true
    dynamodb_table = "trading-bot-terraform-lock"
  }
}
