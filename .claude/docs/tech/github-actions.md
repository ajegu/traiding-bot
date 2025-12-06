# GitHub Actions - Conventions et Bonnes Pratiques

## Structure des Fichiers

```
.github/
├── workflows/
│   ├── ci.yml              # Tests et validation
│   ├── deploy-dev.yml      # Déploiement dev
│   ├── deploy-staging.yml  # Déploiement staging
│   ├── deploy-prod.yml     # Déploiement production
│   └── terraform-plan.yml  # Plan Terraform sur PR
└── actions/
    └── setup-php/          # Actions réutilisables (optionnel)
        └── action.yml
```

## Conventions de Nommage

### Fichiers Workflow

| Convention | Exemple |
|------------|---------|
| kebab-case | `deploy-prod.yml` |
| Nom descriptif | `ci.yml`, `terraform-plan.yml` |
| Préfixe par action | `deploy-`, `test-`, `build-` |

### Jobs et Steps

| Élément | Convention | Exemple |
|---------|------------|---------|
| Job ID | kebab-case | `run-tests`, `deploy-lambda` |
| Job name | Phrase descriptive | `Run PHPUnit Tests` |
| Step name | Verbe d'action | `Setup PHP`, `Run tests`, `Deploy to AWS` |

### Secrets

| Convention | Exemple |
|------------|---------|
| SCREAMING_SNAKE_CASE | `AWS_ACCESS_KEY_ID` |
| Préfixe par service | `AWS_`, `BINANCE_`, `SLACK_` |

#### Secrets Requis
```
AWS_ACCESS_KEY_ID
AWS_SECRET_ACCESS_KEY
AWS_REGION
BINANCE_API_KEY        # Pour les tests d'intégration (testnet)
BINANCE_API_SECRET
```

### Variables d'Environnement

| Convention | Exemple |
|------------|---------|
| SCREAMING_SNAKE_CASE | `PHP_VERSION`, `NODE_VERSION` |
| Préfixe par contexte | `TF_VAR_`, `APP_` |

## Workflows Recommandés

### CI (Continuous Integration)
- Déclenché sur : push et pull_request
- Jobs : lint, tests unitaires, tests d'intégration
- Validation du code avant merge

### Terraform Plan
- Déclenché sur : pull_request
- Affiche le plan Terraform en commentaire PR
- Permet la review des changements d'infrastructure

### Deploy Dev
- Déclenché sur : push vers `develop`
- Déploiement automatique
- Pas d'approbation requise

### Deploy Staging
- Déclenché sur : push vers `main`
- Déploiement automatique
- Tests de smoke post-déploiement

### Deploy Prod
- Déclenché sur : release publiée ou workflow manuel
- Approbation requise (environment protection)
- Rollback automatique si échec

## Bonnes Pratiques

### 1. Sécurité
- Utiliser uniquement les secrets GitHub (jamais en clair)
- Limiter les permissions avec `permissions:`
- Utiliser `environment:` pour les déploiements sensibles
- Activer les protection rules sur les environnements prod

### 2. Performance
- Utiliser le cache pour les dépendances (Composer, npm)
- Paralléliser les jobs indépendants
- Utiliser des matrices pour tester plusieurs versions

### 3. Fiabilité
- Définir des timeouts appropriés
- Implémenter des retries pour les opérations réseau
- Ajouter des health checks post-déploiement

### 4. Maintenabilité
- Versionner les actions utilisées (`@v4` plutôt que `@latest`)
- Documenter les workflows complexes
- Utiliser des actions composites pour le code réutilisable

### 5. Terraform
- Toujours exécuter `terraform plan` avant `apply`
- Stocker le plan en artifact pour review
- Utiliser des workspaces pour les environnements
- État distant sur S3 avec verrouillage DynamoDB

## Environnements GitHub

| Environnement | Protection | Usage |
|---------------|------------|-------|
| `dev` | Aucune | Tests et développement |
| `staging` | Aucune | Validation pré-prod |
| `prod` | Approbation requise | Production |

### Configuration des Protections (prod)
- Required reviewers : 1 minimum
- Wait timer : 5 minutes (optionnel)
- Restrict to specific branches : `main` uniquement

## Déploiement Bref/Lambda

### Étapes du Pipeline
1. Checkout du code
2. Setup PHP 8.4
3. Installation des dépendances (Composer)
4. Exécution des tests
5. Build des assets (si applicable)
6. Terraform init/plan/apply
7. Déploiement Lambda via Bref
8. Health check post-déploiement

### Artefacts
- Plan Terraform sauvegardé pour audit
- Logs de déploiement conservés
- Versions Lambda taguées

## Notifications

### Slack/Discord (recommandé)
- Notification sur échec de déploiement
- Notification sur déploiement prod réussi
- Résumé des tests échoués

### Configuration
- Webhook URL dans les secrets
- Messages formatés avec contexte (commit, auteur, environnement)

## Commandes Utiles

```bash
# Valider la syntaxe des workflows localement
gh workflow view ci.yml

# Lister les runs
gh run list

# Voir les logs d'un run
gh run view <run-id> --log

# Relancer un workflow
gh run rerun <run-id>

# Déclencher manuellement
gh workflow run deploy-prod.yml
```

## Règles Importantes

1. **Pas de secrets en clair** : Toujours utiliser `${{ secrets.X }}`
2. **Versionner les actions** : Éviter `@latest` ou `@main`
3. **Timeouts** : Définir des limites raisonnables
4. **Permissions minimales** : Utiliser le principe du moindre privilège
5. **Tests obligatoires** : Ne jamais déployer sans tests passants
6. **Revue Terraform** : Toujours reviewer le plan avant apply en prod
