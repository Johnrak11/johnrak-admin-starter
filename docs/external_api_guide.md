# Bakong Payment External Integration

This guide details how to integrate your external application (e.g., Mobile App, POS, E-commerce) with the **Johnrak Admin Payment Gateway**.

It allows you to:

1.  **Generate KHQR Codes** (with deep-linking support).
2.  **Check Transaction Status** (with automated Telegram notifications).

---

## 🔒 1. Security & Configuration

### A. Authentication

All requests must include your **API Secret Key** in the header.

- **Header Name**: `X-Api-Key` (or `X-SnapOrder-Key`)
- **Value**: Your Unique Secret Key (Generated in _Admin Dashboard > Security > API Clients_).

### B. CORS (Cross-Origin Resource Sharing)

If calling from a browser (e.g., React/Vue app), your domain must be whitelisted.

- **Backend Config**: Add your domain to `.env` variable `CORS_ALLOWED_ORIGINS` (comma-separated).

---

## 🚀 2. Endpoints

### 🟢 Generate KHQR

Create a dynamic KHQR code for a specific amount.

**POST** `/api/external/generate-qr`

| Field              | Type     | Required | Description                                                |
| :----------------- | :------- | :------- | :--------------------------------------------------------- |
| `amount`           | `number` | **Yes**  | Amount to pay (e.g. `10.50`).                              |
| `currency`         | `string` | No       | `"USD"` (default) or `"KHR"`.                              |
| `orderId`          | `string` | No       | Custom Order ID (max 25 chars). Auto-generated if omitted. |
| `merchant_name`    | `string` | No       | Override default merchant name.                            |
| `merchant_city`    | `string` | No       | Override default city.                                     |
| `telegram_chat_id` | `string` | No       | Send success notification to this Chat ID.                 |
| `source_info`      | `object` | No       | **Deep Link Config** (See below).                          |

#### Request Body Example

```json
{
  "amount": 5.0,
  "currency": "USD",
  "orderId": "INV-2024-001",
  "merchant_name": "John's Coffee",
  "telegram_chat_id": "123456789",
  "source_info": {
    "appIconUrl": "https://yoursite.com/logo.png",
    "appName": "My App",
    "appDeepLinkCallback": "https://yoursite.com/payment/success"
  }
}
```

> **💡 About `source_info` (Deep Linking)**
> When provided, scanning the QR or clicking the link on mobile will open the **Bakong App**. After payment, the user is **automatically redirected** back to your `appDeepLinkCallback` URL.

#### Response Example

```json
{
  "qr_string": "00020101021200041234...",
  "md5": "a35cbaf6e80c75ba4975e6c7a34ecae4...",
  "payment_link": "https://bakong.nbc.gov.kh/short/...",
  "order_id": "INV-2024-001"
}
```

---

### 🔍 Check Status

Verify if a transaction has been paid.

**POST** `/api/external/check-status`

| Field              | Type     | Required | Description                               |
| :----------------- | :------- | :------- | :---------------------------------------- |
| `md5`              | `string` | **Yes**  | The MD5 hash returned from `generate-qr`. |
| `telegram_chat_id` | `string` | No       | Override notification recipient.          |

#### Behavior

1.  **Success**: If the transaction is found and _newly paid_, it updates the status locally and sends a **Telegram Notification**. (Notifications are sent only _once_ per transaction).
2.  **Pending/Not Found**: Returns the raw Bakong status. **No notification**.
3.  **Error**: Returns the error and sends a **Telegram Alert** (Warning).

#### Request Body Example

```json
{
  "md5": "a35cbaf6e80c75ba4975e6c7a34ecae4...",
  "telegram_chat_id": "123456789"
}
```

#### Response Example (Success)

```json
{
  "responseCode": 0,
  "responseMessage": "Success",
  "data": {
    "hash": "...",
    "fromAccountId": "user@bakong",
    "amount": 5.0,
    "currency": "USD",
    "externalRef": "000123..."
  }
}
```

---

### 📦 Check Batch Status

Check multiple transactions at once (Data only, no side-effects/notifications).

**POST** `/api/external/check-status-batch`

#### Request Body Example

```json
{
  "md5_list": ["a35cbaf6e80c75ba49...", "b12389abcdef123123..."]
}
```

---

## 🛠 Testing (Postman/Curl)

Since encyption is **disabled** for ease of use, you can test directly:

**Curl Example:**

```bash
curl -X POST https://admin.johnrak.online/api/external/generate-qr \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: YOUR_SECRET_KEY" \
  -d '{
        "amount": 1,
        "currency": "USD",
        "orderId": "TEST-01"
      }'
```
