# Changelog

Toutes les modifications notables de Secret Magic Cards sont documentées ici.

## [Non publié]

### Documentation et chaîne d’approvisionnement

- Ajout des manifestes Composer pour le Dependency Graph GitHub.
- Ajout de la surveillance hebdomadaire Dependabot.
- Ajout du graphe Mermaid des dépendances internes au README.
- Ajout du fichier de licence propriétaire.

## [1.0.0] — 2026-08-11

### Cartes publiques

- Ajout du rendu complet d’un jeu de 52 cartes.
- Ajout des paramètres d’URL abrégés `c`, `v` et `s`.
- Ajout des styles moderne, classique, minimal, têtes traditionnelles et ancien Pallas.
- Ajout d’illustrations distinctes pour les Valets, Dames et Rois.
- Conservation des proportions originales des figures et correction de leur cadrage.
- Correction du chevauchement entre les symboles des coins et le cadre des figures.
- Ajout d’une animation mobile : arrivée par le bas sur le dos, pause et retournement.
- Ajout du rejeu de l’animation par toucher.

### Disparition magique

- Ajout de la page « Rien dans ma manche » pour une visite sans argument.
- Ajout de « La carte a disparu — C’est de la magie ! » pour les liens épuisés.
- Ajout d’une animation de dissolution et d’étincelles.

### Administration

- Ajout de l’authentification, des sessions durcies et de la limitation des tentatives.
- Séparation du tableau de bord en pages : vue d’ensemble, générateur, liens, NFC et accès.
- Ajout d’un aperçu mobile en direct, de la copie d’URL et du QR code.
- Ajout des rôles `admin` et `utilisateur`.
- Ajout de la création, du changement de rôle et de la suppression confirmée des comptes.
- Protection du dernier administrateur et interdiction de modifier son propre rôle.

### Liens courts

- Ajout de codes aléatoires de quatre caractères.
- Ajout des limites illimitée, unique et personnalisée.
- Ajout des compteurs, états, réarmement et désactivation.
- Ajout de la suppression définitive après confirmation.

### NFC NTAG 424 DNA

- Remplacement du parcours NFC statique par Secure Dynamic Messaging.
- Ajout de profils nommés affichés sous forme de cartes.
- Ajout d’un assistant modal en trois pages avec navigation fixe.
- Ajout des variantes NTAG 424 DNA et NTAG 424 DNA TagTamper.
- Génération d’une clé maître AES aléatoire par profil.
- Chiffrement AES-256-GCM des clés maîtres stockées.
- Compatibilité avec le mode AES de NFC Developer App.
- Déchiffrement de `picc_data` et validation de `cmac`.
- Ajout du verrou anti-rejeu par profil, UID et compteur.
- Ajout des statistiques de scans, activation, désactivation et suppression confirmée.
- Maintien de la lecture des anciennes URL NFC statiques pour compatibilité.

### Installation et portabilité

- Ajout d’un wizard d’installation en quatre étapes.
- Vérification de PHP, PDO MySQL, OpenSSL, mbstring, JSON et des droits d’écriture.
- Test de connexion MySQL avant toute création.
- Génération de la configuration privée et du chargeur public.
- Création sécurisée du premier compte administrateur.
- Création automatique des tables et migrations incrémentales.
- Ajout d’un verrou empêchant la réinstallation.
- Suppression des chemins absolus du code distribué.

### Sécurité

- Ajout de CSP, HSTS, `X-Frame-Options`, `nosniff` et `Referrer-Policy`.
- Ajout de jetons CSRF et de requêtes préparées.
- Configuration, secrets SQL et logique serveur hors de la racine publique.
- Interdiction d’indexation des pages publiques et privées.
- Réponses `HEAD` non consommatrices pour les liens à ouverture limitée.
