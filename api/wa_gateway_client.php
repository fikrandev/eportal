<?php
/**
 * Shared WhatsApp Gateway HTTP Client
 * Centralizes URL normalization, optional X-WA-Secret authentication, and fire-and-forget / synchronous requests.
 */

if (!function_exists('wa_gateway_base_url')) {
    function wa_gateway_base_url() {
        $url = trim((string)get_setting('wa_gateway_url', 'http://localhost:3000/send'));
        if ($url === '') {
            $url = 'http://localhost:3000/send';
        }
        $baseUrl = preg_replace('#/(send|status|groups|logout|clean-memory)/?$#i', '', $url);
        $baseUrl = rtrim((string)$baseUrl, '/');
        return $baseUrl !== '' ? $baseUrl : 'http://localhost:3000';
    }
}

if (!function_exists('wa_gateway_endpoint_url')) {
    function wa_gateway_endpoint_url($endpoint = 'send') {
        return wa_gateway_base_url() . '/' . ltrim((string)$endpoint, '/');
    }
}

if (!function_exists('wa_gateway_secret')) {
    function wa_gateway_secret() {
        $envSecret = getenv('WA_SECRET');
        if (is_string($envSecret) && trim($envSecret) !== '') {
            return trim($envSecret);
        }
        return trim((string)get_setting('wa_gateway_secret', ''));
    }
}

if (!function_exists('wa_gateway_headers')) {
    function wa_gateway_headers($jsonBody = null) {
        $headers = ['Content-Type: application/json'];
        if ($jsonBody !== null) {
            $headers[] = 'Content-Length: ' . strlen($jsonBody);
        }
        $secret = wa_gateway_secret();
        if ($secret !== '') {
            $headers[] = 'X-WA-Secret: ' . $secret;
        }
        return $headers;
    }
}

if (!function_exists('triggerWAGateway')) {
    function triggerWAGateway($phone, $message, $timeoutMs = 800) {
        $phone = trim((string)$phone);
        $message = (string)$message;
        if ($phone === '' || $message === '') return false;

        $rawSetting = trim((string)get_setting('wa_gateway_url', ''));
        if ($rawSetting === '') return false;

        $sendUrl = wa_gateway_endpoint_url('send');
        $data = json_encode(['number' => $phone, 'message' => $message]);

        $ch = curl_init($sendUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, wa_gateway_headers($data));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, min(500, max(200, (int)$timeoutMs)));
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, max(300, (int)$timeoutMs));
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }
}
