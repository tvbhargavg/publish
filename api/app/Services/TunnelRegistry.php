<?php

namespace App\Services;

/** Read/remove access to the active-tunnel registry shared with the Go server. */
interface TunnelRegistry
{
    /** @return array{tunnel_id:string,user_id:string,local_port:int,status:string,created_at:int,last_heartbeat:int}|null */
    public function get(string $subdomain): ?array;

    /** Remove the entry; the Go server drops the live tunnel at its next heartbeat. */
    public function remove(string $subdomain): void;
}
