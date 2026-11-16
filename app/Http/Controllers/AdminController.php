<?php

namespace App\Http\Controllers;

use App\Jobs\SendPlanExport;
use App\Services\Overlays;
use App\Services\Watchdog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class AdminController extends Controller
{
    public function loginForm(Request $request)
    {
        return response()->view('admin.login', ['sent' => (bool) $request->session()->get('sent')])
            ->header('Cache-Control', 'private, no-store');
    }

    /** Envoie un lien de connexion à usage unique. La réponse est identique que l'adresse soit autorisée ou non. */
    public function sendLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:190']]);
        $email = Str::lower(trim($data['email']));
        $key = 'admin-login:'.hash('sha256', (string) $request->ip());
        if (! RateLimiter::tooManyAttempts($key, 5)) {
            RateLimiter::hit($key, 900);
            if (in_array($email, config('radar.admins'), true)) {
                $token = Str::random(48);
                Cache::put('admin-token:'.hash('sha256', $token), $email, now()->addMinutes(15));
                $link = url('/admin/entrer/'.$token);
                Mail::raw("Bonjour,\n\nVoici votre lien de connexion à l'administration d'ATT&CK Radar. Il est valable 15 minutes et ne fonctionne qu'une fois.\n\n$link\n\nSi vous n'avez pas demandé ce lien, ignorez ce message.", function ($m) use ($email) {
                    $m->to($email)->subject('Connexion à l\'administration ATT&CK Radar');
                });
            }
        }

        return redirect('/admin/connexion')->with('sent', true);
    }

    public function enter(Request $request, string $token)
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{48}$/', $token) === 1, 404);
        $email = Cache::pull('admin-token:'.hash('sha256', $token));
        if (! $email || ! in_array($email, config('radar.admins'), true)) {
            return redirect('/admin/connexion');
        }
        $request->session()->regenerate();
        $request->session()->put('radar_admin', $email);

        return redirect('/admin');
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/connexion');
    }

    private function exportsQuery(Request $request)
    {
        $q = DB::table('export_requests')->join('plans', 'plans.id', '=', 'export_requests.plan_id')
            ->select('export_requests.*', 'plans.token', 'plans.sources', 'plans.playbooks', 'plans.language');
        if ($s = trim((string) $request->query('q'))) {
            $q->where('export_requests.email', 'like', '%'.addcslashes(Str::limit($s, 100, ''), '%_\\').'%');
        }
        if (in_array($request->query('statut'), ['queued', 'sent', 'failed'], true)) {
            $q->where('export_requests.status', $request->query('statut'));
        }
        if ($request->query('consentement') === '1') {
            $q->where('marketing_consent', true);
        }

        return $q->orderByDesc('export_requests.id');
    }

    private function decorate($row, $labels, $playbooks)
    {
        $states = json_decode($row->sources, true) ?: [];
        $row->on = array_keys(array_filter($states, fn ($s) => $s === 'on'));
        $row->planned = array_keys(array_filter($states, fn ($s) => $s === 'planned'));
        $row->on_labels = implode(', ', array_map(fn ($s) => $labels[$s] ?? $s, $row->on));
        $row->planned_labels = implode(', ', array_map(fn ($s) => $labels[$s] ?? $s, $row->planned));
        $row->playbook_labels = implode(', ', array_map(fn ($p) => $playbooks[$p] ?? $p, json_decode($row->playbooks, true) ?: []));

        return $row;
    }

    public function dashboard(Request $request)
    {
        $labels = DB::table('sources')->pluck('label', 'id');
        $playbooks = DB::table('playbooks')->pluck('label', 'id');
        $exports = $this->exportsQuery($request)->paginate(50)->withQueryString();
        $exports->getCollection()->transform(fn ($r) => $this->decorate($r, $labels, $playbooks));

        $src = $lang = $pb = [];
        foreach (DB::table('plans')->whereIn('id', DB::table('export_requests')->select('plan_id'))->get() as $p) {
            foreach (json_decode($p->sources, true) ?: [] as $id => $state) {
                if ($state === 'on') {
                    $src[$id] = ($src[$id] ?? 0) + 1;
                }
            }
            foreach (json_decode($p->playbooks, true) ?: [] as $id) {
                $pb[$id] = ($pb[$id] ?? 0) + 1;
            }
            $lang[$p->language] = ($lang[$p->language] ?? 0) + 1;
        }
        arsort($src);
        arsort($pb);
        arsort($lang);
        $top = fn ($arr, $names, $n = 8) => collect(array_slice($arr, 0, $n, true))->map(fn ($c, $id) => ['label' => $names[$id] ?? $id, 'count' => $c])->values();

        return view('admin.dashboard', [
            'admin' => $request->session()->get('radar_admin'),
            'exports' => $exports,
            'filters' => $request->only(['q', 'statut', 'consentement']),
            'message' => $request->session()->get('message'), 'retention' => (int) config('radar.retention_days'),
            'kpis' => [
                'Demandes d\'export' => DB::table('export_requests')->count(),
                'Adresses distinctes' => DB::table('export_requests')->distinct()->count('email'),
                'Accord pour les actualités' => DB::table('export_requests')->where('marketing_consent', true)->whereNull('unsubscribed_at')->distinct()->count('email'),
                '7 derniers jours' => DB::table('export_requests')->where('created_at', '>=', now()->subDays(7))->count(),
                'En attente' => DB::table('export_requests')->where('status', 'queued')->count(),
                'En échec' => DB::table('export_requests')->where('status', 'failed')->count(),
            ],
            'topSources' => $top($src, $labels), 'topPlaybooks' => $top($pb, $playbooks),
            'topLanguages' => $top($lang, [], 7),
        ]);
    }

    public function csv(Request $request)
    {
        $labels = DB::table('sources')->pluck('label', 'id');
        $playbooks = DB::table('playbooks')->pluck('label', 'id');
        $rows = $this->exportsQuery($request)->limit(20000)->get();
        // Protection contre l'injection de formules à l'ouverture dans un tableur
        $safe = fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'".$v : (string) $v;

        return response()->streamDownload(function () use ($rows, $labels, $playbooks, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date (UTC)', 'Email', 'Statut', 'Envoyé le', 'Accord actualités', 'Désinscrit le', 'Langage', 'Sources en place', 'Sources prévues', 'Scénarios', 'Erreur'], ';');
            foreach ($rows as $r) {
                $r = $this->decorate($r, $labels, $playbooks);
                fputcsv($out, array_map($safe, [
                    $r->created_at, $r->email, $r->status, $r->sent_at, $r->marketing_consent ? 'oui' : 'non',
                    $r->unsubscribed_at, $r->language, $r->on_labels, $r->planned_labels, $r->playbook_labels, $r->error,
                ]), ';');
            }
            fclose($out);
        }, 'attck-radar-exports-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function referential(Request $request)
    {
        $pack = DB::table('packs')->orderByDesc('id')->first();
        $latest = Cache::remember('attack-latest', now()->addHours(6), function () {
            try {
                $index = Http::timeout(6)->get(config('radar.attack_index'))->throw()->json();
                foreach ($index['collections'] ?? [] as $c) {
                    if (($c['name'] ?? '') === 'Enterprise ATT&CK') {
                        $versions = array_column($c['versions'] ?? [], 'version');
                        usort($versions, 'version_compare');

                        return end($versions) ?: null;
                    }
                }
            } catch (Throwable $e) {
                return null;
            }

            return null;
        });
        if ($latest === null) {
            Cache::forget('attack-latest');
        }

        return view('admin.referential', [
            'admin' => $request->session()->get('radar_admin'),
            'pack' => $pack,
            'counts' => $pack ? json_decode($pack->counts, true) : [],
            'conversion' => $pack ? (json_decode($pack->conversion, true) ?: []) : [],
            'history' => DB::table('packs')->orderByDesc('id')->limit(10)->get(),
            'latest' => $latest,
            'upToDate' => $pack && $latest ? version_compare($pack->attack_version, $latest, '>=') : null,
            'packUrl' => config('radar.pack_url'),
            'autoSources' => DB::table('sources')->where('auto', true)->orderByDesc('rules_count')->get(['label', 'rules_count']),
            'noRule' => DB::table('techniques')->whereNull('parent_id')
                ->whereNotIn('id', DB::table('rule_technique')->join('techniques as t', 't.id', '=', 'rule_technique.technique_id')
                    ->selectRaw('coalesce(t.parent_id, t.id)'))->count(),
            'roots' => DB::table('techniques')->whereNull('parent_id')->count(),
            'message' => $request->session()->get('message'),
        ]);
    }

    public function importNow(Watchdog $watchdog)
    {
        set_time_limit(280);

        return redirect('/admin/referentiel')->with('message', $watchdog->importPack());
    }

    // ---------------------------------------------------------------- Exports

    public function resend(int $id)
    {
        $export = DB::table('export_requests')->find($id);
        abort_if(! $export, 404);
        DB::table('export_requests')->where('id', $id)->update(['status' => 'queued', 'error' => null, 'updated_at' => now()]);
        SendPlanExport::dispatch($id);

        return back()->with('message', 'Envoi relancé vers '.$export->email.'. Il part dans la minute.');
    }

    public function purge(Request $request, Watchdog $watchdog)
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:190']]);
        $n = $watchdog->purgeEmail(Str::lower(trim($data['email'])));

        return back()->with('message', $n ? "$n demande(s) et leurs plans supprimés pour {$data['email']}." : "Aucune donnée trouvée pour {$data['email']}.");
    }

    // ---------------------------------------------------------------- Sources

    public function sources(Request $request)
    {
        $overridden = DB::table('source_overrides')->pluck('source_id')->flip();
        $rows = DB::table('sources')->orderByDesc('auto')->orderBy('family_id')->orderByDesc('rules_count')->get()
            ->map(function ($s) use ($overridden) {
                $s->overridden = isset($overridden[$s->id]);

                return $s;
            });

        return view('admin.sources', [
            'admin' => $request->session()->get('radar_admin'), 'message' => $request->session()->get('message'),
            'sources' => $rows, 'families' => DB::table('source_families')->orderBy('position')->pluck('label', 'id'),
            'todo' => $rows->where('auto', true)->count(),
        ]);
    }

    public function sourceSave(Request $request, Overlays $overlays, string $id)
    {
        abort_unless(DB::table('sources')->where('id', $id)->exists(), 404);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:150'],
            'family_id' => ['required', Rule::in(DB::table('source_families')->pluck('id')->all())],
            'description' => ['nullable', 'string', 'max:390'],
        ]);
        DB::table('source_overrides')->updateOrInsert(['source_id' => $id], [
            'label' => $data['label'], 'family_id' => $data['family_id'], 'description' => $data['description'] ?? '',
            'updated_at' => now(), 'created_at' => now(),
        ]);
        $overlays->apply();

        return redirect('/admin/sources#s-'.$id)->with('message', 'Source « '.$data['label'].' » enregistrée.');
    }

    public function sourceReset(Overlays $overlays, string $id)
    {
        DB::table('source_overrides')->where('source_id', $id)->delete();
        if ($d = $overlays->packDefault('sources', $id)) {
            DB::table('sources')->where('id', $id)->update([
                'label' => Str::limit($d['label'], 150, ''), 'family_id' => $d['family'],
                'description' => Str::limit($d['description'] ?? '', 390, ''), 'auto' => (bool) $d['auto'],
            ]);
        }
        $overlays->apply();

        return redirect('/admin/sources#s-'.$id)->with('message', 'Valeurs d\'origine rétablies.');
    }

    // ---------------------------------------------------------------- Scénarios

    private function parseTechniques(?string $raw): array
    {
        preg_match_all('/T\d{4}(?:\.\d{3})?/i', (string) $raw, $m);
        $ids = array_values(array_unique(array_map('strtoupper', $m[0])));

        return DB::table('techniques')->whereIn('id', $ids ?: ['-'])->pluck('id')->all();
    }

    public function playbooks(Request $request)
    {
        $over = DB::table('playbook_overrides')->get()->keyBy('id');
        $rows = DB::table('playbooks')->orderBy('position')->get()->map(function ($p) use ($over) {
            $p->techniques = implode(', ', json_decode($p->techniques, true) ?: []);
            $p->overridden = isset($over[$p->id]);

            return $p;
        });

        return view('admin.playbooks', [
            'admin' => $request->session()->get('radar_admin'), 'message' => $request->session()->get('message'),
            'playbooks' => $rows, 'hidden' => $over->where('hidden', true)->values(),
        ]);
    }

    public function playbookSave(Request $request, Overlays $overlays)
    {
        $data = $request->validate([
            'id' => ['nullable', 'string', 'regex:/^[a-z0-9_]{2,60}$/'],
            'label' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:390'],
            'techniques' => ['required', 'string', 'max:4000'],
        ]);
        $techs = $this->parseTechniques($data['techniques']);
        if (! $techs) {
            return back()->withInput()->with('message', 'Aucune technique ATT&CK valide reconnue (format attendu : T1486, T1059.001).');
        }
        $id = ($data['id'] ?? null) ?: 'perso_'.Str::lower(Str::random(8));
        DB::table('playbook_overrides')->updateOrInsert(['id' => $id], [
            'label' => $data['label'], 'description' => $data['description'] ?? '', 'techniques' => json_encode($techs),
            'hidden' => false, 'updated_at' => now(), 'created_at' => DB::table('playbook_overrides')->where('id', $id)->value('created_at') ?? now(),
        ]);
        $overlays->apply();

        return redirect('/admin/scenarios')->with('message', 'Scénario « '.$data['label'].' » enregistré avec '.count($techs).' techniques.');
    }

    public function playbookHide(Overlays $overlays, string $id)
    {
        $p = DB::table('playbooks')->where('id', $id)->first();
        abort_if(! $p, 404);
        if ($overlays->packDefault('playbooks', $id)) {
            DB::table('playbook_overrides')->updateOrInsert(['id' => $id], [
                'label' => $p->label, 'description' => $p->description, 'techniques' => $p->techniques,
                'hidden' => true, 'updated_at' => now(), 'created_at' => now(),
            ]);
        } else {
            DB::table('playbook_overrides')->where('id', $id)->delete();
            DB::table('playbooks')->where('id', $id)->delete();
        }
        $overlays->apply();

        return redirect('/admin/scenarios')->with('message', 'Scénario « '.$p->label.' » retiré du site.');
    }

    public function playbookReset(Overlays $overlays, string $id)
    {
        DB::table('playbook_overrides')->where('id', $id)->delete();
        if ($d = $overlays->packDefault('playbooks', $id)) {
            $row = ['label' => $d['label'], 'description' => $d['description'] ?? '', 'techniques' => json_encode($d['techniques'])];
            DB::table('playbooks')->where('id', $id)->exists()
                ? DB::table('playbooks')->where('id', $id)->update($row)
                : DB::table('playbooks')->insert($row + ['id' => $id, 'position' => (int) DB::table('playbooks')->max('position') + 1]);
        }
        $overlays->apply();

        return redirect('/admin/scenarios')->with('message', 'Scénario d\'origine rétabli.');
    }

    // ---------------------------------------------------------------- Règles maison

    public function rules(Request $request)
    {
        $labels = DB::table('sources')->pluck('label', 'id');
        $edit = $request->query('modifier') ? DB::table('custom_rules')->find((int) $request->query('modifier')) : null;

        return view('admin.rules', [
            'admin' => $request->session()->get('radar_admin'), 'message' => $request->session()->get('message'),
            'rules' => DB::table('custom_rules')->orderByDesc('id')->get()->map(function ($r) use ($labels) {
                $r->source_labels = implode(', ', array_map(fn ($s) => $labels[$s] ?? $s, json_decode($r->sources, true) ?: []));
                $r->technique_list = implode(', ', json_decode($r->techniques, true) ?: []);

                return $r;
            }),
            'edit' => $edit, 'editSources' => $edit ? (json_decode($edit->sources, true) ?: []) : [],
            'editTechniques' => $edit ? implode(', ', json_decode($edit->techniques, true) ?: []) : '',
            'sources' => DB::table('sources')->orderBy('auto')->orderByDesc('rules_count')->get(['id', 'label', 'auto']),
        ]);
    }

    public function ruleSave(Request $request, Overlays $overlays)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:250'],
            'description' => ['required', 'string', 'max:2000'],
            'level' => ['required', Rule::in(['critical', 'high', 'medium', 'low', 'informational'])],
            'sources' => ['required', 'array', 'min:1', 'max:20'],
            'sources.*' => [Rule::in(DB::table('sources')->pluck('id')->all())],
            'techniques' => ['nullable', 'string', 'max:1000'],
            'falsepositives' => ['nullable', 'string', 'max:490'],
            'sigma' => ['required', 'string', 'max:20000'],
        ]);
        $row = [
            'title' => $data['title'], 'description' => $data['description'], 'level' => $data['level'],
            'sources' => json_encode(array_values($data['sources'])), 'techniques' => json_encode($this->parseTechniques($data['techniques'] ?? '')),
            'falsepositives' => $data['falsepositives'] ?? '', 'sigma' => $data['sigma'], 'updated_at' => now(),
        ];
        if (! empty($data['id']) && DB::table('custom_rules')->where('id', $data['id'])->exists()) {
            DB::table('custom_rules')->where('id', $data['id'])->update($row);
        } else {
            DB::table('custom_rules')->insert($row + ['uid' => (string) Str::uuid(), 'active' => true, 'created_at' => now()]);
        }
        $overlays->apply();

        return redirect('/admin/regles')->with('message', 'Règle « '.$data['title'].' » enregistrée et publiée.');
    }

    public function ruleToggle(Overlays $overlays, int $id)
    {
        $r = DB::table('custom_rules')->find($id);
        abort_if(! $r, 404);
        DB::table('custom_rules')->where('id', $id)->update(['active' => ! $r->active, 'updated_at' => now()]);
        $overlays->apply();

        return redirect('/admin/regles')->with('message', $r->active ? 'Règle retirée du site.' : 'Règle publiée.');
    }

    public function ruleDelete(Overlays $overlays, int $id)
    {
        DB::table('custom_rules')->where('id', $id)->delete();
        $overlays->apply();

        return redirect('/admin/regles')->with('message', 'Règle supprimée.');
    }

    // ---------------------------------------------------------------- Statistiques

    public function stats(Request $request)
    {
        $steps = ['accueil' => 'Accueil', 'sources' => 'Mes sources', 'priorites' => 'Mes priorités', 'plan' => 'Mon plan', 'export' => 'Export demandé'];
        $since = now()->subDays(29)->toDateString();
        $rows = DB::table('usage_daily')->where('day', '>=', $since)->get();
        $totals = [];
        $days = [];
        foreach ($rows as $r) {
            $day = substr((string) $r->day, 0, 10);
            $totals[$r->metric] = ($totals[$r->metric] ?? 0) + $r->hits;
            $days[$day][$r->metric] = $r->hits;
        }
        krsort($days);
        $funnel = [];
        $prev = null;
        foreach ($steps as $key => $label) {
            $n = $totals[$key] ?? 0;
            $funnel[] = ['label' => $label, 'n' => $n, 'rate' => $prev ? round(100 * $n / $prev) : null];
            $prev = $n ?: $prev;
        }

        return view('admin.stats', [
            'admin' => $request->session()->get('radar_admin'), 'funnel' => $funnel, 'max' => max(1, ...array_column($funnel, 'n')),
            'days' => $days, 'steps' => $steps, 'changelog' => $totals['nouveautes'] ?? 0,
        ]);
    }
}
