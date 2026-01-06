<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Log;

class KhqrService
{
    /**
     * Generate KHQR string for payment (supports both ABA and Bakong)
     * 
     * Format: 00020101021238570010A00000072701270004ABCD53039365802KH5906MERCHANT6009PHNOM PENH62070703***6304
     * 
     * @param string $merchantId Merchant ID (Bakong ID or ABA Merchant ID)
     * @param float $amount Payment amount
     * @param string $orderId Order ID (will be in remark/bill number)
     * @param string|null $merchantName Merchant name
     * @param string $provider Payment provider ('aba' or 'bakong')
     * @param string|null $merchantCity Merchant city (default: 'Phnom Penh')
     * @return string KHQR string
     */
    public function generateKhqrString(
        string $merchantId,
        float $amount,
        string $orderId,
        ?string $merchantName = null,
        string $provider = 'bakong',
        ?string $merchantCity = null
    ): string {
        // EMV QR Code Standard for Cambodia (KHQR)
        // Based on EMV QR Code Specification for Bakong

        $merchantName = $merchantName ?: 'Merchant';
        // Limit merchant name to 25 chars (EMV standard) and remove special characters
        $merchantName = substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $merchantName), 0, 25);
        $merchantName = trim($merchantName);
        if (empty($merchantName)) {
            $merchantName = 'Merchant';
        }

        $merchantCity = $merchantCity ?? 'Phnom Penh';
        // Remove special characters and limit to 15 chars (EMV standard)
        $merchantCity = preg_replace('/[^a-zA-Z0-9\s]/', '', $merchantCity);
        $merchantCity = trim($merchantCity);
        $merchantCity = substr($merchantCity, 0, 15);
        if (empty($merchantCity)) {
            $merchantCity = 'Phnom Penh';
        }
        $currencyCode = '840'; // USD currency code

        // Tag 00: Payload Format Indicator (always "01" for EMV QR)
        $payload = '000201';

        // Tag 01: Point of Initiation Method
        // "11" = static QR, "12" = dynamic QR (we use 12 for dynamic with amount)
        $payload .= '010212';

        // Merchant Account Information
        // GUID for KHQR (Bakong network): A000000727 (10 chars)
        // This GUID is used for both ABA and Bakong as they're part of the same KHQR network
        $guid = 'A000000727';
        
        // Clean and validate Merchant ID
        $merchantId = trim($merchantId); // Remove leading/trailing spaces
        $merchantId = preg_replace('/\s+/', '', $merchantId); // Remove all spaces
        
        // Validate Merchant ID: must be 1-25 characters
        if (empty($merchantId) || strlen($merchantId) > 25) {
            throw new \InvalidArgumentException('Invalid Merchant ID format. Must be 1-25 characters.');
        }
        
        // For ABA, the Merchant ID should be exactly as shown in ABA Merchant App
        // Common formats:
        // - ABA MID: 15 digits (e.g., 126010616404196)
        // - Phone number: 11-12 digits starting with 855 (e.g., 85512345678)
        // - Bakong ID: varies
        
        if ($provider === 'aba') {
            // Log the Merchant ID format for debugging
            Log::info('ABA Merchant ID format check', [
                'merchant_id' => $merchantId,
                'length' => strlen($merchantId),
                'is_numeric' => ctype_digit($merchantId),
                'starts_with_855' => strpos($merchantId, '855') === 0,
            ]);
        }

        // Build Merchant Account Information according to EMV QR Code standard
        // Sub-tag 00: GUID (2-digit length + value)
        $guidLength = $this->formatLength($guid);
        $merchantAccountInfo = '00' . $guidLength . $guid;

        // Sub-tag 01: Merchant ID (2-digit length + value)
        $merchantIdLength = $this->formatLength($merchantId);
        $merchantAccountInfo .= '01' . $merchantIdLength . $merchantId;

        // Merchant Account Information Tag
        // EMV standard uses Tag 29, but ABA specifically requires Tag 38 for Merchant Account Information
        // Bakong uses Tag 29, while ABA uses Tag 38
        $merchantAccountInfoLength = $this->formatLength($merchantAccountInfo);
        $merchantAccountTag = ($provider === 'aba') ? '38' : '29';
        $payload .= $merchantAccountTag . $merchantAccountInfoLength . $merchantAccountInfo;

        // Tag 52: Merchant Category Code (MCC) - "0000" for general
        $payload .= '52' . $this->formatLength('0000') . '0000';

        // Tag 53: Transaction Currency (3 digits)
        $payload .= '53' . $this->formatLength($currencyCode) . $currencyCode;

        // Tag 54: Transaction Amount (required for dynamic QR)
        $amountStr = number_format($amount, 2, '.', '');
        $payload .= '54' . $this->formatLength($amountStr) . $amountStr;

        // Tag 58: Country Code (2 chars)
        $payload .= '58' . $this->formatLength('KH') . 'KH';

        // Tag 59: Merchant Name (max 25 chars)
        $payload .= '59' . $this->formatLength($merchantName) . $merchantName;

        // Tag 60: Merchant City (max 15 chars) - already limited above
        $payload .= '60' . $this->formatLength($merchantCity) . $merchantCity;

        // Tag 62: Additional Data Field Template
        // Sub-tag 07: Bill Number (we use this for Order ID)
        // Limit order ID to 25 chars and sanitize (remove special characters that might cause issues)
        // Only include alphanumeric characters and hyphens/underscores
        $billNumber = preg_replace('/[^a-zA-Z0-9\-_]/', '', $orderId);
        $billNumber = substr($billNumber, 0, 25);
        
        // If order ID becomes empty after sanitization, use a fallback
        if (empty($billNumber)) {
            $billNumber = 'ORDER' . substr(preg_replace('/[^0-9]/', '', $orderId), 0, 20);
        }
        
        // Only add Tag 62 if we have a bill number
        if (!empty($billNumber)) {
            $additionalData = '07' . $this->formatLength($billNumber) . $billNumber;
            $payload .= '62' . $this->formatLength($additionalData) . $additionalData;
        }

        // Calculate CRC16-CCITT for the payload + CRC tag (without CRC value)
        $crc = $this->calculateCrc($payload . '6304');
        $crcHex = strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));

        $khqrString = $payload . '6304' . $crcHex;

        // Log for debugging (remove in production if needed)
        Log::info('KHQR Generated', [
            'provider' => $provider,
            'merchant_id' => $merchantId,
            'amount' => $amount,
            'order_id' => $orderId,
            'merchant_name' => $merchantName,
            'merchant_city' => $merchantCity,
            'khqr_length' => strlen($khqrString),
            'khqr_string' => $khqrString, // Full string for debugging
        ]);

        return $khqrString;
    }

    /**
     * Generate QR Code image (SVG or PNG base64)
     */
    public function generateQrCode(string $khqrString, string $format = 'svg'): string
    {
        if ($format === 'png') {
            $qr = QrCode::format('png')->size(300)->generate($khqrString);
            return 'data:image/png;base64,' . base64_encode($qr);
        }

        // Default SVG
        return QrCode::format('svg')->size(300)->generate($khqrString);
    }

    /**
     * Generate Bakong Web Link (for click-to-pay)
     * Try multiple formats as Bakong link format may vary
     */
    public function generateBakongLink(string $khqrString): string
    {
        // Option 1: Direct QR string (most common)
        // Option 2: Base64 encoded (some implementations use this)
        // We'll use direct encoding first, but URL-encode it properly
        return 'https://bakong.nbc.gov.kh/pay?qr=' . rawurlencode($khqrString);
    }

    /**
     * Format length for KHQR (2 digits)
     */
    private function formatLength(string $value): string
    {
        return str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate CRC16-CCITT (polynomial 0x1021, initial value 0xFFFF)
     * This is the standard CRC used for EMV QR codes
     */
    private function calculateCrc(string $data): int
    {
        $crc = 0xFFFF;
        $polynomial = 0x1021;

        for ($i = 0; $i < strlen($data); $i++) {
            $byte = ord($data[$i]);
            $crc ^= ($byte << 8);

            for ($j = 0; $j < 8; $j++) {
                if (($crc & 0x8000) !== 0) {
                    $crc = (($crc << 1) ^ $polynomial) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return $crc;
    }
}
