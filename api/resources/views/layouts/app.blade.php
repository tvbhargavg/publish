<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Dashboard') · Publish</title>
<script>
(function(){var pref='{{ auth()->check() ? (auth()->user()->theme ?? '') : '' }}',h=document.documentElement;
try{if(pref){localStorage.setItem('theme',pref)}else{pref=localStorage.getItem('theme')||''}}catch(e){}
function res(p){return p==='dark'||p==='light'?p:(window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light')}
h.setAttribute('data-pref',pref||'system');h.setAttribute('data-theme',res(pref));
if(window.matchMedia)matchMedia('(prefers-color-scheme: dark)').addEventListener('change',function(){if((h.getAttribute('data-pref')||'system')==='system')h.setAttribute('data-theme',res('system'))});})();
</script>
<style>
:root{--bg:#f6f7f9;--card:#fff;--ink:#111827;--mute:#596273;--line:#e5e7eb;--hover:#f3f5f9;--accent:#1f4fd1;--accent-ink:#fff;--accent-soft:#e8efff;--bad:#b42318;--bad-soft:#fdecea;--ok:#05603a;--ok-soft:#e6f5ec;--warn:#8a4b00;--warn-soft:#fff4e0;--radius:12px;--shadow:0 1px 2px rgba(16,24,40,.05);color-scheme:light}
:root[data-theme=dark]{--bg:#0d1117;--card:#151b23;--ink:#e6edf3;--mute:#9ba7b6;--line:#2a323d;--hover:#1a222c;--accent:#7aa2ff;--accent-ink:#0a1020;--accent-soft:#1b2a4d;--bad:#ff9a90;--bad-soft:#3b1d1b;--ok:#56d49b;--ok-soft:#12301f;--warn:#f2b95c;--warn-soft:#3a2a0e;--shadow:none;color-scheme:dark}
*{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
a{color:var(--accent);text-underline-offset:2px}
svg.ic{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex:none}
.wrap{max-width:1120px;margin:0 auto;padding:0 16px}
header{background:var(--card);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:5}
nav{display:flex;align-items:center;gap:4px;min-height:56px}
nav .brand{font-weight:700;font-size:18px;color:var(--ink);text-decoration:none;margin-right:12px;letter-spacing:-.01em}
nav .links{display:flex;gap:2px;flex:1;flex-wrap:wrap}
nav .links a{color:var(--mute);text-decoration:none;padding:7px 12px;border-radius:8px;white-space:nowrap;font-weight:500}
nav .links a:hover{background:var(--hover);color:var(--ink)}
nav .links a.on{background:var(--accent-soft);color:var(--accent)}
.navright{display:flex;align-items:center;gap:6px;margin-left:auto;flex-shrink:0}
nav form{margin:0}
.avatar{width:34px;height:34px;border-radius:50%;background:var(--accent);color:var(--accent-ink);display:grid;place-items:center;font-weight:700;font-size:14px;text-decoration:none;text-transform:uppercase;flex:none}
.avatar:hover{filter:brightness(1.08)}.avatar.on{outline:2px solid var(--accent);outline-offset:2px}
.avatar.lg{width:64px;height:64px;font-size:26px}
main{padding:24px 0 48px}
h1{font-size:22px;line-height:1.25;margin:0 0 4px;letter-spacing:-.01em}
h2{font-size:13px;margin:26px 0 10px;color:var(--mute);text-transform:uppercase;letter-spacing:.06em;font-weight:700}
.sub{color:var(--mute);margin:0 0 16px}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:16px}
.stat b{display:block;font-size:24px;line-height:1.25}.stat span{color:var(--mute);font-size:13px}
/* tables */
table{width:100%;border-collapse:collapse}
th,td{text-align:left;padding:12px 16px;border-bottom:1px solid var(--line);white-space:nowrap;vertical-align:middle;font-size:14px}
th{color:var(--mute);font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.07em;background:var(--bg);padding-top:10px;padding-bottom:10px}
tbody tr:hover td,table tr:hover td{background:var(--hover)}
tr:last-child td{border-bottom:0}
td form{margin:0}
td .t1{font-weight:600;color:var(--ink)}td .t2{display:block;font-size:12px;color:var(--mute);margin-top:1px}
td.act,th.act{text-align:right;width:1%}
.actions{display:inline-flex;gap:4px;justify-content:flex-end}
/* controls */
input,button,select{font:inherit}
input,select{padding:8px 12px;border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--ink);min-height:40px;max-width:100%}
input::placeholder{color:var(--mute);opacity:.85}
input:focus-visible,button:focus-visible,a:focus-visible,select:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
label.f{display:flex;flex-direction:column;gap:4px;font-size:13px;color:var(--mute)}
button,.btn{padding:9px 16px;border:0;border-radius:8px;background:var(--accent);color:var(--accent-ink);cursor:pointer;font-weight:600;min-height:40px;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:6px}
button:hover{filter:brightness(1.07)}
button:disabled{opacity:.45;cursor:not-allowed;filter:none}
button.danger{background:var(--bad-soft);color:var(--bad)}
button.ghost{background:transparent;color:var(--ink);border:1px solid var(--line)}
button.ghost:hover{background:var(--hover);filter:none}
button.sm{padding:0 12px;min-height:34px;font-size:13px}
.ib{width:32px;height:32px;min-height:32px;padding:0;border-radius:8px;background:transparent;color:var(--mute);border:1px solid transparent;display:inline-grid;place-items:center;text-decoration:none;cursor:pointer;flex:none}
.ib:hover{background:var(--hover);color:var(--ink);filter:none;border-color:var(--line)}
.ib.danger{background:transparent;color:var(--mute)}.ib.danger:hover{background:var(--bad-soft);color:var(--bad);border-color:transparent}
.ib.bordered{border-color:var(--line)}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
.err{color:var(--bad);font-size:14px;margin-top:6px}
.flash{background:var(--ok-soft);border:1px solid var(--ok);color:var(--ok);padding:10px 14px;border-radius:10px;margin-bottom:16px}
.warn{background:var(--warn-soft);border:1px solid var(--warn);color:var(--warn);padding:10px 14px;border-radius:10px;margin-bottom:16px}
.pill{display:inline-flex;align-items:center;gap:6px;padding:2px 9px;border-radius:99px;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;border:1px solid var(--line);color:var(--mute);background:transparent;line-height:1.5}
.pill::before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor}
.pill.online{color:var(--ok);background:var(--ok-soft);border-color:color-mix(in srgb,var(--ok) 35%,transparent)}
.pill.premium{color:var(--accent);background:var(--accent-soft);border-color:color-mix(in srgb,var(--accent) 35%,transparent)}
.pill.bad{color:var(--bad);background:var(--bad-soft);border-color:color-mix(in srgb,var(--bad) 35%,transparent)}
.pill.warnp{color:var(--warn);background:var(--warn-soft);border-color:color-mix(in srgb,var(--warn) 35%,transparent)}
code{background:var(--bg);border:1px solid var(--line);padding:2px 7px;border-radius:6px;font:13px ui-monospace,SFMono-Regular,Menlo,monospace;word-break:break-all}
.steps{margin:0;padding-left:20px}.steps li{margin:6px 0}
.muted{color:var(--mute)}.right{text-align:right}.hint{font-size:13px;color:var(--mute);margin:0}
/* panel: toolbar row + table + footer */
.panel{padding:0;overflow:visible}
.bar{display:flex;gap:10px;align-items:center;padding:12px 14px;border-bottom:1px dashed var(--line);flex-wrap:wrap}
.toolbar{display:flex;gap:8px;align-items:center;flex:1 1 320px;min-width:0;flex-wrap:wrap}
.toolbar select,.toolbar .search input{min-height:34px;height:34px;font-size:14px;padding:0 10px}
.toolbar .search input{padding-left:32px}
.toolbar select{flex:0 0 auto;padding-right:6px}
.search{position:relative;flex:1 1 220px;min-width:0;display:block}
.search svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--mute);pointer-events:none}
.search input{width:100%;padding-left:32px}
.toolbar .reset{font-size:13px;white-space:nowrap}
.ph-actions{display:flex;gap:8px;align-items:center;margin-left:auto}
.tablewrap{overflow-x:auto}
.panel-foot{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;padding:10px 14px;border-top:1px solid var(--line);color:var(--mute);font-size:13px}
.panel-foot b{color:var(--ink);font-weight:600}
.pager{display:flex;gap:14px;align-items:center;flex-wrap:wrap}
.pg{display:flex;gap:8px;align-items:center;margin:0}
.pg select,.pg input{min-height:30px;height:30px;font-size:13px;padding:0 8px}
.pg input{width:60px;text-align:center}
.pg button{min-height:30px;padding:0 10px;font-size:13px}
.pgnav{display:flex;gap:6px}
.ib.off{opacity:.4;pointer-events:none}
td.emptycell{text-align:center;padding:40px 16px;white-space:normal}
.emptycell b{display:block;font-size:15px;margin-bottom:2px;color:var(--ink)}.emptycell span{color:var(--mute)}
.panel .tablewrap td:first-child,.panel .tablewrap th:first-child{padding-left:16px}
.tabs{display:flex;gap:4px;margin:0 0 16px;border-bottom:1px solid var(--line);overflow-x:auto}
.tabs a{padding:9px 14px;color:var(--mute);text-decoration:none;font-weight:600;border-bottom:2px solid transparent;margin-bottom:-1px;white-space:nowrap}
.tabs a:hover{color:var(--ink)}.tabs a.on{color:var(--accent);border-color:var(--accent)}
/* password toggle */
.pw{position:relative;display:block}.pw input{width:100%;padding-right:44px}
.pw .pw-toggle{position:absolute;right:4px;top:50%;transform:translateY(-50%);min-height:32px;height:32px;width:36px!important;flex:none;padding:0;background:none;color:var(--mute);border-radius:6px}
.pw .pw-toggle:hover{color:var(--ink);filter:none;background:var(--hover)}
.pw .pw-toggle svg{width:20px;height:20px}
/* modal */
dialog.modal{overflow:visible;border:0;padding:0;background:transparent;max-width:min(520px,calc(100vw - 24px));width:100%;color:var(--ink)}
dialog.modal::backdrop{background:rgba(8,10,14,.6)}
.modal-card{background:var(--card);border:1px solid var(--line);border-radius:14px;box-shadow:0 20px 50px rgba(0,0,0,.35)}
.modal-head{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid var(--line)}
.modal-head h3{margin:0;font-size:17px}
.modal-body>*{min-width:0}
.modal-body{padding:18px;display:grid;gap:14px;max-height:calc(100vh - 140px);overflow:auto}
.modal-body form{display:grid;gap:14px;margin:0}
.modal-actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
.copybox{display:flex;gap:6px;align-items:center}
.copybox code{flex:1;min-width:0;display:flex;align-items:center;min-height:34px;padding:0 10px;border-radius:8px;overflow-x:auto;white-space:nowrap;word-break:normal}
.check{display:flex;gap:10px;align-items:center;font-size:15px;color:var(--ink)}
.check input{width:18px;height:18px;min-height:0;accent-color:var(--accent)}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
footer.foot{padding:0 0 28px;color:var(--mute);font-size:13px;text-align:center}
.auth{max-width:400px;margin:40px auto}
.auth .card{padding:24px}.auth form{display:flex;flex-direction:column;gap:12px}.auth input{width:100%}
.auth button.main{width:100%}
/* profile */
.stackcol{display:grid;gap:16px;max-width:760px;margin:0 auto;grid-template-columns:minmax(0,1fr)}
.stackcol>*,.copybox{min-width:0}
.me{display:flex;gap:16px;align-items:center;flex-wrap:wrap}
.me .who2{min-width:0;flex:1}.me .who2 b{display:block;font-size:18px;word-break:break-word}.me .who2 span.m{color:var(--mute);word-break:break-all}
.sec h3{margin:0 0 4px;font-size:16px}.sec>p.hint{margin-bottom:14px}
.sec form{display:grid;gap:14px;max-width:420px}
.seg{display:inline-flex;border:1px solid var(--line);border-radius:10px;overflow:hidden}
.seg button{border-radius:0;background:transparent;color:var(--ink);min-height:36px;padding:0 16px;font-weight:600}
.seg button+button{border-left:1px solid var(--line)}
.seg button[aria-pressed=true]{background:var(--accent);color:var(--accent-ink)}
.kv{display:grid;grid-template-columns:1fr auto;gap:0}.kv>*{padding:10px 0;border-bottom:1px solid var(--line)}.kv>*:nth-last-child(-n+2){border-bottom:0}
@media (max-width:720px){
 nav{flex-wrap:wrap;padding:8px 0;row-gap:4px}
 nav .brand{order:1;margin-right:auto}.navright{order:2}
 nav .links{order:3;flex:0 0 100%}
 nav .links a{padding:6px 10px}
 main{padding:18px 0 36px}h1{font-size:20px}
 .bar{padding:10px 12px}.toolbar{flex:1 1 100%}.search{flex:1 1 100%}.toolbar select{flex:1 1 calc(50% - 4px)}
 .ph-actions{width:100%}.ph-actions button{flex:1}
 table.stack,table.stack tbody,table.stack tr,table.stack td{display:block;width:100%}
 table.stack tr:first-child:has(th){display:none}
 table.stack tr{padding:12px 14px;border-bottom:1px solid var(--line)}
 table.stack tr:last-child{border-bottom:0}
 table.stack td{border:0;padding:3px 0;white-space:normal;word-break:break-word;display:flex;justify-content:space-between;gap:12px;background:transparent!important}
 table.stack td::before{content:attr(data-label);color:var(--mute);font-size:11px;text-transform:uppercase;letter-spacing:.06em;flex-shrink:0;padding-top:3px}
 table.stack td:not([data-label])::before{display:none}
 table.stack td.act{justify-content:flex-end;padding-top:8px;width:100%}
 td.emptycell{display:block}
 .grid2{grid-template-columns:1fr}
 .panel-foot{justify-content:center;text-align:center}.pager{justify-content:center}
 .modal-actions button{flex:1}
}
@media (prefers-reduced-motion:reduce){*{transition:none!important;animation:none!important}}
</style>
</head>
<body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
<symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
<symbol id="i-trash" viewBox="0 0 24 24"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m5 5v6m4-6v6"/></symbol>
<symbol id="i-edit" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></symbol>
<symbol id="i-unplug" viewBox="0 0 24 24"><path d="m19 5 3-3M2 22l3-3M6.3 20.3a2.4 2.4 0 0 0 3.4 0L12 18l-6-6-2.3 2.3a2.4 2.4 0 0 0 0 3.4ZM7.5 13.5 10 11M10.5 16.5 13 14M12 6l6 6 2.3-2.3a2.4 2.4 0 0 0 0-3.4l-2.6-2.6a2.4 2.4 0 0 0-3.4 0Z"/></symbol>
<symbol id="i-copy" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></symbol>
<symbol id="i-check" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></symbol>
<symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></symbol>
<symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></symbol>
<symbol id="i-moon" viewBox="0 0 24 24"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></symbol>
<symbol id="i-logout" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></symbol>
<symbol id="i-x" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></symbol>
<symbol id="i-left" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></symbol>
<symbol id="i-right" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></symbol>
</defs></svg>
<header><div class="wrap"><nav aria-label="Main">
<a class="brand" href="{{ auth()->check() ? route('dashboard') : '/login' }}">Publish</a>
@auth
@if(auth()->user()->hasVerifiedEmail())<div class="links">
<a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'on' : '' }}">Tunnels</a>
<a href="{{ route('history') }}" class="{{ request()->routeIs('history') ? 'on' : '' }}">History</a>
<a href="{{ route('tokens') }}" class="{{ request()->routeIs('tokens') ? 'on' : '' }}">Tokens</a>
<a href="{{ route('reservations') }}" class="{{ request()->routeIs('reservations') ? 'on' : '' }}">Subdomains</a>
<a href="{{ route('install') }}" class="{{ request()->routeIs('install') ? 'on' : '' }}">Install CLI</a>
@if(auth()->user()->is_admin)<a href="{{ route('admin') }}" class="{{ request()->routeIs('admin*') ? 'on' : '' }}">Admin</a>@endif
</div>@else<div class="links"></div>@endif
@endauth
<div class="navright">
<button type="button" class="ib" id="theme-toggle" aria-label="Switch to dark mode" title="Toggle light / dark" data-url="{{ auth()->check() ? route('profile.theme') : '' }}"><svg class="ic" id="theme-ic"><use href="#i-moon"/></svg></button>
@auth
<a class="avatar {{ request()->routeIs('profile') ? 'on' : '' }}" href="{{ route('profile') }}" aria-label="Your profile" title="{{ auth()->user()->name }} · Profile">{{ mb_substr(auth()->user()->name ?: auth()->user()->email, 0, 1) }}</a>
<form method="post" action="{{ route('logout') }}">@csrf<button class="ib" aria-label="Sign out" title="Sign out"><svg class="ic"><use href="#i-logout"/></svg></button></form>
@endauth
</div>
</nav></div></header>
<main><div class="wrap">
@if(session('ok'))<div class="flash" role="status">{{ session('ok') }}</div>@endif
@if(session('warn'))<div class="warn" role="alert">{{ session('warn') }}</div>@endif
@if(auth()->check() && $errors->any() && ! old('_form') && ! $__env->hasSection('own-errors'))<div class="warn" role="alert">{{ $errors->first() }}</div>@endif
@yield('content')
</div></main>
<footer class="foot"><div class="wrap">Publish @if(config('tunnel.support_email')) · Need help? <a href="mailto:{{ config('tunnel.support_email') }}">{{ config('tunnel.support_email') }}</a>@endif</div></footer>
<script>
(function(){
var h=document.documentElement;
function icon(id){return '<svg class="ic"><use href="#i-'+id+'"/></svg>'}
// theme
var tg=document.getElementById('theme-toggle');
function paint(){var dark=h.getAttribute('data-theme')==='dark';tg.innerHTML=icon(dark?'sun':'moon');tg.setAttribute('aria-label',dark?'Switch to light mode':'Switch to dark mode');
 document.querySelectorAll('[data-theme-set]').forEach(function(b){b.setAttribute('aria-pressed',(h.getAttribute('data-pref')||'system')===b.getAttribute('data-theme-set'))});}
function setTheme(pref){var dark=pref==='dark'||(pref==='system'&&matchMedia('(prefers-color-scheme: dark)').matches);
 h.setAttribute('data-pref',pref);h.setAttribute('data-theme',dark?'dark':'light');try{localStorage.setItem('theme',pref)}catch(e){}paint();
 var u=tg.getAttribute('data-url');if(u){fetch(u,{method:'PUT',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({theme:pref})}).catch(function(){})}}
tg.addEventListener('click',function(){setTheme(h.getAttribute('data-theme')==='dark'?'light':'dark')});
document.querySelectorAll('[data-theme-set]').forEach(function(b){b.addEventListener('click',function(){setTheme(b.getAttribute('data-theme-set'))})});
paint();
// password show/hide
var eye='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
var eyeOff='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.9 17.9A10.9 10.9 0 0 1 12 19c-6.4 0-10-7-10-7a18.5 18.5 0 0 1 5.1-5.9M9.9 5.2A9.7 9.7 0 0 1 12 5c6.4 0 10 7 10 7a18.6 18.6 0 0 1-2.2 3.2M1 1l22 22"/><path d="M14.1 14.1a3 3 0 1 1-4.2-4.2"/></svg>';
document.querySelectorAll('input[type=password]').forEach(function(i){
 var w=document.createElement('div');w.className='pw';i.parentNode.insertBefore(w,i);w.appendChild(i);
 var b=document.createElement('button');b.type='button';b.className='pw-toggle';b.setAttribute('aria-label','Show password');b.setAttribute('aria-pressed','false');b.innerHTML=eye;w.appendChild(b);
 b.addEventListener('click',function(){var show=i.type==='password';i.type=show?'text':'password';b.setAttribute('aria-pressed',show);b.setAttribute('aria-label',show?'Hide password':'Show password');b.innerHTML=show?eyeOff:eye;});
});
// copy + modals
function copy(t){if(navigator.clipboard&&window.isSecureContext)return navigator.clipboard.writeText(t);return new Promise(function(res){var a=document.createElement('textarea');a.value=t;a.style.position='fixed';a.style.opacity='0';document.body.appendChild(a);a.select();try{document.execCommand('copy')}catch(e){}a.remove();res();});}
document.addEventListener('click',function(e){
 var c=e.target.closest('[data-copy]');
 if(c){copy(c.getAttribute('data-copy')).then(function(){var o=c.innerHTML;c.innerHTML=icon('check');c.setAttribute('aria-label','Copied');setTimeout(function(){c.innerHTML=o;c.setAttribute('aria-label','Copy')},1400)});return;}
 var o=e.target.closest('[data-open]');
 if(o){var d=document.getElementById(o.getAttribute('data-open'));if(!d)return;
  if(o.hasAttribute('data-manage')){var f=d.querySelector('form'),g=o.dataset;f.action=g.action;d.querySelector('[data-f=email]').textContent=g.email;
   f.querySelector('input[type=checkbox][name=tunnel_access]').checked=g.premium==='1';f.elements.max_tunnels.value=g.tunnels;f.elements.max_reservations.value=g.reservations;
   var dis=f.querySelector('input[type=checkbox][name=disabled]');dis.checked=g.disabled==='1';dis.closest('.check').hidden=g.self==='1';}
  d.showModal();return;}
 var x=e.target.closest('[data-close]');if(x){x.closest('dialog').close();return;}
 if(e.target.matches&&e.target.matches('dialog.modal'))e.target.close();
});
document.querySelectorAll('dialog[data-autoopen]').forEach(function(d){d.showModal();});
// auto-submitting filter forms
document.querySelectorAll('form[data-auto]').forEach(function(f){
 var t;f.querySelectorAll('select').forEach(function(s){s.addEventListener('change',function(){f.submit()})});
 var q=f.querySelector('input[type=search]');
 if(q){q.addEventListener('input',function(){clearTimeout(t);t=setTimeout(function(){f.submit()},550)});
  if(q.value){q.focus();q.setSelectionRange(q.value.length,q.value.length);}}
 f.addEventListener('submit',function(){clearTimeout(t)});
});
})();
</script>
</body></html>
