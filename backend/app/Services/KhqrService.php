<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Log;

class KhqrService
{
    /**
     * Generate KHQR string for Bakong Payment (Tag 29)
     * 
     * @param string $bakongAccountId Bakong Account ID (e.g. username@devb)
     * @param float $amount Payment amount in USD
     * @param string $orderId Order ID (Tag 62/Bill Number or Tag 99) -> actually usually generic description or bill number
     * @param string|null $merchantName Merchant name (Tag 59)
     * @param string|null $merchantCity Merchant city (Tag 60)
     * @return string KHQR string
     */
    public function generateKhqrString(
        string $bakongAccountId,
        float $amount,
        ?string $description = null, // Mapping orderId to description/bill number for now
        ?string $merchantName = null,
        ?string $merchantCity = null
    ): string {
        // Merchant Name: limit to 25 chars (EMV standard)
        $merchantName = $merchantName ?: 'Merchant';
        $merchantName = substr(trim($merchantName), 0, 25) ?: 'Merchant';

        // Merchant City: limit to 15 chars
        $merchantCity = $merchantCity ?? 'Phnom Penh';
        $merchantCity = substr(trim($merchantCity), 0, 15) ?: 'Phnom Penh';

        // USD currency code
        $currencyCode = '840';

        // Tag 00: Payload Format Indicator (01)
        $payload = '000201';

        // Tag 01: Point of Initiation Method - "11" (static) or "12" (dynamic)
        // Using 12 for dynamic amount? Or 11. Let's stick to 12 if including amount often implies dynamic. 
        // Actually typical dynamic QR with amount is 12.
        $payload .= '010212';

        // Tag 29: Merchant Activity Information (Bakong)
        // Globally Unique Identifier for Bakong: bakong@bakong or kh.gov.nbc.bakong? 
        // SIT environment usually accepts the straightforward account id structure.
        // Standard Bakong generic KHQR: 
        // 00 (GUID): bakong
        // 01 (Account): username@devb

        // Tag 29: Merchant Activity Information (Bakong Individual)
        // Subtag 00 (Globally Unique Identifier): Contains the Bakong Account ID directly

        $bakongAccountId = trim($bakongAccountId);

        $tag29Inner =
            '00' . $this->formatLength($bakongAccountId) . $bakongAccountId;

        $payload .= '29' . $this->formatLength($tag29Inner) . $tag29Inner;

        // Tag 52: Merchant Category Code (General)
        $mcc = '5999';
        $payload .= '52' . $this->formatLength($mcc) . $mcc;

        // Tag 53: Transaction Currency
        $payload .= '53' . $this->formatLength($currencyCode) . $currencyCode;

        // Tag 54: Transaction Amount
        // Bakong/KHQR often expects X.XX format? Or just number. 
        $amountStr = number_format($amount, 2, '.', ''); // Ensure 2 decimals
        $payload .= '54' . $this->formatLength($amountStr) . $amountStr;

        // Tag 58: Country Code
        $countryCode = 'KH';
        $payload .= '58' . $this->formatLength($countryCode) . $countryCode;

        // Tag 59: Merchant Name
        $payload .= '59' . $this->formatLength($merchantName) . $merchantName;

        // Tag 60: Merchant City
        $payload .= '60' . $this->formatLength($merchantCity) . $merchantCity;

        // Tag 99: Timestamp (data=timestamp, mobile number etc)
        // For dynamic QR to be unique, we put timestamp or order ID here.
        // Bakong App sample used: 99340013176819486241... (Tag 00 inside Tag 99)
        // Simplified usage: 99 + len + value. 
        // Or specific structure? "00" + len + timestamp.

        $timestamp = (string) time(); // e.g. 1768194862
        // Or use the order ID if it's unique enough? 
        // Let's use timestamp in subtag 01 or 00?
        // Sample: 9934 00131768194862417...
        // 00 -> 13 -> 1768194862417 (millis likely)
        $timestampMs = round(microtime(true) * 1000);
        $tag99Inner = '00' . $this->formatLength((string) $timestampMs) . $timestampMs;

        // If description/orderId is present, maybe start putting it in 62 as usual
        // But Tag 99 helps uniqueness.
        $payload .= '99' . $this->formatLength($tag99Inner) . $tag99Inner;

        // Tag 62: Additional Data Field Template
        // We can put the Order ID / Bill Number here (Subtag 01)
        if (!empty($description)) {
            $billNumber = substr($description, 0, 25);
            $tag62Inner = '01' . $this->formatLength($billNumber) . $billNumber;
            // Mobile Number (02)? Store Label (03)? 
            $payload .= '62' . $this->formatLength($tag62Inner) . $tag62Inner;
        }

        // Tag 63: CRC16
        $crcPayload = $payload . '6304';
        $crc = $this->calculateCrc($crcPayload);
        $crcHex = strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));

        $khqrString = $crcPayload . $crcHex;

        // Log everything including the MD5 logic
        $md5 = md5($khqrString);

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

    // Legacy Payment Link method removed or kept generic?
    // Bakong typically uses deep links: https://bakong.nbc.gov.kh/pay?qr=...
    public function generatePaymentLink(string $khqrString): string
    {
        return 'https://bk.com.kh/pay?qr=' . $khqrString; // Example deep link structure
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
