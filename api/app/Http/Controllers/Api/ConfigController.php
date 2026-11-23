<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/** Public, non-secret client configuration. */
class ConfigController extends Controller
{
    public function show()
    {
        return [
            'base_domain' => config('tunnel.base_domain'),
            'server_host' => config('tunnel.server_host'),
            'control_port' => config('tunnel.control_port'),
            'tls' => config('tunnel.tls'),
        ];
    }
}
