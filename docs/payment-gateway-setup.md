# Payment Gateway Setup Guide

## Overview

The Payment Gateway feature allows you to:

- Generate KHQR codes for Bakong payments
- Test payments with QR code generation
- Track all transactions with status
- Generate secure API tokens for your Python bot
- Receive payment webhooks from Telegram bot

## Setup Steps

### 1. Run Migrations

```bash
cd backend
php artisan migrate
```

This creates three tables:

- `payment_configs` - Store payment configuration
- `transactions` - Store all payment transactions
- `payment_tokens` - Store API tokens for Python bot

### 2. Configure Payment Settings

1. Go to **Payment Gateway → Payment Config**
2. Enter your **Bakong ID** (your merchant account ID)
3. Enter **Merchant Name** (optional)
4. Enable payments
5. **Save** - You'll receive a **Webhook Secret** (save this!)

### 3. Generate API Token

1. Go to **Payment Gateway → Generate Token**
2. Enter a token name (e.g., "Python Bot Token")
3. Set expiration (optional, leave empty for no expiration)
4. Click **Generate Token**
5. **Copy the token immediately** - it's shown only once!

### 4. Configure Python Bot

Update your `listener.py` environment variables:

```bash
export TELEGRAM_BOT_TOKEN="your-telegram-bot-token"
export PAYMENT_WEBHOOK_URL="https://admin.johnrak.online/api/payment/webhook"
```

Or in Docker:

```yaml
environment:
  - TELEGRAM_BOT_TOKEN=your-token
  - PAYMENT_WEBHOOK_URL=https://admin.johnrak.online/api/payment/webhook
```

### 5. Test Payment Flow

1. Go to **Payment Gateway → Test Payment**
2. Enter an amount (e.g., `10.00`)
3. Enter an Order ID (e.g., `ORDER-12345`)
4. Click **Generate QR Code**
5. Scan the QR code with Bakong app or click "Open in Bakong App"
6. Complete the payment
7. Check **Payment Gateway → Transactions** to see the payment status

## How It Works

### Payment Flow

1. **Generate QR Code**: Admin creates a test payment with amount and order ID
2. **KHQR Generation**: Backend generates a KHQR string with Order ID in the remark field
3. **User Pays**: User scans QR and pays via Bakong
4. **ABA Bot Notification**: ABA bot sends message to Telegram group
5. **Python Listener**: Extracts Order ID from message remark
6. **Webhook Call**: Python bot calls `/api/payment/webhook` with payment data
7. **Backend Processing**:
   - Validates webhook secret
   - Checks for duplicate transactions (idempotency)
   - Locks database row to prevent race conditions
   - Verifies amount matches
   - Updates transaction status to "paid"

### Security Features

- **Webhook Secret**: All webhook calls require a secret key
- **Idempotency**: Duplicate transactions are ignored (based on ABA transaction ID)
- **Database Locks**: `lockForUpdate()` prevents concurrent updates
- **Amount Verification**: Backend verifies received amount matches expected amount
- **Token Expiration**: API tokens can have expiration dates

### Transaction Statuses

- `pending` - Payment QR generated, waiting for payment
- `paid` - Payment received and verified
- `failed` - Payment failed
- `expired` - Payment QR expired (24 hours default)
- `error` - Error occurred (e.g., amount mismatch)

## API Endpoints

### Protected Routes (Require Auth)

- `GET /api/payment/config` - Get payment configuration
- `POST /api/payment/config` - Save payment configuration
- `POST /api/payment/test` - Generate test payment QR
- `GET /api/payment/transactions` - List transactions (with pagination, search, filter)
- `GET /api/payment/transactions/{id}` - Get single transaction
- `POST /api/payment/tokens` - Generate API token
- `GET /api/payment/tokens` - List all tokens
- `DELETE /api/payment/tokens/{id}` - Revoke token

### Public Webhook

- `POST /api/payment/webhook?key=SECRET` - Payment webhook (called by Python bot)

## Python Bot Integration

The updated `listener.py`:

1. **Listens** for ABA bot payment messages
2. **Extracts** Order ID from message remark/bill number using regex
3. **Validates** payment data structure
4. **Calls** webhook with:
   - Order ID
   - Amount
   - Transaction ID (for idempotency)
   - Payer information
   - Metadata (location, time, etc.)

### Regex Patterns

The bot uses multiple regex patterns to extract Order ID:

- Main pattern: Captures remark from ABA message
- Alternative patterns: Looks for "Remark:", "Bill Number:", etc.
- Fallback patterns: Common order ID formats (ORDER-123, etc.)

## Troubleshooting

### QR Code Not Generating

- Check that Bakong ID is configured
- Verify payment is enabled
- Check browser console for errors

### Webhook Not Receiving Payments

1. Verify webhook secret is correct in Python bot
2. Check Python bot logs for errors
3. Verify Order ID is being extracted correctly
4. Check backend logs: `storage/logs/laravel.log`

### Transaction Stuck in Pending

- Check if payment was actually completed
- Verify Python bot is running and listening
- Check if Order ID in QR matches what bot extracted
- Look for errors in Python bot logs

### Amount Mismatch Error

- User paid wrong amount
- Transaction marked as "error" status
- Check transaction metadata for details

## Production Checklist

- [ ] Run migrations
- [ ] Configure Bakong ID
- [ ] Save webhook secret securely
- [ ] Generate API token
- [ ] Configure Python bot environment variables
- [ ] Test payment flow end-to-end
- [ ] Monitor transactions page
- [ ] Set up process manager (PM2/systemd) for Python bot
- [ ] Configure backup for transactions table
- [ ] Set up alerts for failed payments

## Notes

- **KHQR Format**: The KHQR string follows the EMV QR Code standard for Cambodia
- **Order ID**: Must be unique per transaction
- **Expiration**: Test payments expire after 24 hours
- **Rate Limiting**: Webhook endpoint limited to 60 requests/minute per IP
- **Database**: Uses transactions and row locking for concurrency safety
