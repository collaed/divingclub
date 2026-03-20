---
title: "DivingClub-Manager — Guide Administrateur"
author: "ClubCEP.eu"
lang: fr
date: "Mars 2026"
---

# Guide Administrateur — DivingClub-Manager

Guide complet pour les administrateurs (Bureau Master) du système de gestion de club de plongée.

---

## 1. Vue d'ensemble

Bienvenue dans le système d'administration DivingClub — la plateforme de gestion pour votre club de plongée.

### Ce que le système gère

| Module | Description |
|--------|-------------|
| 👥 Membres | Profils, rôles, statuts, licences multi-fédérations, niveaux de certification, gestion tuteur/mineur |
| 📅 Événements | Calendrier, saisons récurrentes, inscriptions, listes d'attente, groupes WhatsApp, photos d'événements |
| 🫧 Palanquées | Planificateur de groupes, 39 règles sur 5 fédérations, validation multi-niveaux |
| 🏥 Médical | Suivi des certificats par fédération, rappels automatiques, blocage d'inscription |
| 💰 Paiements | Calcul des cotisations, rapprochement bancaire avec matching IBAN, QR codes SEPA |
| 🤿 Équipement | Inventaire, prêts, planification de maintenance |
| 📰 Contenu | Articles typés, traduction auto, galeries d'images, commentaires, petites annonces, bibliothèque de documents |
| ✉️ Email | Templates, ciblage par groupe, bilingue, journal d'envoi |
| 🗳️ Votes | Sondages simples et élections anonymes, multi-sélection, résultats publics |
| 🤝 Partenariats | API inter-clubs, échange de clés symétriques, inscriptions croisées |
| 📱 Réseaux sociaux | Publication auto Facebook avec triple vérification RGPD |
| 🔒 RGPD | Gestion des consentements, consentement parental pour mineurs, export de données, effacement |
| 📋 Journal d'audit | Historique complet des modifications, vue diff, export CSV, politique de rétention |
| 📊 Tableau de bord | Statistiques, liste de tâches bureau, exports CSV |

### Rôles

| Rôle | Slug | Accès |
|------|------|-------|
| Bureau Master | `bureau_master` | Admin complet — tous les paramètres, membres, finances, équipement, communications |
| Bureau | `bureau_member` | Accès bureau en lecture |
| Instructeur | `instructor` | Gérer les événements qu'il encadre, voir les participants |
| Assistant | `assistant` | Assister aux événements |
| Membre | `member` | Standard — profil, événements, documents |
| En attente | `pending` | En attente d'approbation après inscription |

### Statuts des membres (slugs français)

| Statut | Slug | Multiplicateur | Notes |
|--------|------|---------------|-------|
| Actif | `actif` | 1.00 | Membre actif standard |
| Fonctionnaire | `fonctionnaire` | 1.00 | Fonctionnaire |
| Honoraire | `honoraire` | 0.00 | Toujours gratuit |
| Junior | `junior` | 0.50 | Moins de 18 ans |
| Famille | `famille` | 0.75 | Adhésion familiale |
| Membre de droit | `membre_de_droit` | 0.00 | Membre de droit |

### Fédérations supportées

11 fédérations configurées avec 105 niveaux de certification : FFESSM, LIFRAS, FLASSA, NELOS, VDST, PADI, SSI, UCPA, BSAC, NASDS et CMAS.

---

## 2. Premiers pas après le déploiement

Après le déploiement de DivingClub, l'**assistant d'installation** apparaît automatiquement à la première visite. Il permet de choisir :

- **SQLite** — zéro configuration, fichier unique, idéal pour les petits clubs et déploiements Wasmer/cloud (100 Mo gratuit)
- **MySQL / MariaDB** — pour les déploiements multi-clubs plus importants

L'assistant crée la base de données, exécute les migrations, insère les données de référence (fédérations, niveaux de certification, règles de plongée) et crée votre compte administrateur.

### 2.1 Configuration post-installation

Éditez `.env` sur le serveur :

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.lu

# Mail — utiliser un vrai SMTP en production
MAIL_MAILER=smtp
MAIL_HOST=smtp.votre-fournisseur.com
MAIL_PORT=587
MAIL_USERNAME=noreply@divingclub.eu
MAIL_PASSWORD=votre_mot_de_passe_mail
MAIL_ENCRYPTION=tls

# Spécifique au club
CLUB_IBAN=LU00 0000 0000 0000 0000
CLUB_ID=MONCLUB
FEDERATION_SALT=changer_ceci_par_une_chaine_aleatoire

# Mode staging (optionnel — active la boîte mail de test)
# STAGING_MODE=true
# STAGING_USER=staging        ← laisser vide pour désactiver l'auth HTTP Basic
# STAGING_PASS=changeme
```

### 2.2 Configurer les clés API

Voir la section 14 (Clés API et OAuth) pour les instructions détaillées.

### 2.3 Configurer les fédérations

Allez dans Paramètres → Fédérations. 11 fédérations sont pré-configurées avec 105 niveaux de certification. Ajoutez ou modifiez selon vos besoins.

### 2.4 Configurer les règles de conformité médicale

Paramètres → Règles de conformité médicale. Six règles sont pré-insérées pour FFESSM et LIFRAS. Ajustez les périodes de validité et tranches d'âge selon votre fédération.

### 2.5 Configurer les composantes de cotisation

Paiements → Composantes de cotisation. Créez une cotisation de base et des composantes optionnelles (assurance, double affiliation).

Formule : `final = (base × multiplicateur_statut × (1 − réduction_âge)) + options`

- Âge < 18 → réduction 50%
- Âge ≥ 65 → réduction 25%
- Statut honoraire → toujours 0€

### 2.6 Créer la première saison

Saisons → Créer avec dates de début/fin, ajouter des schémas hebdomadaires, ajouter les vacances, puis générer les événements.

### 2.7 Configurer les règles de maintenance équipement

Paramètres → Règles de maintenance. Définir les intervalles par type d'équipement (ex : « Révision détendeur tous les 12 mois »).

### 2.8 Personnaliser le thème

Paramètres → Thème et apparence. Choisir un preset (Ocean, Coral, Lagoon, Abyss, Tropical, Arctic) ou définir des couleurs personnalisées. Uploader un logo.

### 2.9 Configurer les tâches planifiées (Cron)

Les tâches planifiées gèrent les rappels médicaux, l'ouverture/fermeture auto des votes, la traduction d'articles et les sauvegardes hebdomadaires.

**Option A : Cron serveur (VPS / dédié)**

```bash
* * * * * cd /chemin/vers/projet && php artisan schedule:run >> /dev/null 2>&1
```

**Option B : Hébergement mutualisé sans accès cron**

Utilisez le service gratuit cron-job.org pour pinger votre site toutes les 15 minutes :

1. Définir une clé secrète dans `.env` : `CRON_KEY=votre-secret-aleatoire`
2. Créer un compte gratuit sur cron-job.org
3. Ajouter un cron job : URL `{APP_URL}/cron/run?key=votre-secret`, toutes les 15 min, méthode GET
4. Tester : visiter l'URL dans le navigateur — vous devriez voir `OK` suivi de la date/heure

Alternatives : UptimeRobot (gratuit, toutes les 5 min) ou tout service capable de pinger une URL.

### 2.10 Changer le mot de passe par défaut

Admin par défaut : `admin@divingclub.eu` / `password`. **Changer immédiatement** via la page de profil.

> **Sécurité :** Assurez-vous que `APP_DEBUG=false` en production et changez tous les mots de passe par défaut.


---

## 3. Gestion des membres

### 3.1 Liste des membres

Administration → Membres affiche tous les utilisateurs enregistrés. La liste montre l'avatar, le nom, l'email, le rôle, le statut et le badge de conformité médicale.

### 3.2 Modifier un membre

Cliquez sur un nom pour voir/modifier le profil complet. En tant que Bureau Master, vous pouvez modifier tous les onglets :

- **Info** — nom, nationalité, coordonnées, contact d'urgence
- **Privé** — adresse, date de naissance (visible uniquement par le Bureau)
- **Plongée** — niveaux de certification (depuis la table de référence), nombre de plongées, inscriptions aux formations
- **Médical** — upload/vérification des certificats médicaux, statut de conformité
- **Licences** — numéros de licence fédérale et suivi des demandes
- **Documents** — scans de pièce d'identité, assurance, autres uploads
- **Emails** — gérer jusqu'à 5 adresses email par membre
- **Équipement** — voir les prêts en cours

### 3.3 Niveaux de certification

Le système inclut 105 niveaux de certification sur 11 fédérations. Les membres peuvent ajouter plusieurs certifications et en marquer une comme « principale ». Le système apprend les préférences d'affichage via le compteur `display_priority`.

### 3.4 Rôles et statuts

Changer le rôle d'un membre via la liste des membres (menu déroulant). Changer le statut via son profil. Les multiplicateurs de cotisation sont liés aux statuts — voir Paramètres.

### 3.5 Impersonation

Le Bureau Master peut se faire passer pour n'importe quel membre pour voir le système de son point de vue. Cliquer « Impersonner » dans la liste des membres. Un bandeau jaune s'affiche pendant l'impersonation avec un lien « Arrêter ». Toutes les actions d'impersonation sont journalisées.

### 3.6 Auto-inscription

Les nouveaux utilisateurs s'inscrivent sur `/register` et obtiennent le rôle `pending`. Le Bureau Master doit changer leur rôle en `member` (ou autre) pour accorder l'accès.

---

## 4. Conformité médicale

### 4.1 Fonctionnement

La conformité médicale est évaluée selon les règles de chaque fédération. Chaque règle définit : fédération, tranche d'âge, type de certificat et validité en mois.

### 4.2 Types de certificats

| Type | Description |
|------|-------------|
| `gp` | Certificat de médecin généraliste |
| `ent` | Certificat ORL |
| `cardio` | Certificat de cardiologue |
| `ophthalmologist` | Certificat d'ophtalmologue |
| `other` | Autre certificat médical |

### 4.3 Évaluation de la conformité

Quand un membre uploade un certificat médical :

1. Le système trouve toutes les règles correspondant à la/les fédération(s) et l'âge du membre
2. Utilise la période de validité **la plus restrictive**
3. Calcule la date d'expiration à partir de la date d'examen
4. Définit le statut : conforme / expire bientôt (30 jours) / expiré

### 4.4 Badges de conformité

- ✅ **Conforme** — certificat valide en dossier
- ⚠️ **Expire bientôt** — expire dans les 30 jours
- ❌ **Non conforme** — expiré ou pas de certificat

Les badges apparaissent sur : l'en-tête du profil, l'onglet médical, la liste admin des membres, la liste des participants aux événements.

### 4.5 Blocage d'inscription

Les membres avec un statut médical non conforme ne peuvent pas s'inscrire aux événements de piscine, plongée ou formation. Les événements sociaux sont exemptés.

### 4.6 Rappels automatiques

Quotidiennement à 08h00, le système envoie des rappels par email à 30, 15, 7 et 0 jours avant l'expiration du certificat. Chaque rappel n'est envoyé qu'une fois (suivi via les colonnes `reminder_*_sent_at`).

### 4.7 Vérification des certificats

Le Bureau Master peut vérifier les certificats uploadés en cliquant « Vérifier » dans l'onglet médical. Cela marque le document comme vérifié dans le journal d'audit.

### 4.8 Gestion des règles

Paramètres → Règles de conformité médicale. Règles pré-insérées :

- FFESSM : GP 12 mois (tous âges), ORL 12 mois (40+)
- LIFRAS : GP 12 mois (<40), GP 6 mois (40+), Cardio 24 mois (50+)

### 4.9 Export médical pour la fédération

Administration → Membres → Export médical :

- Sélectionner la fédération (ex : FFESSM)
- **Liste CSV** : fichier semicolonne avec nom, prénom, date de naissance, sexe, adresse, date d'examen
- **Certificats ZIP** : archive de tous les certificats, nommés `NOM Prénom Age Type.pdf`

---

## 5. Paramètres

### 5.1 Fédérations

Ajouter/modifier/supprimer les fédérations de plongée. Pré-configurées : FFESSM, LIFRAS, FLASSA, NELOS, VDST, PADI, SSI, UCPA, BSAC, NASDS, CMAS. Chaque fédération a des niveaux de certification (insérés automatiquement).

### 5.2 Statuts des membres et multiplicateurs

Chaque statut a un slug (utilisé dans le code) et un multiplicateur de cotisation appliqué au tarif de base.

### 5.3 Règles de conformité médicale

Définir quels certificats médicaux sont requis par fédération, tranche d'âge et type. Le système utilise la règle la plus restrictive quand plusieurs s'appliquent.

### 5.4 Règles de maintenance équipement

Définir les calendriers de maintenance par type d'équipement. Les règles obligatoires affectent la disponibilité de l'équipement.

### 5.5 Thème et apparence

Personnaliser l'apparence de toute l'application :

- **Presets** — thèmes en un clic : Ocean (bleu marine par défaut), Coral (rouge), Lagoon (vert), Abyss (bleu profond), Tropical (cyan), Arctic (gris)
- **Couleurs personnalisées** — primaire, secondaire, accent, dégradé d'en-tête, fond de pied de page
- **Fonds d'articles par type** — couleur de fond distincte pour chaque type d'article (actualité, sécurité, formation, etc.)
- **Branding** — emoji logo, texte, nom complet du club
- **Mise en page** — largeur (normal/large/extra-large/plein), animation de bulles dans l'en-tête
- **Upload de logo** — logo image personnalisé

Les changements prennent effet immédiatement (cache de 5 minutes).

### 5.6 Identité du club

Nom complet du club, code court, email, adresse, téléphone, pays, et emplacement du local/entrepôt avec coordonnées GPS. Apparaît sur les pages publiques, emails, QR codes et communications de paiement.

### 5.7 Banque (IBAN / SEPA)

Entrer l'IBAN et le BIC du club. Utilisé pour générer les QR codes EPC SEPA sur le calculateur de cotisation et les pages de paiement.

### 5.8 Langues

Activer/désactiver les langues parmi les 11 disponibles. Configurable lors de l'installation et modifiable ensuite.


---

## 6. Événements et saisons

### 6.1 Saisons

Une saison représente une année de club (ex : septembre 2025 – juin 2026). Administration → Saisons.

1. **Créer** une saison avec nom, année, dates de début/fin
2. **Ajouter les vacances** — dates sans événements (vacances de Noël, scolaires, etc.)
3. **Ajouter des schémas hebdomadaires** — événements récurrents (ex : « Piscine chaque mercredi 19h00–21h00 à Bonnevoie »)
4. **Prévisualiser** — voir toutes les dates qui seraient générées
5. **Générer** — crée les événements individuels à partir des schémas, en sautant les vacances

Numérotation des jours : 0=Lundi, 1=Mardi, ..., 6=Dimanche.

### 6.2 Événements

Les événements apparaissent sur le Calendrier (vues mois/semaine/jour). Types avec couleurs par défaut :

| Type | Couleur | Usage |
|------|---------|-------|
| pool | 🔵 Bleu | Entraînements piscine |
| dive | 🔵 Marine | Plongées en milieu naturel |
| training | 🟢 Vert | Formations pratiques/théoriques |
| theory | 🟣 Violet | Sessions en salle |
| social | 🟡 Jaune | Événements sociaux, fêtes |

### 6.3 Inscriptions et liste d'attente

- Définir `max_participants` pour activer les limites de capacité
- Activer `waiting_list` — quand c'est plein, les nouvelles inscriptions vont en liste d'attente
- Quand quelqu'un annule, la première personne en liste d'attente est promue automatiquement
- La conformité médicale est vérifiée à l'inscription pour les événements piscine/plongée/formation

### 6.4 Groupes WhatsApp

Chaque événement (et schéma de saison) peut avoir une URL de groupe WhatsApp. Le lien apparaît comme un bouton vert « Rejoindre le groupe WhatsApp » sur la page de l'événement. Quand défini sur un schéma de saison, tous les événements générés héritent du lien.

### 6.5 Acomptes

Les événements peuvent avoir jusqu'à 3 échéances d'acompte avec dates et montants. Ceux-ci génèrent des enregistrements payment_expected pour chaque participant inscrit.

### 6.6 Fiche de sécurité FFESSM

Pour les sorties en milieu naturel, le système génère une fiche de sécurité FFESSM 2024-2025 en PDF :

- 4 palanquées max (12-16 plongeurs)
- Colonnes : Pal, Mode, Prof, Rôle, Nom, Brevet, Féd, N° Licence, Aptitude, Méd, H.Imm, H.Sort, DTR, Obs
- Paramètres de plongée par palanquée : profondeur réelle, paliers 3/6/9m, arrêt sécu, GPS
- Bloc urgences : téléphone, VHF, hôpital + distance, caisson hyperbare + téléphone + distance
- Équipement de sécurité requis du site de plongée

---

## 7. Paiements

### 7.1 Calcul des cotisations

Formule : `final = (base × multiplicateur_statut × (1 − réduction_âge)) + composantes_optionnelles`

| Facteur | Valeur |
|---------|--------|
| Âge < 18 | Réduction 50% |
| Âge ≥ 65 | Réduction 25% |
| Statut honoraire | Toujours 0€ |
| Multiplicateur statut | Configuré par statut dans Paramètres |

### 7.2 Composantes de cotisation

Paiements → Composantes. Créer :

- Une composante **de base** (ex : « Cotisation de base 120€ »)
- Des composantes optionnelles : niveaux d'assurance, double affiliation, etc.

Les composantes peuvent être liées à une saison spécifique ou indépendantes (s'appliquent à toutes).

### 7.3 Génération des cotisations

Depuis la page Paiements, sélectionner un membre et cliquer « Générer la cotisation ». Le système calcule le montant et crée un enregistrement `payment_expected` avec une communication structurée unique :

```
CLUB-2026-42-DUPONT MARIE+assurance_standard
```

Cette chaîne est utilisée pour le rapprochement bancaire.

### 7.4 Rapprochement bancaire

1. Paiements → Rapprochement
2. **Importer** — coller le texte du relevé bancaire (format : `date;montant;communication;contrepartie` par ligne)
3. **Auto-matching** — le système fait un matching flou des transactions contre les paiements attendus : communication (+80), montant (+20), nom de famille (+30), IBAN (+50). Score 0–100, seuil 60 pour auto-match
4. **Vérifier** — confirmer les correspondances correctes, ignorer les faux positifs
5. Les correspondances confirmées mettent à jour le statut de paiement à « payé »

### 7.5 QR codes SEPA

Les membres peuvent générer un QR code SEPA EPC pour leur paiement. Le scanner dans une app bancaire pré-remplit le virement avec IBAN, montant et communication. Configurer `CLUB_IBAN` dans `.env`.

---

## 8. Contenu et CMS

### 8.1 Types d'articles

13 types d'articles disponibles, chacun avec une icône et une couleur de fond configurable dans Paramètres → Apparence → Fonds d'articles par type.

### 8.2 Créer un article

Administration → Articles → Créer. Définir le titre, le type, le corps (texte riche), l'image à la une et les images de galerie. Basculer Publié et Public (visible sans connexion).

### 8.3 Traduction automatique

Cliquer « 🌐 Générer les traductions » sur n'importe quel article pour traduire automatiquement vers toutes les langues configurées. Le planificateur traduit aussi un article non traduit par heure automatiquement. Les traductions apparaissent comme onglets sur la page de l'article avec un indicateur 🤖.

### 8.4 Commentaires

Les membres authentifiés peuvent commenter les articles. Les commentaires sont filetés (jusqu'à 3 niveaux). Les auteurs et le bureau peuvent supprimer les commentaires.

### 8.5 Petites annonces

Les membres peuvent publier des annonces d'achat/vente via la page Petites annonces. Les annonces expirent après 30 jours et peuvent être prolongées.

### 8.6 Bibliothèque de documents

Administration → Bibliothèque de documents. Créer des dossiers, uploader des fichiers (PDF, images, documents). Marquer les fichiers comme Publics (visibles par tous les membres via Info → Documents) ou Privés (bureau uniquement).

---

## 9. Email

### 9.1 Templates

Administration → Email. Créer des templates réutilisables avec des variables :

| Variable | Remplacée par |
|----------|--------------|
| `{{first_name}}` | Prénom du membre |
| `{{last_name}}` | Nom du membre |
| `{{name}}` | Nom complet |
| `{{email}}` | Email principal |
| `{{club_name}}` | Nom du club depuis les paramètres |

### 9.2 Ciblage par groupe

Sélectionner un groupe de destinataires lors de l'envoi :

- **Tous les membres** — tout le monde dans le système
- **Membres actifs** — statut = actif
- **Instructeurs** — rôle = instructor
- **Bureau** — rôles bureau_master + bureau_member
- **Certificats expirants** — certificat médical expirant dans les 30 jours
- **Cotisations impayées** — enregistrements payment_expected en attente

### 9.3 Envoi

1. Sélectionner un template (ou écrire un sujet/corps personnalisé)
2. Choisir le groupe cible
3. Cliquer « Prévisualiser » pour voir l'email rendu avec des données d'exemple
4. Cliquer « Envoyer » — les emails sont mis en file d'attente et envoyés (3 tentatives)

### 9.4 Journal d'envoi

Tous les emails envoyés sont journalisés avec : destinataire, sujet, statut (en file/envoyé/échoué), message d'erreur, horodatage.


---

## 10. Votes et élections

### 10.1 Deux modes de vote

| Mode | Comportement |
|------|-------------|
| **Simple** | Le votant peut changer son choix jusqu'à la clôture. Le token est stocké sur le bulletin (auditable). |
| **Élection** | Anonyme et irréversible. Une fois voté, le bulletin est stocké avec `token_hash=NULL` — le lien entre votant et bulletin est définitivement rompu. |

### 10.2 Options de vote

| Option | Effet |
|--------|-------|
| **Sélection multiple** | Les votants peuvent sélectionner plus d'une option (style approbation). Cases à cocher au lieu de boutons radio. |
| **Changement autorisé** | Les votants peuvent re-soumettre jusqu'à la clôture. Activé par défaut en mode simple. Ignoré pour les élections (toujours irréversible). |
| **Résultats publics** | Résultats en direct (barres de progression avec pourcentages) visibles par les votants. |

### 10.3 Créer un vote

1. Administration → Votes → Créer
2. Définir titre, description, mode (simple/élection), dates d'ouverture/fermeture
3. Basculer multi-sélection, modifiable et résultats publics selon les besoins
4. Ajouter les options (minimum 2)
5. Enregistrer

Les votes peuvent aussi être attachés à des articles de proposition de sortie — le vote est alors intégré directement dans la page de l'article.

### 10.4 Génération de tokens

Cliquer « Générer les tokens » sur la page de détail du vote. Cela crée un token unique de 128 caractères par membre éligible. Les tokens sont envoyés via le système d'email — chaque membre reçoit un lien comme :

```
https://votre-domaine.lu/vote/abc123...xyz
```

Aucune connexion n'est requise pour voter — le token est l'authentification.

### 10.5 Ouverture/fermeture automatique

Le planificateur ouvre automatiquement les votes à leur heure `opens_at` et les ferme à `closes_at`. Vous pouvez aussi ouvrir/fermer/annuler manuellement depuis la page admin.

---

## 11. Équipement

### 11.1 Inventaire

Administration → Équipement. Suivre tout l'équipement du club avec :

- Nom, type (gilet, détendeur, bloc, combinaison, masque, palmes, ordinateur, autre)
- Numéro de série, date d'achat, état
- Statut : disponible, en prêt, maintenance requise, retiré

### 11.2 Planification de maintenance

Quand un équipement est créé, le système génère automatiquement les tâches de maintenance à partir des règles définies dans Paramètres. Quand une tâche est complétée, le système planifie automatiquement la suivante. Si une maintenance obligatoire est en retard, le statut passe à `maintenance_required` et l'équipement ne peut pas être prêté.

### 11.3 Gestion des prêts

1. Aller sur la page de détail d'un équipement
2. Cliquer « Prêter » et sélectionner un membre
3. Le statut passe à `on_loan`
4. Au retour, cliquer « Retourner » — le statut revient à `available` (ou `maintenance_required` si en retard)

---

## 12. Planificateur de palanquées

### 12.1 Fonctionnement

Pour les événements de plongée, le planificateur aide à organiser les plongeurs en groupes sûrs basés sur les règles fédérales.

1. Ouvrir un événement de plongée → cliquer **Palanquées**
2. Créer des groupes et assigner les plongeurs inscrits
3. Cliquer **Valider** pour vérifier tous les groupes contre les règles fédérales

### 12.2 Moteur de règles

Le système inclut 39 règles sur 5 fédérations (LIFRAS, FFESSM, BSAC, PADI, CMAS). Les règles couvrent :

- **Taille du groupe** — min/max plongeurs par groupe
- **Exigences de chef de palanquée** — niveau minimum pour le chef
- **Limites de profondeur** — profondeur max par niveau
- **Ratios d'encadrement** — ex : max 4 débutants par instructeur
- **Règles multi-niveaux** — quels niveaux peuvent plonger ensemble

### 12.3 Résultat de validation

- ✅ **Valide** — la composition du groupe respecte toutes les règles
- ❌ **Violation** — règle spécifique enfreinte, avec explication
- ⚠️ **Avertissement** — certificat médical expirant dans les 30 jours

---

## 13. Partenariats inter-clubs

### 13.1 Configuration

L'API de fédération permet à deux clubs utilisant DivingClub de partager des événements et d'accepter des inscriptions croisées.

1. Administration → Partenariats → Ajouter un partenaire
2. Entrer le nom et l'URL de base du club partenaire
3. Le système génère un **Key ID** et un **Secret** (clé symétrique)
4. Envoyer ces identifiants à l'admin du club partenaire (via canal sécurisé)
5. Le partenaire fait de même et vous envoie ses identifiants
6. Modifier le partenariat, coller leur Key ID et Secret
7. Les deux clubs ont maintenant un accès API bidirectionnel

### 13.2 Événements fédérés

Lors de la création d'un événement, cocher **« Fédéré »** et définir le nombre de places externes. Cet événement devient visible par les clubs partenaires via l'API.

### 13.3 Inscriptions externes

Administration → Partenariats → Inscriptions externes pour voir les demandes d'inscription entrantes. Chaque demande inclut le nom, le niveau de certification et la validité médicale. Approuver ou rejeter chacune.

### 13.4 Sécurité

Tous les appels API sont authentifiés avec des signatures HMAC-SHA256 utilisant le secret partagé. Les requêtes incluent un horodatage pour prévenir les attaques par rejeu.

---

## 14. Clés API et OAuth

Toutes les clés API sont stockées dans le fichier `.env`. Après modification, exécuter `php artisan config:clear`.

### 14.1 OAuth / Connexion sociale

Le système supporte 5 fournisseurs OAuth. Chacun nécessite un Client ID et un Client Secret.

| Fournisseur | Variables env | URL de configuration | URL de callback |
|-------------|--------------|---------------------|-----------------|
| Google | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | console.cloud.google.com | `{APP_URL}/auth/google/callback` |
| Facebook | `FACEBOOK_CLIENT_ID`, `FACEBOOK_CLIENT_SECRET` | developers.facebook.com | `{APP_URL}/auth/facebook/callback` |
| Microsoft | `MICROSOFT_CLIENT_ID`, `MICROSOFT_CLIENT_SECRET` | portal.azure.com | `{APP_URL}/auth/microsoft/callback` |
| X (Twitter) | `X_CLIENT_ID`, `X_CLIENT_SECRET` | developer.x.com | `{APP_URL}/auth/x/callback` |
| Amazon | `AMAZON_CLIENT_ID`, `AMAZON_CLIENT_SECRET` | developer.amazon.com | `{APP_URL}/auth/amazon/callback` |

> **Conseil :** Google OAuth est le plus utilisé. Commencez par celui-là. Tous les fournisseurs sont gratuits à configurer.

### 14.2 Google Maps

| | |
|---|---|
| Variable env | `GOOGLE_MAPS_KEY` |
| Configuration | Google Cloud Console → Maps Embed API |
| Coût | Gratuit : embeds illimités. L'API Maps Embed n'a pas de limites d'utilisation. |
| Utilisé pour | Carte intégrée sur les pages de détail d'événement |

Sans clé, les lieux d'événement renvoient vers une recherche Google Maps au lieu d'intégrer la carte.

### 14.3 API de traduction (optionnel)

Pour la traduction automatique du contenu vers les 11 langues supportées.

| Service | Coût | Variables env | Notes |
|---------|------|--------------|-------|
| **LibreTranslate** (recommandé) | 🆓 Gratuit — auto-hébergé | `TRANSLATION_DRIVER=libretranslate`, `LIBRETRANSLATE_URL=http://localhost:5000` | Docker : `docker run -p 5000:5000 libretranslate/libretranslate` |
| **Argos Translate** | 🆓 Gratuit — auto-hébergé | `TRANSLATION_DRIVER=argos`, `ARGOS_URL=http://localhost:5100` | Python, hors ligne. Bon pour la confidentialité. |
| DeepL | Gratuit : 500K car/mois | `TRANSLATION_DRIVER=deepl`, `DEEPL_API_KEY=votre_clé` | Meilleure qualité pour les langues européennes |
| Google Translate | $20/million car | `TRANSLATION_DRIVER=google`, `GOOGLE_TRANSLATE_KEY=votre_clé` | Cloud Translation API |

### 14.4 API OCR (optionnel)

Pour la lecture automatique des certificats médicaux et brevets de plongée depuis les scans uploadés.

| Service | Coût | Variables env | Notes |
|---------|------|--------------|-------|
| **Tesseract OCR** (recommandé) | 🆓 Gratuit — local | `OCR_DRIVER=tesseract` | `sudo apt install tesseract-ocr tesseract-ocr-fra tesseract-ocr-deu`. Local, pas de clé API. |
| **PaddleOCR** | 🆓 Gratuit — auto-hébergé | `OCR_DRIVER=paddle`, `PADDLE_URL=http://localhost:8866` | Python, excellent support multilingue. Docker disponible. |
| Google Vision | Gratuit : 1K/mois | `OCR_DRIVER=google_vision`, `GOOGLE_VISION_KEY=votre_clé` | Meilleure précision pour le texte manuscrit |
| Azure Computer Vision | Gratuit : 5K/mois | `OCR_DRIVER=azure`, `AZURE_VISION_KEY=votre_clé`, `AZURE_VISION_ENDPOINT=votre_endpoint` | Azure AI Vision |

### 14.5 LLM / Assistant IA (optionnel)

Pour l'analyse intelligente de documents, la catégorisation automatique et les requêtes en langage naturel.

| Service | Coût | Variables env | Notes |
|---------|------|--------------|-------|
| **Ollama** (recommandé) | 🆓 Gratuit — local | `LLM_DRIVER=ollama`, `OLLAMA_URL=http://localhost:11434`, `OLLAMA_MODEL=llama3.2` | `curl -fsSL https://ollama.com/install.sh \| sh && ollama pull llama3.2`. Aucune donnée ne quitte votre réseau. |
| **LM Studio** | 🆓 Gratuit — local | `LLM_DRIVER=openai`, `LLM_URL=http://localhost:1234/v1`, `LLM_API_KEY=lm-studio` | API compatible OpenAI. Modèles depuis Hugging Face. |
| OpenAI | Paiement par token | `LLM_DRIVER=openai`, `OPENAI_API_KEY=votre_clé` | platform.openai.com |
| Anthropic Claude | Paiement par token | `LLM_DRIVER=anthropic`, `ANTHROPIC_API_KEY=votre_clé` | console.anthropic.com |

### 14.6 SMTP / Email

| Service | Coût | Notes |
|---------|------|-------|
| **Brevo (ex-Sendinblue)** | 🆓 Gratuit : 300 emails/jour | Bon pour les petits clubs |
| **Mailgun** | Gratuit : 100 emails/jour (plan Flex) | mailgun.com |
| Amazon SES | $0.10/1K emails | Le moins cher à grande échelle |
| Postmark | $15/mois pour 10K | Meilleure délivrabilité |

> **Recommandation pour un petit club :** Commencez avec Google OAuth (gratuit), Tesseract OCR (gratuit, local), LibreTranslate (gratuit, Docker), Ollama (gratuit, local) et Brevo SMTP (gratuit, 300/jour). Coût total : 0€.


---

## 15. Publication automatique sur les réseaux sociaux

### 15.1 Triple vérification RGPD

Une photo n'est publiée que lorsque les **trois** conditions sont remplies :

1. **Consentement de l'auteur** — l'uploadeur a coché la case de consentement RGPD lors de l'upload
2. **Groupe fermé** — l'admin a confirmé que le groupe Facebook est un groupe fermé/privé (Paramètres → Technique → Réseaux sociaux)
3. **Publication auto activée** — l'admin a activé la publication automatique dans les paramètres

Si une condition échoue, la photo reste uniquement sur le site web.

### 15.2 Configuration

1. Créer une Facebook App sur developers.facebook.com
2. Obtenir un Page Token avec la permission `publish_to_groups`
3. Ajouter `FACEBOOK_PAGE_TOKEN=votre_token` dans `.env`
4. Paramètres → Technique → Publication auto réseaux sociaux
5. Entrer l'ID du groupe Facebook, confirmer que c'est un groupe fermé, activer la publication auto

### 15.3 Journal de publication

Chaque tentative de publication est journalisée avec le statut (en attente/publié/échoué), l'ID du post externe et les messages d'erreur.

---

## 16. Mineurs et consentement parental

### 16.1 Liste des mineurs

Administration → Mineurs et consentement. Cette page liste tous les membres de moins de 18 ans (basé sur la date de naissance).

### 16.2 Lier un tuteur

Chaque mineur a besoin d'au moins un tuteur lié. Développer la ligne du mineur et sélectionner un membre comme parent ou tuteur légal. Le tuteur doit aussi être un membre enregistré. Le tableau de bord affiche une alerte rouge pour tout mineur sans tuteur lié.

### 16.3 Enregistrer le consentement

Quatre types de consentement sont suivis par mineur :

- **Général** — consentement global d'adhésion
- **Événements** — participation aux activités du club
- **Photos** — publication de photos sur le site/réseaux sociaux
- **Médical** — gestion des certificats médicaux par le club

Chaque consentement peut inclure un document uploadé (formulaire d'autorisation signé, PDF ou image). Les documents sont stockés en privé et accessibles uniquement par les membres du bureau.

### 16.4 Révocation

Cliquer Révoquer à côté de tout consentement actif. La révocation est horodatée et journalisée. Quand un mineur atteint 18 ans, il n'apparaît plus sur cette page et gère ses propres consentements via la page Confidentialité.

---

## 17. RGPD

### 17.1 Gestion des consentements

Les membres peuvent accorder ou révoquer le consentement pour :

- **Traitement des données** — requis pour l'adhésion
- **Marketing** — newsletter et emails promotionnels
- **Publication de photos** — photos sur le site/réseaux sociaux

Chaque changement de consentement est horodaté. Le système d'email respecte le consentement marketing lors du ciblage des groupes.

### 17.2 Export de données

Les membres peuvent télécharger un export JSON complet de toutes leurs données personnelles : profil, emails, licences, métadonnées des documents et historique des consentements.

### 17.3 Droit à l'effacement

Les membres peuvent demander l'effacement de leur compte. Cela :

- Supprime tous les documents uploadés et l'avatar du stockage
- Anonymise tous les champs personnels (nom → « ERASED », email → « erased-ID@erased.local »)
- Supprime toutes les adresses email et liens de comptes sociaux
- Journalise l'effacement dans le journal d'audit
- Déconnecte l'utilisateur

L'enregistrement utilisateur est conservé (anonymisé) pour maintenir l'intégrité référentielle dans les journaux d'audit et l'historique des événements.

### 17.4 Consentement cookies

Les visiteurs non authentifiés voient une bannière de consentement cookies. L'acceptation définit un cookie d'un an. Aucun suivi n'a lieu avant le consentement.

---

## 18. Journal d'audit

### 18.1 Navigation et filtrage

Administration → Journal d'audit. Chaque modification de données est automatiquement journalisée via le trait `Auditable`.

- **ID utilisateur** — filtrer par qui a fait le changement
- **Action** — created, updated, deleted, sso_linked, impersonate_start
- **Type de modèle** — ex : « User », « Event », « Document »
- **Plage de dates** — sélecteurs de date début/fin

### 18.2 Vue détaillée

Cliquer **Voir** sur une entrée pour voir le diff complet :

- **Updated** — tableau avant/après côte à côte avec code couleur
- **Created** — toutes les valeurs initiales
- **Deleted** — toutes les valeurs au moment de la suppression
- Adresse IP, user agent et avertissements d'impersonation affichés

### 18.3 Export CSV

Cliquer le bouton 📥 pour exporter la vue filtrée actuelle en CSV. Utile pour les réunions du bureau ou les audits de conformité.

### 18.4 Politique de rétention

Définir la période de purge automatique (6–60 mois) dans l'en-tête du journal d'audit. Le système supprime automatiquement les entrées plus anciennes le 1er de chaque mois à 04h00. Purge manuelle aussi disponible.

---

## 19. Sauvegardes et maintenance

### 19.1 Sauvegardes automatiques

Le système exécute une sauvegarde hebdomadaire chaque dimanche à 03h00 via le job `WeeklyBackup` :

- Dump MySQL compressé avec gzip
- Stocké dans `storage/app/backups/`
- Les 4 dernières sauvegardes conservées, les plus anciennes auto-supprimées

### 19.2 Sauvegarde manuelle

```bash
# Dump de la base de données
mysqldump -u divingclub -p divingclub | gzip > backup-$(date +%Y%m%d).sql.gz

# Sauvegarde complète (inclut les uploads)
tar czf divingclub-full-$(date +%Y%m%d).tar.gz \
    --exclude=node_modules --exclude=vendor \
    /chemin/vers/divingclub
```

### 19.3 Tâches planifiées

| Fréquence | Tâche |
|-----------|-------|
| Quotidien 08h00 | Rappels d'expiration des certificats médicaux (30/15/7/0 jours) |
| Horaire | Traduction auto d'un article non traduit |
| Chaque minute | Ouverture/fermeture auto des votes |
| 1er du mois 04h00 | Purge auto du journal d'audit (selon politique de rétention) |
| Dimanche 03h00 | Sauvegarde hebdomadaire de la base de données |

### 19.4 Worker de file d'attente

Le driver de file d'attente est `database`. Démarrer le worker :

```bash
# Développement
php artisan queue:work

# Production (utiliser supervisor)
sudo apt install supervisor
```

Configuration supervisor :

```ini
[program:divingclub-worker]
command=php /chemin/vers/divingclub/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/divingclub-worker.log
```

### 19.5 Gestion du cache

```bash
php artisan config:clear    # Vider le cache de config
php artisan cache:clear     # Vider le cache applicatif
php artisan view:clear      # Vider les vues compilées
php artisan route:clear     # Vider le cache de routes

# Optimisation production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 20. Import/Export de données de plongée

### 20.1 UDDF (Universal Dive Data Format)

Les membres peuvent importer leurs plongées depuis leur ordinateur de plongée au format UDDF 3.2.1. Le système extrait : profondeur, durée, température, paliers de décompression, arrêt de sécurité.

### 20.2 DAN DL7

Le bureau peut exporter toutes les plongées du club au format DAN DL7 (pipe-delimited) pour contribuer au programme de recherche DAN sur les accidents de décompression.

Administration → Export DAN → télécharge un fichier `.dl7` uploadable sur dan.org/PDE.

---

## 21. Licence

### 21.1 Palier gratuit

Le système fonctionne gratuitement jusqu'à 100 membres. Au-delà, une clé de licence est nécessaire.

### 21.2 Installation

1. Obtenir une clé auprès du mainteneur du projet
2. Administration → Paramètres → Licence
3. Coller la clé → Enregistrer
4. Vérification : signature RSA, domaine, nombre de membres, date d'expiration

Voir `docs/LICENSE-PROCEDURE.md` pour la procédure complète de génération de clés.

---

## 22. Dépannage

| Problème | Solution |
|----------|----------|
| Pages affichent erreur 500 | 1. Vérifier `storage/logs/laravel.log` 2. Vérifier `APP_KEY` : `php artisan key:generate` 3. Permissions : `sudo chown -R www-data:www-data storage bootstrap/cache && sudo chmod -R 775 storage bootstrap/cache` |
| CSS/JS ne charge pas | 1. `npm run build` 2. Vérifier que `public/build/manifest.json` existe 3. Nginx sert le répertoire `public/` |
| Emails non envoyés | 1. Vérifier `MAIL_MAILER` dans `.env` 2. Worker de file : `php artisan queue:work` 3. Jobs échoués : `php artisan queue:failed` |
| Connexion OAuth échoue | 1. Vérifier Client ID et Secret dans `.env` 2. URL de callback exacte (https vs http) 3. App OAuth publiée/approuvée chez le fournisseur |
| Conformité médicale incorrecte | 1. Vérifier les règles dans Paramètres 2. Vérifier fédération et date de naissance du membre 3. Re-uploader le certificat pour déclencher la réévaluation |
| Événements non générés | 1. Vérifier que la saison a des schémas 2. Vérifier que les vacances ne couvrent pas toutes les dates 3. Jours : 0=Lundi, 6=Dimanche |
| Rapprochement bancaire ne matche pas | 1. Communication doit suivre le format `CLUB-ANNEE-ID-NOM` 2. Seuil auto-match : 60/100 3. Confirmation manuelle pour les cas limites |
| Changements de thème non visibles | 1. Cache de 5 min : `php artisan cache:clear` 2. Rafraîchir le navigateur : Ctrl+Shift+R |
| Langue ne change pas | 1. Cliquer le menu déroulant de langue 2. Pour les authentifiés, la préférence est sauvée au profil 3. Pour les invités, stockée en session 4. Vérifier que `lang/{locale}/messages.php` existe |

### Commandes utiles

```bash
php artisan about                    # Santé de l'application
php artisan route:list               # Lister toutes les routes
php artisan tinker --execute="echo App\Models\User::count().' users'"
php artisan optimize:clear           # Tout vider
php artisan migrate:fresh --seed     # Réinitialiser la BDD (DESTRUCTIF)
php artisan schedule:list            # Vérifier le planificateur
php artisan queue:retry all          # Relancer les jobs échoués
```
