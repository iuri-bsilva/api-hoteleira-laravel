<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*')) {
            $request->attributes->set('audit_request_id', (string) Str::uuid());
            $request->attributes->set('audit_started_at', microtime(true));
        }

        $response = $next($request);
        if ($id = $request->attributes->get('audit_request_id')) {
            $response->headers->set('X-Request-ID', $id);
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $request->attributes->has('audit_request_id')) {
            return;
        }
        $status = $response->getStatusCode();
        $level = $status >= 500 ? 'error' : ($status >= 400 ? 'warning' : 'info');
        try {
            Log::channel('audit')->log($level, 'api.request', [
                'request_id' => $request->attributes->get('audit_request_id'),
                'user_id' => $request->user()?->id,
                'method' => $request->method(),
                'route' => $request->route()?->uri() ?? 'unmatched',
                'status' => $status,
                'duration_ms' => round((microtime(true) - $request->attributes->get('audit_started_at')) * 1000, 2),
            ]);
        } catch (\Throwable) {
            // Falha de escrita do log não deve alterar a resposta da API.
            error_log('Foco: não foi possível gravar o log de auditoria.');
        }
    }
}
