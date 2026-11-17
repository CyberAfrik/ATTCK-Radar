# ATT&CK Radar

De vos sources de logs à vos règles de détection. Application Laravel + Vue publiée par CyberAfrik.

## Fonctionnement

- L'utilisateur déclare ses sources de logs (en place ou prévues) et, s'il le souhaite, des scénarios d'attaque.
- L'application calcule la couverture MITRE ATT&CK, liste les règles applicables et les angles morts.
- L'utilisateur reçoit par email un lien pour télécharger le classeur Excel et rouvrir son plan.
  Le classeur n'est pas joint : les passerelles de messagerie le rejettent à cause des signatures qu'il contient.

Une règle est applicable si au moins une des sources qui fournissent sa télémétrie est en place.
Une technique est détectable si au moins une règle applicable la vise.

## Administration

`/admin` : connexion par lien envoyé par email aux adresses de `RADAR_ADMIN_EMAILS`.

- Exports demandés (filtres, CSV, renvoi), suppression des données d'une adresse, rétention automatique.
- Catalogue de sources et scénarios d'attaque modifiables ; les corrections survivent aux imports.
- Règles maison : publiées tout de suite en Sigma depuis l'administration, ou converties vers tous les
  langages si le fichier est déposé dans `pipeline/custom-rules`.
- Statistiques d'usage anonymes (totaux par jour et par étape), journal des changements (`/nouveautes`).
- Alertes email : nouvelle version de MITRE ATT&CK, échec d'un import.

## Référentiel

`pipeline/build.sh` télécharge MITRE ATT&CK (STIX) et SigmaHQ, convertit les règles avec pySigma
(Splunk, Elastic, Sentinel, Falcon LogScale, Cortex XQL) et produit `manifest.json`, `catalog.json`
et `rules.ndjson.gz`. Le workflow GitHub Actions le publie chaque semaine.

Import côté serveur : `php artisan radar:import [dossier] [--url=...]`. Les empreintes SHA-256 sont
vérifiées et le remplacement se fait dans une seule transaction.

Le catalogue de sources est défini dans `pipeline/src/catalog.py`. Tout `logsource` Sigma non classé
devient une source automatique, à reclasser.

## Installation

1. Pointer le domaine sur le dossier `public/`.
2. Copier `.env.production.example` vers `.env`, renseigner la base MySQL et le SMTP, puis `php artisan key:generate`.
3. `php artisan migrate --force` puis `php artisan radar:import`.
4. Cron, chaque minute : `php /chemin/artisan schedule:run` (envoi des exports et mises à jour).

## Développement

`composer update`, `npm install`, `npm run build`, `php artisan serve`.

## Licences

Code : MIT. Règles : SigmaHQ, Detection Rule License 1.1 (auteur et origine affichés sur chaque règle).
Taxonomie : MITRE ATT&CK®.
