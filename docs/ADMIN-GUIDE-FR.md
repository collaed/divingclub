---
title: "DivingClub-Manager — Guide Administrateur"
author: "ClubCEP.eu"
lang: fr
date: "Mars 2026"
---

# Guide Administrateur — DivingClub-Manager

Guide complet pour les administrateurs (Bureau Master) du système de gestion de club de plongée.

---

## 1. Premiers pas après l'installation

### 1.1 Connexion initiale

Connectez-vous avec le compte administrateur créé lors de l'installation :

- URL : `https://votre-domaine.lu/login`
- Email : celui défini lors de l'installation
- Mot de passe : celui défini lors de l'installation

### 1.2 Configuration de l'identité du club

Allez dans **Administration → Paramètres → Identité du club** :

- Nom complet du club
- Code court (ex : CEP)
- Adresse postale, email, téléphone
- Coordonnées GPS du local
- IBAN et BIC pour les paiements SEPA
- Logo du club (upload)

### 1.3 Choix du thème

**Administration → Paramètres → Apparence** :

6 thèmes prédéfinis : Ocean (bleu marine), Coral (rouge), Lagoon (vert), Abyss (indigo), Tropical (cyan), Arctic (gris). Vous pouvez aussi personnaliser les couleurs individuellement.

### 1.4 Langues

**Administration → Paramètres → Langues** :

Activez/désactivez les langues parmi les 11 disponibles : anglais, français, allemand, luxembourgeois, portugais, italien, néerlandais, espagnol, polonais, hongrois, roumain.

---

## 2. Gestion des membres

### 2.1 Liste des membres

**Administration → Membres** affiche tous les membres avec :

- Nom, email, rôle, statut, fédération, niveau
- Indicateurs : certificat médical (✓/⚠/✗), profil complet (%)
- Filtres par rôle, statut, fédération
- Export CSV

### 2.2 Rôles

| Rôle | Slug | Accès |
|------|------|-------|
| Bureau Master | `bureau_master` | Accès total, paramètres, licence |
| Bureau | `bureau_member` | Administration courante |
| Instructeur | `instructor` | Calendrier disponibilité, bio |
| Assistant | `assistant` | Aide aux instructeurs |
| Membre | `member` | Fonctions de base |
| Invité | `guest` | Accès limité |

### 2.3 Statuts et cotisations

6 statuts avec multiplicateurs de cotisation :

| Statut | Multiplicateur |
|--------|---------------|
| Actif | 1.0× |
| Fonctionnaire | variable |
| Honoraire | réduit |
| Junior (<18 ans) | réduit |
| Famille | réduit |
| Membre de droit | 0× |

### 2.4 Vérification des certificats médicaux

1. Ouvrir le profil du membre → onglet **Certificat médical**
2. Télécharger et vérifier le document
3. Corriger le type si nécessaire (généraliste, ORL, médecine du sport)
4. Corriger la date d'examen si nécessaire
5. Cliquer **Vérifier** → le système calcule la date d'expiration selon les règles de la fédération

Règles automatiques : rappels à 30, 15, 7 et 0 jours avant expiration. Inscription aux événements bloquée si certificat expiré.

### 2.5 Export médical pour la fédération

**Administration → Membres → Export médical** :

- Sélectionner la fédération (ex : FFESSM)
- **Liste CSV** : fichier semicolonne avec nom, prénom, date de naissance, sexe, adresse, date d'examen
- **Certificats ZIP** : archive de tous les certificats, nommés `NOM Prénom Age Type.pdf`

---

## 3. Événements et saisons

### 3.1 Créer une saison

**Administration → Saisons → Créer** :

1. Année, date de début, date de fin
2. Ajouter des **schémas hebdomadaires** (ex : mardi 19h30 piscine Bonnevoie)
3. Ajouter les **vacances** (Toussaint, Noël, Carnaval, Pâques)
4. **Prévisualiser** → vérifier les dates générées
5. **Générer** → création automatique de tous les événements
6. **Activer** → cette saison devient la saison courante

### 3.2 Événements ponctuels

Créez des événements individuels pour les sorties, soirées, formations spéciales. Chaque événement peut avoir :

- Lieu avec lien Google Maps
- Limite de participants + liste d'attente
- Exigences de certification et profondeur max
- Site de plongée associé (avec données d'urgence)
- Option « fédéré » pour les partenariats inter-clubs

### 3.3 Fiche de sécurité FFESSM

Pour les sorties en milieu naturel, le système génère une **fiche de sécurité FFESSM 2024-2025** en PDF :

- 4 palanquées max (12-16 plongeurs)
- Colonnes : Pal, Mode, Prof, Rôle, Nom, Brevet, Féd, N° Licence, Aptitude, Méd, H.Imm, H.Sort, DTR, Obs
- Paramètres de plongée par palanquée : profondeur réelle, paliers 3/6/9m, arrêt sécu, GPS
- Bloc urgences : téléphone, VHF, hôpital + distance, caisson hyperbare + téléphone + distance
- Équipement de sécurité requis du site de plongée

---

## 4. Paiements

### 4.1 Calcul des cotisations

Le système calcule automatiquement : tarif de base × multiplicateur du statut × réduction d'âge + options.

### 4.2 Rapprochement bancaire

1. **Administration → Paiements → Rapprochement**
2. Coller le relevé bancaire
3. Le système fait un **matching flou** sur les communications structurées
4. Vérifier les correspondances → **Confirmer** ou **Ignorer**

### 4.3 QR codes SEPA

Chaque paiement génère un QR code EPC que le membre scanne avec son app bancaire → virement pré-rempli avec IBAN, montant et communication.

---

## 5. Contenu et communication

### 5.1 Articles

**Administration → Articles → Créer** :

- 13 types d'articles : actualité, sécurité, formation, sortie, biologie, matériel, etc.
- Éditeur de texte riche
- Image à la une + galerie d'images avec mise en page (pleine largeur, demi, tiers)
- Option public/privé (visible sans connexion ou réservé aux membres)
- Possibilité d'attacher un vote (pour les propositions de sortie)
- Commentaires filetés (3 niveaux max)

### 5.2 Traductions automatiques

Après publication d'un article en français :

1. Ouvrir l'article → cliquer **🌐 Générer les traductions**
2. Le système traduit automatiquement vers les 10 autres langues activées
3. Les traductions sont stockées en base → affichage instantané
4. Possibilité de modifier manuellement chaque traduction
5. Indicateur 🤖 sur les traductions automatiques

### 5.3 Emails

**Administration → Email** :

- Templates avec variables (`{{first_name}}`, `{{last_name}}`, etc.)
- 6 groupes de destinataires : tous les membres, par rôle, par statut, participants d'un événement
- Prévisualisation avant envoi
- Journal d'envoi complet

### 5.4 Bibliothèque de documents

**Administration → Documents** :

- Créer des dossiers (procès-verbaux, statuts, assurance, photos)
- Upload de fichiers avec visibilité public/privé
- Les membres accèdent aux documents publics via **Info → Documents**

---

## 6. Votes et élections

### 6.1 Sondage simple

- Sélection multiple autorisée
- Vote modifiable
- Résultats visibles en temps réel
- Intégrable dans un article (proposition de sortie)

### 6.2 Élection formelle

- Vote anonyme et irréversible
- Sélection unique
- Résultats cachés jusqu'à la clôture
- Tokens uniques par membre éligible
- Ouverture/fermeture automatique ou manuelle

---

## 7. Équipement

**Administration → Équipement** :

- Inventaire avec numéros de série, dates d'achat
- Prêts aux membres avec historique
- Maintenance programmée (ex : révision détendeur tous les 12 mois)
- Alertes quand la maintenance est due

---

## 8. Planification de plongée

### 8.1 Planificateur de palanquées

Interface Trello pour organiser les groupes de plongée :

- Glisser-déposer les plongeurs dans les palanquées
- 14 règles de binôme par fédération (FFESSM, LIFRAS, BSAC, PADI, etc.)
- Alertes : certificat médical expirant, niveau insuffisant pour la profondeur
- Suggestions automatiques basées sur les niveaux et fédérations

### 8.2 Sites de plongée

13 sites pré-configurés avec :

- Coordonnées GPS, profondeur max
- Données d'urgence : hôpital le plus proche + distance, caisson hyperbare + téléphone + distance
- Canal VHF, téléphone d'urgence
- Équipement de sécurité requis

---

## 9. Licence

### 9.1 Palier gratuit

Le système fonctionne gratuitement jusqu'à 100 membres. Au-delà, une clé de licence est nécessaire.

### 9.2 Installation de la licence

1. Obtenir une clé auprès du mainteneur du projet
2. **Administration → Paramètres → Licence**
3. Coller la clé → **Enregistrer**
4. Vérification : signature RSA, domaine, nombre de membres, date d'expiration

Voir le document `docs/LICENSE-PROCEDURE.md` pour la procédure complète de génération.

---

## 10. Import/Export de données de plongée

### 10.1 UDDF (Universal Dive Data Format)

Les membres peuvent importer leurs plongées depuis leur ordinateur de plongée au format UDDF 3.2.1. Le système extrait : profondeur, durée, température, paliers de décompression, arrêt de sécurité.

### 10.2 DAN DL7

Le bureau peut exporter toutes les plongées du club au format DAN DL7 (pipe-delimited) pour contribuer au programme de recherche DAN sur les accidents de décompression.

**Administration → Export DAN** → télécharge un fichier `.dl7` uploadable sur dan.org/PDE.

---

## 11. Sécurité et RGPD

### 11.1 Authentification

- Verrouillage après tentatives échouées (15 min)
- Vérification email obligatoire
- OAuth : Google, Facebook, Microsoft
- Impersonation pour le dépannage (bureau uniquement)

### 11.2 RGPD

- Page **Confidentialité** pour chaque membre : consentements, export JSON, demande d'effacement
- Anonymisation des données sur demande d'effacement
- Gestion des mineurs : lien tuteur, consentements parentaux

### 11.3 Journal d'audit

**Administration → Journal d'audit** :

- Filtres par modèle, action, date
- Détail avec diff champ par champ
- Export CSV
- Politique de rétention configurable (défaut : 24 mois)

---

## 12. Tâches planifiées

Configurer le cron sur le serveur :

```bash
* * * * * cd /chemin/vers/divingclub && php artisan schedule:run >> /dev/null 2>&1
```

| Fréquence | Tâche |
|-----------|-------|
| Quotidien 08h00 | Rappels certificats médicaux |
| Chaque minute | Ouverture/fermeture automatique des votes |
| Dimanche 03h00 | Sauvegarde hebdomadaire (4 dernières conservées) |

---

## 13. Dépannage

| Problème | Solution |
|----------|----------|
| Page blanche | `php artisan cache:clear && php artisan view:clear` |
| Emails non envoyés | Vérifier `MAIL_MAILER` dans `.env`, consulter `storage/logs/laravel.log` |
| Traductions manquantes | Cliquer 🌐 sur l'article ou attendre le cron horaire |
| Licence invalide | Voir section 9 et `docs/LICENSE-PROCEDURE.md` |
| Erreur 500 | Consulter `storage/logs/laravel.log` |
| QR code ne fonctionne pas | Vérifier IBAN et BIC dans Paramètres → Identité |
