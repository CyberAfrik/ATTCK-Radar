@extends('admin.layout')
@section('title', 'Statistiques')
@section('content')
<h1>Statistiques d'usage</h1>
<p class="muted">30 derniers jours. Une visite est comptée une fois par étape et par session de navigateur. Aucun cookie de suivi, aucune adresse IP et aucun identifiant ne sont enregistrés : ce sont de simples totaux par jour.</p>
<div class="card"><h2>Parcours</h2>
@foreach ($funnel as $f)
<div class="bar"><span>{{ $f['label'] }}@if ($f['rate'] !== null) <span class="muted">· {{ $f['rate'] }} % de l'étape précédente</span>@endif</span><span class="muted">{{ $f['n'] }}</span><i><u style="width:{{ round(100 * $f['n'] / $max) }}%"></u></i></div>
@endforeach
<p class="muted" style="margin:10px 0 0">Page Nouveautés : {{ $changelog }} visites.</p>
</div>
<div class="card"><h2>Détail par jour</h2>
<div class="scroll"><table><thead><tr><th>Jour</th>@foreach ($steps as $label)<th>{{ $label }}</th>@endforeach</tr></thead><tbody>
@forelse ($days as $day => $m)
<tr><td>{{ $day }}</td>@foreach ($steps as $key => $label)<td>{{ $m[$key] ?? 0 }}</td>@endforeach</tr>
@empty
<tr><td colspan="6" class="muted">Pas encore de visites enregistrées.</td></tr>
@endforelse
</tbody></table></div>
</div>
@endsection
