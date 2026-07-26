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
            $threadId = $request->thread_id;
            
            // If thread_id matches format thread-X-Y, get all messages between participant X and participant Y
            if (preg_match('/^thread-(\d+)-(\d+)$/', $threadId, $matches)) {
                $p1 = (int) $matches[1];
                $p2 = (int) $matches[2];
                $query->where(function ($q) use ($threadId, $p1, $p2) {
                    $q->where('thread_id', $threadId)
                      ->orWhere(function ($q2) use ($p1, $p2) {
                          $q2->where(function ($sub) use ($p1, $p2) {
                              $sub->where('sender_id', $p1)->where('recipient_id', $p2);
                          })->orWhere(function ($sub) use ($p1, $p2) {
                              $sub->where('sender_id', $p2)->where('recipient_id', $p1);
                          });
                      });
                });
            } else {
                $query->where('thread_id', $threadId);
            }
        }

        // Filter unread only
        if ($request->boolean('unread_only')) {
            $query->where('recipient_id', $user->id)
                  ->where('is_read', false);
        }

        $perPage = $request->get('per_page', 100);
        $messages = $query->orderBy('created_at', 'asc')->paginate($perPage);

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

        $recipientId = (int) $validated['recipient_id'];
        $u1 = min($user->id, $recipientId);
        $u2 = max($user->id, $recipientId);
        $canonicalThreadId = "thread-{$u1}-{$u2}";

        $threadId = $validated['thread_id'] ?? $canonicalThreadId;
        if (empty($threadId) || !preg_match('/^thread-\d+-\d+$/', $threadId)) {
            $threadId = $canonicalThreadId;
        }

        $message = Message::create([
            'thread_id' => $threadId,
            'sender_id' => $user->id,
            'recipient_id' => $recipientId,
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
        try {
            \Log::info('Broadcasting message', [
                'message_id' => $message->id,
                'thread_id' => $message->thread_id,
                'channel' => 'chat.' . $message->thread_id,
                'sender_id' => $message->sender_id,
                'recipient_id' => $message->recipient_id,
            ]);
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

        // Mark as read if recipient
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

        $allMessages = Message::where(function ($q) use ($user) {
            $q->where('sender_id', $user->id)
              ->orWhere('recipient_id', $user->id);
        })
        ->with(['sender', 'recipient'])
        ->orderBy('created_at', 'desc')
        ->get();

        $groupedThreads = [];

        foreach ($allMessages as $msg) {
            $otherUserId = $msg->sender_id === $user->id ? $msg->recipient_id : $msg->sender_id;
            $u1 = min($user->id, $otherUserId);
            $u2 = max($user->id, $otherUserId);
            $canonicalThreadId = "thread-{$u1}-{$u2}";

            if (!isset($groupedThreads[$canonicalThreadId])) {
                $participant = $msg->sender_id === $user->id ? $msg->recipient : $msg->sender;
                $groupedThreads[$canonicalThreadId] = [
                    'thread_id' => $canonicalThreadId,
                    'last_message' => [
                        'id' => $msg->id,
                        'subject' => $msg->subject,
                        'body' => $msg->body,
                        'is_read' => $msg->is_read,
                        'is_important' => $msg->is_important,
                        'created_at' => $msg->created_at->toIso8601String(),
                        'sender' => $msg->sender,
                    ],
                    'unread_count' => 0,
                    'participant' => $participant,
                ];
            }

            if ($msg->recipient_id === $user->id && !$msg->is_read) {
                $groupedThreads[$canonicalThreadId]['unread_count']++;
            }
        }

        $threadData = array_values($groupedThreads);

        usort($threadData, function ($a, $b) {
            $timeA = strtotime($a['last_message']['created_at']);
            $timeB = strtotime($b['last_message']['created_at']);
            return $timeB - $timeA;
        });

        return response()->json([
            'success' => true,
            'data' => $threadData,
        ]);
    }
}
