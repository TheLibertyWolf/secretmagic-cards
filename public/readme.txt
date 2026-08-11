CARDS.SECRETMAGIC.FR
====================

La page affiche une carte de jeu à partir de trois paramètres abrégés placés
dans l'URL :

  c = couleur / enseigne de la carte
  v = valeur de la carte
  s = style visuel


EXEMPLE
-------

https://cards.secretmagic.fr/?c=coeur&v=as&s=moderne


VALEURS ACCEPTEES
-----------------

Paramètre c :
  coeur, carreau, trefle, pique

Paramètre v :
  2, 3, 4, 5, 6, 7, 8, 9, 10, valet, dame, roi, as

Paramètre s :
  moderne, classique, minimal, tetes, ancien

Le style "tetes" utilise des figures de cartes traditionnelles colorées.
Le style "ancien" utilise des figures inspirées des cartes françaises Pallas.


AUTRES EXEMPLES
---------------

Roi de pique, style classique :
https://cards.secretmagic.fr/?c=pique&v=roi&s=classique

10 de carreau, style minimal :
https://cards.secretmagic.fr/?c=carreau&v=10&s=minimal

Dame de trefle, style moderne :
https://cards.secretmagic.fr/?c=trefle&v=dame&s=moderne


RACCOURCIS TOLERES POUR LES VALEURS
-----------------------------------

  a = as
  v ou j = valet
  q = dame
  k = roi

Les termes anglais (heart, diamond, club, spade, ace, jack, queen, king)
sont également reconnus.


COMPORTEMENT PAR DEFAUT
-----------------------

Si un paramètre est absent ou incorrect, la page utilise :

  c=coeur
  v=as
  s=moderne

La carte arrive d'abord du bas en montrant son dos, marque une courte pause,
puis se retourne avec un mouvement naturel. Un appui sur la carte rejoue
l'animation.


ADMINISTRATION
--------------

Le tableau de bord privé se trouve à l'adresse :

https://cards.secretmagic.fr/admin/

Il permet de prévisualiser une carte, produire son lien direct et son QR code,
créer des liens courts à quatre caractères, consulter leurs visites, les
désactiver ou les réarmer, et modifier l'accès administrateur.

Le menu sépare les fonctions en six pages : vue d'ensemble, générateur,
liens courts, programmation NFC 424, styles de cartes et accès administrateur.

La page « Styles de cartes » permet de nommer un style personnalisé et de
charger chaque photo disponible (enseigne + valeur). Une fois une première
photo ajoutée, le style apparaît dans le générateur et le wizard NFC. Seules
les cartes réellement chargées pour ce style peuvent être sélectionnées.
Les images restent dans un dossier privé, sont converties en WebP et leurs
proportions originales sont conservées.

Exemple de lien court :

https://cards.secretmagic.fr/?card=Ac12

Un lien peut être illimité, utilisable une seule fois ou limité à un nombre
personnalisé de visites. Une fois sa limite atteinte, il affiche la disparition
magique. La racine du site sans paramètre affiche le même type d'expérience.


PUCES NFC NTAG 424 DNA
----------------------

Le module NFC utilise Secure Dynamic Messaging (SDM), et non une URL NDEF
statique. La puce fabrique à chaque scan une nouvelle adresse signée contenant
son identifiant et son compteur de lecture chiffrés.

Fonctionnement :
  - premier scan physique : une nouvelle URL affiche la carte ;
  - rechargement de cette même URL : « La carte a disparu » ;
  - scan physique suivant : une autre URL affiche de nouveau la carte.

Le wizard de la page /admin/nfc.php recommande NFC Developer App sur Android.
La version d'essai gratuite utilise un CAPTCHA. L'administrateur crée un profil,
copie l'adresse de base et la clé maître dans l'application, laisse « Custom
data » vide, puis approche la NTAG 424 DNA pour la programmer.

Chaque puce reçoit un surnom libre (numéro écrit dessus, couleur, accessoire,
etc.) et apparaît sous forme de carte dans le gestionnaire. La carte « + » ouvre
un assistant modal en trois étapes. Une puce peut être désactivée ou archivée
après confirmation. L’archive conserve son profil, son historique et surtout
sa clé maître : ils restent consultables pour reprogrammer la puce. Restaurer
une puce la réactive.

Une clé AES aléatoire est créée pour chaque profil et stockée chiffrée hors de
la racine publique. La signature de chaque scan est validée par le serveur. Le
couple puce/compteur ne peut être consommé qu'une fois. Ne jamais partager la
clé maître et ne pas verrouiller définitivement une puce pendant les essais.

Les anciennes adresses ?nfc=... restent lisibles pour compatibilité, mais elles
sont statiques et ne doivent plus être utilisées pour le tour réutilisable.

Les liens courts disposent eux aussi d'une suppression avec confirmation. Cette
action efface définitivement le lien et son compteur de visites.
