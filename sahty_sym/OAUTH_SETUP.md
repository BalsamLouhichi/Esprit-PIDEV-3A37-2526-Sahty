# Configuration de la Connexion Google OAuth2

## ✅ Étapes pour activer la connexion Google

### 1. Obtenir les identifiants Google
1. Accédez à [Google Cloud Console](https://console.cloud.google.com)
2. Créez un nouveau projet (ou sélectionnez un existant)
3. Activez l'API "Google+ API"
4. Créez des identifiants OAuth 2.0:
   - Type: Application Web
   - Origine autorisée: `http://127.0.0.1:8000`
   - URI de redirection: `http://127.0.0.1:8000/connect/google/check`

### 2. Configurer les variables d'environnement
Modifiez le fichier `.env.local` dans `sahty_sym/`:
```env
OAUTH_GOOGLE_CLIENT_ID=YOUR_CLIENT_ID_FROM_GOOGLE
OAUTH_GOOGLE_CLIENT_SECRET=YOUR_CLIENT_SECRET_FROM_GOOGLE
```

### 3. Vérifier la configuration
La configuration OAuth2 de Symfony est déjà dans `config/packages/knpu_oauth2_client.yaml`:
```yaml
knpu_oauth2_client:
    clients:
        google:
            type: google
            client_id: '%env(OAUTH_GOOGLE_CLIENT_ID)%'
            client_secret: '%env(OAUTH_GOOGLE_CLIENT_SECRET)%'
            redirect_route: connect_google_check
```

### 4. Vérifier le bouton dans le formulaire de login
Le bouton est présent dans `templates/securityL/login.html.twig`:
- ✅ Bouton "Connexion avec Google" visible
- ✅ Lien vers la route `connect_google`
- ✅ Style personnalisé avec l'icône Google

### 5. Fonctionnement du flux OAuth2
1. L'utilisateur clique sur "Connexion avec Google"
2. Il est redirigé vers Google pour l'authentification
3. Après connexion, Google le redirige vers `/connect/google/check`
4. Le contrôleur GoogleController:
   - Récupère les informations de l'utilisateur Google
   - Cherche l'utilisateur dans la base de données
   - Le crée s'il n'existe pas (rôle Patient par défaut)
   - L'authentifie
   - Le redirige vers la page d'accueil

### 6. Environnement de production
Pour la production, ajoutez l'URL de votre site:
```env
APP_URL=https://votresite.com
OAUTH_GOOGLE_CLIENT_ID=YOUR_PRODUCTION_CLIENT_ID
OAUTH_GOOGLE_CLIENT_SECRET=YOUR_PRODUCTION_CLIENT_SECRET
```
Et mettez à jour les URI autorisées dans Google Cloud Console.

## 📝 Fichiers modifiés
- `src/Controller/GoogleController.php` - Contrôleur OAuth2 complet
- `config/packages/security.yaml` - Routes OAuth publiques
- `templates/securityL/login.html.twig` - Bouton de connexion Google
- `.env.local` - Variables d'environnement

## ❓ Dépannage
- **Erreur "Invalid client"**: Vérifiez que client_id et client_secret sont corrects
- **Erreur de redirection**: Vérifiez que l'URI de redirection correspond exactement dans Google Cloud Console
- **Utilisateur non créé**: Vérifiez que la base de données est accessible
