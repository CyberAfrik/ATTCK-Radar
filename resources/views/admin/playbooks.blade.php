@extends('admin.layout')
@section('title', 'Scénarios')
@section('content')
<h1>Scénarios d'attaque</h1>
@if ($message)<p class="note">{{ $message }}</p>@endif
<p class="muted">Les scénarios servent à classer les angles morts. Indiquez les techniques par leur identifiant ATT&amp;CK, séparées par des virgules. Les identifiants inconnus sont ignorés.</p>
@foreach ($playbooks as $p)
<div class="card stack">
<form class="stack" method="post" action="/admin/scenarios">@csrf
<input type="hidden" name="id" value="{{ $p->id }}">
<div class="row"><label class="f grow">Nom<input type="text" name="label" value="{{ $p->label }}" maxlength="150" required></label>
<label class="f grow">Description<input type="text" name="description" value="{{ $p->description }}" maxlength="390"></label></div>
<label class="f">Techniques<textarea name="techniques" style="min-height:70px" required>{{ $p->techniques }}</textarea></label>
<div class="row"><button class="btn sm primary" type="submit">Enregistrer</button>@if ($p->overridden)<span class="muted" style="font-size:13px">modifié</span>@endif</div>
</form>
<div class="row">
<form method="post" action="/admin/scenarios/{{ $p->id }}/retirer" style="margin:0">@csrf<button class="btn sm danger" type="submit">Retirer du site</button></form>
@if ($p->overridden)<form method="post" action="/admin/scenarios/{{ $p->id }}/retablir" style="margin:0">@csrf<button class="btn sm" type="submit">Rétablir l'original</button></form>@endif
</div>
</div>
@endforeach

@if ($hidden->count())
<div class="card"><h2>Scénarios retirés</h2>
@foreach ($hidden as $h)
<div class="row" style="margin-bottom:8px"><span>{{ $h->label }}</span><form method="post" action="/admin/scenarios/{{ $h->id }}/retablir" style="margin:0">@csrf<button class="btn sm" type="submit">Rétablir</button></form></div>
@endforeach
</div>
@endif

<div class="card"><h2>Nouveau scénario</h2>
<form class="stack" method="post" action="/admin/scenarios">@csrf
<div class="row"><label class="f grow">Nom<input type="text" name="label" value="{{ old('label') }}" maxlength="150" required></label>
<label class="f grow">Description<input type="text" name="description" value="{{ old('description') }}" maxlength="390"></label></div>
<label class="f">Techniques<textarea name="techniques" style="min-height:70px" required placeholder="T1566, T1078, T1486">{{ old('techniques') }}</textarea></label>
<div><button class="btn primary" type="submit">Créer le scénario</button></div>
</form>
</div>
@endsection
