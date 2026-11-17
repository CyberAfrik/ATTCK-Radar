@extends('admin.layout')
@section('title', 'Connexion')
@section('content')
<div class="card" style="max-width:480px;margin:40px auto;width:100%">
<h1 style="margin-bottom:10px">Connexion</h1>
@if ($sent)
<p class="note">Si cette adresse est autorisée, un lien de connexion vient de lui être envoyé. Il est valable 15 minutes.</p>
@else
<p class="muted">Saisissez votre adresse d'administrateur. Vous recevrez un lien de connexion à usage unique.</p>
@endif
<form method="post" action="/admin/connexion" style="display:flex;flex-direction:column;gap:12px;margin-top:16px">
@csrf
<label for="email">Adresse email</label>
<input id="email" name="email" type="email" required maxlength="190" autocomplete="email">
@error('email')<span style="color:#FFC9CD">Adresse email invalide.</span>@enderror
<button class="btn primary" type="submit">Recevoir le lien</button>
</form>
</div>
@endsection
