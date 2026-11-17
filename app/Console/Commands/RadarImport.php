<?php

namespace App\Console\Commands;

use App\Services\CatalogIndex;
use App\Services\Overlays;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Importe un paquet de référentiel (manifest.json, catalog.json, rules.ndjson.gz).
 * Les empreintes SHA-256 sont vérifiées avant toute écriture, et le remplacement
 * se fait dans une seule transaction : en cas d'échec, l'ancien référentiel reste en place.
 */
class RadarImport extends Command
{
    protected $signature = 'radar:import {path? : Dossier du paquet} {--url= : URL de base où télécharger le paquet} {--force : Réimporter même si la version est déjà en place}';

    protected $description = 'Importe le référentiel ATT&CK Radar (taxonomie, sources, règles)';

    private const LEVELS = ['informational' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

    public function handle(): int
    {
        $dir = $this->argument('path') ?: storage_path('app/radar-pack');
        if ($url = $this->option('url')) {
            $this->download(rtrim($url, '/'), $dir);
        }

        $manifest = json_decode((string) @file_get_contents("$dir/manifest.json"), true);
        if (! is_array($manifest) || empty($manifest['files'])) {
            $this->error("manifest.json introuvable ou invalide dans $dir");

            return self::FAILURE;
        }
        foreach ($manifest['files'] as $name => $info) {
            $file = $dir.'/'.basename($name);
            if (! is_file($file) || ! hash_equals($info['sha256'], hash_file('sha256', $file))) {
                $this->error("Empreinte SHA-256 incorrecte pour $name : import annulé.");

                return self::FAILURE;
            }
        }
        $current = DB::table('packs')->orderByDesc('id')->value('version');
        if ($current === $manifest['version'] && ! $this->option('force')) {
            $this->info("Version {$manifest['version']} déjà en place.");

            return self::SUCCESS;
        }

        $catalog = json_decode(file_get_contents("$dir/catalog.json"), true, 512, JSON_THROW_ON_ERROR);
        if (realpath($dir) !== realpath(storage_path('app/radar-pack'))) {
            @mkdir(storage_path('app/radar-pack'), 0775, true);
            @copy("$dir/catalog.json", storage_path('app/radar-pack/catalog.json'));
        }
        $this->validateCatalog($catalog);

        DB::transaction(function () use ($catalog, $manifest, $dir) {
            // État précédent, pour le journal des changements
            $before = [];
            foreach (DB::table('rules')->where('kind', '!=', 'admin')->get(['uid', 'title', 'level', 'modified', 'technique_list']) as $old) {
                $before[$old->uid] = $old;
            }
            $changes = [];
            $hadBefore = (bool) $before;
            foreach (['rule_tactic', 'rule_technique', 'rule_source', 'rules', 'playbooks', 'sources',
                'source_families', 'tactic_technique', 'techniques', 'tactics'] as $table) {
                DB::table($table)->delete();
            }

            DB::table('tactics')->insert(array_map(fn ($t) => [
                'id' => $t['id'], 'shortname' => $t['shortname'], 'name' => $t['name'], 'position' => $t['position'],
            ], $catalog['tactics']));

            $pivot = [];
            foreach (array_chunk($catalog['techniques'], 200) as $chunk) {
                DB::table('techniques')->insert(array_map(fn ($t) => [
                    'id' => $t['id'], 'name' => Str::limit($t['name'], 190, ''), 'is_sub' => $t['is_sub'],
                    'parent_id' => $t['parent'], 'url' => $t['url'],
                ], $chunk));
                foreach ($chunk as $t) {
                    foreach (array_unique($t['tactics']) as $tac) {
                        $pivot[] = ['tactic_id' => $tac, 'technique_id' => $t['id']];
                    }
                }
            }
            foreach (array_chunk($pivot, 300) as $chunk) {
                DB::table('tactic_technique')->insert($chunk);
            }

            DB::table('source_families')->insert($catalog['families']);
            $sourceIds = [];
            foreach (array_chunk($catalog['sources'], 100) as $chunk) {
                DB::table('sources')->insert(array_map(function ($s) use (&$sourceIds) {
                    $sourceIds[$s['id']] = true;

                    return [
                        'id' => $s['id'], 'family_id' => $s['family'], 'label' => Str::limit($s['label'], 150, ''),
                        'description' => Str::limit($s['description'] ?? '', 390, ''),
                        'products' => json_encode($s['products'] ?? []), 'auto' => (bool) $s['auto'],
                        'minor' => (bool) ($s['minor'] ?? $s['auto']),
                    ];
                }, $chunk));
            }

            foreach ($catalog['playbooks'] as $i => $p) {
                DB::table('playbooks')->insert([
                    'id' => $p['id'], 'label' => $p['label'], 'description' => $p['description'] ?? '',
                    'techniques' => json_encode($p['techniques']), 'position' => $i,
                ]);
            }

            $techIds = array_flip(array_column($catalog['techniques'], 'id'));
            $tacIds = array_flip(array_column($catalog['tactics'], 'id'));
            $gz = gzopen("$dir/rules.ndjson.gz", 'rb');
            $n = 0;
            $seenUids = [];
            $rs = $rt = $rc = [];
            $flush = function () use (&$rs, &$rt, &$rc) {
                foreach ([['rule_source', &$rs], ['rule_technique', &$rt], ['rule_tactic', &$rc]] as [$table, &$rows]) {
                    foreach (array_chunk($rows, 300) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                    $rows = [];
                }
            };
            while (($line = gzgets($gz)) !== false) {
                $r = json_decode($line, true);
                if (! is_array($r) || empty($r['uid']) || empty($r['title']) || ! isset($r['sigma']) || isset($seenUids[$r['uid']])) {
                    continue;
                }
                $seenUids[$r['uid']] = true;
                $level = isset(self::LEVELS[$r['level'] ?? '']) ? $r['level'] : 'medium';
                $techs = array_values(array_filter($r['techniques'] ?? [], fn ($t) => isset($techIds[$t])));
                $id = DB::table('rules')->insertGetId([
                    'uid' => Str::limit((string) $r['uid'], 64, ''), 'origin' => $r['origin'] ?? 'sigmahq',
                    'title' => Str::limit($r['title'], 250, ''), 'description' => (string) ($r['description'] ?? ''),
                    'level' => $level, 'level_rank' => self::LEVELS[$level], 'status' => Str::limit($r['status'] ?? 'test', 20, ''),
                    'author' => Str::limit($r['author'] ?? '', 250, ''), 'rule_date' => $r['date'] ?? '', 'modified' => $r['modified'] ?? '',
                    'ls_product' => $r['logsource']['product'] ?? null, 'ls_service' => $r['logsource']['service'] ?? null,
                    'ls_category' => $r['logsource']['category'] ?? null, 'kind' => $r['kind'] ?? 'rules',
                    'upstream_url' => Str::limit($r['upstream_url'] ?? '', 390, ''), 'license' => $r['license'] ?? '',
                    'cves' => json_encode($r['cves'] ?? []), 'refs' => json_encode($r['references'] ?? []),
                    'falsepositives' => json_encode($r['falsepositives'] ?? []),
                    'technique_list' => Str::limit(implode(' ', $techs), 390, ''),
                    'sigma' => $r['sigma'], 'queries' => json_encode($r['queries'] ?? [], JSON_UNESCAPED_UNICODE),
                    'langs' => ' '.implode(' ', array_merge($r['sigma'] !== '' ? ['sigma'] : [], array_keys(array_filter($r['queries'] ?? [])))).' ',
                    'native' => isset($r['native']) ? Str::limit((string) $r['native'], 30, '') : null,
                ]);
                $prev = $before[$r['uid']] ?? null;
                if ($hadBefore && (! $prev || $prev->modified !== ($r['modified'] ?? ''))) {
                    $changes[] = ['kind' => $prev ? 'modified' : 'added', 'uid' => Str::limit((string) $r['uid'], 64, ''),
                        'title' => Str::limit($r['title'], 250, ''), 'level' => $level, 'techniques' => Str::limit(implode(' ', $techs), 390, '')];
                }
                unset($before[$r['uid']]);
                foreach (array_unique($r['sources'] ?? []) as $s) {
                    if (isset($sourceIds[$s])) {
                        $rs[] = ['rule_id' => $id, 'source_id' => $s];
                    }
                }
                foreach ($techs as $t) {
                    $rt[] = ['rule_id' => $id, 'technique_id' => $t];
                }
                foreach (array_unique($r['tactics'] ?? []) as $t) {
                    if (isset($tacIds[$t])) {
                        $rc[] = ['rule_id' => $id, 'tactic_id' => $t];
                    }
                }
                if (++$n % 200 === 0) {
                    $flush();
                }
            }
            gzclose($gz);
            $flush();
            if ($n < 100) {
                throw new RuntimeException("Seulement $n règles lues : paquet suspect, import annulé.");
            }

            foreach (DB::table('rule_source')->selectRaw('source_id, count(*) as c')->groupBy('source_id')->get() as $row) {
                DB::table('sources')->where('id', $row->source_id)->update(['rules_count' => $row->c]);
            }

            $packId = DB::table('packs')->insertGetId([
                'version' => $manifest['version'], 'attack_version' => $catalog['attack_version'],
                'sigmahq_commit' => $manifest['upstream']['sigmahq_commit'] ?? null,
                'counts' => json_encode($manifest['counts'] + ['rules_imported' => $n]),
                'conversion' => json_encode($manifest['conversion'] ?? []),
                'generated_at' => isset($manifest['generated_at']) ? date('Y-m-d H:i:s', strtotime($manifest['generated_at'])) : null,
                'imported_at' => now(),
            ]);
            if ($hadBefore) {
                foreach ($before as $gone) {
                    $changes[] = ['kind' => 'removed', 'uid' => $gone->uid, 'title' => $gone->title, 'level' => $gone->level, 'techniques' => $gone->technique_list];
                }
            }
            foreach (array_chunk(array_slice($changes, 0, 5000), 200) as $chunk) {
                DB::table('pack_changes')->insert(array_map(fn ($c) => $c + ['pack_id' => $packId], $chunk));
            }
            $this->info("$n règles importées, ".count($changes).' changements.');
        });

        app(CatalogIndex::class)->rebuild($catalog['languages'] ?? []);
        app(Overlays::class)->apply();
        $this->info("Référentiel {$manifest['version']} en place (ATT&CK {$catalog['attack_version']}).");

        return self::SUCCESS;
    }

    private function validateCatalog(array $c): void
    {
        foreach (['attack_version', 'tactics', 'techniques', 'families', 'sources', 'playbooks'] as $key) {
            if (empty($c[$key])) {
                throw new RuntimeException("catalog.json : clé « $key » absente.");
            }
        }
        foreach ($c['techniques'] as $t) {
            if (! preg_match('/^T\d{4}(\.\d{3})?$/', $t['id'] ?? '')) {
                throw new RuntimeException('catalog.json : identifiant de technique invalide.');
            }
        }
        foreach ($c['sources'] as $s) {
            if (! preg_match('/^[a-z0-9_]{2,80}$/', $s['id'] ?? '')) {
                throw new RuntimeException('catalog.json : identifiant de source invalide.');
            }
        }
    }

    private function download(string $base, string $dir): void
    {
        if (! str_starts_with($base, 'https://')) {
            throw new RuntimeException('Seules les URL https sont acceptées.');
        }
        @mkdir($dir, 0775, true);
        foreach (['manifest.json', 'catalog.json', 'rules.ndjson.gz'] as $file) {
            $this->line("Téléchargement de $file");
            Http::timeout(120)->sink("$dir/$file")->get("$base/$file")->throw();
        }
    }
}
