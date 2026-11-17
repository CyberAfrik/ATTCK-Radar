<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Index compact envoyé au navigateur : il permet de calculer la couverture en direct,
 * sans aller-retour serveur à chaque clic. Reconstruit à chaque import du référentiel.
 */
class CatalogIndex
{
    public function path(): string
    {
        return storage_path('app/radar/index.json');
    }

    public function get(): ?string
    {
        return is_file($this->path()) ? file_get_contents($this->path()) : null;
    }

    public function rebuild(array $languages = []): void
    {
        $pack = DB::table('packs')->orderByDesc('id')->first();
        $tacticTechs = [];
        foreach (DB::table('tactic_technique')->get() as $row) {
            $tacticTechs[$row->tactic_id][] = $row->technique_id;
        }
        $techniques = [];
        foreach (DB::table('techniques')->orderBy('id')->get() as $t) {
            $techniques[$t->id] = ['n' => $t->name, 'p' => $t->parent_id];
        }
        $tactics = DB::table('tactics')->orderBy('position')->get()->map(function ($t) use ($tacticTechs, $techniques) {
            $roots = array_values(array_filter($tacticTechs[$t->id] ?? [], fn ($id) => $techniques[$id]['p'] === null));
            sort($roots);

            return ['id' => $t->id, 'name' => $t->name, 'shortname' => $t->shortname, 'techniques' => $roots];
        })->all();

        $sourceRules = $sourceTechs = [];
        foreach (DB::table('rule_source')->orderBy('rule_id')->get() as $row) {
            $sourceRules[$row->source_id][] = (int) $row->rule_id;
        }
        $pairs = DB::table('rule_source')->join('rule_technique', 'rule_source.rule_id', '=', 'rule_technique.rule_id')
            ->select('source_id', 'technique_id')->distinct()->get();
        foreach ($pairs as $row) {
            $sourceTechs[$row->source_id][] = $row->technique_id;
        }
        foreach ($sourceTechs as $sid => $list) {
            DB::table('sources')->where('id', $sid)->update(['techniques_count' => count($list)]);
        }

        $sources = DB::table('sources')->orderBy('minor')->orderByDesc('rules_count')->get()->map(fn ($s) => [
            'id' => $s->id, 'family' => $s->family_id, 'label' => $s->label, 'description' => $s->description,
            'products' => json_decode($s->products, true) ?: [], 'auto' => (bool) $s->auto || (bool) $s->minor,
            'rules' => (int) $s->rules_count, 'techniques' => count($sourceTechs[$s->id] ?? []),
        ])->all();

        $index = [
            'pack' => $pack ? [
                'version' => $pack->version, 'attack' => $pack->attack_version,
                'generated_at' => $pack->generated_at, 'rules' => DB::table('rules')->count(),
                'origins' => DB::table('rules')->selectRaw('origin, count(*) as c')->groupBy('origin')->pluck('c', 'origin'),
            ] : null,
            'languages' => $languages ?: $this->defaultLanguages(),
            'families' => DB::table('source_families')->orderBy('position')->get(['id', 'label'])->all(),
            'sources' => $sources,
            'tactics' => $tactics,
            'techniques' => $techniques,
            'playbooks' => DB::table('playbooks')->orderBy('position')->get()->map(fn ($p) => [
                'id' => $p->id, 'label' => $p->label, 'description' => $p->description,
                'techniques' => json_decode($p->techniques, true) ?: [],
            ])->all(),
            'sourceRules' => $sourceRules,
            'sourceTechs' => $sourceTechs,
        ];

        @mkdir(dirname($this->path()), 0775, true);
        file_put_contents($this->path(), json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    public function languages(): array
    {
        $json = $this->get();

        return $json ? (json_decode($json, true)['languages'] ?? $this->defaultLanguages()) : $this->defaultLanguages();
    }

    private function defaultLanguages(): array
    {
        return [['id' => 'sigma', 'label' => 'Sigma', 'note' => '']];
    }
}
