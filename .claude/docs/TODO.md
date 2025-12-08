# TODO - Trading Bot Binance

## Vue d'ensemble

Ce fichier permet de suivre l'avancement de l'implémentation du projet.

**Légende :**
- [ ] À faire
- [~] En cours
- [x] Terminé

---

## Phase 1 : Infrastructure AWS (Terraform)

- [x] **1.1** Backend Terraform (bucket S3 + table DynamoDB lock)
- [x] **1.2** Tables DynamoDB (trades, bot_config, reports)
- [ ] **1.3** SSM Parameter Store (clés API Binance, token Telegram)
- [ ] **1.4** SNS Topics (trade-alerts, error-alerts)
- [ ] **1.5** SQS Queues + DLQ (orders, notifications)
- [ ] **1.6** EventBridge Rules (cron 5min bot, cron daily report)
- [ ] **1.7** IAM Roles et Policies (Lambda execution)
- [ ] **1.8** Lambda Functions (Bref PHP runtime)

---

## Phase 2 : Application Laravel (Backend)

- [ ] **2.1** Setup Laravel + Bref + packages AWS
- [ ] **2.2** Configuration (config/bot.php, config/services.php)
- [ ] **2.3** Enums (OrderSide, OrderType, OrderStatus, Strategy)
- [ ] **2.4** DTOs (TradeResult, TradeStats, DailyReport)
- [ ] **2.5** Models/Repositories DynamoDB (Trade, BotConfig, Report)
- [ ] **2.6** BinanceService (prix, soldes, ordres market/limit)
- [ ] **2.7** Indicateurs techniques (RSI, MA50, MA200)
- [ ] **2.8** TradingStrategy (analyse signaux, exécution trades)
- [ ] **2.9** NotificationService (SNS publish, Telegram send)
- [ ] **2.10** TelegramService (formatage MarkdownV2, sendMessage)
- [ ] **2.11** ReportService (calcul P&L, génération rapport)
- [ ] **2.12** Commande bot:run (exécution stratégie)
- [ ] **2.13** Commande report:daily (rapport quotidien)

---

## Phase 3 : Dashboard Web (Frontend)

- [ ] **3.1** Routes web (/, /dashboard, /bot/*, /trades/*)
- [ ] **3.2** DashboardController (index, données compilées)
- [ ] **3.3** BotController (toggle, execute, status)
- [ ] **3.4** TradeController (index paginé, show détail)
- [ ] **3.5** Form Requests (validation strategy, symbol, amount)
- [ ] **3.6** Layout Blade + Tailwind (header, navigation)
- [ ] **3.7** Vue dashboard (prix, soldes, contrôles bot)
- [ ] **3.8** Vue historique trades (liste, filtres, pagination)
- [ ] **3.9** Composants UI (badges statut, indicateurs, alertes)

---

## Phase 4 : CI/CD (GitHub Actions)

- [ ] **4.1** Workflow CI (tests PHPUnit, lint PSR-12)
- [ ] **4.2** Workflow Terraform Plan (PR review)
- [ ] **4.3** Workflow Deploy Dev (push develop)
- [ ] **4.4** Workflow Deploy Prod (release avec approbation)

---

## Phase 5 : Tests et Documentation

- [ ] **5.1** Tests unitaires (RSI, MA, P&L calculation)
- [ ] **5.2** Tests intégration (BinanceService mock, DynamoDB local)
- [ ] **5.3** Tests Testnet Binance (ordres réels en sandbox)
- [ ] **5.4** README.md (installation, configuration, usage)

---

## Progression

| Phase | Progression | Statut |
|-------|-------------|--------|
| 1. Infrastructure AWS | 2/8 | En cours |
| 2. Application Laravel | 0/13 | Non commencé |
| 3. Dashboard Web | 0/9 | Non commencé |
| 4. CI/CD | 0/4 | Non commencé |
| 5. Tests & Docs | 0/4 | Non commencé |
| **Total** | **2/38** | **5%** |

---

## Notes

- Commencer par la Phase 1 (Infrastructure) pour avoir l'environnement AWS prêt
- La Phase 2 peut démarrer en parallèle pour le développement local
- Toujours tester sur Binance Testnet avant de passer en production
- Respecter les limites AWS Free Tier (voir `.claude/docs/tech/aws.md`)
