# Système de Gestion d'Opportunités avec Chatbot IA

## 📋 Description du Projet

Ce projet est une application web complète qui comprend :
- **Interface d'administration** pour gérer des opportunités (emplois, formations, etc.)
- **Chatbot IA intelligent** intégrant l'API Oreus/Alogo pour répondre aux questions sur les opportunités
- **Base de données MySQL** pour stocker les informations des opportunités
- **Système d'extraction de contenu** automatique depuis des URLs

## 🏗️ Architecture

### Fichiers Principaux

1. **admin.php** - Interface d'administration
   - Gestion complète des opportunités (CRUD)
   - Extraction automatisée de contenu depuis des URLs
   - Interface utilisateur moderne avec Tailwind CSS

2. **index.php** - Chatbot public
   - Interface conversationnelle avec sélection d'opportunités
   - Intégration avec l'API Oreus/Alogo pour des réponses IA
   - Fallback local en cas d'indisponibilité de l'API

### Base de Données

Table `opportunites` :
- `id` (INT, clé primaire)
- `nom` (VARCHAR) - Nom de l'opportunité
- `description_extract` (TEXT) - Description extraite
- `date_debut` (DATE) - Date de début
- `date_fin` (DATE) - Date limite
- `lien_postuler` (VARCHAR) - Lien pour postuler
- `infos_supp` (TEXT) - Informations supplémentaires
- `date_creation` (DATETIME) - Date de création

## 🚀 Installation

### Prérequis

- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Accès à l'API Oreus/Alogo (clé API requise)
- Serveur web (Apache, Nginx, etc.)

### Configuration

1. **Base de données** :
   - Créer une base de données MySQL
   - Modifier les constantes dans les fichiers PHP :
     ```php
     define('DB_HOST', 'votre_host');
     define('DB_NAME', 'votre_base');
     define('DB_USER', 'votre_utilisateur');
     define('DB_PASS', 'votre_mot_de_passe');
     ```

2. **API Oreus/Alogo** :
   - Obtenir une clé API sur Oreus
   - Configurer dans index.php :
     ```php
     define('OREUS_API_KEY', 'votre_clé_api');
     define('OREUS_API_URL', 'https://oreus-staging.dev2.dev-id.fr/api/v1/sdk/chat/completions');
     ```

3. **Upload** :
   - Placer les fichiers sur votre serveur web
   - S'assurer que PHP a les extensions PDO MySQL activées

## 🎯 Fonctionnalités

### Interface d'Administration (admin.php)

- ✅ **Gestion des opportunités** : Ajout, visualisation
- ✅ **Extraction automatique** : Depuis n'importe quelle URL
- ✅ **Interface responsive** : Adapté à tous les appareils
- ✅ **Statistiques** : Compteur d'opportunités actives/expirées
- ✅ **Navigation** : Lien vers le chatbot public

### Chatbot Public (index.php)

- ✅ **Sélection d'opportunités** : Interface visuelle intuitive
- ✅ **Intégration IA** : Réponses intelligentes via Oreus/Alogo
- ✅ **Fallback local** : Réponses prédéfinies si l'API échoue
- ✅ **Interface moderne** : Design gradient et animations
- ✅ **Responsive** : Fonctionne sur mobile et desktop

## 🔧 Technologies Utilisées

- **Backend** : PHP 7.4+, PDO MySQL
- **Frontend** : Tailwind CSS, JavaScript Vanilla
- **API** : Oreus/Alogo pour l'IA conversationnelle
- **Base de données** : MySQL
- **Librairies** : FontAwesome, Google Fonts

## 🛠️ Fonctionnement Technique

### Extraction de Contenu

La fonction `extractContentFromUrl()` dans admin.php :
1. Récupère le contenu HTML d'une URL via cURL
2. Nettoie le HTML (supprime scripts, styles, commentaires)
3. Extrait le texte avec `strip_tags()`
4. Formate pour une meilleure lisibilité
5. Limite à 5000 caractères

### Chatbot IA

Le chatbot utilise deux méthodes :
1. **Mode API** : Appel à l'API Oreus avec prompt contextuel
2. **Mode Fallback** : Réponses prédéfinies basées sur des mots-clés

### Structure du Prompt API

```text
INFORMATIONS SUR L'OPPORTUNITÉ:
[Informations détaillées]

QUESTION DE L'UTILISATEUR:
[Question de l'utilisateur]

INSTRUCTIONS:
[Instructions pour la réponse IA]
```

## 📱 Utilisation

### Pour les administrateurs

1. Accéder à `admin.php`
2. Extraire du contenu depuis une URL (optionnel)
3. Créer une opportunité avec les informations nécessaires
4. Visualiser et gérer les opportunités existantes

### Pour les utilisateurs

1. Accéder à `index.php`
2. Sélectionner une opportunité dans la liste
3. Poser des questions sur :
   - Dates limites
   - Prérequis
   - Processus de candidature
   - Informations générales

## 🔒 Sécurité

- **Validation des entrées** : Filtrage avec `filter_input()`
- **Protection XSS** : `htmlspecialchars()` pour l'affichage
- **Validation des URLs** : `FILTER_VALIDATE_URL`
- **Gestion des erreurs** : Try/catch pour les opérations critiques

## ⚡ Optimisations

- **Base de données** : Index automatique sur `id`
- **Frontend** : Lazy loading implicite, animations CSS
- **API** : Timeout configurable, gestion des erreurs
- **UI/UX** : Feedback visuel, validation en temps réel

## 🐛 Dépannage

### Problèmes courants

1. **Connexion base de données** :
   - Vérifier les identifiants MySQL
   - S'assurer que PDO MySQL est activé

2. **API Oreus** :
   - Vérifier la clé API
   - Tester la connexion réseau
   - Consulter les logs PHP

3. **Extraction de contenu** :
   - Vérifier que cURL est activé
   - S'assurer que l'URL est accessible

### Logs

Les erreurs sont loggées avec :
```php
error_log("Message d'erreur");
```

## 📊 Structure des Fichiers

```
/
├── admin.php          # Interface d'administration
├── index.php          # Chatbot public
├── README.md          # Documentation (ce fichier)
└── (base de données)  # Gérée automatiquement
```

## 🔮 Améliorations Futures

- [ ] Authentification administrateur
- [ ] Export des opportunités (CSV, PDF)
- [ ] Recherche/filtrage avancé
- [ ] Notifications par email
- [ ] API REST pour intégration tierce
- [ ] Dashboard avec graphiques

## 📝 Licence

Projet développé pour la gestion d'opportunités. Libre d'utilisation et modification.

## 👥 Support

Pour toute question ou problème :
1. Consulter la section Dépannage
2. Vérifier les logs PHP
3. Tester les connexions (DB, API)

