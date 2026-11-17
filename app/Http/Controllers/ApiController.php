<?php

namespace App\Http\Controllers;

use App\Jobs\SendPlanExport;
use App\Services\CatalogIndex;
use App\Services\PlanExporter;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ApiController extends Controller
{
    public function __construct(private PlanService $plans, private CatalogIndex $index) {}

    public function catalog()
    {
        $json = $this->index->get();
        abort_if($json === null, 503, 'Référentiel en cours de chargement.');

        return response($json, 200, ['Content-Type' => 'application/json', 'Cache-Control' => 'public, max-age=300'])
            ->setEtag(md5($json));
    }

    public function rulesSearch(Request $request)
    {
        $v = $request->validate([
            'sources' => ['present', 'array', 'max:300'],
            'sources.*' => ['string', 'max:80'],
            'q' => ['nullable', 'string', 'max:100'],
            'level' => ['nullable', Rule::in(['critical', 'high', 'medium', 'low', 'informational'])],
            'tactic' => ['nullable', 'regex:/^TA\d{4}$/'],
            'technique' => ['nullable', 'regex:/^T\d{4}(\.\d{3})?$/'],
            'source' => ['nullable', 'string', 'max:80'],
            'origin' => ['nullable', 'string', 'max:30'],
            'lang' => ['nullable', 'regex:/^[a-z_]{2,30}$/'],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $base = $this->plans->applicableRules($v['sources']);
        if (! empty($v['q'])) {
            $like = '%'.addcslashes($v['q'], '%_\\').'%';
            $base->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('technique_list', 'like', $like));
        }
        if (! empty($v['tactic'])) {
            $base->whereIn('rules.id', fn ($q) => $q->select('rule_id')->from('rule_tactic')->where('tactic_id', $v['tactic']));
        }
        if (! empty($v['technique'])) {
            $root = explode('.', $v['technique'])[0];
            $ids = str_contains($v['technique'], '.') ? [$v['technique']]
                : DB::table('techniques')->where('id', $root)->orWhere('parent_id', $root)->pluck('id')->all();
            $base->whereIn('rules.id', fn ($q) => $q->select('rule_id')->from('rule_technique')->whereIn('technique_id', $ids));
        }
        if (! empty($v['source'])) {
            $base->whereIn('rules.id', fn ($q) => $q->select('rule_id')->from('rule_source')->where('source_id', $v['source']));
        }

        if (! empty($v['lang'])) {
            $base->where('langs', 'like', '% '.$v['lang'].' %');
        }
        $origins = (clone $base)->selectRaw('origin, count(*) as c')->groupBy('origin')->pluck('c', 'origin');
        if (! empty($v['origin'])) {
            $base->where('origin', $v['origin']);
        }
        $levels = (clone $base)->selectRaw('level, count(*) as c')->groupBy('level')->pluck('c', 'level');
        if (! empty($v['level'])) {
            $base->where('level', $v['level']);
        }
        $total = (clone $base)->count();
        $page = (int) ($v['page'] ?? 1);
        $items = $base->orderByDesc('level_rank')->orderBy('title')->forPage($page, 25)
            ->get(['id', 'title', 'level', 'technique_list', 'modified', 'origin']);
        $srcs = DB::table('rule_source')->whereIn('rule_id', $items->pluck('id'))->get()->groupBy('rule_id');

        return [
            'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / 25), 'levels' => $levels, 'origins' => $origins,
            'items' => $items->map(fn ($r) => [
                'id' => $r->id, 'title' => $r->title, 'level' => $r->level, 'modified' => $r->modified, 'origin' => $r->origin,
                'techniques' => $r->technique_list === '' ? [] : explode(' ', $r->technique_list),
                'sources' => ($srcs[$r->id] ?? collect())->pluck('source_id')->all(),
            ]),
        ];
    }

    public function rule(int $id)
    {
        $r = DB::table('rules')->find($id);
        abort_if(! $r, 404);

        return [
            'id' => $r->id, 'uid' => $r->uid, 'title' => $r->title, 'description' => $r->description,
            'level' => $r->level, 'status' => $r->status, 'author' => $r->author, 'date' => $r->rule_date, 'modified' => $r->modified,
            'logsource' => array_filter(['product' => $r->ls_product, 'service' => $r->ls_service, 'category' => $r->ls_category]),
            'techniques' => $r->technique_list === '' ? [] : explode(' ', $r->technique_list),
            'sources' => DB::table('rule_source')->where('rule_id', $r->id)->pluck('source_id'),
            'cves' => json_decode($r->cves, true), 'references' => json_decode($r->refs, true),
            'falsepositives' => json_decode($r->falsepositives, true),
            'upstream_url' => $r->upstream_url, 'license' => $r->license, 'origin' => $r->origin, 'native' => $r->native,
            'queries' => ($r->sigma !== '' ? ['sigma' => $r->sigma] : []) + (json_decode($r->queries, true) ?: []),
        ];
    }

    public function export(Request $request)
    {
        $languages = array_column($this->index->languages(), 'id');
        $v = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'consent' => ['nullable', 'boolean'],
            'sources' => ['required', 'array', 'min:1', 'max:300'],
            'playbooks' => ['nullable', 'array', 'max:50'],
            'language' => ['required', Rule::in($languages)],
            'website' => ['nullable', 'string', 'max:200'],
        ], [
            'email.required' => 'Saisissez votre adresse email.',
            'email.email' => 'Saisissez une adresse email valide.',
            'email.max' => 'Cette adresse email est trop longue.',
            'sources.required' => 'Sélectionnez au moins une source.',
            'language.in' => 'Ce langage de requête n\'est pas disponible.',
        ]);

        // Champ piège : rempli uniquement par les robots. On répond comme si tout allait bien.
        if (! empty($v['website'])) {
            return response()->json(['ok' => true], 202);
        }

        $email = Str::lower(trim($v['email']));
        $ipKey = 'export-ip:'.hash('sha256', (string) $request->ip());
        $mailKey = 'export-mail:'.hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($ipKey, 5) || RateLimiter::tooManyAttempts($mailKey, 3)) {
            return response()->json(['message' => 'Trop de demandes pour le moment. Réessayez plus tard.'], 429);
        }
        RateLimiter::hit($ipKey, 3600);
        RateLimiter::hit($mailKey, 86400);

        $states = $this->plans->sanitizeStates($v['sources']);
        if (! in_array('on', $states, true) && ! in_array('planned', $states, true)) {
            return response()->json(['message' => 'Sélectionnez au moins une source.'], 422);
        }

        $exportId = DB::transaction(function () use ($states, $v, $email, $request) {
            $planId = DB::table('plans')->insertGetId([
                'token' => Str::random(40), 'sources' => json_encode($states),
                'playbooks' => json_encode($this->plans->sanitizePlaybooks($v['playbooks'] ?? [])),
                'language' => $v['language'], 'pack_version' => DB::table('packs')->orderByDesc('id')->value('version'),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $consent = (bool) ($v['consent'] ?? false);

            return DB::table('export_requests')->insertGetId([
                'plan_id' => $planId, 'email' => $email, 'marketing_consent' => $consent,
                'consent_at' => $consent ? now() : null, 'unsubscribe_token' => Str::random(40),
                'ip_hash' => hash('sha256', $request->ip().'|'.config('app.key')), 'status' => 'queued',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $this->count('export');
        SendPlanExport::dispatch($exportId);

        return response()->json(['ok' => true], 202);
    }

    public function plan(string $token)
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{40}$/', $token) === 1, 404);
        $plan = DB::table('plans')->where('token', $token)->first();
        abort_if(! $plan, 404);
        $states = $this->plans->sanitizeStates(json_decode($plan->sources, true) ?: []);
        $on = array_keys(array_filter($states, fn ($s) => $s === 'on'));

        return [
            'sources' => (object) $states, 'playbooks' => json_decode($plan->playbooks, true) ?: [],
            'language' => $plan->language, 'created_at' => substr($plan->created_at, 0, 10),
            'new_rules' => $this->plans->applicableRules($on)->where('modified', '>', substr($plan->created_at, 0, 10))->count(),
        ];
    }

    /**
     * Téléchargement du classeur ou du calque Navigator, généré à la demande.
     * Le lien est envoyé par email : les fichiers ne sont pas joints au message, car un classeur
     * rempli de signatures d'attaque est rejeté comme « virus » par les passerelles de messagerie.
     */
    public function download(string $token, string $file, PlanExporter $exporter)
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{40}$/', $token) === 1, 404);
        $plan = DB::table('plans')->where('token', $token)->first();
        abort_if(! $plan, 404);
        abort_unless(DB::table('export_requests')->where('plan_id', $plan->id)->exists(), 404);

        $files = $exporter->build(
            json_decode($plan->sources, true) ?: [], json_decode($plan->playbooks, true) ?: [],
            $plan->language, url('/p/'.$plan->token)
        );
        [$path, $other, $name, $mime] = $file === 'classeur'
            ? [$files['xlsx'], $files['layer'], 'ATTCK-Radar-plan-de-detection.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            : [$files['layer'], $files['xlsx'], 'ATTCK-Radar-calque-navigator.json', 'application/json'];
        @unlink($other);

        return response()->download($path, $name, [
            'Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex',
        ])->deleteFileAfterSend(true);
    }

    /** Compteur d'usage anonyme : un total par jour et par étape, sans adresse IP ni identifiant. */
    public function hit(Request $request)
    {
        $v = $request->validate(['k' => ['required', Rule::in(['accueil', 'sources', 'priorites', 'plan', 'nouveautes'])]]);
        $this->count($v['k']);

        return response()->noContent();
    }

    private function count(string $metric): void
    {
        try {
            DB::table('usage_daily')->upsert(
                [['day' => now()->toDateString(), 'metric' => $metric, 'hits' => 1]],
                ['day', 'metric'], ['hits' => DB::raw('usage_daily.hits + 1')]
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function changelog()
    {
        $packs = DB::table('packs')->orderByDesc('id')->limit(6)->get();
        $out = [];
        foreach ($packs as $p) {
            $counts = DB::table('pack_changes')->where('pack_id', $p->id)->selectRaw('kind, count(*) as c')->groupBy('kind')->pluck('c', 'kind');
            $list = fn (string $kind) => DB::table('pack_changes')->where('pack_id', $p->id)->where('kind', $kind)
                ->orderBy('title')->limit(60)->get(['title', 'level', 'techniques'])->all();
            $out[] = [
                'version' => $p->version, 'attack' => $p->attack_version, 'date' => substr((string) $p->imported_at, 0, 10),
                'counts' => ['added' => (int) ($counts['added'] ?? 0), 'modified' => (int) ($counts['modified'] ?? 0), 'removed' => (int) ($counts['removed'] ?? 0)],
                'added' => $list('added'), 'modified' => $list('modified'), 'removed' => $list('removed'),
            ];
        }

        return response()->json(['packs' => $out])->header('Cache-Control', 'public, max-age=300');
    }
}
