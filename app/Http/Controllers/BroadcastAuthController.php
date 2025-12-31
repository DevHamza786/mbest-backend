<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

class BroadcastAuthController extends \Illuminate\Broadcasting\BroadcastController
{
    /**
     * Authenticate the request for channel access.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function authenticate(Request $request)
    {
        Log::info('Broadcast auth request received', [
            'channel_name' => $request->input('channel_name'),
            'socket_id' => $request->input('socket_id'),
            'has_user' => $request->user() !== null,
            'user_id' => $request->user()?->id,
            'has_token' => $request->bearerToken() !== null,
        ]);

        if ($request->hasSession()) {
            $request->session()->reflash();
        }

        try {
            // Ensure user is authenticated and set on request for API-only setup
            $user = $request->user();
            if (!$user) {
                Log::error('No authenticated user found for broadcast auth', [
                    'has_token' => $request->bearerToken() !== null,
                    'channel_name' => $request->input('channel_name'),
                ]);
                return response()->json([
                    'error' => 'Unauthenticated',
                    'message' => 'You must be authenticated to access this channel.',
                ], 401);
            }
            
            // Get the broadcaster to check registered channels
            $broadcaster = app('Illuminate\Broadcasting\BroadcastManager')->connection();
            if (method_exists($broadcaster, 'getChannels')) {
                $channels = $broadcaster->getChannels()->keys()->toArray();
                Log::info('Broadcast channels registered', [
                    'channels' => $channels,
                    'requested_channel' => $request->input('channel_name'),
                    'normalized' => str_replace('private-', '', $request->input('channel_name')),
                    'user_id' => $user->id,
                ]);
            }
            
            $response = Broadcast::auth($request);
            
            // Broadcast::auth() can return null, array, or Response
            if ($response === null) {
                $channelName = $request->input('channel_name');
                $normalizedChannel = str_replace('private-', '', $channelName);
                
                Log::error('Broadcast::auth() returned null', [
                    'original_channel' => $channelName,
                    'normalized_channel' => $normalizedChannel,
                    'available_channels' => ['chat.{threadId}'],
                    'user_id' => $request->user()?->id,
                ]);
                
                return response()->json([
                    'error' => 'Channel authorization failed',
                    'message' => 'Unable to authorize channel access.',
                ], 403);
            }
            
            // Handle array response (Pusher/Reverb returns array for private channels)
            if (is_array($response)) {
                Log::info('Broadcast auth response (array)', [
                    'response' => $response,
                ]);
                return response()->json($response);
            }
            
            // Handle Response object
            if (is_object($response) && method_exists($response, 'getStatusCode')) {
                Log::info('Broadcast auth response (Response)', [
                    'status' => $response->getStatusCode(),
                    'content' => $response->getContent(),
                ]);
                return $response;
            }
            
            // Unknown response type
            Log::error('Broadcast::auth() returned unknown type', [
                'response_type' => gettype($response),
                'response' => $response,
                'channel_name' => $request->input('channel_name'),
            ]);
            
            // Try to convert to JSON response
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Broadcast auth error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Authentication failed',
                'message' => $e->getMessage(),
            ], 403);
        }
    }
}

