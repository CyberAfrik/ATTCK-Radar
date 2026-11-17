<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Réapplique les personnalisations de l'administration (sources, scénarios, règles maison)
 * par-dessus le référentiel importé. Appelé après chaque import et après chaque modification.
 */
class Overlays
{
    public const ORIGIN = 'CyberAfrik';

    private const LEVELS = ['informational' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

    public function __construct(private CatalogIndex $index) {}

    public function apply(bool $rebuild = true): void
    {
        foreach (DB::table('source_overrides')->get() as $o) {
            DB::table('sources')->where('id', $o->source_id)->update([
                'label' => $o->label, 'family_id' => $o->family_id, 'description' => $o->description, 'auto' => false, 'minor' => (bool) $o->minor,
            ]);
        }

        $position = (int) DB::table('playbooks')->max('position');
        foreach (DB::table('playbook_overrides')->orderBy('created_at')->get() as $o) {
            if ($o->hidden) {
                DB::table('playbooks')->where('id', $o->id)->delete();

                continue;
            }
            $data = ['label' => $o->label, 'description' => $o->description, 'techniques' => $o->techniques];
            DB::table('playbooks')->where('id', $o->id)->exists()
                ? DB::table('playbooks')->where('id', $o->id)->update($data)
                : DB::table('playbooks')->insert($data + ['id' => $o->id, 'position' => ++$position]);
        }

        $this->syncCustomRules();

        DB::table('sources')->update(['rules_count' => 0]);
        foreach (DB::table('rule_source')->selectRaw('source_id, count(*) as c')->groupBy('source_id')->get() as $row) {
            DB::table('sources')->where('id', $row->source_id)->update(['rules_count' => $row->c]);
        }
        if ($rebuild) {
            $this->index->rebuild($this->index->languages());
        }
    }

    private function syncCustomRules(): void
    {
        $old = DB::table('rules')->where('origin', self::ORIGIN)->where('kind', 'admin')->pluck('id');
        foreach (['rule_source', 'rule_technique', 'rule_tactic'] as $pivot) {
            DB::table($pivot)->whereIn('rule_id', $old)->delete();
        }
        DB::table('rules')->whereIn('id', $old)->delete();

        $sources = DB::table('sources')->pluck('id')->flip();
        $techs = DB::table('techniques')->pluck('id')->flip();
        foreach (DB::table('custom_rules')->where('active', true)->get() as $r) {
            if (DB::table('rules')->where('uid', $r->uid)->exists()) {
                continue;
            }
            $ruleTechs = array_values(array_filter(json_decode($r->techniques, true) ?: [], fn ($t) => isset($techs[$t])));
            $ruleSources = array_values(array_filter(json_decode($r->sources, true) ?: [], fn ($s) => isset($sources[$s])));
            if (! $ruleSources) {
                continue;
            }
            $id = DB::table('rules')->insertGetId([
                'uid' => $r->uid, 'origin' => self::ORIGIN, 'title' => $r->title, 'description' => $r->description,
                'level' => $r->level, 'level_rank' => self::LEVELS[$r->level] ?? 2, 'status' => 'stable',
                'author' => 'CyberAfrik', 'rule_date' => substr((string) $r->created_at, 0, 10),
                'modified' => substr((string) $r->updated_at, 0, 10), 'kind' => 'admin', 'upstream_url' => '',
                'license' => 'CyberAfrik', 'cves' => '[]', 'refs' => '[]',
                'falsepositives' => json_encode($r->falsepositives !== '' ? [$r->falsepositives] : []),
                'technique_list' => Str::limit(implode(' ', $ruleTechs), 390, ''), 'sigma' => $r->sigma, 'queries' => '{}', 'langs' => ' sigma ',
            ]);
            DB::table('rule_source')->insert(array_map(fn ($s) => ['rule_id' => $id, 'source_id' => $s], $ruleSources));
            if ($ruleTechs) {
                DB::table('rule_technique')->insert(array_map(fn ($t) => ['rule_id' => $id, 'technique_id' => $t], $ruleTechs));
                $tactics = DB::table('tactic_technique')->whereIn('technique_id', $ruleTechs)->distinct()->pluck('tactic_id');
                DB::table('rule_tactic')->insert($tactics->map(fn ($t) => ['rule_id' => $id, 'tactic_id' => $t])->all());
            }
        }
    }

    /** Valeurs d'origine d'une source ou d'un scénario, lues dans le dernier paquet importé. */
    public function packDefault(string $type, string $id): ?array
    {
        $file = storage_path('app/radar-pack/catalog.json');
        $catalog = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        foreach ($catalog[$type] ?? [] as $row) {
            if (($row['id'] ?? null) === $id) {
                return $row;
            }
        }

        return null;
    }
}
