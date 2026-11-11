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

if (!function_exists('textbee_shorten_url')) {
    /**
     * Shorten a URL through Bitly. Requires BITLY_ACCESS_TOKEN; if Bitly is
     * unavailable, retain the original link so notifications can still be sent.
     */
    function textbee_shorten_url($url)
    {
        $url = trim((string)$url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        $token = trim((string)textbee_env('BITLY_ACCESS_TOKEN'));
        if ($token === '' || !function_exists('curl_init')) {
            if ($token === '') {
                error_log('Bitly URL shortening skipped: BITLY_ACCESS_TOKEN is not configured.');
            } else {
                error_log('Bitly URL shortening skipped: PHP cURL is unavailable.');
            }
            return $url;
        }

        $payload = json_encode(array('long_url' => $url));
        if ($payload === false) {
            error_log('Unable to encode Bitly request: ' . json_last_error_msg());
            return $url;
        }

        $ch = curl_init('https://api-ssl.bitly.com/v4/shorten');
        if ($ch === false) {
            error_log('Unable to initialize Bitly URL shortening request.');
            return $url;
        }

        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ));

        $response = curl_exec($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $http_code < 200 || $http_code >= 300) {
            error_log('Bitly URL shortening failed (HTTP ' . $http_code . '): ' . $curl_error);
            return $url;
        }

        $body = json_decode($response, true);
        $short_url = is_array($body) && isset($body['link']) ? (string)$body['link'] : '';
        $parts = $short_url !== '' ? parse_url($short_url) : false;
        if (!is_array($parts) || strtolower(isset($parts['host']) ? $parts['host'] : '') !== 'bit.ly' || empty($parts['scheme']) || strtolower($parts['scheme']) !== 'https') {
            error_log('Bitly returned an invalid short URL.');
            return $url;
        }

        return $short_url;
    }
}

if (!function_exists('textbee_shorten_message_urls')) {
    function textbee_shorten_message_urls($message)
    {
        return preg_replace_callback('/https?:\/\/[^\s]+/i', function ($matches) {
            $url = $matches[0];
            $suffix = '';
            while ($url !== '' && preg_match('/[),.!?;:]$/', $url)) {
                $suffix = substr($url, -1) . $suffix;
                $url = substr($url, 0, -1);
            }
            return textbee_shorten_url($url) . $suffix;
        }, (string)$message);
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
            'message' => textbee_shorten_message_urls($message),
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
