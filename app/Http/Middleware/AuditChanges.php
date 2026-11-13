<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuditTrail;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditChanges
{
    public function __construct(private AuditTrail $trail) {}

    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();
        if ($request->isMethodSafe() || ! $actor instanceof User) {
            return $next($request);
        }
        $this->trail->begin($actor);
        $level = DB::transactionLevel();
        try {
            DB::beginTransaction();
            $response = $next($request);
            if ($response->getStatusCode() >= 400) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            return $response;
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > $level) {
                DB::rollBack();
            }
            throw $exception;
        } finally {
            $this->trail->end();
        }
    }
}
