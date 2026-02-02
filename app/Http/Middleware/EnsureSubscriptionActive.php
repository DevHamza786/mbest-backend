<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    /**
     * Handle an incoming request.
     * 
     * Redirects parents to subscription selection if they haven't purchased a package
     * or if their subscription is not approved.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only apply to parent users
        if ($user && $user->role === 'parent') {
            // Check if parent has an active subscription
            if (!$user->package_id || $user->subscription_status !== 'active') {
                // Allow access to subscription-related routes
                $allowedPaths = [
                    '/subscription',
                    '/packages',
                    '/payment',
                    '/auth/logout',
                    '/auth/me',
                    '/profile',
                    '/notifications',
                    '/messages',
                ];

                $currentPath = $request->path();
                $isAllowedRoute = false;

                foreach ($allowedPaths as $path) {
                    if (str_contains($currentPath, $path)) {
                        $isAllowedRoute = true;
                        break;
                    }
                }

                if (!$isAllowedRoute) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please select and pay for a subscription package to continue.',
                        'redirect_to' => '/parent/subscription/packages',
                        'subscription_status' => $user->subscription_status,
                        'has_package' => !!$user->package_id,
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
