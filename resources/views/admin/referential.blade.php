@extends('admin.layout')
@section('title', 'Référentiel')
@section('content')
<h1>Référentiel</h1>
@if ($message)<p class="note" style="white-space:pre-line">{{ $message }}</p>@endif

@if ($upToDate === false)
<p class="warn"><strong>MITRE ATT&amp;CK {{ $latest }} est disponible.</strong> Le site utilise la version {{ $pack->attack_version }}. Régénérez le paquet de référentiel puis importez-le.</p>
@elseif ($upToDate === true)
<p class="note">Le site utilise la dernière version publiée de MITRE ATT&amp;CK ({{ $latest }}).</p>
@else
<p class="warn">La dernière version de MITRE ATT&amp;CK n'a pas pu être vérifiée pour le moment.</p>
@endif

<div class="cols">
<div class="card"><h2>Version en place</h2>
@if ($pack)
<dl>
<dt>Paquet</dt><dd>{{ $pack->version }}</dd>
<dt>MITRE ATT&amp;CK</dt><dd>{{ $pack->attack_version }}</dd>
<dt>Généré le</dt><dd>{{ $pack->generated_at }} UTC</dd>
<dt>Importé le</dt><dd>{{ $pack->imported_at }} UTC</dd>
<dt>Commit SigmaHQ</dt><dd style="word-break:break-all">{{ \Illuminate\Support\Str::limit((string) $pack->sigmahq_commit, 12, '') }}</dd>
<dt>Tactiques</dt><dd>{{ $counts['tactics'] ?? '?' }}</dd>
<dt>Techniques</dt><dd>{{ $counts['techniques'] ?? '?' }}</dd>
<dt>Sources de logs</dt><dd>{{ $counts['sources'] ?? '?' }}</dd>
<dt>Règles</dt><dd>{{ $counts['rules_imported'] ?? ($counts['rules'] ?? '?') }}</dd>
<dt>Techniques sans règle</dt><dd>{{ $noRule }} sur {{ $roots }}</dd>
</dl>
@else
<p class="muted">Aucun référentiel importé.</p>
@endif
</div>

<div class="card"><h2>Conversion des règles par langage</h2>
@forelse ($conversion as $lang => $n)
<div class="bar"><span>{{ $lang }}</span><span class="muted">{{ $n }}</span><i><u style="width:{{ ($counts['rules'] ?? 0) ? round(100 * $n / $counts['rules']) : 0 }}%"></u></i></div>
@empty
<p class="muted">Pas de données.</p>
@endforelse
</div>

<div class="card"><h2>Mise à jour</h2>
@if ($packUrl)
<p class="muted">Le paquet est téléchargé chaque lundi depuis l'adresse configurée. Vous pouvez aussi lancer l'import maintenant.</p>
<form method="post" action="/admin/referentiel/import">@csrf<button class="btn primary" type="submit">Importer maintenant</button></form>
@else
<p class="warn">La mise à jour automatique n'est pas encore active : aucune adresse de paquet n'est configurée (<code>RADAR_PACK_URL</code>).</p>
@endif
</div>
</div>

<div class="card"><h2>Sources à classer ({{ $autoSources->count() }})</h2>
<p class="muted">Ces sources ont été créées automatiquement à partir des règles. Leur libellé et leur famille sont à relire.</p>
<div class="scroll"><table><thead><tr><th>Libellé automatique</th><th>Règles</th></tr></thead><tbody>
@foreach ($autoSources as $s)<tr><td>{{ $s->label }}</td><td>{{ $s->rules_count }}</td></tr>@endforeach
</tbody></table></div>
</div>

<div class="card"><h2>Historique des imports</h2>
<div class="scroll"><table><thead><tr><th>Importé le (UTC)</th><th>Paquet</th><th>ATT&amp;CK</th></tr></thead><tbody>
@foreach ($history as $h)<tr><td>{{ $h->imported_at }}</td><td>{{ $h->version }}</td><td>{{ $h->attack_version }}</td></tr>@endforeach
</tbody></table></div>
</div>
@endsection
