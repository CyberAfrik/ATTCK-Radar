@extends('admin.layout')
@section('title', 'Sources')
@section('content')
<h1>Catalogue de sources</h1>
@if ($message)<p class="note">{{ $message }}</p>@endif
<p class="muted">{{ $sources->count() }} sources, dont {{ $todo }} à classer (créées automatiquement à partir de règles dont la source n'est pas encore reconnue). Une source « repliée » n'apparaît sur le site qu'après un clic sur « Afficher les autres sources ». Vos corrections sont conservées lors des mises à jour du référentiel.</p>
<div class="card"><div class="scroll">
<table>
<thead><tr><th>Libellé</th><th>Famille</th><th>Description</th><th>Repliée</th><th>Règles</th><th></th></tr></thead>
<tbody>
@foreach ($sources as $s)
<tr id="s-{{ $s->id }}">
<td style="min-width:230px"><input form="f-{{ $s->id }}" class="grow" type="text" name="label" value="{{ $s->label }}" maxlength="150" required aria-label="Libellé de {{ $s->label }}">
@if ($s->auto)<span class="badge">à classer</span>@endif <span class="muted" style="font-size:12px">{{ $s->id }}</span></td>
<td><select form="f-{{ $s->id }}" name="family_id" aria-label="Famille de {{ $s->label }}">
@foreach ($families as $fid => $flabel)<option value="{{ $fid }}" @selected($fid === $s->family_id)>{{ $flabel }}</option>@endforeach
</select></td>
<td style="min-width:260px"><input form="f-{{ $s->id }}" class="grow" type="text" name="description" value="{{ $s->description }}" maxlength="390" aria-label="Description de {{ $s->label }}"></td>
<td><input form="f-{{ $s->id }}" type="checkbox" name="minor" value="1" @checked($s->minor) aria-label="Replier {{ $s->label }} sous « autres sources »"></td>
<td>{{ $s->rules_count }}</td>
<td><div class="row">
<form id="f-{{ $s->id }}" method="post" action="/admin/sources/{{ $s->id }}" style="margin:0">@csrf<button class="btn sm primary" type="submit">Enregistrer</button></form>
@if ($s->overridden)<form method="post" action="/admin/sources/{{ $s->id }}/retablir" style="margin:0">@csrf<button class="btn sm" type="submit">Rétablir</button></form>@endif
</div></td>
</tr>
@endforeach
</tbody>
</table>
</div></div>
@endsection
