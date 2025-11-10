<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, x-gc-token, x-hash, x-hash-2, x-token, x-token-2, x-token-3');
require_once __DIR__ . '/../includes/config.php';

if (!function_exists('safe_random_int')) {
  function safe_random_int($min, $max) {
    if (function_exists('random_int')) return random_int($min, $max);
    return mt_rand($min, $max);
  }
}

define('SPECTER_ALPHABET', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890');

function alph_char($idx) {
    $idx = max(0, min((int)$idx, 61));
    return substr(SPECTER_ALPHABET, $idx, 1);
}

function n_encode($obj) {
    $raw = json_encode($obj, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $b64 = rtrim(base64_encode($raw));
    $max_i = min(41, strlen($b64));
    $i = max(2, safe_random_int(2, $max_i));
    $L = safe_random_int(0, 61);
    $R = '';
    for ($k = 0; $k < $L; $k++) { $R .= alph_char(safe_random_int(0, 61)); }
    $core = substr($b64, 0, $i) . $R . substr($b64, $i);
    return $core . alph_char($i) . alph_char($L);
}

function m_decode($s) {
    if (strlen($s) < 2) throw new RuntimeException('payload too short');
    $iChar = substr($s, -2, 1); $lChar = substr($s, -1, 1);
    $i = strpos(SPECTER_ALPHABET, $iChar); $L = strpos(SPECTER_ALPHABET, $lChar);
    if ($i === false || $L === false) throw new RuntimeException('bad markers');
    $core = substr($s, 0, -2);
    $b64  = substr($core, 0, $i) . substr($core, $i + $L);
    $raw  = base64_decode($b64, true);
    if ($raw === false) throw new RuntimeException('base64 decode failed');
    return trim($raw);
}

function read_obfuscated_payload() {
    $raw = file_get_contents('php://input') ?: '';
    $in  = json_decode($raw, true);
    if (!is_array($in)) $in = [];
    if (!isset($in['data']) || !is_string($in['data'])) {
        http_response_code(400); echo json_encode(['status'=>false,'message'=>'missing data']); exit;
    }
    try { $decodedJson = m_decode($in['data']); }
    catch (Exception $e) { http_response_code(400); echo json_encode(['status'=>false,'message'=>'decode_failed']); exit; }
    $decoded = json_decode($decodedJson, true);
    if (!is_array($decoded)) { http_response_code(400); echo json_encode(['status'=>false,'message'=>'bad json']); exit; }
    return $decoded;
}

function mac_from_input($maybe) {
    $s = trim((string)$maybe); if ($s === '') return '';
    if (preg_match('/^[0-9a-fA-F]{2}([:\-][0-9a-fA-F]{2}){5}$/', $s)) return strtolower(str_replace('-', ':', $s));
    $dec = base64_decode($s, true);
    if ($dec !== false) { $dec = trim($dec);
        if (preg_match('/^[0-9a-fA-F]{2}([:\-][0-9a-fA-F]{2}){5}$/', $dec)) return strtolower(str_replace('-', ':', $dec));
    }
    $hex = preg_replace('/[^0-9a-fA-F]/', '', $s);
    if (strlen($hex) === 12) return implode(':', str_split(strtolower($hex), 2));
    return strtolower($s);
}

function api_device_key($mac, $prefix='ib9x_') {
    $norm = strtolower(trim((string)$mac));
    $hex16 = substr(sha1($norm), 0, 16);
    return $prefix . $hex16;
}
