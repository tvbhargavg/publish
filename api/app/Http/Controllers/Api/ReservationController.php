<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\Audit;
use App\Services\TunnelAuthorizer;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function index(Request $r)
    {
        return $r->user()->reservations()->get(['id', 'subdomain', 'created_at']);
    }

    public function store(Request $r)
    {
        $sub = strtolower((string) $r->input('subdomain'));
        abort_unless(TunnelAuthorizer::validSubdomain($sub) && ! in_array($sub, config('tunnel.reserved'), true), 422, 'Invalid or reserved subdomain');
        abort_if($r->user()->reservations()->count() >= $r->user()->max_reservations, 429, 'Reservation limit reached');
        abort_if(Reservation::where('subdomain', $sub)->exists(), 409, 'Subdomain already reserved');
        $res = $r->user()->reservations()->create(['subdomain' => $sub]);
        Audit::log($r->user(), 'reservation.created', 'reservation', $res->id, ['subdomain' => $sub]);

        return response()->json($res, 201);
    }

    public function destroy(Request $r, int $id)
    {
        $res = $r->user()->reservations()->whereKey($id)->firstOrFail();
        $res->delete();
        Audit::log($r->user(), 'reservation.deleted', 'reservation', $id, ['subdomain' => $res->subdomain]);

        return response()->noContent();
    }
}
