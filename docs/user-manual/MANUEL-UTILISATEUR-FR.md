# 🤿 DivingClub-Manager — Manuel Utilisateur

*Guide illustré du système complet de gestion de club de plongée.*
*Basé sur une démonstration avec le Club Européen de Plongée (CEP), Luxembourg.*

---

## Chapitre 1 : Configuration de la saison

Avant de générer les événements, le bureau configure la saison avec les dates, les vacances scolaires et les entraînements récurrents.

### 1.1 Paramètres administrateur

Naviguer vers **Admin → Paramètres** pour configurer l'identité du club, les fédérations, les règles médicales et le thème.

![Paramètres admin](ch01_01_admin_settings.png)

### 1.2 Créer une saison

Aller dans **Admin → Saisons**. Créer la saison avec les dates de début/fin correspondant à l'année scolaire (15 septembre → 15 juillet pour le Luxembourg).

![Liste des saisons](ch01_02_seasons_list.png)

**Ajouter les vacances scolaires** pour ne pas générer d'événements pendant les congés :
- Toussaint (1-9 nov), Noël (20 déc - 4 jan), Carnaval (14-22 fév)
- Pâques (28 mar - 12 avr), Ascension (14 mai), Pentecôte (23-31 mai)
- Fête nationale (23 juin)

**Ajouter les entraînements récurrents** — le planning hebdomadaire :

| Jour | Horaire | Activité | Lieu | Max |
|------|---------|----------|------|-----|
| Lundi | 19:00-21:00 | Piscine | Piscine Steinfort | 16 |
| Mercredi | 17:20-20:00 | Piscine | Forum Geesseknäppchen | 20 |
| Vendredi | 18:30-20:00 | Apnée | Forum Geesseknäppchen | 12 |

Plus une séance **Fosse** mensuelle le jeudi ou vendredi à Nemo 33, Bruxelles.

---

## Chapitre 2 : Calendrier

Une fois la saison configurée, les événements sont générés automatiquement à partir des modèles, en sautant les semaines de vacances.

### 2.1 Avril 2026

Après les vacances de Pâques (fin le 12 avril), le planning reprend avec les séances piscine le lundi, mercredi et vendredi.

![Calendrier avril](ch02_01_calendar_april.png)

### 2.2 Mai 2026

Les vacances de Pentecôte (23-31 mai) créent une pause dans le planning. La séance Fosse du 15 mai est visible.

![Calendrier mai](ch02_02_calendar_may.png)

### 2.3 Juin 2026

La saison se termine vers le 15 juillet. La Fête nationale (23 juin) est marquée.

![Calendrier juin](ch02_03_calendar_june.png)

---

## Chapitre 3 : Planning des moniteurs

Le calendrier des moniteurs a un **thème bleu-vert distinct** pour le différencier du calendrier classique. Il montre quels moniteurs sont disponibles pour chaque séance.

### 3.1 Marquer sa disponibilité

Les moniteurs cliquent **➕** sur un événement pour se déclarer disponibles. Leur initiale colorée apparaît. Cliquer **✅** pour retirer.

Les membres réguliers voient ce calendrier en **lecture seule** — ils peuvent vérifier qui enseigne mais ne peuvent pas modifier.

![Planning moniteurs avril](ch03_01_instructor_april.png)

La légende **Types d'activité** en bas montre le code couleur : Piscine (bleu), Apnée (vert), Fosse (olive), Carrière (magenta), etc.

### 3.2 Alerte d'annulation

Les événements sans aucun moniteur disponible **doivent être annulés 2 heures avant**. Le bureau surveille le calendrier et contacte les moniteurs en cas de manque.

![Planning moniteurs mai](ch03_02_instructor_may.png)

> ⚠️ Les événements sans initiale de moniteur nécessitent une attention. Le bureau doit envoyer un rappel ou annuler la séance.

---

## Chapitre 4 : Inscription aux événements

Les membres s'inscrivent depuis la page de détail de l'événement. La page affiche toutes les informations : date, heure, lieu, site de plongée, météo et liste des participants.

### 4.1 Détail de l'événement

![Détail événement](ch04_01_event_detail.png)

La barre latérale droite affiche :
- **Panneau d'inscription** — s'inscrire soi-même ou un autre membre (proxy bureau)
- **Liste des participants** — qui est inscrit, avec les positions en liste d'attente
- **Groupes de plongée** — planification des palanquées (pour les sorties)

Quand l'événement atteint sa capacité maximale, les nouvelles inscriptions passent en **liste d'attente** et sont automatiquement promues en cas de désistement.

---

## Chapitre 5 : Paiements et rapprochement bancaire

Les événements payants (sorties, Fosse, événements sociaux) génèrent des fiches de paiement avec des codes de communication uniques pour les virements.

### 5.1 Vue d'ensemble des paiements

![Liste des paiements](ch05_01_payments_list.png)

Les cartes de résumé affichent :
- **Encaissé** — montant total reçu
- **En attente** — montant total dû
- **Payé / En attente** — nombre de fiches par statut

Chaque paiement a un **code de communication** unique (ex. `CEP-VTXC-14`) que les membres incluent dans leur virement. Cela permet le rapprochement automatique.

### 5.2 Paiements en attente

Filtrer par statut pour voir uniquement les paiements en souffrance :

![Paiements en attente](ch05_02_payments_pending.png)

Le bouton **Rapprochement bancaire** en bas ouvre l'outil de correspondance assistée — télécharger un relevé bancaire CSV et le système fait la correspondance floue avec les paiements attendus.

---

## Chapitre 6 : Partenariats inter-clubs

Deux instances DivingClub-Manager peuvent établir une relation de confiance, permettant aux membres d'un club de s'inscrire aux événements de l'autre.

### 6.1 Configuration du partenariat

![Partenariats](ch06_01_partnerships.png)

Chaque club génère des identifiants API (Key ID + Secret) et les partage avec le partenaire. Le bouton « Voir les événements » affiche les événements fédérés du partenaire.

### 6.2 Inscriptions externes

Les clubs partenaires inscrivent leurs membres via API. Le club organisateur approuve ou refuse depuis la page **Inscriptions externes** :

![Inscriptions externes](ch06_02_external_regs.png)

---

## Chapitre 7 : Communications

Le système d'email permet au bureau d'envoyer des messages ciblés aux groupes de membres ou aux participants d'un événement.

### 7.1 Templates et envoi

![Système email](ch07_01_email_system.png)

- **Templates** — modèles réutilisables avec variables (`{{first_name}}`, `{{club_name}}`, etc.)
- **Envoi** — sélectionner un template et un groupe cible
- **Journal** — historique complet de tous les emails envoyés

Les emails sont répartis automatiquement sur 3 fournisseurs (Resend × 2 + Mailjet).

---

## Chapitre 8 : Planification des palanquées

Pour les sorties plongée, le planificateur organise les participants en binômes selon les règles de sécurité FFESSM.

### 8.1 Groupes de plongée

![Groupes de plongée](ch08_01_event_with_groups.png)

Chaque palanquée a un **chef de palanquée** (minimum N3 ou E1 pour les plongées profondes), des **plongeurs** appariés par niveau, et un plan de plongée (profondeur, durée, mélange gazeux).

---

## Chapitre 9 : Tableau de bord administrateur

Le tableau de bord du bureau offre une vue complète des opérations du club.

### 9.1 Statistiques et liste de tâches

![Tableau de bord](ch09_01_dashboard.png)

Sections clés :
- **Statistiques** — membres, événements, fréquentation, revenus
- **Liste de tâches** — actions en attente (certificats non vérifiés, médicaux expirés, IBAN manquants, inscriptions externes, anniversaires)
- **Quota d'envoi email** — utilisation en temps réel sur les 3 fournisseurs
- **Tâches planifiées** — surveillance des jobs en arrière-plan

---

## Chapitre 10 : Newsletter

Newsletters HTML riches avec templates thématiques, emplacements d'articles et éléments décoratifs.

### 10.1 Liste des newsletters

![Newsletters](ch10_01_newsletters_list.png)

### 10.2 Composition

![Compositeur](ch10_02_newsletter_compose.png)

Le compositeur offre 5 emplacements d'articles, un teaser éditable par emplacement, des URL personnalisées, 25 décorations SVG, et un aperçu/envoi test.

---

## Chapitre 11 : Statistiques de livraison email

Suivi de la livraison des emails avec statut par destinataire.

### 11.1 Tableau de bord

![Stats email](ch11_01_email_stats.png)

Naviguer par date pour voir : messages totaux, ouverts, cliqués, échoués. Données en temps réel depuis Mailjet et Resend.

---

## Chapitre 12 : Tableau de bord membre

Le tableau de bord moderne avec tuiles offre un accès rapide aux fonctions les plus utilisées, adapté au rôle de l'utilisateur.

### 12.1 Actions rapides

![Tableau de bord tuiles](ch12_01_tile_dashboard.png)

**Tous les membres** : Événements, Mon Profil, Planning Moniteurs, Documents, Paiements, Petites annonces.

**Bureau** (tuiles jaunes) : Liste de tâches, Membres, Sites de plongée, Équipement, Email, Newsletters, Rapprochement, Stats email.

---

## Chapitre 13 : Gestion des membres

### 13.1 Liste des membres

**Admin → Membres** affiche tous les membres avec recherche, badges de rôle et statut.

![Liste des membres](ch13_01_members_list.png)

### 13.2 Profil d'un membre

Cliquer sur un membre pour voir son profil complet : détails personnels, certifications, licences fédérales, certificats médicaux et documents.

![Profil membre](ch13_02_member_profile.png)

Le bureau peut modifier tous les champs, vérifier les certificats et attribuer les rôles.

### 13.3 Mon profil

Les membres modifient leur propre profil dans **Mon Compte → Profil** : contacts d'urgence, téléphone, adresse, avatar et téléchargement de certifications.

![Mon profil](ch13_03_my_profile.png)

---

## Chapitre 14 : Conformité médicale

### 14.1 Règles et rappels

Les règles de conformité médicale sont configurées par fédération et tranche d'âge dans **Admin → Paramètres**. Le système envoie automatiquement des rappels à 30, 15, 7 et 0 jours avant l'expiration.

![Règles médicales](ch14_01_medical_rules.png)

Les membres avec un certificat médical expiré sont **bloqués pour l'inscription** aux événements de plongée.

---

## Chapitre 15 : Équipement

### 15.1 Inventaire

**Admin → Équipement** affiche tout le matériel du club avec numéros courts, type, état, statut et prêt en cours.

![Liste équipement](ch15_01_equipment_list.png)

### 15.2 Ajout d'équipement

Cliquer **+ Ajouter** pour enregistrer un nouveau matériel.

![Ajout équipement](ch15_02_equipment_create.png)

**Prêt rapide** : sélectionner un membre et un type — le système suggère les articles disponibles correspondant à la taille de gilet du membre.

**Maintenance** : les règles définissent les intervalles par type (ex. « Retest bouteille tous les 24 mois »). Le système planifie automatiquement la prochaine date.

---

## Chapitre 16 : Articles et CMS

### 16.1 Liste des articles

**Admin → Articles** affiche tous les articles avec badges de type, statut de publication et recherche multilingue.

![Liste articles](ch16_01_articles_list.png)

### 16.2 Création d'un article

L'éditeur utilise TinyMCE pour le texte riche, les galeries d'images et les vidéos intégrées. Les articles peuvent être auto-traduits dans les 15 langues.

![Créer article](ch16_02_article_create.png)

### 16.3 Petites annonces

Les membres publient des annonces (matériel à vendre, recherche de binôme) avec expiration automatique à 30 jours.

![Petites annonces](ch16_03_classifieds.png)

---

## Chapitre 17 : Votes et élections

### 17.1 Liste des votes

**Admin → Votes** affiche tous les sondages et élections avec leur statut.

![Liste votes](ch17_01_votes_list.png)

### 17.2 Création d'un vote

Deux modes :
- **Simple** — les membres peuvent changer leur vote, résultats visibles immédiatement
- **Élection** — anonyme, irréversible, par jeton, résultats uniquement après clôture

![Créer vote](ch17_02_vote_create.png)

---

## Chapitre 18 : Authentification et impersonation

### 18.1 Options de connexion

Les membres se connectent par email/mot de passe, ou via les fournisseurs sociaux (Google, Microsoft, Facebook, X) et EU Login (CAS).

### 18.2 Impersonation

Les membres du bureau peuvent se connecter en tant que n'importe quel membre pour diagnostiquer son expérience.

![Impersonation](ch18_01_impersonation.png)

---

## Chapitre 19 : Thème et page d'accueil

### 19.1 Paramètres du thème

**Admin → Paramètres → Thème** offre 6 préréglages (Ocean, Coral, Lagoon, Abyss, Tropical, Arctic) plus des couleurs personnalisées, le téléchargement du logo et le mode sombre.

![Paramètres thème](ch19_01_theme_settings.png)

### 19.2 Éditeur de widgets

La page d'accueil classique utilise un système de widgets glisser-déposer. Cliquer **⚙ Modifier la mise en page** pour réorganiser les widgets et définir leur visibilité.

![Widgets page d'accueil](ch19_02_homepage_widgets.png)

---

## Chapitre 20 : Sauvegardes

**Admin → Sauvegardes** permet de créer, inspecter, télécharger et supprimer des sauvegardes. Chaque sauvegarde inclut le dump de la base de données et tous les fichiers téléchargés.

Sauvegarde automatique hebdomadaire le dimanche à 03h00, conservation des 4 dernières.

---

## Chapitre 21 : Documents

### 21.1 Bibliothèque du bureau

**Admin → Documents** est le gestionnaire de fichiers du club : glisser-déposer, dossiers, recherche, téléchargement ZIP et aperçu PDF/image.

![Bibliothèque](ch21_01_library.png)

### 21.2 Documents des membres

Les membres consultent les documents du club et leurs propres fichiers dans **Ressources → Documents**.

![Documents membre](ch21_02_member_documents.png)

---

## Chapitre 22 : Baptême de plongée

### 22.1 Formulaire public

Les visiteurs peuvent demander un baptême de plongée depuis la page publique.

![Page baptême](ch22_01_trial_page.png)

### 22.2 Gestion par le bureau

Le bureau gère les demandes : confirmer, planifier ou refuser.

![Admin baptêmes](ch22_02_trial_admin.png)

---

## Chapitre 23 : RGPD

**Admin → RGPD** gère les consentements, l'export de données (JSON) et le droit à l'effacement avec anonymisation.

![RGPD](ch23_01_gdpr.png)

---

## Chapitre 24 : Langues

Le site supporte 15 langues. Les membres sélectionnent leur langue préférée dans la barre de navigation. La préférence est sauvegardée.

![Sélecteur de langue](ch24_01_language.png)

Les articles sont auto-traduits et affichés dans la langue préférée du membre.

---

## Chapitre 25 : Sites de plongée

**Admin → Sites de plongée** gère la base de données des sites : profondeur, conditions, faune, sécurité, accès, installations, hôpital le plus proche et météo.

![Sites de plongée](ch25_01_dive_sites.png)

---

## Chapitre 26 : Guide administrateur

Un guide intégré de 24 pages accessible dans **Admin → Guide** couvre toutes les procédures de configuration et d'exploitation.

![Guide admin](ch26_01_admin_guide.png)

---

## Chapitre 27 : Journal d'audit

**Admin → Journal d'audit** enregistre toutes les actions avec les anciennes/nouvelles valeurs, l'utilisateur, l'adresse IP et l'horodatage. Filtrable et exportable en CSV.

![Journal d'audit](ch27_01_audit_log.png)

---

*Manuel complet — 27 chapitres, 46 captures d'écran.*
*Captures réalisées sur test.clubcep.eu le 6 avril 2026.*
*Généré par DivingClub-Manager v1.1.0.*
