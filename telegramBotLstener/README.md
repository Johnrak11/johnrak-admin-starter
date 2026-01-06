# Telegram Bot Listener for Payment Webhooks

This Python bot listens to Telegram messages from the ABA payment bot and forwards payment notifications to your admin panel webhook.

## Setup

### 1. Create Virtual Environment (Recommended)

```bash
python3 -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate
```

### 2. Install Dependencies

```bash
pip install -r requirements.txt
```

### 3. Create `.env` File

Create a `.env` file in this directory with the following variables:

```env
# Telegram Bot Configuration
TELEGRAM_BOT_TOKEN=your-telegram-bot-token-here

# Payment Webhook Configuration
# Get this from: Payment Gateway → Payment Config (after saving, you'll see the webhook secret)
PAYMENT_WEBHOOK_SECRET=your-webhook-secret-from-admin-panel

# Payment Webhook URL
# Default: https://admin.johnrak.online/api/payment/webhook
# Change this if your admin panel is hosted elsewhere
PAYMENT_WEBHOOK_URL=https://admin.johnrak.online/api/payment/webhook
```

### 4. Get Your Credentials

#### Telegram Bot Token
1. Open [@BotFather](https://t.me/botfather) on Telegram
2. Create a new bot or use existing bot
3. Copy the bot token

#### Webhook Secret
1. Go to your admin panel: **Payment Gateway → Payment Config**
2. Enter your Bakong ID and save
3. Copy the **Webhook Secret** that appears (shown only once!)

### 5. Test Webhook Connection (Optional but Recommended)

Before running the bot, test if your webhook is reachable:

```bash
python test_webhook.py
```

This will:
- Test connectivity to your webhook URL
- Verify the webhook secret is correct
- Send a sample payment data to check if it works

### 6. Run the Bot

Make sure your virtual environment is activated, then:

```bash
python listener.py
```

Or if you're not using a virtual environment:

```bash
python3 listener.py
```

**The bot will now show detailed logs:**
- When it receives payment messages
- When it extracts Order IDs
- When it calls the webhook
- Webhook response status and data

### Docker Deployment (Recommended for Production)

The Telegram bot is integrated into the main `docker-compose.yml`. To set it up:

1. **Create `.env` file** in `telegramBotLstener/` directory:
```env
TELEGRAM_BOT_TOKEN=your-telegram-bot-token-here
PAYMENT_WEBHOOK_SECRET=your-webhook-secret-from-admin-panel
# PAYMENT_WEBHOOK_URL is set automatically in docker-compose.yml
```

2. **Start all services** (including the bot):
```bash
docker compose up -d --build
```

3. **Check bot status**:
```bash
docker logs -f johnrak-admin-telegram-bot
```

4. **Restart bot if needed**:
```bash
docker compose restart telegram-bot
```

The bot will automatically restart if it crashes (configured with `restart: unless-stopped`).

**Or run standalone with Docker:**

```bash
cd telegramBotLstener
docker build -t payment-listener .
docker run -d --name payment-listener --env-file .env payment-listener
```

## How It Works

1. **Listens** for ABA bot payment messages in your Telegram group
2. **Extracts** Order ID from the payment message (from Remark/Bill Number field)
3. **Validates** the payment data structure
4. **Calls** your webhook endpoint with payment details
5. **Logs** all activities for debugging

## Environment Variables

| Variable | Required | Description |
|----------|----------|-------------|
| `TELEGRAM_BOT_TOKEN` | Yes | Your Telegram bot token from @BotFather |
| `PAYMENT_WEBHOOK_SECRET` | Yes | Webhook secret from admin panel |
| `PAYMENT_WEBHOOK_URL` | No | Webhook URL (default: https://admin.johnrak.online/api/payment/webhook) |

## Troubleshooting

### Bot Not Starting
- Check that `TELEGRAM_BOT_TOKEN` is set correctly
- Verify the token is valid by testing with @BotFather

### Webhook Calls Failing
- Verify `PAYMENT_WEBHOOK_SECRET` matches the one in admin panel
- Check that `PAYMENT_WEBHOOK_URL` is correct
- Check network connectivity to your admin panel

### Order ID Not Extracted
- Check the bot logs for the raw message
- Verify the ABA bot message format matches the expected pattern
- The Order ID should be in the "Remark" or "Bill Number" field

## Production Deployment

For production, use a process manager:

### PM2
```bash
pm2 start listener.py --name payment-listener --interpreter python3
pm2 save
pm2 startup
```

### Systemd
Create `/etc/systemd/system/payment-listener.service`:
```ini
[Unit]
Description=Payment Listener Bot
After=network.target

[Service]
Type=simple
User=your-user
WorkingDirectory=/path/to/telegramBotLstener
EnvironmentFile=/path/to/telegramBotLstener/.env
ExecStart=/usr/bin/python3 /path/to/telegramBotLstener/listener.py
Restart=always

[Install]
WantedBy=multi-user.target
```

Then:
```bash
sudo systemctl enable payment-listener
sudo systemctl start payment-listener
```
