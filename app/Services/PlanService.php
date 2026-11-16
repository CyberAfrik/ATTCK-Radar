<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Calcul de couverture côté serveur (utilisé pour l'export).
 * Règle de base : une règle est applicable si au moins une des sources qui fournissent
 * sa télémétrie est en place. Une technique est détectable si au moins une règle applicable la vise.
 */
class PlanService
{
    /** Ne garde que les sources connues et les états valides. */
    public function sanitizeStates(array $states): array
    {
        $known = DB::table('sources')->pluck('id')->flip();
        $clean = [];
        foreach ($states as $id => $state) {
            if (is_string($id) && isset($known[$id]) && in_array($state, ['on', 'planned'], true)) {
                $clean[$id] = $state;
            }
        }

        return $clean;
    }

    public function sanitizePlaybooks(array $ids): array
    {
        return DB::table('playbooks')->whereIn('id', array_filter($ids, 'is_string'))->pluck('id')->all();
    }

    public function applicableRules(array $sourceIds): Builder
    {
        return DB::table('rules')->whereIn('rules.id', function ($q) use ($sourceIds) {
            $q->select('rule_id')->from('rule_source')->whereIn('source_id', $sourceIds ?: ['__none__']);
        });
    }

    public function compute(array $states, array $playbookIds): array
    {
        $on = array_keys(array_filter($states, fn ($s) => $s === 'on'));
        $planned = array_keys(array_filter($states, fn ($s) => $s === 'planned'));

        $techs = DB::table('techniques')->get()->keyBy('id');
        $rootOf = fn (string $id) => $techs[$id]->parent_id ?? $id;

        $rootsFor = function (array $sourceIds) use ($rootOf) {
            if (! $sourceIds) {
                return [];
            }
            $ids = DB::table('rule_source')->join('rule_technique', 'rule_source.rule_id', '=', 'rule_technique.rule_id')
                ->whereIn('source_id', $sourceIds)->distinct()->pluck('technique_id');
            $roots = [];
            foreach ($ids as $id) {
                $roots[$rootOf($id)] = true;
            }

            return $roots;
        };
        $rootsOn = $rootsFor($on);
        $rootsPlan = $rootsFor(array_merge($on, $planned));
        $rootsAny = [];
        foreach (DB::table('rule_technique')->distinct()->pluck('technique_id') as $id) {
            $rootsAny[$rootOf($id)] = true;
        }

        $stateOf = fn (string $root) => isset($rootsOn[$root]) ? 'on'
            : (isset($rootsPlan[$root]) ? 'planned' : (isset($rootsAny[$root]) ? 'gap' : 'none'));

        $tacticRows = [];
        $all = ['total' => 0, 'on' => 0, 'planned' => 0];
        $seen = [];
        $techTactic = [];
        foreach (DB::table('tactics')->orderBy('position')->get() as $tac) {
            $roots = DB::table('tactic_technique')->where('tactic_id', $tac->id)->pluck('technique_id')
                ->filter(fn ($id) => isset($techs[$id]) && $techs[$id]->parent_id === null)->values();
            $row = ['id' => $tac->id, 'name' => $tac->name, 'total' => $roots->count(), 'on' => 0, 'planned' => 0, 'gap' => 0, 'none' => 0];
            foreach ($roots as $root) {
                $state = $stateOf($root);
                $row[$state]++;
                $techTactic[$root] ??= $tac->name;
                if (! isset($seen[$root])) {
                    $seen[$root] = $state;
                    $all['total']++;
                    $all['on'] += $state === 'on' ? 1 : 0;
                    $all['planned'] += $state === 'planned' ? 1 : 0;
                }
            }
            $tacticRows[] = $row;
        }

        // Angles morts : techniques des scénarios choisis (ou toutes, à défaut) non détectables aujourd'hui
        $priority = [];
        foreach (DB::table('playbooks')->whereIn('id', $playbookIds ?: ['__none__'])->get() as $pb) {
            foreach (json_decode($pb->techniques, true) ?: [] as $t) {
                $priority[$rootOf($t)][] = $pb->label;
            }
        }
        $sourceLabels = DB::table('sources')->pluck('label', 'id');
        $candidates = $priority ? array_keys($priority) : array_keys($rootsAny);
        $gaps = [];
        foreach ($candidates as $root) {
            if (! isset($techs[$root]) || isset($rootsOn[$root])) {
                continue;
            }
            $family = DB::table('techniques')->where('id', $root)->orWhere('parent_id', $root)->pluck('id');
            $providers = DB::table('rule_technique')->join('rule_source', 'rule_technique.rule_id', '=', 'rule_source.rule_id')
                ->whereIn('technique_id', $family)->selectRaw('source_id, count(distinct rule_source.rule_id) as c')
                ->groupBy('source_id')->orderByDesc('c')->limit(4)->get();
            $gaps[] = [
                'id' => $root, 'name' => $techs[$root]->name, 'tactic' => $techTactic[$root] ?? '',
                'state' => isset($rootsPlan[$root]) ? 'planned' : (isset($rootsAny[$root]) ? 'gap' : 'none'),
                'scenarios' => implode(', ', array_unique($priority[$root] ?? [])),
                'providers' => $providers->map(fn ($p) => ($sourceLabels[$p->source_id] ?? $p->source_id)." ({$p->c} règles)")->implode(' ou '),
            ];
        }
        usort($gaps, fn ($a, $b) => [$a['state'], $a['id']] <=> [$b['state'], $b['id']]);

        $sources = DB::table('sources')->join('source_families', 'sources.family_id', '=', 'source_families.id')
            ->whereIn('sources.id', array_merge($on, $planned))
            ->orderBy('source_families.position')->orderByDesc('rules_count')
            ->get(['sources.*', 'source_families.label as family_label'])
            ->map(fn ($s) => [
                'id' => $s->id, 'label' => $s->label, 'family' => $s->family_label, 'state' => $states[$s->id],
                'description' => $s->description, 'products' => implode(', ', json_decode($s->products, true) ?: []),
                'rules' => (int) $s->rules_count, 'techniques' => (int) $s->techniques_count,
            ])->all();

        return [
            'on' => $on, 'planned' => $planned,
            'totals' => $all + [
                'rules_on' => $this->applicableRules($on)->count(),
                'rules_planned' => $this->applicableRules(array_merge($on, $planned))->count(),
            ],
            'tactics' => $tacticRows, 'gaps' => $gaps, 'sources' => $sources,
            'techStates' => $seen, 'techNames' => $techs->map(fn ($t) => $t->name)->all(),
        ];
    }
}
