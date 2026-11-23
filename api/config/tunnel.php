<?php

// Platform configuration. Everything comes from the environment so the domain
// and ports can change without touching application code.
return [
    'base_domain' => env('TUNNEL_BASE_DOMAIN', 'localhost'),
    'server_host' => env('TUNNEL_SERVER_HOST', 'localhost'),
    'control_port' => (int) env('TUNNEL_CONTROL_PORT', 4443),
    'tls' => filter_var(env('TUNNEL_TLS', false), FILTER_VALIDATE_BOOL),
    'public_port' => (int) env('TUNNEL_PUBLIC_PORT', 443),
    'internal_secret' => env('INTERNAL_API_SECRET'),
    'allow_unreserved_custom' => filter_var(env('TUNNEL_ALLOW_UNRESERVED_CUSTOM', true), FILTER_VALIDATE_BOOL),
    'allow_registration' => filter_var(env('ALLOW_REGISTRATION', false), FILTER_VALIDATE_BOOL),
    'stale_grace_seconds' => 90,
    'reserved' => ['www', 'api', 'admin', 'app', 'dashboard', 'mail', 'ftp', 'ns1', 'ns2', 'smtp', 'tunnel', 'tunnels',
        'status', 'auth', 'login', 'static', 'assets', 'internal', 'localhost', 'root', 'support', 'help', 'docs'],
];
