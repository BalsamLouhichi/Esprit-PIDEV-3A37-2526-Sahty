# 📧 Configuration Email - Sahty System

## Status Actuel

✅ **DÉVELOPPEMENT (env: dev)** - Emails n'envoient pas vraiment (pour éviter les erreurs d'auth)
```
MAILER_DSN="null://null"
```

## Problème Identifié

Le compte Gmail `maramouerghi1234@gmail.com` refuse l'authentification avec le mot de passe app fourni.
**Code d'erreur:** `535-5.7.8 Username and Password not accepted`

## Solutions

### ✅ Solution 1 : Vérifier/Corriger les identifiants Gmail (Recommandé)

1. Aller à: https://myaccount.google.com/apppasswords
2. S'assurer que le compte a la **vérification en deux étapes activée**
3. Sélectionner `Mail` + `Windows`
4. Google génère un password de 16 caractères
5. **Copier le password SANS espaces**
6. Exemple: `"caoc szvo ppxr tnkv"` → `"caocszovppxrtnkv"`

### ✅  Solution 2 : Utiliser Mailtrap (Service de test gratuit)

1. S'inscrire à https://mailtrap.io (gratuit)
2. Créer un projet test
3. Copier les identifiants SMTP
4. Configurer dans `.env.local`:
```
MAILER_DSN="smtps://username:password@smtp.mailtrap.io:2525"
```

### ✅ Solution 3 : Utiliser un autre service d'email
- **SendGrid**
- **Brevo** (ex-Sendinblue)
- **AWS SES**
- **MailPace**

## Configuration pour Production

Créer/modifier `.env.prod` :

```dotenv
# Production avec Gmail
MAILER_DSN="smtps://maramouerghi1234@gmail.com:VotreMotDePasseCorrect@smtp.gmail.com:465"

# OU avec Mailtrap
MAILER_DSN="smtps://username:password@smtp.mailtrap.io:2525"
```

## Tester l'envoi d'email

```bash
php bin/console app:test-email votre@email.com
```

## Voir les logs

```bash
tail -f var/log/dev.log | grep -i email
```

## Notes de Sécurité

⚠️ **Jamais** stocker les identifiants directs dans le code!
- Utiliser des variables d'environnement
- Utiliser le fichier `.env.local` (non committé)
- En production: utiliser les secrets de la plateforme d'hosting

## Architecture Actuelle

- `EmailService` : Service centralisé pour tous les envois
- `ResponsableLaboratoireController` : Envoie les résultats d'analyses
- `PasswordResetController` : Envoie les emails de réinitialisation
- Transport en dev: `null://` (pas d'envoi réel)
- Transport en prod: À configurer selon votre choix

