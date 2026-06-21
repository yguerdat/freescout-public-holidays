<?php

namespace Modules\PublicHolidays\Http\Middleware;

use Closure;

/**
 * Authenticates the Public Holidays REST API.
 *
 * The same key as FreeScout's "API & Webhooks" module is accepted (header
 * X-FreeScout-API-Key or ?api_key=). If that module is not installed, a token
 * configured in the module settings is used instead.
 */
class ApiAuth
{
    public function handle($request, Closure $next)
    {
        if ($request->getMethod() == 'OPTIONS') {
            return response('', 204)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Headers', 'Content-Type,Authorization,X-FreeScout-API-Key')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS');
        }

        $provided = $request->header('x_freescout_api_key')
            ?? $request->header('X-FreeScout-API-Key')
            ?? $request->input('api_key')
            ?? '';

        if (empty($provided) || !$this->isValid($provided)) {
            return response()->json(['message' => 'Not Authorized'], 401)
                ->header('Access-Control-Allow-Origin', '*');
        }

        $response = $next($request);

        return $response->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Validate the provided key against the accepted keys.
     */
    private function isValid($provided)
    {
        $keys = [];

        // FreeScout "API & Webhooks" key, when that module is active.
        if (class_exists('\ApiWebhooks') && method_exists('\ApiWebhooks', 'getApiKey')) {
            $fsKey = \ApiWebhooks::getApiKey();
            if (!empty($fsKey)) {
                $keys[] = $fsKey;
            }
        }

        // Module-specific token (optional fallback / additional key).
        $own = \Option::get('publicholidays.api_token', '');
        if (!empty($own)) {
            $keys[] = $own;
        }

        foreach ($keys as $key) {
            if (hash_equals((string) $key, (string) $provided)) {
                return true;
            }
        }

        return false;
    }
}
