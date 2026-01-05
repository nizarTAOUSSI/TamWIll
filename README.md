# 💰 TamWill - Crowdfunding Platform
**Projet Développement Web Full-Stack** - École Marocaine des Sciences de l'Ingénieur (EMSI) - Les Orangers  
**Année Universitaire :** 2025-2026

## 👥 Membres du Groupe 8
Ce projet a été réalisé par :

- **NIZAR TAOUSSI**
- **OTHMANE BAZ** 
- **ANOUAR ELACHGAR**

**Filière :** 3IIR  
**Campus :** EMSI Les Orangers

---

## 📋 Description du Projet

**TamWill** est une plateforme de financement participatif (crowdfunding) moderne permettant aux entrepreneurs et créateurs de présenter leurs projets et de collecter des fonds auprès de la communauté. La plateforme offre une interface intuitive pour les porteurs de projets et les contributeurs, ainsi qu'un tableau de bord administrateur complet pour la gestion.

### 🎯 Objectifs Principaux
- Faciliter le financement participatif pour les projets innovants
- Offrir une expérience utilisateur fluide et sécurisée
- Fournir des outils d'administration avancés
- Permettre une gestion complète des paiements et des versements

---

## 🛠️ Technologies Utilisées

### Backend
- **Framework :** Symfony 7.1
- **Langage :** PHP 8.2+
- **Base de données :** MySQL/MariaDB
- **ORM :** Doctrine
- **Authentification :** Symfony Security Component
- **Emails :** Symfony Mailer + Mailtrap

### Frontend
- **CSS Framework :** Tailwind CSS
- **JavaScript :** Vanilla JS + Chart.js
- **Template Engine :** Twig
- **Icons :** Heroicons

### Paiements & Services
- **Paiement :** Stripe API
- **Email Testing :** Mailtrap
- **Développement :** Symfony Local Server

---

## ✨ Fonctionnalités Principales

### 👤 Espace Utilisateur
- **Inscription/Connexion** sécurisée
- **Récupération de mot de passe** par email
- **Gestion de profil** avec photo
- **Création et gestion de projets**
- **Tableau de bord personnel**

### 💳 Système de Financement
- **Contributions** via Stripe
- **Suivi en temps réel** des montants collectés
- **Historique des contributions**
- **Système de commentaires** sur les projets

### 🔧 Administration Avancée
- **Dashboard administrateur** avec statistiques
- **Gestion des utilisateurs** (rôles, statuts)
- **Modération des projets**
- **Traitement des demandes de versement**
- **Confirmation automatique de paiements**
- **Envoi d'emails automatisés**

### 📊 Analytics & Reporting
- **Graphiques interactifs** (Chart.js)
- **Filtrage par dates** (aujourd'hui, semaine, mois, année)
- **Statistiques en temps réel**
- **Export et visualisation des données**

---

## 🚀 Installation et Configuration

### Prérequis
- PHP 8.2 ou supérieur
- Composer
- MySQL/MariaDB
- Node.js et npm (optionnel pour Tailwind)

### 1. Cloner le Projet
```bash
git clone [URL_DU_REPOSITORY]
cd TamWill
```

### 2. Installation des Dépendances
```bash
composer install
```

### 3. Configuration de l'Environnement
```bash
cp .env .env.local
```

Configurer les variables dans `.env.local` :
```env
DATABASE_URL="mysql://root:@127.0.0.1:3306/tamwill"
MAILER_DSN="smtp://username:password@sandbox.smtp.mailtrap.io:2525"
STRIPE_PUBLIC_KEY="pk_test_..."
STRIPE_SECRET_KEY="sk_test_..."
```

### 4. Base de Données
```bash
# Créer la base de données
php bin/console doctrine:database:create

# Exécuter les migrations
php bin/console doctrine:migrations:migrate

# Charger les données de test (optionnel)
php bin/console doctrine:fixtures:load
```

### 5. Démarrage du Serveur
```bash
php bin/console server:start
# ou
symfony server:start
```

L'application sera accessible sur `http://localhost:8000`

---

## 📱 Utilisation

### Pour les Porteurs de Projets
1. **Inscription** sur la plateforme
2. **Création d'un projet** avec description, objectif financier, images
3. **Publication** et partage du projet
4. **Suivi des contributions** via le tableau de bord
5. **Demande de versement** une fois l'objectif atteint

### Pour les Contributeurs
1. **Navigation** des projets disponibles
2. **Contribution financière** via Stripe
3. **Ajout de commentaires** et soutien aux projets
4. **Suivi des contributions** personnelles

### Pour les Administrateurs
1. **Accès au dashboard** administrateur (`/admin`)
2. **Modération** des projets et utilisateurs
3. **Traitement des demandes** de versement
4. **Analyse des statistiques** et performances

---

## 🔐 Sécurité

- **Protection CSRF** sur tous les formulaires
- **Validation côté serveur** de toutes les données
- **Hachage sécurisé** des mots de passe
- **Tokens de réinitialisation** avec expiration
- **Contrôle d'accès** basé sur les rôles
- **Sanitisation** des entrées utilisateur

---

## 🎨 Interface Utilisateur

L'interface utilise **Tailwind CSS** pour un design moderne et responsive :
- **Design responsive** adapté mobile/desktop
- **Thème sombre/clair** cohérent
- **Animations fluides** et interactions intuitives
- **Accessibility** respectée (WCAG 2.1)

---

## 📧 Système d'Emails

Templates professionnels pour :
- **Confirmation d'inscription**
- **Réinitialisation de mot de passe**
- **Notifications de contributions**
- **Confirmation de versements**

---

## 📈 Performance et Optimisation

- **Cache Symfony** optimisé
- **Requêtes BDD** optimisées avec Doctrine
- **Assets compilés** et minifiés
- **Images optimisées** et compression
- **CDN ready** pour la production

---

## 🤝 Contribution au Projet

Ce projet étant réalisé dans le cadre académique, les contributions externes ne sont pas acceptées. Cependant, les suggestions et retours sont les bienvenus.

---

## 📄 Licence

Ce projet est réalisé à des fins éducatives dans le cadre du cursus EMSI.

---

**Développé avec ❤️ par l'équipe du Groupe 8 - EMSI Les Orangers**
