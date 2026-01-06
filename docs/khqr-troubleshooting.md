# KHQR Troubleshooting Guide

## Common Issues

### 1. "QR is invalid!" in ABA/Bakong App

**Possible Causes:**
- Invalid Bakong ID format
- Incorrect KHQR structure
- Invalid CRC checksum
- Order ID too long (max 25 characters)

**Solutions:**
1. **Verify Bakong ID**: 
   - Should be your phone number (e.g., `85512345678`) or account ID
   - Must be 1-25 characters
   - No special characters except numbers

2. **Check Order ID**:
   - Maximum 25 characters
   - Use simple format: `ORDER-123` or `12345`
   - Avoid special characters

3. **Test with Simple Values**:
   - Amount: `10.00`
   - Order ID: `TEST123`
   - Bakong ID: Your phone number (e.g., `85512345678`)

### 2. Bakong Web Link Returns "Not Found"

The Bakong web link format may vary. Try these alternatives:

**Option 1: Direct QR String (Current)**
```
https://bakong.nbc.gov.kh/pay?qr=YOUR_QR_STRING
```

**Option 2: Base64 Encoded**
```
https://bakong.nbc.gov.kh/pay?qr=BASE64_ENCODED_QR
```

**Option 3: Use Bakong App Deep Link**
```
bakong://pay?qr=YOUR_QR_STRING
```

**Note**: The web link might not work if Bakong doesn't support web payments. Users should scan the QR code directly with the app.

### 3. QR Code Structure Validation

A valid KHQR should have this structure:
```
000201                    # Payload Format (01) + Point of Initiation (12)
010212                    # Point of Initiation Method (12 = dynamic)
38XX                      # Merchant Account Information (Tag 38)
  00XXA000000727          # GUID (A000000727)
  01XXYOUR_BAKONG_ID      # Your Bakong ID
52XX0000                  # Merchant Category Code
53XX840                   # Currency (840 = USD)
54XX10.00                 # Amount
58XXKH                    # Country Code
59XXMerchantName          # Merchant Name (max 25 chars)
60XXPhnom Penh            # City (max 15 chars)
62XX                      # Additional Data
  07XXORDER_ID            # Bill Number (Order ID, max 25 chars)
6304XXXX                  # CRC16-CCITT checksum
```

### 4. Testing Your QR Code

1. **Generate QR Code** in Test Payment page
2. **Copy the KHQR String** from the debug section
3. **Use Online Validator** (if available):
   - Some QR code validators can check EMV format
   - Verify the structure matches EMV standard

4. **Test with Real Bakong Account**:
   - Use a small test amount (e.g., $0.01 if possible)
   - Verify the Order ID appears in the payment remark

### 5. Bakong ID Format

Your Bakong ID should be:
- **Phone Number Format**: `85512345678` (country code + number)
- **Account ID**: Provided by your bank
- **Length**: 1-25 characters
- **Characters**: Alphanumeric, no spaces

### 6. Debugging Steps

1. **Check Generated QR String**:
   - Look at the KHQR string in the Test Payment page
   - Verify it starts with `000201`
   - Check the length (should be reasonable, not too long)

2. **Verify Bakong ID**:
   - Go to Payment Config
   - Ensure Bakong ID is correct
   - Try with just your phone number (e.g., `85512345678`)

3. **Test with Minimal Data**:
   - Amount: `1.00`
   - Order ID: `TEST1`
   - Merchant Name: `Test`
   - Bakong ID: Your phone number

4. **Check Logs**:
   - Backend logs: `storage/logs/laravel.log`
   - Look for "KHQR generation failed" errors

### 7. Alternative: Use Static QR Code

If dynamic QR codes don't work, you might need to:
1. Contact your bank for merchant account setup
2. Get proper merchant credentials
3. Use bank-provided QR code generation API

### 8. Contact Support

If issues persist:
- **ABA Bank**: Contact merchant services
- **Bakong Support**: Check Bakong website for developer resources
- **Verify Account**: Ensure your Bakong account is activated for receiving payments

## Valid KHQR Example

Here's what a valid KHQR string should look like:
```
00020101021238330010A000000727011285512345678520400005303840540510.005802KH5907Merchant6010Phnom Penh62070705ORDER16304A1B2
```

Breaking it down:
- `000201` - Format + Initiation
- `010212` - Dynamic QR
- `3833` - Merchant Account Info (33 chars)
  - `0010A000000727` - GUID
  - `011285512345678` - Bakong ID (phone number)
- `52040000` - MCC
- `5303840` - Currency USD
- `540510.00` - Amount $10.00
- `5802KH` - Country
- `5907Merchant` - Merchant name
- `6010Phnom Penh` - City
- `62070705ORDER1` - Bill number (Order ID)
- `6304A1B2` - CRC checksum
