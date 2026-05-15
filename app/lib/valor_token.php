<?php

if(!function_exists('encodeValorToken')) {
    function encodeValorToken($value) {
        $encoded = base64_encode($value);
        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }
}

if(!function_exists('decodeValorToken')) {
    function decodeValorToken($token) {
        $normalized = strtr($token, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        return $decoded === false ? null : $decoded;
    }
}
