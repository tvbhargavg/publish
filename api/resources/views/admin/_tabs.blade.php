<nav class="tabs" aria-label="Admin sections">
<a href="{{ route('admin') }}" class="{{ request()->routeIs('admin') ? 'on' : '' }}">Users</a>
<a href="{{ route('admin.tunnels') }}" class="{{ request()->routeIs('admin.tunnels') ? 'on' : '' }}">Active tunnels</a>
<a href="{{ route('admin.audit') }}" class="{{ request()->routeIs('admin.audit') ? 'on' : '' }}">Audit log</a>
</nav>
