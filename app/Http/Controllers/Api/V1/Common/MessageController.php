<?php

namespace App\Http\Controllers\Api\V1\Common;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Message::where(function ($q) use ($user) {
            $q->where('sender_id', $user->id)
              ->orWhere('recipient_id', $user->id);
        })
        ->with(['sender', 'recipient', 'attachments']);

        // Filter by thread
        if ($request->has('thread_id')) {
            $query->where('thread_id', $request->thread_id);
        }

        // Filter unread only
        if ($request->boolean('unread_only')) {
            $query->where('recipient_id', $user->id)
                  ->where('is_read', false);
        }

        $perPage = $request->get('per_page', 15);
        $messages = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'recipient_id' => 'required|exists:users,id',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'thread_id' => 'nullable|string',
            'is_important' => 'nullable|boolean',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
        ]);

        // Generate thread_id if not provided
        $threadId = $validated['thread_id'] ?? 'thread-' . uniqid();

        $message = Message::create([
            'thread_id' => $threadId,
            'sender_id' => $user->id,
            'recipient_id' => $validated['recipient_id'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'is_read' => false,
            'is_important' => $validated['is_important'] ?? false,
        ]);

        // Handle attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('messages/attachments', 'public');
                MessageAttachment::create([
                    'message_id' => $message->id,
                    'name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ]);
            }
        }

        // Load relationships
        $message->load(['sender', 'recipient', 'attachments']);

        // Broadcast the message to the thread channel
        // Note: Using broadcast() instead of ->toOthers() so sender also receives it
        // The frontend will handle duplicate prevention
        try {
            \Log::info('Broadcasting message', [
                'message_id' => $message->id,
                'thread_id' => $message->thread_id,
                'channel' => 'chat.' . $message->thread_id,
                'sender_id' => $message->sender_id,
                'recipient_id' => $message->recipient_id,
            ]);
            // Broadcast the message - uses default broadcaster (reverb)
            broadcast(new MessageSent($message));
            \Log::info('Message broadcasted successfully');
        } catch (\Exception $e) {
            \Log::error('Failed to broadcast message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message_id' => $message->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $message,
            'message' => 'Message sent successfully',
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $message = Message::where(function ($q) use ($user) {
            $q->where('sender_id', $user->id)
              ->orWhere('recipient_id', $user->id);
        })
        ->with(['sender', 'recipient', 'attachments'])
        ->findOrFail($id);

        // Mark as read if recipient (only when explicitly viewing)
        if ($message->recipient_id === $user->id && !$message->is_read) {
            $message->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $message,
        ]);
    }

    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();

        $message = Message::where('recipient_id', $user->id)
            ->findOrFail($id);

        $message->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $message->load(['sender', 'recipient', 'attachments']),
            'message' => 'Message marked as read',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $message = Message::where(function ($q) use ($user) {
            $q->where('sender_id', $user->id)
              ->orWhere('recipient_id', $user->id);
        })->findOrFail($id);

        // Delete attachments
        foreach ($message->attachments as $attachment) {
            if ($attachment->file_path && Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }
            $attachment->delete();
        }

        $message->delete();

        return response()->json([
            'success' => true,
            'message' => 'Message deleted successfully',
        ]);
    }

    public function threads(Request $request)
    {
        $user = $request->user();

        $threads = Message::where(function ($q) use ($user) {
            $q->where('sender_id', $user->id)
              ->orWhere('recipient_id', $user->id);
        })
        ->select('thread_id')
        ->distinct()
        ->get()
        ->pluck('thread_id');

        $threadData = [];
        foreach ($threads as $threadId) {
            $lastMessage = Message::where('thread_id', $threadId)
                ->where(function ($q) use ($user) {
                    $q->where('sender_id', $user->id)
                      ->orWhere('recipient_id', $user->id);
                })
                ->with(['sender', 'recipient'])
                ->orderBy('created_at', 'desc')
                ->first();

            // Skip if no last message found (shouldn't happen, but safety check)
            if (!$lastMessage) {
                continue;
            }

            $unreadCount = Message::where('thread_id', $threadId)
                ->where('recipient_id', $user->id)
                ->where('is_read', false)
                ->count();

            $participant = $lastMessage->sender_id === $user->id 
                ? $lastMessage->recipient 
                : $lastMessage->sender;

            $threadData[] = [
                'thread_id' => $threadId,
                'last_message' => [
                    'id' => $lastMessage->id,
                    'subject' => $lastMessage->subject,
                    'body' => $lastMessage->body,
                    'is_read' => $lastMessage->is_read,
                    'is_important' => $lastMessage->is_important,
                    'created_at' => $lastMessage->created_at,
                    'sender' => $lastMessage->sender,
                ],
                'unread_count' => $unreadCount,
                'participant' => $participant,
            ];
        }

        // Sort by last message created_at (most recent first)
        usort($threadData, function ($a, $b) {
            $timeA = strtotime($a['last_message']['created_at']);
            $timeB = strtotime($b['last_message']['created_at']);
            return $timeB - $timeA; // Descending order (newest first)
        });

        return response()->json([
            'success' => true,
            'data' => $threadData,
        ]);
    }
}

