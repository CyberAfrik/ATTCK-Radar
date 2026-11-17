@extends('admin.layout')
@section('title', 'Exports')
@section('content')
<h1>Exports demandés</h1>
@if ($message)<p class="note">{{ $message }}</p>@endif
<div class="kpis">
@foreach ($kpis as $label => $value)
<div class="card kpi"><b>{{ $value }}</b><span>{{ $label }}</span></div>
@endforeach
</div>

<div class="cols">
@foreach (['Sources les plus déclarées' => $topSources, 'Scénarios les plus choisis' => $topPlaybooks, 'Langages demandés' => $topLanguages] as $title => $list)
<div class="card"><h2>{{ $title }}</h2>
@forelse ($list as $item)
<div class="bar"><span>{{ $item['label'] }}</span><span class="muted">{{ $item['count'] }}</span><i><u style="width:{{ $list->max('count') ? round(100 * $item['count'] / $list->max('count')) : 0 }}%"></u></i></div>
@empty
<p class="muted">Pas encore de données.</p>
@endforelse
</div>
@endforeach
</div>

<div class="card">
<form class="filters" method="get" action="/admin">
<label for="q" style="position:absolute;left:-9999px">Rechercher une adresse</label>
<input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher une adresse">
<label for="statut" style="position:absolute;left:-9999px">Statut</label>
<select id="statut" name="statut">
<option value="">Tous les statuts</option>
@foreach (['sent' => 'Envoyé', 'queued' => 'En attente', 'failed' => 'Échec'] as $k => $v)
<option value="{{ $k }}" @selected(($filters['statut'] ?? '') === $k)>{{ $v }}</option>
@endforeach
</select>
<label><input type="checkbox" name="consentement" value="1" @checked(($filters['consentement'] ?? '') === '1')> Accord actualités uniquement</label>
<button class="btn" type="submit">Filtrer</button>
<a class="btn primary" href="/admin/exports.csv?{{ http_build_query($filters) }}">Télécharger en CSV</a>
</form>

<div class="scroll" style="margin-top:16px">
<table>
<thead><tr><th>Date (UTC)</th><th>Email</th><th>Statut</th><th>Actualités</th><th>Langage</th><th>Sources en place</th><th>Sources prévues</th><th>Scénarios</th><th>Plan</th><th>Action</th></tr></thead>
<tbody>
@forelse ($exports as $e)
<tr>
<td style="white-space:nowrap">{{ \Illuminate\Support\Str::limit($e->created_at, 16, '') }}</td>
<td>{{ $e->email }}</td>
<td><span class="tag {{ $e->status }}">{{ ['sent' => 'Envoyé', 'queued' => 'En attente', 'failed' => 'Échec'][$e->status] ?? $e->status }}</span>@if ($e->error)<div class="muted" style="font-size:12px;max-width:260px">{{ \Illuminate\Support\Str::limit($e->error, 140) }}</div>@endif</td>
<td>{{ $e->unsubscribed_at ? 'Désinscrit' : ($e->marketing_consent ? 'Oui' : 'Non') }}</td>
<td>{{ $e->language }}</td>
<td class="small">{{ count($e->on) }} : {{ \Illuminate\Support\Str::limit($e->on_labels, 160) }}</td>
<td class="small">{{ count($e->planned) }}@if ($e->planned_labels) : {{ \Illuminate\Support\Str::limit($e->planned_labels, 120) }}@endif</td>
<td class="small">{{ $e->playbook_labels ?: 'Aucun' }}</td>
<td><a href="/p/{{ $e->token }}" target="_blank" rel="noopener">Ouvrir</a></td>
<td><form method="post" action="/admin/exports/{{ $e->id }}/renvoyer" style="margin:0">@csrf<button class="btn sm" type="submit">Renvoyer</button></form></td>
</tr>
@empty
<tr><td colspan="10" class="muted">Aucun export ne correspond.</td></tr>
@endforelse
</tbody>
</table>
</div>
<div class="pager">
@if ($exports->previousPageUrl())<a class="btn" href="{{ $exports->previousPageUrl() }}">Précédent</a>@endif
<span class="muted">Page {{ $exports->currentPage() }} sur {{ $exports->lastPage() }} · {{ $exports->total() }} lignes</span>
@if ($exports->nextPageUrl())<a class="btn" href="{{ $exports->nextPageUrl() }}">Suivant</a>@endif
</div>
</div>
<div class="card">
<h2>Suppression des données d'une personne</h2>
<p class="muted">Supprime toutes les demandes d'export d'une adresse et les plans associés. Les liens déjà envoyés cessent de fonctionner. Cette action est définitive.@if ($retention) Les demandes de plus de {{ $retention }} jours sont supprimées automatiquement.@endif</p>
<form class="filters" method="post" action="/admin/purge">@csrf
<label for="purge-email" style="position:absolute;left:-9999px">Adresse à supprimer</label>
<input id="purge-email" type="email" name="email" required maxlength="190" placeholder="adresse@exemple.com">
<button class="btn danger" type="submit">Supprimer ses données</button>
</form>
</div>
@endsection
