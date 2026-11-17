<?php

namespace App\Services;

use App\Support\SimpleXlsx;
use Illuminate\Support\Facades\DB;

class PlanExporter
{
    private const LEVELS = ['critical' => 'Critique', 'high' => 'Élevée', 'medium' => 'Moyenne', 'low' => 'Faible', 'informational' => 'Information'];

    private const ORIGINS = ['sigmahq' => 'SigmaHQ', 'splunk' => 'Splunk Security Content', 'sentinel' => 'Microsoft Sentinel'];

    private const STATES = ['on' => 'En place', 'planned' => 'Prévue', 'gap' => 'Angle mort', 'none' => 'Aucune règle connue'];

    public function __construct(private PlanService $plans, private CatalogIndex $index) {}

    /** @return array{xlsx:string, layer:string, summary:array} Chemins des fichiers générés. */
    public function build(array $states, array $playbookIds, string $language, string $planUrl): array
    {
        $data = $this->plans->compute($states, $playbookIds);
        $pack = DB::table('packs')->orderByDesc('id')->first();
        $langs = collect($this->index->languages())->keyBy('id');
        $langLabel = $langs[$language]['label'] ?? 'Sigma';
        $t = $data['totals'];
        $pct = fn (int $n) => $t['total'] ? round(100 * $n / $t['total']) : 0;

        $xlsx = new SimpleXlsx;

        $rows = [
            [['ATT&CK Radar : plan de détection', 2]],
            ['Généré le '.now()->format('d/m/Y').' sur radar.cyber-afrik.com'],
            [],
            [['Indicateur', 1], ['Valeur', 1]],
            ['Techniques détectables aujourd\'hui', $t['on'].' sur '.$t['total'].' ('.$pct($t['on']).' %)'],
            ['Avec les sources prévues', ($t['on'] + $t['planned']).' sur '.$t['total'].' ('.$pct($t['on'] + $t['planned']).' %)'],
            ['Règles applicables aujourd\'hui', $t['rules_on']],
            ['Règles applicables avec les sources prévues', $t['rules_planned']],
            ['Sources en place', count($data['on'])],
            ['Sources prévues', count($data['planned'])],
            ['Langage des requêtes', $langLabel],
            ['Règles disponibles dans ce langage (feuille « Règles de détection »)', $this->plans->applicableRules(array_merge($data['on'], $data['planned']))->where('langs', 'like', '% '.$language.' %')->count()],
            ['Référentiel', 'MITRE ATT&CK '.($pack->attack_version ?? '').', version '.($pack->version ?? '')],
            ['Rouvrir ce plan', $planUrl],
            [],
            [['Tactique', 1], ['Techniques', 1], ['Détectables', 1], ['Avec sources prévues', 1], ['Angles morts', 1], ['Sans règle connue', 1]],
        ];
        foreach ($data['tactics'] as $row) {
            $rows[] = [$row['name'], $row['total'], $row['on'], $row['on'] + $row['planned'], $row['gap'], $row['none']];
        }
        $rows[] = [];
        $rows[] = ['Méthode : une règle est applicable si au moins une des sources qui fournissent sa télémétrie est en place. Une technique est détectable si au moins une règle applicable la vise. Ces chiffres mesurent la couverture par des règles publiques, pas l\'efficacité réelle de la détection.'];
        $rows[] = ['Règles issues de SigmaHQ (Detection Rule License 1.1), de Splunk Security Content (Apache 2.0) et de Microsoft Sentinel (MIT). L\'origine et la licence de chaque règle figurent dans la dernière colonne. Les requêtes converties supposent les schémas de champs standards de chaque produit et doivent être adaptées à votre ingestion.'];
        $xlsx->addSheet('Synthèse', $rows, [46, 44, 16, 22, 16, 20]);

        $rows = [[['Famille', 1], ['Source', 1], ['État', 1], ['Ce qu\'elle apporte', 1], ['Produits types', 1], ['Règles', 1], ['Techniques', 1]]];
        foreach ($data['sources'] as $s) {
            $rows[] = [$s['family'], [$s['label'], 4], self::STATES[$s['state']], $s['description'], $s['products'], $s['rules'], $s['techniques']];
        }
        $xlsx->addSheet('Plan de collecte', $rows, [26, 36, 12, 60, 50, 10, 12], 1);

        $rows = [[['Sévérité', 1], ['Règle', 1], ['Techniques', 1], ['Disponible', 1], ['Source de logs', 1], ['Description', 1], ['Requête '.$langLabel, 1], ['Faux positifs', 1], ['Origine', 1]]];
        $labels = DB::table('sources')->pluck('label', 'id');
        $onSet = array_flip($data['on']);
        $all = array_merge($data['on'], $data['planned']);
        $ruleSources = [];
        foreach (DB::table('rule_source')->whereIn('source_id', $all ?: ['__none__'])->get() as $rs) {
            $ruleSources[$rs->rule_id][] = $rs->source_id;
        }
        $this->plans->applicableRules($all)->where('langs', 'like', '% '.$language.' %')->orderByDesc('level_rank')->orderBy('title')
            ->select(['id', 'title', 'level', 'technique_list', 'description', 'sigma', 'queries', 'falsepositives', 'upstream_url', 'origin', 'license'])
            ->chunk(300, function ($chunk) use (&$rows, $language, $labels, $onSet, $ruleSources) {
                foreach ($chunk as $r) {
                    $srcs = $ruleSources[$r->id] ?? [];
                    $now = (bool) array_filter($srcs, fn ($s) => isset($onSet[$s]));
                    $query = $language === 'sigma' ? $r->sigma : (json_decode($r->queries, true)[$language] ?? null);
                    $rows[] = [
                        self::LEVELS[$r->level] ?? $r->level, [$r->title, 4], str_replace(' ', ', ', $r->technique_list),
                        $now ? 'Aujourd\'hui' : 'Avec source prévue',
                        implode(' ou ', array_map(fn ($s) => $labels[$s] ?? $s, $srcs)),
                        $r->description, [$query ?? 'Non disponible dans ce langage. Voir la règle Sigma d\'origine.', 3],
                        implode(' ; ', json_decode($r->falsepositives, true) ?: []),
                        (self::ORIGINS[$r->origin] ?? $r->origin).' ('.$r->license.') '.$r->upstream_url,
                    ];
                }
            });
        $xlsx->addSheet('Règles de détection', $rows, [11, 48, 20, 18, 34, 60, 90, 34, 40], 1);

        $rows = [[['Technique', 1], ['Nom', 1], ['Tactique', 1], ['État', 1], ['Scénarios concernés', 1], ['Sources qui la rendraient détectable', 1]]];
        foreach ($data['gaps'] as $g) {
            $rows[] = [$g['id'], [$g['name'], 4], $g['tactic'], $g['state'] === 'planned' ? 'Couverte par une source prévue' : self::STATES[$g['state']], $g['scenarios'], $g['providers'] ?: 'Aucune règle publique connue'];
        }
        $xlsx->addSheet('Angles morts', $rows, [12, 44, 24, 28, 44, 70], 1);

        $dir = storage_path('app/exports');
        @mkdir($dir, 0775, true);
        $base = $dir.'/'.bin2hex(random_bytes(12));
        $xlsx->save($base.'.xlsx');

        $colors = ['on' => '#00D4FF', 'planned' => '#F5A524', 'gap' => '#5A6A8A'];
        $scores = ['on' => 100, 'planned' => 50, 'gap' => 0];
        $layer = [
            'name' => 'ATT&CK Radar - couverture', 'domain' => 'enterprise-attack',
            'versions' => ['attack' => explode('.', (string) ($pack->attack_version ?? '20'))[0], 'navigator' => '5.1.0', 'layer' => '4.5'],
            'description' => 'Couverture calculée par ATT&CK Radar (CyberAfrik). 100 : détectable, 50 : avec source prévue, 0 : angle mort.',
            'techniques' => [],
        ];
        foreach ($data['techStates'] as $id => $state) {
            if ($state !== 'none') {
                $layer['techniques'][] = ['techniqueID' => $id, 'score' => $scores[$state], 'color' => $colors[$state], 'comment' => self::STATES[$state]];
            }
        }
        file_put_contents($base.'.json', json_encode($layer, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return ['xlsx' => $base.'.xlsx', 'layer' => $base.'.json', 'summary' => [
            'pct_on' => $pct($t['on']), 'pct_planned' => $pct($t['on'] + $t['planned']),
            'rules' => $this->plans->applicableRules($data['on'])->where('langs', 'like', '% '.$language.' %')->count(), 'gaps' => count(array_filter($data['gaps'], fn ($g) => $g['state'] !== 'planned')),
            'language' => $langLabel,
        ]];
    }
}
