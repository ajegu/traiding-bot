# Sécurité

## Points Critiques

1. **Ne jamais commiter les clés API** : Ajouter `.env` au `.gitignore`
2. **Utiliser HTTPS** en production
3. **Limiter les permissions API** : Pas de retrait
4. **Implémenter un système d'authentification** pour l'accès au dashboard
5. **Valider toutes les entrées utilisateur**
6. **Logger toutes les actions critiques**

## Permissions API Binance

| Permission | Statut | Raison |
|------------|--------|--------|
| Lecture | Activée | Lecture des données du compte |
| Trading Spot | Activée | Passage d'ordres |
| Retrait | Désactivée | Sécurité |

## Restrictions IP (Recommandé)

Limiter l'accès API uniquement à l'IP du serveur pour éviter tout accès non autorisé.

## Gestion des Erreurs

- Try-catch sur tous les appels API
- Logs détaillés avec `Log::error()`
- Enregistrement des trades en erreur dans la base
- Notifications en cas d'échec critique (optionnel)

## Variables d'Environnement Sensibles

```env
BINANCE_API_KEY=votre_cle_api
BINANCE_API_SECRET=votre_secret_api
```

Ces variables ne doivent JAMAIS être commitées dans le dépôt Git.
