<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Number;

class InstallController extends Controller
{
    private const PLATFORMS = [
        'linux-amd64' => ['Linux (Intel/AMD)', 'publish-linux-amd64'],
        'linux-arm64' => ['Linux (ARM)', 'publish-linux-arm64'],
        'darwin-arm64' => ['macOS (Apple Silicon)', 'publish-darwin-arm64'],
        'darwin-amd64' => ['macOS (Intel)', 'publish-darwin-amd64'],
        'windows-amd64' => ['Windows (64-bit)', 'publish-windows-amd64.exe'],
    ];

    public function show(Request $r)
    {
        $dir = config('tunnel.cli_dist_dir');
        $items = [];
        foreach (self::PLATFORMS as $key => [$label, $file]) {
            $path = $dir.'/'.$file;
            $items[] = ['key' => $key, 'label' => $label, 'file' => $file, 'ready' => is_file($path),
                'size' => is_file($path) ? Number::fileSize(filesize($path)) : null];
        }
        $server = config('tunnel.server_host').':'.config('tunnel.control_port');

        return view('install', ['items' => $items, 'server' => $server, 'base' => rtrim(config('app.url'), '/')]);
    }

    /** Public, whitelisted download of a built CLI binary (no path input is ever used). */
    public function download(string $file)
    {
        $allowed = array_column(self::PLATFORMS, 1) + [99 => 'SHA256SUMS'];
        abort_unless(in_array($file, $allowed, true), 404);
        $path = config('tunnel.cli_dist_dir').'/'.$file;
        abort_unless(is_file($path), 404);

        return response()->download($path, $file, ['Cache-Control' => 'public, max-age=300']);
    }
}
