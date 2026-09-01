# Facebook Messenger Webhook Setup Guide

This guide walks you through setting up Facebook Messenger integration for the Algerian School AI Support system.

## Prerequisites

1. Facebook Developer Account
2. PHP 8.2+ with required extensions
3. Python 3.10+ with AI engine installed
4. ngrok (for local testing)

---

## Step 1: Create Facebook Developer App

### 1.1 Go to Facebook Developers
1. Visit https://developers.facebook.com/
2. Click **"Get Started"** or **"My Apps"**
3. Log in with your Facebook account

### 1.2 Create New App
1. Click **"Create App"**
2. Select **"Business"** as app type
3. Click **"Next"**
4. Fill in:
   - **App Name**: `Algerian School Support`
   - **App Contact Email**: Your email
   - **Business Account**: Select or create one
5. Click **"Create App"**

### 1.3 Add Messenger Product
1. In your app dashboard, click **"Add Product"**
2. Find **"Messenger"** and click **"Set Up"**

---

## Step 2: Configure Messenger

### 2.1 Generate Page Access Token
1. In Messenger > Settings, find **"Token Generation"**
2. Select your Facebook Page (or create one)
3. Copy the **Page Access Token**
4. Add it to your `.env` file:
   ```
   MESSENGER_PAGE_ACCESS_TOKEN=EAAxxxxxxx...
   ```

### 2.2 Get App Secret
1. Go to **App Settings** > **Basic**
2. Find **"App Secret"**
3. Click **"Show"** and copy it
4. Add it to your `.env` file:
   ```
   MESSENGER_APP_SECRET=EAAxxxxxxx...
   ```

### 2.3 Set Webhook Verify Token
1. Choose a secure random string (you already have one in `.env`):
   ```
   MESSENGER_VERIFY_TOKEN=algerian-school-verify-token-2026
   ```
2. **Important**: Remember this token, you'll need it in Step 4

---

## Step 3: Install and Configure ngrok

### 3.1 Download ngrok
1. Visit https://ngrok.com/download
2. Download for Windows
3. Extract `ngrok.exe` to a folder in your PATH

### 3.2 Setup ngrok Account
1. Sign up at https://ngrok.com
2. Get your authtoken from the dashboard
3. Run:
   ```bash
   ngrok config add-authtoken YOUR_AUTHTOKEN
   ```

### 3.3 Start ngrok Tunnel
1. Start your Laravel server:
   ```bash
   cd school_dashboard
   php artisan serve --port=8080
   ```

2. In a new terminal, start ngrok:
   ```bash
   ngrok http http://localhost:8080
   ```

3. ngrok will show something like:
   ```
   Forwarding  https://xxxx-xxx-xxx.ngrok.io -> http://localhost:8080
   ```

4. Copy the **https** URL (e.g., `https://abc123.ngrok.io`)

---

## Step 4: Configure Webhook in Facebook

### 4.1 Subscribe to Webhook
1. In Messenger > Settings > **Webhooks**
2. Click **"Subscribe to Events"**
3. Fill in:
   - **Callback URL**: `https://your-ngrok-url.ngrok.io/api/webhook/messenger`
   - **Verify Token**: `algerian-school-verify-token-2026`
4. Click **"Verify and Save"**
5. You should see a success message

### 4.2 Subscribe to Events
1. After verification, click **"Subscribe to Events"**
2. Enable these permissions:
   - ✅ `messages` - Receive messages
   - ✅ `messaging_postbacks` - Receive postbacks
   - ✅ `messaging_optins` - Receive opt-ins
   - ✅ `feed` - Receive comments on posts
3. Click **"Save"**

### 4.3 Configure Comments Webhook (Optional)
To enable auto-replies to comments:

1. Go to **Webhooks** > **Callback URL**
2. Add the same URL for comments: `https://your-ngrok-url.ngrok.io/api/webhook/facebook/comments`
3. Verify the URL
4. Subscribe to the `feed` field

---

## Step 5: Test the Integration

### 5.1 Send a Test Message
1. Find your Facebook Page
2. Click **"Send Message"** (or go to Messenger)
3. Send a message to your page

### 5.2 Check Laravel Logs
```bash
tail -f storage/logs/laravel.log
```

You should see:
```
[YYYY-MM-DD HH:MM:SS] local.INFO: Messenger message received {"sender":"123456","text":"Bonjour"}
[YYYY-MM-DD HH:MM:SS] local.INFO: Lead stored from Messenger {"lead_id":"xxx","sender":"123456","score":"HOT"}
[YYYY-MM-DD HH:MM:SS] local.INFO: Auto-reply sent {"recipient":"123456"}
```

### 5.3 Check the Dashboard
1. Open your dashboard: `http://localhost:8080/ai-dashboard`
2. You should see the new lead appear with source "messenger"

---

## Step 6: Production Deployment

### 6.1 Deploy the Laravel dashboard to Render
1. The repo ships a `render.yaml` Blueprint (Docker web service + 1 GB disk).
2. Push `main`, then Render → **New → Blueprint** → select the repo. Render
   auto-creates the `algerian-school-dashboard` service.
3. In **Render Dashboard → Environment**, set the `sync: false` secrets:
   `APP_KEY`, `APP_URL`, `MESSENGER_VERIFY_TOKEN`, `MESSENGER_PAGE_ACCESS_TOKEN`,
   `MESSENGER_APP_SECRET`, `AI_API_KEY`.
4. Deploy. Apache binds to Render's `$PORT`; health check hits `/api/health`.

### 6.2 Use a Real Domain
1. Replace ngrok with the Render URL (or attach a custom domain in Render).
2. Use HTTPS (required by Facebook — Render provides TLS automatically).
3. Update `MESSENGER_AI_SERVICE_URL` in the Render environment to point at the
   AI engine's public URL.

### 6.3 Enable Webhook
1. In Facebook Developer Dashboard
2. Go to Messenger > Settings
3. Enable **"Webhook"**
4. Set Callback URL to `https://<your-render-domain>/api/webhook/...` (the
   routes are under the `api` prefix on Render).

### 6.4 Submit for Review
1. Complete App Review
2. Request `pages_messaging` permission
3. Submit for Facebook review

---

## Facebook Comments Handling

The system automatically handles comments on your Facebook posts with **dual response**:

### How It Works

1. **Comment Received**: When someone comments on your post, Facebook sends the event to `/api/webhook/facebook/comments`

2. **User PSID Extracted**: The system extracts the user's Page-Scoped ID (PSID) from the comment

3. **AI Processing**: The comment text is sent to the AI engine with context about Facebook comments

4. **Public Reply**: A friendly reply is posted as a comment response under the original comment

5. **Private Message**: A personalized welcome message is sent directly to the user's Messenger inbox

6. **Comment Liked**: The original comment is liked for engagement

### Comment Reply Flow

```
Comment → Facebook → Webhook → AI Engine → Graph API → Reply Under Comment
                                                    ↓
                                                    → Private Message via Messenger
```

### Dual Response System

| Response | Location | Purpose |
|----------|----------|---------|
| **Public Reply** | Under the comment | Acknowledge the comment, encourage Messenger contact |
| **Private Message** | User's Messenger inbox | Detailed information, pricing, schedule, personalized welcome |

### Private Message Content

The private message includes:
- Personalized greeting with user's name
- Thank you for their comment
- School pricing details (BEM: 2000 DA, BAC: 2500 DA)
- Schedule information (Fridays, Saturdays, evenings)
- Contact phone number
- Invitation to register

### Important Notes

- **No Lead Storage**: Comments are NOT stored as leads (only Messenger messages are)
- **Loop Prevention**: Comments from your own page are ignored to prevent infinite loops
- **PSID Required**: The user must have interacted with your page before (comments count as interaction)
- **24-Hour Window**: Private messages are sent within the 24-hour messaging window

### Testing Comments

1. Create a test post on your Facebook Page
2. Post a comment on that post (from a different account)
3. Check Laravel logs for the comment event
4. Verify the AI reply appears under the comment (public)
5. Check your Messenger inbox for the private message (personalized)

---

## Troubleshooting

### Webhook Verification Fails
- Check that `MESSENGER_VERIFY_TOKEN` matches in both `.env` and Facebook dashboard
- Ensure ngrok is running and URL is correct
- Check Laravel logs for errors

### Messages Not Received
1. Verify webhook is subscribed to `messages` event
2. Check page is published (not in draft mode)
3. Ensure your Facebook account is a page admin

### Auto-Reply Not Working
1. Verify `MESSENGER_PAGE_ACCESS_TOKEN` is valid
2. Check token hasn't expired
3. Ensure page has permission to send messages

### AI Engine Not Responding
1. Check `MESSENGER_AI_SERVICE_URL` is correct
2. Ensure FastAPI service is running
3. Check Python script has correct permissions

### Comments Not Getting Replies
1. Verify `feed` permission is enabled in Facebook Developer Dashboard
2. Check that webhook URL is correct: `/api/webhook/facebook/comments`
3. Ensure `MESSENGER_PAGE_ID` is set correctly
4. Verify the page has permission to comment on its own posts
5. Check Laravel logs for comment processing errors

### Comment Replies Not Showing
1. Verify `MESSENGER_PAGE_ACCESS_TOKEN` has `pages_manage_posts` permission
2. Check that the comment ID is valid
3. Ensure the page is allowed to comment on its own posts
4. Review Facebook Graph API error logs

### Private Messages Not Sent
1. Verify `MESSENGER_PAGE_ACCESS_TOKEN` has `pages_messaging` permission
2. Check that the user PSID is valid (extracted from comment)
3. Ensure the user has interacted with your page (24-hour window)
4. Check if the message template is valid
5. Review Facebook Graph API error logs for message delivery issues

### Private Messages Go to Spam
1. Use personalized content, not generic templates
2. Include the user's name in the message
3. Reference their specific comment
4. Avoid excessive capitalization or emojis
5. Build a relationship before asking for contact info

---

## API Endpoints Reference

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/webhook/messenger` | Webhook verification |
| POST | `/api/webhook/messenger` | Receive messages |
| POST | `/api/webhook/facebook/comments` | Receive comments on posts |
| GET | `/api/health` | Health check |

---

## Security Notes

1. **Never commit tokens to Git**
2. Use environment variables for all secrets
3. Validate webhook signatures in production
4. Use HTTPS in production
5. Implement rate limiting for API endpoints

---

## Environment Variables Summary

```env
# Facebook Messenger
MESSENGER_VERIFY_TOKEN=algerian-school-verify-token-2026
MESSENGER_PAGE_ACCESS_TOKEN=EAAxxxxxxx
MESSENGER_APP_SECRET=EAAxxxxxxx
MESSENGER_AI_SERVICE_URL=http://localhost:8000
MESSENGER_PAGE_ID=your_page_id_here
MESSENGER_CONTACT_PHONE=+213 XXX XX XX XX
MESSENGER_REPLY_LANGUAGE=darija
MESSENGER_SCHOOL_NAME=مدرستنا

# AI Engine
AI_API_KEY=<your_generated_key>
```

---

## Quick Start Commands

```bash
# 1. Start Laravel server
cd school_dashboard
php artisan serve --port=8080

# 2. Start ngrok (in new terminal)
ngrok http http://localhost:8080

# 3. Copy ngrok URL and configure in Facebook

# 4. Start AI engine (optional, for full pipeline)
cd ../ai_engine
uvicorn service.main:app --reload --port=8000
```

---

## Support

For issues, check:
1. Laravel logs: `storage/logs/laravel.log`
2. ngrok inspect: http://localhost:4040
3. Facebook Developer Dashboard
