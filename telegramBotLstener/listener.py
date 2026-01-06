import requests
import json
import re
from telegram import Update
from telegram.ext import Application, MessageHandler, filters
from telegram.ext import ContextTypes
import os
from dotenv import load_dotenv

# Load environment variables from .env file
load_dotenv()

# Configuration from environment variables
# Default webhook URL - can be overridden via environment variable
# For Docker: https://admin-service.johnrak.online/api/payment/webhook
# For local dev: http://localhost:8000/api/payment/webhook
SERVICE_URL = os.getenv('PAYMENT_WEBHOOK_URL', 'https://admin-service.johnrak.online/api/payment/webhook')
SERVICE_TOKEN = os.getenv('PAYMENT_WEBHOOK_SECRET', '')  # Webhook secret key from admin panel

# ABA bot message format (including both Khmer Riel and USD currency support)
# Updated regex to match actual ABA message formats
# Examples:
# - "$1.00 paid by Yun Vorak (*444) on Jan 06, 04:58 PM via ABA KHQR (ACLBKHPPXXX) at Johnrak by V.YUN. Trx. ID: 176769352210115, APV: 657806."
# - "$0.01 paid by YUN VORAK (*436) on Jan 06, 10:28 PM via ABA PAY at Johnrak by V.YUN. Trx. ID: 176771328799871, APV: 433119."
# Using a more flexible pattern that matches the actual format
# Simplified pattern that matches the actual ABA message format
# More permissive pattern that extracts key fields
ABA_BOT_PAYMENT_ALERT = r"\$(\d+\.\d{2})\s+paid\s+by\s+([^(]+)\s+\((\d+)\)\s+on\s+(.+?)\s+via\s+ABA\s+(?:KHQR\s+\([^)]+\)|PAY)\s+at\s+([^\s]+)\s+by\s+([^.]+)\.\s+Trx\.\s+ID:\s+(\d+),\s+APV:\s+(\d+)\.(?:\s+Remark:\s+([^\n]+))?"

# Alternative regex if Remark is in a different format
# Some ABA messages might have: "Bill Number: ORDER-123" or "Remark: ORDER-123"
ABA_BOT_PAYMENT_ALTERNATIVE = r"Remark[:\s]+([^\n]+)|Bill\s+Number[:\s]+([^\n]+)"

def extract_order_id_from_message(message_text):
    """
    Extract Order ID from ABA payment message.
    The Order ID should be in the Remark or Bill Number field.
    """
    # First, try to find Remark in the main regex match
    match = re.search(ABA_BOT_PAYMENT_ALERT, message_text)
    if match and match.group(10):  # Group 10 is the Remark
        remark = match.group(10).strip()
        # Order ID might be the entire remark or part of it
        return remark
    
    # Try alternative patterns
    alt_match = re.search(ABA_BOT_PAYMENT_ALTERNATIVE, message_text, re.IGNORECASE)
    if alt_match:
        order_id = alt_match.group(1) or alt_match.group(2)
        if order_id:
            return order_id.strip()
    
    # If no explicit remark found, try to extract from the message structure
    # Some messages might have the order ID embedded differently
    # Look for common patterns like "ORDER-123" or numeric IDs
    order_patterns = [
        r'ORDER[-_]?(\d+)',
        r'Order\s+ID[:\s]+([^\s\n]+)',
        r'Bill[:\s]+([^\s\n]+)',
    ]
    
    for pattern in order_patterns:
        match = re.search(pattern, message_text, re.IGNORECASE)
        if match:
            return match.group(1) if match.lastindex else match.group(0)
    
    return None

def verify_payload(payload):
    """
    Check if the payload contains required fields
    Note: order_id is optional - can be null
    """
    required_fields = ['amount', 'transaction_id']
    return all(field in payload for field in required_fields)

def send_to_service(payment_data):
    """
    Sends the payment data to the webhook endpoint
    """
    if not SERVICE_TOKEN:
        print("ERROR: SERVICE_TOKEN not configured. Set PAYMENT_WEBHOOK_SECRET environment variable.")
        return None
    
    headers = {
        'Content-Type': 'application/json',
        'X-Webhook-Secret': SERVICE_TOKEN  # Send secret in header
    }
    
    # Also include in query string as backup
    url = f"{SERVICE_URL}?key={SERVICE_TOKEN}"
    
    print(f"\n{'='*60}")
    print(f"🌐 WEBHOOK CALL ATTEMPT")
    print(f"{'='*60}")
    print(f"URL: {url}")
    print(f"Headers: {list(headers.keys())}")
    print(f"Payload: {json.dumps(payment_data, indent=2)}")
    print(f"{'='*60}\n")
    
    try:
        response = requests.post(url, json=payment_data, headers=headers, timeout=10)
        print(f"✅ Response Status: {response.status_code}")
        print(f"✅ Response Body: {response.text[:200]}")  # First 200 chars
        
        if response.status_code == 200:
            result = response.json()
            print(f"✅ Webhook call SUCCESSFUL!")
            return result
        else:
            print(f"❌ Error: Status {response.status_code}, Response: {response.text}")
            return None
    except requests.exceptions.ConnectionError as e:
        print(f"❌ Connection Error: Cannot reach {SERVICE_URL}")
        print(f"   Make sure your backend server is running on {SERVICE_URL}")
        print(f"   Error details: {e}")
        return None
    except requests.exceptions.Timeout as e:
        print(f"❌ Timeout Error: Request took too long (>10s)")
        print(f"   Error details: {e}")
        return None
    except requests.exceptions.RequestException as e:
        print(f"❌ Request failed: {e}")
        return None

async def process_payment_alert(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """
    Process the message from ABA bot and send payment details to the webhook.
    """
    message_text = update.message.text
    print(f"\n{'='*60}")
    print(f"📨 PAYMENT MESSAGE RECEIVED")
    print(f"{'='*60}")
    print(f"Message: {message_text}")
    print(f"{'='*60}\n")
    
    # Extract key fields using individual patterns (more reliable)
    # Try USD first, then KHR
    amount_match = re.search(r'\$(\d+\.\d{2})', message_text)
    if not amount_match:
        # Try KHR format
        amount_match = re.search(r'(\d+\.\d{2})\s?៛', message_text)
        if amount_match:
            amount = amount_match.group(1)
            currency = "KHR"
        else:
            print("⚠️  Could not extract amount from message")
            print(f"   Message: {message_text[:200]}")
            return
    else:
        amount = amount_match.group(1)
        currency = "USD"
    
    print(f"✅ Extracted Amount: {amount} {currency}")
    
    # Extract payer name and phone (handle * in phone number)
    payer_match = re.search(r'paid\s+by\s+(.+?)\s+\(\*?(\d+)\)', message_text)
    payer_name = payer_match.group(1).strip() if payer_match else ""
    payer_phone = payer_match.group(2) if payer_match else ""
    
    # Extract transaction ID
    tx_match = re.search(r'Trx\.\s+ID:\s+(\d+)', message_text)
    transaction_id = tx_match.group(1) if tx_match else ""
    
    if not transaction_id:
        print("⚠️  Could not extract transaction ID from message")
        print(f"   Message: {message_text[:200]}")
        return
    
    print(f"✅ Extracted Transaction ID: {transaction_id}")
    
    # Extract APV
    apv_match = re.search(r'APV:\s+(\d+)', message_text)
    apv = apv_match.group(1) if apv_match else ""
    
    # Extract payment time
    time_match = re.search(r'on\s+(.+?)\s+via', message_text)
    payment_time = time_match.group(1).strip() if time_match else ""
    
    # Extract location
    location_match = re.search(r'at\s+([^\s]+)', message_text)
    location = location_match.group(1) if location_match else ""
    
    # Extract payer_by
    payer_by_match = re.search(r'by\s+([^.]+)\.', message_text)
    payer_by = payer_by_match.group(1).strip() if payer_by_match else ""
    
    # Extract Order ID from Remark (optional - can be null)
    remark_match = re.search(r'Remark:\s+([^\n]+)', message_text)
    remark = remark_match.group(1).strip() if remark_match else ""
    order_id = remark if remark else extract_order_id_from_message(message_text)
    
    if order_id:
        print(f"✅ Extracted Order ID: {order_id}")
    else:
        print("ℹ️  No Order ID found in message (Remark field is empty)")
        print("   Payment will be processed using Transaction ID only")
    
    # Prepare payment data
    payment_data = {
        "amount": float(amount),
        "currency": currency,
        "transaction_id": transaction_id,
        "payer_name": payer_name,
        "payer_phone": payer_phone,
        "metadata": {
            "payment_time": payment_time,
            "location": location,
            "payer_by": payer_by,
            "apv": apv,
            "raw_message": message_text[:500]  # Store first 500 chars for debugging
        }
    }
    
    # Add order_id only if it exists
    if order_id:
        payment_data["order_id"] = order_id
    
    # Verify payload (order_id is now optional)
    if not verify_payload(payment_data):
        print("Invalid payload structure")
        return
    
    # Send to webhook (run in executor since requests is blocking)
    import asyncio
    print(f"🚀 Sending payment data to webhook...")
    try:
        # Use to_thread for Python 3.9+, fallback to run_in_executor for older versions
        if hasattr(asyncio, 'to_thread'):
            response = await asyncio.to_thread(send_to_service, payment_data)
        else:
            loop = asyncio.get_event_loop()
            response = await loop.run_in_executor(None, send_to_service, payment_data)
    except Exception as e:
        print(f"❌ Error sending to webhook: {e}")
        import traceback
        traceback.print_exc()
        response = None
    
    if response:
        print(f"\n✅ SUCCESS: Payment data sent successfully to webhook!")
        print(f"   Response: {json.dumps(response, indent=2)}")
        print(f"   ✅ Transaction should now be in database!")
    else:
        print(f"\n❌ FAILED: Could not send payment data to webhook.")
        print(f"   Check your backend server is running at: {SERVICE_URL}")
        print(f"   Verify webhook secret matches in admin panel")
        print(f"   Check backend logs for errors")
    
    print(f"{'='*60}\n")

async def process_custom_bot_message(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """
    Listen for your custom bot's messages (for listening to the responses).
    This handler catches ALL text messages, so we need to check if it's a payment message.
    """
    message_text = update.message.text
    
    # Check if this looks like a payment message
    if "$" in message_text and "paid by" in message_text and "Trx. ID:" in message_text:
        print(f"\n⚠️  Payment message caught by catch-all handler!")
        print(f"   This means the payment handler didn't match. Processing manually...")
        # Try to process it as a payment
        await process_payment_alert(update, context)
    else:
        # Not a payment message, just log it
        print(f"Received message from my bot: {message_text[:100]}")

def start_bot_listener():
    """
    Start the Telegram bot listener to handle messages.
    """
    import sys
    
    # Force unbuffered output
    sys.stdout.flush()
    sys.stderr.flush()
    
    bot_token = os.getenv('TELEGRAM_BOT_TOKEN', 'your-telegram-bot-token')
    
    print(f"\n{'='*60}", flush=True)
    print(f"🤖 TELEGRAM BOT LISTENER STARTING", flush=True)
    print(f"{'='*60}", flush=True)
    print(f"Webhook URL: {SERVICE_URL}", flush=True)
    print(f"Webhook Secret: {'✅ Set' if SERVICE_TOKEN else '❌ NOT SET'}", flush=True)
    print(f"Bot Token: {'✅ Set' if bot_token != 'your-telegram-bot-token' else '❌ NOT SET'}", flush=True)
    print(f"{'='*60}\n", flush=True)
    
    # Check if .env file exists
    env_file_path = os.path.join(os.path.dirname(__file__), '.env')
    if not os.path.exists(env_file_path):
        print(f"⚠️  WARNING: .env file not found at {env_file_path}", flush=True)
        print(f"   Make sure to create .env file with TELEGRAM_BOT_TOKEN and PAYMENT_WEBHOOK_SECRET", flush=True)
    
    if bot_token == 'your-telegram-bot-token':
        print("❌ ERROR: TELEGRAM_BOT_TOKEN not set. Set it as an environment variable or in .env file.", flush=True)
        print("   The bot will not start without a valid token.", flush=True)
        return
    
    if not SERVICE_TOKEN:
        print("⚠️  WARNING: PAYMENT_WEBHOOK_SECRET not set. Webhook calls will fail.", flush=True)
    
    print("🔄 Creating Telegram application...", flush=True)
    
    try:
        # Create application
        application = Application.builder().token(bot_token).build()
        print("✅ Application created successfully", flush=True)
    except Exception as e:
        print(f"❌ ERROR: Failed to create Telegram application", flush=True)
        print(f"   Error: {str(e)}", flush=True)
        print(f"   Check that your TELEGRAM_BOT_TOKEN is valid", flush=True)
        raise

    # Add handlers for the ABA bot's payment alert messages
    # Use a more flexible filter that catches any message with payment indicators
    payment_filter = filters.TEXT & (
        filters.Regex(r'\$\d+\.\d{2}.*paid\s+by') |  # USD amount
        filters.Regex(r'\d+\.\d{2}\s?៛.*paid\s+by') |  # KHR amount
        filters.Regex(r'Trx\.\s+ID:')  # Transaction ID
    )
    application.add_handler(MessageHandler(payment_filter, process_payment_alert))

    # Add handlers for your custom bot messages (catch-all, but lower priority)
    application.add_handler(MessageHandler(filters.TEXT, process_custom_bot_message))

    print("✅ Bot listener started. Waiting for messages...")
    print("📱 Listening for ABA payment notifications...")
    print("💡 Tip: Send a test payment message to verify webhook calls\n")
    
    # Start polling for new messages (blocking call)
    application.run_polling(allowed_updates=Update.ALL_TYPES, drop_pending_updates=True)

if __name__ == "__main__":
    try:
        start_bot_listener()
    except KeyboardInterrupt:
        print("\n\n⚠️  Bot stopped by user (KeyboardInterrupt)")
    except Exception as e:
        print(f"\n\n❌ FATAL ERROR: Bot crashed!")
        print(f"Error type: {type(e).__name__}")
        print(f"Error message: {str(e)}")
        import traceback
        traceback.print_exc()
        # Keep container running so we can see the error
        import time
        print("\n⏳ Waiting 60 seconds before exit...")
        time.sleep(60)
        raise
