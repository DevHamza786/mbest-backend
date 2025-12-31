# Environment Variables Configuration Reference

## Backend .env (Laravel) - Lines 71-76

These lines should contain your Reverb WebSocket server configuration:

```env
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=3zfuo1xe9mwxccevpwvc
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http
```

### Important Notes:
- `REVERB_APP_KEY` must match exactly with frontend `VITE_REVERB_APP_KEY`
- `REVERB_HOST=0.0.0.0` allows connections from any interface
- `REVERB_PORT=8080` is the WebSocket server port
- `REVERB_SCHEME=http` for local development (use `https` in production)

## Frontend .env (Vite) - Lines 1-5

These lines should contain your frontend Reverb configuration:

```env
VITE_REVERB_APP_KEY=3zfuo1xe9mwxccevpwvc
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
VITE_API_BASE_URL=http://localhost:8000/api/v1
```

### Important Notes:
- `VITE_REVERB_APP_KEY` must match exactly with backend `REVERB_APP_KEY`
- `VITE_REVERB_HOST=localhost` (not 0.0.0.0)
- `VITE_REVERB_PORT=8080` must match backend `REVERB_PORT`
- `VITE_REVERB_SCHEME=http` must match backend `REVERB_SCHEME`
- `VITE_API_BASE_URL` should point to your Laravel API

## Verification Checklist

### Backend .env (Lines 71-76):
- [ ] `REVERB_APP_KEY` is set and matches frontend
- [ ] `REVERB_HOST=0.0.0.0`
- [ ] `REVERB_PORT=8080`
- [ ] `REVERB_SCHEME=http`
- [ ] `REVERB_APP_ID` is set (can be any unique string)
- [ ] `REVERB_APP_SECRET` is set (can be any unique string)

### Frontend .env (Lines 1-5):
- [ ] `VITE_REVERB_APP_KEY` matches backend `REVERB_APP_KEY` exactly
- [ ] `VITE_REVERB_HOST=localhost`
- [ ] `VITE_REVERB_PORT=8080` matches backend
- [ ] `VITE_REVERB_SCHEME=http` matches backend
- [ ] `VITE_API_BASE_URL` points to correct Laravel API URL

## Common Issues

### Issue 1: App Key Mismatch
**Symptom:** WebSocket connection fails
**Fix:** Ensure `REVERB_APP_KEY` in backend matches `VITE_REVERB_APP_KEY` in frontend exactly (case-sensitive)

### Issue 2: Port Mismatch
**Symptom:** Connection refused errors
**Fix:** Ensure both `REVERB_PORT` and `VITE_REVERB_PORT` are `8080`

### Issue 3: Scheme Mismatch
**Symptom:** WebSocket tries to use wrong protocol (ws vs wss)
**Fix:** Ensure both `REVERB_SCHEME` and `VITE_REVERB_SCHEME` are `http` for local dev

### Issue 4: Host Configuration
**Symptom:** Can't connect from frontend
**Fix:** 
- Backend: `REVERB_HOST=0.0.0.0` (allows all interfaces)
- Frontend: `VITE_REVERB_HOST=localhost` (connects to local machine)

## Quick Test

After updating .env files:

1. **Backend:**
   ```bash
   php artisan config:clear
   php artisan reverb:start
   ```
   Should see: `INFO Starting server on 0.0.0.0:8080 (localhost).`

2. **Frontend:**
   ```bash
   # Restart dev server
   npm run dev
   ```
   Check browser console for: `✅ Connected to Reverb WebSocket server`

