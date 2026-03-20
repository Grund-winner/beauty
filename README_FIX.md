# 🔧 Corrections - Beauty Clinic Générateur

## Problèmes identifiés

### 1. ❌ Clé API Groq non configurée (ERREUR CRITIQUE)
**Symptôme** : `Erreur de connexion à l'API (HTTP 401)`

**Cause** : Le fichier `config.php` contient une clé placeholder (`gsk_votre_cle_api_ici`)

**Solution** :

#### Option A: Variable d'environnement Render (RECOMMANDÉ)
1. Allez sur votre dashboard Render : https://dashboard.render.com
2. Sélectionnez votre service `beauty-kodm`
3. Cliquez sur **Environment**
4. Ajoutez une variable :
   - **Key** : `GROQ_API_KEY`
   - **Value** : Votre vraie clé API Groq (commence par `gsk_`)
5. Cliquez sur **Save Changes**
6. Redéployez le service

#### Option B: Modifier config.php
1. Remplacez dans `config.php` :
```php
// AVANT
define('GROQ_API_KEY', 'gsk_votre_cle_api_ici');

// APRÈS
define('GROQ_API_KEY', 'gsk_votre_vraie_cle_api_ici');
```

**Obtenir une clé API gratuite** : https://console.groq.com/keys

---

### 2. ⚠️ .htaccess vide
**Cause** : Le fichier `.htaccess` était vide, ce qui peut causer des problèmes de routage.

**Solution** : Le nouveau fichier `.htaccess` contient :
- Redirection vers `index.php`
- Configuration PHP (upload size, timeout)
- Protection des fichiers sensibles
- Compression et cache

---

### 3. ⚠️ Gestion des erreurs améliorée
Le code original ne gérait pas bien les erreurs :
- Pas de message clair quand la clé API est manquante
- Pas de vérification des dossiers d'upload
- Téléchargement automatique pouvait échouer silencieusement

---

## 📁 Fichiers modifiés

| Fichier | Changements |
|---------|-------------|
| `config.php` | Support variable d'environnement, vérification clé API, création auto des dossiers |
| `.htaccess` | Configuration complète Apache/PHP |
| `index.php` | Gestion erreurs améliorée, messages clairs, téléchargement auto robuste |
| `diagnostic.php` | Nouveau fichier pour vérifier la configuration |

---

## 🚀 Déploiement sur Render

### Méthode 1: Git (Recommandée)

1. **Mettre à jour votre repo GitHub** :
```bash
git add .
git commit -m "Fix: API key config, htaccess, error handling"
git push origin main
```

2. **Render se mettra à jour automatiquement**

### Méthode 2: Manuelle

1. Téléchargez les fichiers modifiés
2. Allez sur https://dashboard.render.com
3. Sélectionnez votre service
4. Cliquez sur **Manual Deploy** → **Deploy latest commit**

---

## ✅ Vérification après déploiement

1. **Accédez au diagnostic** :
   ```
   https://beauty-kodm.onrender.com/diagnostic.php
   ```

2. **Vérifiez que** :
   - ✅ Clé API est configurée
   - ✅ Toutes les extensions PHP sont chargées
   - ✅ Les dossiers sont accessibles en écriture
   - ✅ La connexion API Groq fonctionne

3. **Testez le générateur** :
   - Remplissez le formulaire
   - Cliquez sur "Générer le produit"
   - Vérifiez que l'image se télécharge automatiquement

---

## 🔍 Codes d'erreur HTTP courants

| Code | Signification | Solution |
|------|---------------|----------|
| 401 | Clé API invalide | Vérifier la clé dans config.php ou variable d'environnement |
| 429 | Trop de requêtes | Attendre quelques secondes et réessayer |
| 500 | Erreur serveur | Vérifier les logs Render (Logs tab) |
| 404 | Page non trouvée | Vérifier le routage .htaccess |

---

## 📋 Checklist de configuration Render

### Variables d'environnement (Environment)
```
GROQ_API_KEY=gsk_votre_cle_api_groq
```

### Build Command (si nécessaire)
```bash
# Rien de spécial, le Dockerfile s'en charge
```

### Start Command (si nécessaire)
```bash
# Par défaut, Apache démarre automatiquement
```

---

## 🐛 Debugging

### Voir les logs Render
1. Dashboard Render → Votre service → **Logs**
2. Cherchez les erreurs PHP

### Activer le mode debug (développement uniquement)
Dans `config.php` :
```php
define('DEBUG_MODE', true);
```

### Tester l'API Groq manuellement
```bash
curl https://api.groq.com/openai/v1/models \
  -H "Authorization: Bearer gsk_votre_cle"
```

---

## 📞 Support

- **Groq API** : https://console.groq.com/docs
- **Render Docs** : https://render.com/docs
- **PHP cURL** : https://www.php.net/manual/fr/book.curl.php
