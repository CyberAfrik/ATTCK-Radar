Bonjour,

Votre plan de détection ATT&CK Radar est prêt.

Télécharger le classeur Excel : {{ $xlsxUrl }}
Calque ATT&CK Navigator : {{ $layerUrl }}

Couverture aujourd'hui : {{ $summary['pct_on'] }} %
Avec les sources prévues : {{ $summary['pct_planned'] }} %
Règles applicables : {{ $summary['rules'] }}
Angles morts : {{ $summary['gaps'] }}
Langage des requêtes : {{ $summary['language'] }}

Rouvrir mon plan : {{ $planUrl }}
Gardez ces liens pour vous : toute personne qui les possède peut consulter ce plan.
Le classeur contient des signatures d'attaque en clair, il est possible que votre antivirus le signale.

Les requêtes sont converties automatiquement à partir de règles Sigma. Testez-les avant de les mettre en production.

CyberAfrik SARL, Cotonou, Bénin
@if ($unsubscribeUrl)
Ne plus recevoir les actualités de CyberAfrik : {{ $unsubscribeUrl }}
@endif
