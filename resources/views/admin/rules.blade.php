@extends('admin.layout')
@section('title', 'Règles maison')
@section('content')
<h1>Règles CyberAfrik</h1>
@if ($message)<p class="note">{{ $message }}</p>@endif
@if ($errors->any())<p class="warn">{{ $errors->first() }}</p>@endif
<p class="muted">Une règle ajoutée ici est publiée tout de suite, au format Sigma uniquement. Pour qu'elle soit aussi convertie en Splunk, Elastic, KQL, LogScale et XQL, déposez son fichier dans le dossier <code>pipeline/custom-rules</code> du dépôt : elle sera convertie à la mise à jour suivante.</p>

<div class="card"><h2>{{ $edit ? 'Modifier la règle' : 'Nouvelle règle' }}</h2>
<form class="stack" method="post" action="/admin/regles">@csrf
@if ($edit)<input type="hidden" name="id" value="{{ $edit->id }}">@endif
<div class="row"><label class="f grow">Titre<input type="text" name="title" value="{{ old('title', $edit->title ?? '') }}" maxlength="250" required></label>
<label class="f">Sévérité<select name="level">
@foreach (['critical' => 'Critique', 'high' => 'Élevée', 'medium' => 'Moyenne', 'low' => 'Faible', 'informational' => 'Information'] as $k => $v)
<option value="{{ $k }}" @selected(old('level', $edit->level ?? 'medium') === $k)>{{ $v }}</option>
@endforeach
</select></label></div>
<label class="f">Description<textarea name="description" style="min-height:70px;font-family:inherit;font-size:14px" required maxlength="2000">{{ old('description', $edit->description ?? '') }}</textarea></label>
<div class="row"><label class="f grow">Techniques ATT&amp;CK<input type="text" name="techniques" value="{{ old('techniques', $editTechniques) }}" placeholder="T1558.003, T1110"></label>
<label class="f grow">Faux positifs connus<input type="text" name="falsepositives" value="{{ old('falsepositives', $edit->falsepositives ?? '') }}" maxlength="490"></label></div>
<fieldset style="border:none;padding:0;margin:0"><legend class="muted" style="font-size:13px;margin-bottom:6px">Sources de logs qui permettent d'appliquer la règle (au moins une)</legend>
<div class="checks">
@foreach ($sources as $s)
<label><input type="checkbox" name="sources[]" value="{{ $s->id }}" @checked(in_array($s->id, old('sources', $editSources)))> {{ $s->label }}</label>
@endforeach
</div></fieldset>
<label class="f">Règle Sigma (YAML)<textarea name="sigma" required maxlength="20000" spellcheck="false">{{ old('sigma', $edit->sigma ?? '') }}</textarea></label>
<div class="row"><button class="btn primary" type="submit">{{ $edit ? 'Enregistrer' : 'Publier la règle' }}</button>@if ($edit)<a class="btn" href="/admin/regles">Annuler</a>@endif</div>
</form>
</div>

<div class="card"><h2>Règles enregistrées ({{ $rules->count() }})</h2>
<div class="scroll"><table><thead><tr><th>Titre</th><th>Sévérité</th><th>Techniques</th><th>Sources</th><th>État</th><th></th></tr></thead><tbody>
@forelse ($rules as $r)
<tr><td>{{ $r->title }}</td><td>{{ $r->level }}</td><td>{{ $r->technique_list ?: 'Aucune' }}</td><td class="small">{{ $r->source_labels }}</td>
<td><span class="tag {{ $r->active ? 'sent' : '' }}">{{ $r->active ? 'Publiée' : 'Retirée' }}</span></td>
<td><div class="row"><a class="btn sm" href="/admin/regles?modifier={{ $r->id }}">Modifier</a>
<form method="post" action="/admin/regles/{{ $r->id }}/publier" style="margin:0">@csrf<button class="btn sm" type="submit">{{ $r->active ? 'Retirer' : 'Publier' }}</button></form>
<form method="post" action="/admin/regles/{{ $r->id }}/supprimer" style="margin:0">@csrf<button class="btn sm danger" type="submit">Supprimer</button></form></div></td></tr>
@empty
<tr><td colspan="6" class="muted">Aucune règle maison pour l'instant.</td></tr>
@endforelse
</tbody></table></div>
</div>
@endsection
