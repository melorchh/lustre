<?php

if (!function_exists('textbee_env')) {
    function textbee_env($key, $default = '')
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            $value = isset($_ENV[$key]) ? $_ENV[$key] : '';
        }
        if ($value === '' || $value === false) {
            $value = isset($_SERVER[$key]) ? $_SERVER[$key] : '';
        }
        return ($value === '' || $value === false) ? $default : $value;
    }
}

if (!function_exists('textbee_send_sms')) {
    function textbee_send_sms($recipient, $message)
    {
        $api_key = textbee_env('TEXTBEE_API_KEY');
        if (trim($api_key) === '') {
            error_log('SMS notification skipped: TEXTBEE_API_KEY is not configured.');
            return false;
        }

        $phone = preg_replace('/\D+/', '', (string)$recipient);
        if (strpos((string)$recipient, '+') === 0) {
            $phone = '+' . $phone;
        } elseif (strpos($phone, '00') === 0) {
            $phone = '+' . substr($phone, 2);
        } elseif (preg_match('/^09\d{9}$/', $phone)) {
            $phone = '+63' . substr($phone, 1);
        } elseif (preg_match('/^9\d{9}$/', $phone)) {
            $phone = '+63' . $phone;
        } elseif (preg_match('/^63\d{10}$/', $phone)) {
            $phone = '+' . $phone;
        }

        if (!preg_match('/^\+[1-9]\d{7,14}$/', $phone)) {
            error_log('SMS notification skipped: patient phone number is not in a supported international or Philippine mobile format.');
            return false;
        }

        $payload = array(
            'message' => (string)$message,
            'recipients' => array($phone),
        );
        $device_id = textbee_env('TEXTBEE_DEVICE_ID');
        if (trim($device_id) !== '') {
            $payload['deviceId'] = trim($device_id);
        }

        $body = json_encode($payload);
        if ($body === false) {
            error_log('Unable to encode TextBee SMS request: ' . json_last_error_msg());
            return false;
        }

        $context = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n"
                    . "Accept: application/json\r\n"
                    . 'x-api-key: ' . trim($api_key) . "\r\n",
                'content' => $body,
                'timeout' => 15,
                'ignore_errors' => true,
            ),
        ));

        $response = @file_get_contents('https://api.textbee.dev/api/v1/gateway/send-sms', false, $context);
        $status = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $matches)) {
                    $status = (int)$matches[1];
                }
            }
        }

        if ($response === false || $status < 200 || $status >= 300) {
            error_log('TextBee SMS request failed (HTTP ' . $status . ').');
            return false;
        }

        return true;
    }
}
