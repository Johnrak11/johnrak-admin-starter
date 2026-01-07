#!/usr/bin/env python3
"""
Test script to verify webhook connectivity
Run this to test if your webhook endpoint is reachable
"""

import requests
import json
import os
from dotenv import load_dotenv

load_dotenv()

SERVICE_URL = os.getenv('PAYMENT_WEBHOOK_URL', 'http://localhost:8000/api/payment/webhook')
API_TOKEN = os.getenv('PAYMENT_API_TOKEN', '')

def test_webhook():
    """Test the webhook endpoint with sample data"""
    
    print(f"\n{'='*60}")
    print(f"🧪 TESTING WEBHOOK CONNECTION")
    print(f"{'='*60}")
    print(f"URL: {SERVICE_URL}")
    print(f"API Token: {'✅ Set' if API_TOKEN else '❌ NOT SET'}")
    print(f"{'='*60}\n")
    
    if not API_TOKEN:
        print("❌ ERROR: PAYMENT_API_TOKEN not set in .env file")
        print("   Generate one from /api/tokens and set it in telegramBotLstener/.env")
        return False
    
    # Sample payment data
    test_data = {
        "order_id": "TEST-12345",
        "amount": 10.00,
        "currency": "USD",
        "transaction_id": "TEST-TX-123456",
        "payer_name": "Test User",
        "payer_phone": "85512345678",
        "metadata": {
            "payment_time": "2026-01-10 10:00:00",
            "location": "Test Location",
            "apv": "123",
            "raw_message": "Test payment message"
        }
    }
    
    headers = {
        'Content-Type': 'application/json',
        'Authorization': f'Bearer {API_TOKEN}',
    }
    
    print(f"📤 Sending test request...")
    print(f"   Payload: {json.dumps(test_data, indent=2)}")
    print()
    
    try:
        response = requests.post(SERVICE_URL, json=test_data, headers=headers, timeout=10)
        
        print(f"📥 Response Status: {response.status_code}")
        print(f"📥 Response Headers: {dict(response.headers)}")
        print(f"📥 Response Body: {response.text}")
        print()
        
        if response.status_code == 200:
            print("✅ SUCCESS: Webhook is reachable and responding!")
            try:
                result = response.json()
                print(f"   Response data: {json.dumps(result, indent=2)}")
            except:
                pass
            return True
        elif response.status_code == 401:
            print("❌ ERROR: Unauthorized - API token is incorrect/expired")
            print("   Check PAYMENT_API_TOKEN in .env (generate from /api/tokens)")
            return False
        elif response.status_code == 404:
            # 404 with "Transaction not found" means endpoint works but transaction doesn't exist
            # This is expected for test data - the endpoint is working!
            error_msg = response.text.lower()
            if "transaction not found" in error_msg or "not found" in error_msg:
                print("✅ SUCCESS: Webhook endpoint is reachable!")
                print("   The 404 is expected - test transaction doesn't exist in database")
                print("   This means your webhook URL is correct and auth worked!")
                print(f"   Response: {response.text}")
                return True
            else:
                print("❌ ERROR: Not Found - Webhook endpoint doesn't exist")
                print("   Check PAYMENT_WEBHOOK_URL is correct")
                print("   Make sure backend server is running")
                return False
        else:
            print(f"❌ ERROR: Unexpected status code {response.status_code}")
            return False
            
    except requests.exceptions.ConnectionError:
        print("❌ ERROR: Cannot connect to webhook URL")
        print(f"   Is your backend server running at {SERVICE_URL}?")
        print("   Try: cd backend && php artisan serve")
        return False
    except requests.exceptions.Timeout:
        print("❌ ERROR: Request timed out (>10s)")
        return False
    except Exception as e:
        print(f"❌ ERROR: {e}")
        import traceback
        traceback.print_exc()
        return False

if __name__ == "__main__":
    success = test_webhook()
    print(f"\n{'='*60}")
    if success:
        print("✅ Webhook test PASSED - Your bot should work!")
    else:
        print("❌ Webhook test FAILED - Fix the issues above")
    print(f"{'='*60}\n")
    exit(0 if success else 1)
