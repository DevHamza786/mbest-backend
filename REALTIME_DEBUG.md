# Real-time Messaging Debug Guide

## Issue: Messages not updating in real-time

### What to Check

1. **Backend Broadcasting Configuration**
   - Check `.env` file has: `BROADCAST_DRIVER=reverb`
   - Verify Reverb server is running: `php artisan reverb:start`
   - Check Laravel logs for broadcast errors

2. **Frontend WebSocket Connection**
   - Open browser console
   - Look for: `✅ Connected to Reverb WebSocket server`
   - Check for: `✅ Successfully subscribed to channel: chat.{threadId}`

3. **Channel Subscription**
   - Verify channel name matches: `chat.{thread_id}`
   - Check for subscription errors in console
   - Verify authentication token is valid

### Debugging Steps

1. **Check Backend Logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```
   Look for:
   - "Broadcasting message" - confirms broadcast attempt
   - "Message broadcasted successfully" - confirms broadcast success
   - Any errors related to broadcasting

2. **Check Browser Console:**
   - `🔌 Subscribing to channel: chat.{threadId}` - subscription attempt
   - `✅ Successfully subscribed to channel` - subscription success
   - `🔔 WebSocket event received` - message received
   - `📨 Received new message via WebSocket` - message added

3. **Test Broadcasting:**
   ```bash
   php artisan tinker
   ```
   Then:
   ```php
   $message = App\Models\Message::latest()->first();
   broadcast(new App\Events\MessageSent($message));
   ```
   Check if message appears in frontend.

### Common Issues

1. **BROADCAST_DRIVER not set to 'reverb'**
   - Fix: Add `BROADCAST_DRIVER=reverb` to `.env`
   - Run: `php artisan config:clear`

2. **Channel subscription happening before connection**
   - Fix: Code now waits for connection before subscribing

3. **Channel authorization failing**
   - Check: `routes/channels.php` authorization logic
   - Verify: User is authenticated and has access to thread

4. **Event not being broadcast**
   - Check: Laravel logs for broadcast errors
   - Verify: `MessageSent` event implements `ShouldBroadcast`

### Verification Checklist

- [ ] `BROADCAST_DRIVER=reverb` in backend `.env`
- [ ] Reverb server running (`php artisan reverb:start`)
- [ ] Frontend shows: `✅ Connected to Reverb WebSocket server`
- [ ] Frontend shows: `✅ Successfully subscribed to channel`
- [ ] Backend logs show: "Broadcasting message"
- [ ] Backend logs show: "Message broadcasted successfully"
- [ ] Browser console shows: `🔔 WebSocket event received`
- [ ] Message appears in UI without refresh

