<?php

return [
    // Adresses autorisées à ouvrir l'administration (connexion par lien envoyé par email, sans mot de passe)
    'admins' => array_values(array_filter(array_map(
        fn ($e) => strtolower(trim($e)), explode(',', (string) env('RADAR_ADMIN_EMAILS', ''))
    ))),
    // URL du paquet de référentiel publié (GitHub Actions)
    'pack_url' => env('RADAR_PACK_URL'),
    // Durée de conservation des demandes d'export, en jours (0 = illimitée)
    'retention_days' => (int) env('RADAR_RETENTION_DAYS', 730),
    // Index public des versions de MITRE ATT&CK
    'attack_index' => 'https://raw.githubusercontent.com/mitre-attack/attack-stix-data/master/index.json',
];
