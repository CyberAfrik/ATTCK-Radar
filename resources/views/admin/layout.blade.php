<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', 'Administration') | ATT&amp;CK Radar</title>
<style>
:root{--bg:#0A0E1A;--panel:#0F1524;--panel2:#141B2E;--border:#232C42;--border2:#3A4868;--text:#E8F4FD;--muted:#A9B8CE;--accent:#00D4FF;--amber:#F5A524}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
a{color:var(--accent)}h1{font-size:26px;margin:0}h2{font-size:18px;margin:0 0 12px}
.wrap{max-width:1320px;margin:0 auto;padding:0 20px}.top{border-bottom:1px solid var(--border);padding:14px 0}
.top .wrap{display:flex;flex-wrap:wrap;gap:12px 24px;align-items:center;justify-content:space-between}
nav{display:flex;flex-wrap:wrap;gap:8px;align-items:center}nav a{padding:8px 14px;border-radius:999px;border:1px solid var(--border2);color:var(--text);text-decoration:none;font-size:14px}
nav a.on{background:var(--accent);border-color:var(--accent);color:#04121A;font-weight:700}
main{padding:28px 0 60px;display:flex;flex-direction:column;gap:24px}
.card{background:var(--panel);border:1px solid var(--border);border-radius:14px;padding:20px}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px}.kpi b{display:block;font-size:30px;line-height:1.1}.kpi span{color:var(--muted);font-size:13px}
.cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}
.bar{display:grid;grid-template-columns:1fr auto;gap:2px 10px;font-size:14px;margin-bottom:8px}.bar i{grid-column:1/-1;height:6px;border-radius:3px;background:#1E2740;display:block;overflow:hidden}.bar i u{display:block;height:100%;background:var(--accent)}
.scroll{overflow-x:auto}table{border-collapse:collapse;width:100%;font-size:14px}th,td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:top}
th{color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap}td.small{color:var(--muted);font-size:13px;min-width:220px}
.tag{display:inline-block;padding:2px 8px;border-radius:5px;font-size:12px;font-weight:700;background:#3A4868}.tag.sent{background:var(--accent);color:#04121A}.tag.failed{background:#FF8A7A;color:#2A0703}.tag.queued{background:var(--amber);color:#1A1203}
form.filters{display:flex;flex-wrap:wrap;gap:10px;align-items:center}input,select,button{font:inherit;color:inherit}
input[type=text],input[type=email],input[type=search],select{min-height:42px;padding:0 12px;border-radius:8px;border:1px solid var(--border2);background:var(--bg)}
.btn{display:inline-flex;align-items:center;min-height:42px;padding:0 16px;border-radius:8px;border:1px solid var(--border2);background:transparent;cursor:pointer;text-decoration:none;color:var(--text);font-weight:600}
.btn.primary{background:var(--accent);border-color:var(--accent);color:#04121A}.muted{color:var(--muted)}.note{background:#0E2233;border:1px solid var(--accent);border-radius:10px;padding:12px 16px}
.warn{background:#1F1A10;border:1px solid var(--amber);border-radius:10px;padding:12px 16px}dl{display:grid;grid-template-columns:auto 1fr;gap:6px 18px;margin:0}dt{color:var(--muted)}dd{margin:0}
.pager{display:flex;gap:10px;align-items:center;margin-top:14px}
textarea{font:13px/1.5 ui-monospace,Menlo,Consolas,monospace;color:inherit;width:100%;min-height:220px;padding:12px;border-radius:8px;border:1px solid var(--border2);background:var(--bg)}
.btn.sm{min-height:34px;padding:0 10px;font-size:13px}.btn.danger{border-color:#7A2E35;color:#FFC9CD}.stack{display:flex;flex-direction:column;gap:12px}.row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.grow{flex:1 1 220px;width:100%}label.f{display:flex;flex-direction:column;gap:6px;font-size:13px;color:var(--muted)}.checks{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:4px 14px;max-height:230px;overflow:auto;border:1px solid var(--border);border-radius:8px;padding:10px;font-size:14px}.badge{display:inline-block;padding:1px 7px;border-radius:5px;font-size:11px;font-weight:700;background:var(--amber);color:#1A1203}tr:target{outline:2px solid var(--accent)}
</style>
</head>
<body>
<header class="top"><div class="wrap">
<strong>ATT&amp;CK Radar <span class="muted" style="font-weight:400">· administration</span></strong>
@isset($admin)
<nav>
<a href="/admin" class="{{ request()->is('admin') ? 'on' : '' }}">Exports</a>
<a href="/admin/sources" class="{{ request()->is('admin/sources') ? 'on' : '' }}">Sources</a>
<a href="/admin/scenarios" class="{{ request()->is('admin/scenarios') ? 'on' : '' }}">Scénarios</a>
<a href="/admin/regles" class="{{ request()->is('admin/regles') ? 'on' : '' }}">Règles maison</a>
<a href="/admin/statistiques" class="{{ request()->is('admin/statistiques') ? 'on' : '' }}">Statistiques</a>
<a href="/admin/referentiel" class="{{ request()->is('admin/referentiel') ? 'on' : '' }}">Référentiel</a>
<a href="/" target="_blank" rel="noopener">Voir le site</a>
<form method="post" action="/admin/deconnexion" style="margin:0">@csrf<button class="btn" type="submit">Déconnexion</button></form>
</nav>
@endisset
</div></header>
<main class="wrap">@yield('content')</main>
</body>
</html>
