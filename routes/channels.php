<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private channel for chat threads
// Specify 'sanctum' guard for API-only authentication
Broadcast::channel('chat.{threadId}', function ($user, $threadId) {
    try {
        // Log authorization attempt for debugging
        \Log::info('Channel authorization attempt', [
            'user_id' => $user->id ?? 'null',
            'user_type' => get_class($user),
            'thread_id' => $threadId,
        ]);
        
        // Check if user is authenticated
        if (!$user) {
            \Log::warning('Channel authorization failed: User is null');
            return false;
        }
        
        if (!isset($user->id) || !$user->id) {
            \Log::warning('Channel authorization failed: User ID is missing', [
                'user' => $user,
            ]);
            return false;
        }
        
        // Check if threadId is canonical format thread-X-Y
        if (preg_match('/^thread-(\d+)-(\d+)$/', $threadId, $matches)) {
            $p1 = (int) $matches[1];
            $p2 = (int) $matches[2];
            if ((int)$user->id === $p1 || (int)$user->id === $p2) {
                \Log::info('Channel authorization granted by canonical thread ID match', [
                    'user_id' => $user->id,
                    'thread_id' => $threadId,
                ]);
                return true;
            }
        }

        // Fallback: Check if user is part of any message in this thread
        $message = \App\Models\Message::where('thread_id', $threadId)
            ->where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)
                  ->orWhere('recipient_id', $user->id);
            })
            ->first();

        $authorized = $message !== null;
        
        \Log::info('Channel authorization result', [
            'user_id' => $user->id,
            'thread_id' => $threadId,
            'authorized' => $authorized,
            'message_found' => $message !== null,
        ]);
        
        // Return boolean explicitly (not null)
        return (bool) $authorized;
    } catch (\Exception $e) {
        \Log::error('Channel authorization exception', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return false;
    }
}, ['guards' => ['sanctum']]);

