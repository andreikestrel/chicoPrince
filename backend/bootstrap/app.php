<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $exception) => $request->is('api/*') || $request->expectsJson());

        $exceptions->respond(function (Response $response): Response {
            if (! request()->is('api/*') || $response->getStatusCode() < 400) {
                return $response;
            }

            $status = $response->getStatusCode();
            [$code, $message] = match ($status) {
                400 => ['bad_request', 'Requisição inválida.'],
                401 => ['unauthenticated', 'Autenticação necessária.'],
                403 => ['forbidden', 'Acesso não permitido.'],
                404 => ['not_found', 'Recurso não encontrado.'],
                405 => ['method_not_allowed', 'Método não permitido.'],
                419 => ['session_expired', 'Sessão expirada.'],
                422 => ['validation_failed', 'Os dados enviados são inválidos.'],
                429 => ['too_many_requests', 'Muitas requisições. Tente novamente em instantes.'],
                default => ['http_error', 'Não foi possível processar a requisição.'],
            };

            if ($status >= 500) {
                [$code, $message] = ['server_error', 'Serviço temporariamente indisponível.'];
            }

            $original = json_decode($response->getContent(), true);
            $headers = $response->headers->all();
            unset($headers['content-type'], $headers['content-length']);

            return new JsonResponse(['error' => [
                'code' => $code,
                'message' => $message,
                'details' => $status === 422 ? (object) ($original['errors'] ?? []) : (object) [],
            ]], $status, $headers);
        });
    })->create();
