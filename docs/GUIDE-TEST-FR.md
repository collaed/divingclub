# 🤿 Guide de Test — DivingClub-Manager (Staging)

## Accès au site

| | |
|---|---|
| **URL** | `https://divingclub.wasmer.app` |
| **Protection** | Le site demande un identifiant/mot de passe au premier accès |
| **Utilisateur** | `cep` |
| **Mot de passe** | `cep2026` |

> ⚠️ C'est un site de test. Aucun email réel ne sera envoyé. Toutes les données sont celles du CEP mais dans un environnement isolé.

---

## Vos comptes

Connectez-vous avec votre adresse email habituelle. Le mot de passe pour tout le monde est :

```
cep2026!
```

| Testeur | Email | Rôle |
|---------|-------|------|
| Keran Chaussard | kchaussard@tti-network.com | Bureau Technique |
| Nikolaos Dimisianos | nidimus@gmail.com | Membre |
| Michel Brochard | michel.brochard@mac.com | Bureau Master |
| Mafalda Collart | m.collart@eib.org | Bureau Finance |
| Lilian Godfrin | lilian.godfrin@gmail.com | Bureau |
| Roger Kraemer | rogerk210@gmail.com | Membre |
| Etienne Coupez | etienne.coupez@gmail.com | Bureau Master |

Vous pouvez aussi tester la connexion via **Google**, **Microsoft** ou **Facebook** (boutons sur la page de login).

---

## Quoi tester ?

Explorez le site librement et notez tout ce qui vous semble bizarre, cassé, moche, ou manquant. Voici quelques pistes selon votre rôle :

### Tout le monde 🧑‍🤝‍🧑

- [ ] Se connecter (email + mot de passe, ou via Google/Microsoft/Facebook)
- [ ] Consulter et modifier votre profil (photo, contacts d'urgence, téléphone)
- [ ] Parcourir le calendrier des événements
- [ ] S'inscrire / se désinscrire d'un événement
- [ ] Lire les articles (changer de langue via les onglets)
- [ ] Consulter les sites de plongée (carte, météo, documents de sécurité)
- [ ] Tester le calculateur de cotisation
- [ ] Consulter la bibliothèque de documents
- [ ] Changer la langue du site (menu en haut à droite)
- [ ] Tester sur téléphone (le site est responsive)

### Membres du Bureau 👔 (Keran, Michel, Mafalda, Lilian, Etienne)

- [ ] Accéder au tableau de bord admin (menu Admin)
- [ ] Consulter la liste des membres
- [ ] Créer un événement test
- [ ] Envoyer un email test (Admin → Email) — aucun email réel ne part
- [ ] Consulter le journal des emails envoyés
- [ ] Gérer les documents (bibliothèque Bureau)
- [ ] Créer/modifier un article
- [ ] Lancer un vote test
- [ ] Consulter les sauvegardes (Admin → Sauvegardes)
- [ ] Parcourir le guide admin (Admin → Guide)
- [ ] Vérifier les certificats médicaux des membres
- [ ] Tester la réconciliation bancaire (Admin → Paiements)

### Membres simples 🤿 (Nikolaos, Roger)

- [ ] Vérifier que vous ne voyez PAS les menus admin
- [ ] Tester l'inscription aux événements
- [ ] Poster une petite annonce (Classifieds)
- [ ] Commenter un article
- [ ] Participer à un vote (si un vote est ouvert)
- [ ] Exporter vos données personnelles (RGPD)

---

## Comment remonter vos retours ?

Pour chaque problème ou suggestion, notez :

1. **Quoi** — ce que vous avez fait (ex: « j'ai cliqué sur Inscription »)
2. **Résultat** — ce qui s'est passé (ex: « page blanche », « erreur 500 », « texte en anglais »)
3. **Attendu** — ce que vous attendiez (ex: « confirmation d'inscription »)
4. **Écran** — une capture d'écran si possible (sur téléphone : bouton power + volume bas)

Envoyez vos retours par email ou WhatsApp — un simple message avec vos notes suffit.

---

## Points importants

- 🔒 **Aucun email réel** n'est envoyé — tout est intercepté et visible dans Admin → Email
- 🔒 **Données de test** — ce sont vos vraies données CEP mais dans un bac à sable isolé
- 🔒 **Le site est protégé** par un mot de passe global (`cep` / `cep2026`) — il n'est pas public
- 🌐 **OAuth** — Google, Microsoft et Facebook sont configurés pour le test
- 📱 **Mobile** — testez aussi depuis votre téléphone, le site est conçu pour

---

## En cas de problème technique

Si le site ne répond plus ou affiche une erreur grave, contactez l'administrateur. Ne vous inquiétez pas — c'est un environnement de test, rien ne peut casser en production.

---

Merci pour votre aide ! Vos retours sont précieux pour améliorer l'outil avant la mise en production. 🙏
