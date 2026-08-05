<div align="center">

# ATT&CK Radar

**Du framework MITRE ATT&CK au plan de collecte SOC, en un clic.**

Un projet communautaire de [CyberAfrik](https://cyber-afrik.com)

[![License: MIT](https://img.shields.io/badge/License-MIT-00D4FF.svg)](LICENSE)
[![MITRE ATT&CK](https://img.shields.io/badge/MITRE%20ATT%26CK-Enterprise-0099CC.svg)](https://attack.mitre.org)
[![Offline](https://img.shields.io/badge/100%25-Hors%20ligne-3ecf8e.svg)](#)

[Essayer l'outil](#démarrage-rapide) · [Fonctionnalités](#fonctionnalités) · [Contribuer](#contribuer) · [cyber-afrik.com](https://cyber-afrik.com)

</div>

---

## Le problème

Monter un SOC pose presque toujours la même question, et elle reste rarement bien répondue : sur les centaines de techniques du référentiel [MITRE ATT&CK](https://attack.mitre.org), lesquelles couvrir en premier ? Quelles sources de logs collecter, quels événements précis activer, quelles règles écrire ?

MITRE ATT&CK documente les tactiques et techniques des attaquants, mais il ne dit pas quoi brancher concrètement dans un SIEM. ATT&CK Radar comble cet écart : il relie chaque technique à sa source de log, ses événements et ses règles de détection.

## Fonctionnalités

**Quatre points d'entrée, un même résultat exploitable.**

- **Par technique** : parcourez les 14 tactiques et plus de 600 techniques et sous-techniques MITRE ATT&CK Enterprise.
- **Par type d'attaque** : choisissez un scénario prêt à l'emploi (rançongiciel, compromission Active Directory, abus PKI/ADCS, phishing, exfiltration de données) et laissez l'outil sélectionner les techniques associées.
- **Par règle de détection** : partez d'une idée de règle et remontez jusqu'à la technique et la source qu'elle nécessite.
- **Par source de logs** : à l'inverse, choisissez une source déjà collectée (Active Directory, firewall, DNS, PAM) et découvrez quelles techniques elle couvre et quelles règles construire avec.

Pour chaque sélection, l'outil affiche les sources de logs nécessaires avec leur statut de collecte, les événements précis à activer, les prérequis de collecte, et un exemple de logique de détection.

Deux visualisations complètent l'analyse : une carte de couverture façon MITRE Navigator, colorée en direct par niveau de couverture, et un radar par tactique qui montre en un coup d'œil où se trouvent les angles morts.

## Démarrage rapide

Aucune installation, aucune dépendance, aucun serveur.

```bash
git clone https://github.com/cyberafrik/attck-radar.git
cd attck-radar
open CyberAfrik_ATTCK_Radar.html   # macOS
# ou double-cliquez simplement sur le fichier
```

Le fichier fonctionne directement dans n'importe quel navigateur moderne, y compris hors ligne. Aucune donnée saisie ne quitte votre machine.

## Comment c'est construit

Tout tient dans un seul fichier HTML autonome, données comprises :

| Bloc | Contenu |
|---|---|
| Taxonomie | 14 tactiques et 607 techniques/sous-techniques, référentiel officiel MITRE ATT&CK Enterprise |
| Mapping technique → source | Table curatée manuellement : catégorie de source, événements à activer, prérequis de collecte, exemple de règle |
| Catégories de sources | 35 catégories de sources de logs génériques (Active Directory, firewall, PAM, DNS, hyperviseur, etc.) |
| Scénarios d'attaque | 10 playbooks prêts à l'emploi regroupant les techniques d'un même type d'attaque |

Le mapping technique → source de log est le cœur du projet et sa principale limite actuelle : sur 196 techniques racines, 65 sont documentées à ce jour. Les autres apparaissent grisées dans l'outil, en attente de contribution.

## Contribuer

C'est la partie la plus utile où aider. Chaque technique documentée suit cette structure :

```python
add("T1110", ["ad_dc", "vpn_sase", "radius", "entra_id"],
    "Multiples echecs d'authentification suivis (ou non) d'un succes, sur AD/VPN/RADIUS/Entra ID",
    "Journaliser echecs ET succes d'authentification, pas seulement les echecs",
    "Plus de N echecs d'authentification en X minutes pour un compte, suivis d'un succes")
```

Dans l'ordre : l'identifiant de la technique MITRE ATT&CK, les catégories de sources de logs nécessaires, les événements à collecter, les prérequis de collecte côté infrastructure, et un exemple de logique de détection.

Pour proposer une nouvelle technique documentée ou corriger un mapping existant :

1. Ouvrez une issue avec l'identifiant de la technique et les sources concernées, ou
2. Proposez directement une pull request en ajoutant l'entrée correspondante.

Toute contribution est bienvenue : un mapping technique, une traduction, une correction de règle, une nouvelle catégorie de source. Ce projet est fait pour grandir avec la communauté.

## Feuille de route

- [ ] Étendre le mapping technique → source au-delà des 65 techniques actuelles
- [ ] Version anglaise de l'interface
- [ ] Export d'un calque compatible ATT&CK Navigator
- [ ] Synchronisation optionnelle avec le corpus [SigmaHQ](https://github.com/SigmaHQ/sigma) pour relier chaque règle à une source réelle
- [ ] Mode hébergé (le fichier reste utilisable en ligne sans backend)

## Limites connues

Le mapping technique → source de log reflète l'expérience terrain de l'équipe CyberAfrik et n'est pas (encore) synchronisé avec le corpus SigmaHQ ni avec les objets Groupes/Logiciels de la base STIX MITRE. Les tactiques Reconnaissance et Développement de ressources ne sont pas couvertes : elles décrivent une activité qui se déroule hors du système d'information surveillé, donc invisible dans des logs internes.

## Crédits

- Taxonomie : [MITRE ATT&CK®](https://attack.mitre.org), projet de MITRE Corporation.
- Développé et maintenu par [CyberAfrik SARL](https://cyber-afrik.com), Cotonou, Bénin.

## Licence

Distribué sous licence MIT. Voir [LICENSE](LICENSE).

---

<div align="center">

Nous existons pour un cyberespace sécurisé en Afrique.

[cyber-afrik.com](https://cyber-afrik.com) · [contact@cyber-afrik.com](mailto:contact@cyber-afrik.com) · [LinkedIn](https://www.linkedin.com/company/cyberafrik-sarl/)

</div>
