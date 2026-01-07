<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Log;

class KhqrService
{
    /**
     * Generate KHQR string for ABA Merchant payment
     * 
     * @param string $merchantId ABA Merchant ID (MID) - 15 digits from ABA Merchant App
     * @param float $amount Payment amount in USD
     * @param string $orderId Order ID for tracking
     * @param string|null $merchantName Merchant name (max 25 chars)
     * @param string|null $merchantCity Merchant city (max 15 chars, default: 'Phnom Penh')
     * @return string KHQR string
     */
    public function generateKhqrString(
        string $merchantId,
        float $amount,
        string $orderId,
        ?string $merchantName = null,
        ?string $merchantCity = null
    ): string {
        // Merchant Name: limit to 25 chars (EMV standard)
        $merchantName = $merchantName ?: 'Merchant';
        $merchantName = substr(trim($merchantName), 0, 25) ?: 'Merchant';

        // Merchant City: limit to 15 chars, uppercase for ABA compatibility
        $merchantCity = $merchantCity ?? 'Phnom Penh';
        $merchantCity = strtoupper(substr(trim($merchantCity), 0, 15)) ?: 'PHNOM PENH';

        // USD currency code
        $currencyCode = '840';

        // Tag 00: Payload Format Indicator (EMV QR standard)
        $payload = '000201';

        // Tag 01: Point of Initiation Method - "11" (static QR, ABA standard)
        $payload .= '010211';

        // Validate and clean Merchant ID
        $merchantId = preg_replace('/\s+/', '', trim($merchantId));

        if (empty($merchantId) || strlen($merchantId) > 25) {
            throw new \InvalidArgumentException('Invalid ABA Merchant ID. Must be 1-25 characters.');
        }

        // Tag 30: Merchant Account Information (ABA-specific structure)
        // Based on decoded ABA Merchant App QR codes
        $abaGuid = 'abaakhppxxx@abaa';
        $abaBankLabel = 'ABA Bank';

        $merchantAccountInfo =
            '00' . $this->formatLength($abaGuid) . $abaGuid .
            '01' . $this->formatLength($merchantId) . $merchantId .
            '02' . $this->formatLength($abaBankLabel) . $abaBankLabel;

        $payload .= '30' . $this->formatLength($merchantAccountInfo) . $merchantAccountInfo;

        // Tag 52: Merchant Category Code - 5399 (ABA standard)
        $payload .= '52045399';

        // Tag 53: Transaction Currency (USD)
        $payload .= '5303840';

        // Tag 54: Transaction Amount (ABA format: minimal, e.g. "10" not "10.00")
        $amountStr = rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.') ?: '0';
        $payload .= '54' . $this->formatLength($amountStr) . $amountStr;

        // Tag 58: Country Code
        $payload .= '5802KH';

        // Tag 59: Merchant Name
        $payload .= '59' . $this->formatLength($merchantName) . $merchantName;

        // Tag 60: Merchant City
        $payload .= '60' . $this->formatLength($merchantCity) . $merchantCity;

        // Tag 62: Additional Data Field Template (ABA proprietary structure)
        // Inner TLV for subtag 68 (PAYWAY@ABA template) - required by ABA Merchant App
        $aba01 = '2080920';     // ABA-specific constant
        $aba02 = '021971302';   // ABA-specific constant
        $aba05 = '1';           // ABA-specific flag

        $inner68 =
            '00' . $this->formatLength('PAYWAY@ABA') . 'PAYWAY@ABA' .
            '01' . $this->formatLength($aba01) . $aba01 .
            '02' . $this->formatLength($aba02) . $aba02 .
            '05' . $this->formatLength($aba05) . $aba05;

        $additionalData = '68' . $this->formatLength($inner68) . $inner68;
        $payload .= '62' . $this->formatLength($additionalData) . $additionalData;

        // Tag 99: Proprietary Data (ABA-specific, required for acceptance)
        // 13-digit epoch milliseconds + 'mmp' marker
        $epochMs = str_pad(substr((string) (floor(microtime(true) * 1000)), 0, 13), 13, '0', STR_PAD_RIGHT);

        $tag99Inner =
            '00' . $this->formatLength($epochMs) . $epochMs .
            '68' . $this->formatLength('mmp') . 'mmp';

        $payload .= '99' . $this->formatLength($tag99Inner) . $tag99Inner;

        // Tag 63: CRC16-CCITT checksum
        $crc = $this->calculateCrc($payload . '6304');
        $crcHex = strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));

        $khqrString = $payload . '6304' . $crcHex;

        Log::info('ABA KHQR Generated', [
            'merchant_id' => $merchantId,
            'amount' => $amount,
            'order_id' => $orderId,
            'khqr_length' => strlen($khqrString),
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
     * Generate ABA PayWay link (for click-to-pay)
     */
    public function generatePaymentLink(string $khqrString): string
    {
        return 'https://link.payway.com.kh/ABAPAY' . bin2hex(random_bytes(4));
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
