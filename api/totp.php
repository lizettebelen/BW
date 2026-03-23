<?php
/**
 * TOTP (Time-based One-Time Password) Helper Class
 * Compatible with Google Authenticator, Microsoft Authenticator, etc.
 */
class TOTP {
    private static $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    
    /**
     * Generate a random secret key
     */
    public static function generateSecret($length = 16) {
        $secret = '';
        $chars = self::$base32Chars;
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }
    
    /**
     * Base32 decode
     */
    private static function base32Decode($secret) {
        $secret = strtoupper($secret);
        $buffer = '';
        
        foreach (str_split($secret) as $char) {
            if ($char === '=') continue;
            $position = strpos(self::$base32Chars, $char);
            if ($position === false) continue;
            $buffer .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }
        
        $binary = '';
        foreach (str_split($buffer, 8) as $byte) {
            if (strlen($byte) < 8) continue;
            $binary .= chr(bindec($byte));
        }
        
        return $binary;
    }
    
    /**
     * Generate TOTP code for a given secret and time
     */
    public static function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }
        
        $secretKey = self::base32Decode($secret);
        
        // Pack time into binary string
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        
        // Generate HMAC-SHA1 hash
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        
        // Get offset from last nibble
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        
        // Get 4 bytes from hash starting at offset
        $code = (
            ((ord($hash[$offset + 0]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Verify a code (with time drift tolerance)
     */
    public static function verifyCode($secret, $code, $discrepancy = 1) {
        $currentTimeSlice = floor(time() / 30);
        
        // Check current time slice and adjacent ones (for clock drift)
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate QR code URL for authenticator apps
     */
    public static function getQRCodeUrl($secret, $email, $issuer = 'BW Dashboard') {
        $issuer = rawurlencode($issuer);
        $email = rawurlencode($email);
        $otpauthUrl = "otpauth://totp/{$issuer}:{$email}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
        
        // Use Google Charts API to generate QR code
        return 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . urlencode($otpauthUrl);
    }
}
