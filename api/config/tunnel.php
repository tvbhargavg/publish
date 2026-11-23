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
    // Plans: everyone gets `free_max_tunnels` without approval; an admin-granted premium flag
    // (users.tunnel_access) raises it to the per-user users.max_tunnels (default below).
    'free_max_tunnels' => (int) env('FREE_MAX_TUNNELS', 1),
    'free_max_reservations' => (int) env('FREE_MAX_RESERVATIONS', 1),
    'premium_default_max_tunnels' => (int) env('PREMIUM_MAX_TUNNELS', 4),
    'admin_email' => env('ADMIN_EMAIL', 'admin@example.com'),
    'admin_password' => env('ADMIN_PASSWORD'),
    // Where the built CLI binaries live (deploy/release/build-cli.sh writes here).
    'cli_dist_dir' => env('CLI_DIST_DIR', base_path('../dist/cli')),
    // Port used in example commands shown in the UI.
    'example_port' => (int) env('CLI_EXAMPLE_PORT', 5003),
    'support_email' => env('SUPPORT_EMAIL'),
    'per_page' => 10,
    'stale_grace_seconds' => 90,
    'reserved' => ['www', 'api', 'admin', 'app', 'dashboard', 'mail', 'ftp', 'ns1', 'ns2', 'smtp', 'tunnel', 'tunnels',
        'status', 'auth', 'login', 'static', 'assets', 'internal', 'localhost', 'root', 'support', 'help', 'docs'],
];
