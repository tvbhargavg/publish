<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Dashboard') · mytunnel</title>
<style>
:root{--bg:#f6f7f9;--card:#fff;--ink:#1b1f24;--mute:#667085;--line:#e4e7ec;--accent:#2457d6;--bad:#b42318;--ok:#067647}
@media (prefers-color-scheme:dark){:root{--bg:#0f1216;--card:#171b21;--ink:#e8eaed;--mute:#9aa4b2;--line:#2a3039;--accent:#6c97ff;--bad:#ff8a80;--ok:#4cd39a}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 system-ui,sans-serif}
header{background:var(--card);border-bottom:1px solid var(--line)}
.wrap{max-width:1040px;margin:0 auto;padding:0 16px}
nav{display:flex;gap:16px;align-items:center;min-height:52px;flex-wrap:wrap}nav a{color:var(--mute);text-decoration:none}nav a.on,nav a:hover{color:var(--ink)}
nav .brand{font-weight:700;color:var(--ink);margin-right:8px}nav form{margin-left:auto}
main{padding:24px 0 48px}h1{font-size:22px;margin:0 0 16px}h2{font-size:16px;margin:28px 0 10px}
.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:16px;overflow-x:auto}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:8px 10px;border-bottom:1px solid var(--line);white-space:nowrap}th{color:var(--mute);font-weight:600;font-size:13px}
tr:last-child td{border-bottom:0}.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}
.stat b{display:block;font-size:24px}.stat span{color:var(--mute);font-size:13px}
input,button,select{font:inherit}input{padding:8px 10px;border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--ink)}
button{padding:8px 14px;border:0;border-radius:8px;background:var(--accent);color:#fff;cursor:pointer}button.danger{background:var(--bad)}button.link{background:none;color:var(--accent);padding:0}
.row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.err{color:var(--bad)}.flash{background:var(--card);border:1px solid var(--ok);color:var(--ok);padding:10px 14px;border-radius:8px;margin-bottom:16px}
.pill{display:inline-block;padding:1px 8px;border-radius:99px;font-size:12px;border:1px solid var(--line)}.pill.online{color:var(--ok);border-color:var(--ok)}.pill.closed{color:var(--mute)}
code{background:var(--bg);padding:2px 6px;border-radius:6px;word-break:break-all}.secret{border:1px dashed var(--accent);padding:12px;border-radius:8px;margin-bottom:16px}
</style>
</head>
<body>
<header><div class="wrap"><nav>
<span class="brand">mytunnel</span>
@auth
<a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'on' : '' }}">Tunnels</a>
<a href="{{ route('history') }}" class="{{ request()->routeIs('history') ? 'on' : '' }}">History</a>
<a href="{{ route('tokens') }}" class="{{ request()->routeIs('tokens') ? 'on' : '' }}">Tokens</a>
<a href="{{ route('reservations') }}" class="{{ request()->routeIs('reservations') ? 'on' : '' }}">Subdomains</a>
@if(auth()->user()->is_admin)<a href="{{ route('admin') }}" class="{{ request()->routeIs('admin*') ? 'on' : '' }}">Admin</a>@endif
<form method="post" action="{{ route('logout') }}">@csrf<button class="link">Sign out ({{ auth()->user()->email }})</button></form>
@endauth
</nav></div></header>
<main><div class="wrap">
@if(session('ok'))<div class="flash">{{ session('ok') }}</div>@endif
@yield('content')
</div></main>
</body></html>
