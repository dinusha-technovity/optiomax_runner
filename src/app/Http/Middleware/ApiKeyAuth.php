<?php

namespace App\Http\Middleware;
 
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
 
class ApiKeyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');
        
        // Check if API key is provided
        if (!$apiKey) {
            return response()->json([
                'status' => false,
                'message' => 'API key is required',
                'error' => 'Missing X-API-Key header'
            ], 401);
        }
        
        // Get allowed API keys from environment
        $allowedApiKeys = explode(',', env('RUNNER_API_KEY', ''));
        
        // Check if the provided API key is valid
        if (!in_array($apiKey, $allowedApiKeys)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid API key',
                'error' => 'Unauthorized access'
            ], 401);
        }
        
        // Optional: Log API usage
        \Log::info('API Key Access', [
            'api_key' => substr($apiKey, 0, 8) . '...',
            'endpoint' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);
        
        return $next($request);
    }
}
