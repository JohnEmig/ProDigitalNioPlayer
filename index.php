<?php
/*
 * NiO Player API (simplified)
 * Path: api/nio/index.php
 * PHP 7.4+
 *
 * Notes:
 * - Uses panel DB wrapper (DB::one/all/exec) via api/_bootstrap.php
 * - Drops module_utils.php, license_check.php, visitor_log.php, protocol_check.php
 * - Keeps response shapes and XOR+base64 encryption used by the app
 */

declare(strict_types=1);

// Panel/API bootstrap (DB::, helpers)
require_once __DIR__ . '/_bootstrap.php';

/* ---------------- Encryption helpers (kept as-is) ---------------- */

function xor_encrypt_decrypt($data, $key) {
    $keyLength = strlen($key);
    $result = "";
    for ($i = 0, $n = strlen($data); $i < $n; $i++) {
        $result .= $data[$i] ^ $key[$i % $keyLength];
    }
    return $result;
}
function encrypt_response($json_data) {
    $encryption_key = "(this as java.lang.String).getBytes(...)";
    $encrypted = xor_encrypt_decrypt($json_data, $encryption_key);
    return base64_encode($encrypted);
}

/* ---------------- Small DB helpers (DB:: wrapper) ---------------- */

function db_setting(string $key, $default = null) {
    $row = DB::one('SELECT v FROM nio_settings WHERE k=? LIMIT 1', [$key]);
    if (!$row || $row['v'] === null) return $default;
    return (string)$row['v'];
}
function db_table_exists(string $table): bool {
    $row = DB::one("SELECT name FROM sqlite_master WHERE type='table' AND name=? LIMIT 1", [$table]);
    return (bool)$row;
}
function db_column_exists(string $table, string $col): bool {
    $rows = DB::all('PRAGMA table_info(' . $table . ')');
    foreach ($rows as $r) {
        if (isset($r['name']) && strcasecmp((string)$r['name'], $col) === 0) return true;
    }
    return false;
}

/* ---------------- Main entry ---------------- */

header("Content-Type: application/json; charset=utf-8");
http_response_code(200);

$endpoint    = (string)(parse_url($_SERVER["REQUEST_URI"] ?? '', PHP_URL_PATH) ?? '');
$device_code = trim((string)($_GET["device_code"] ?? ""));
$device_key  = trim((string)($_GET["device_key"]  ?? ""));
$unique_id   = trim((string)($_GET["unique_id"]   ?? ""));

try {
    // Services (active only)
    $services = DB::all(
        "SELECT id, name, url FROM nio_services WHERE status=? ORDER BY name COLLATE NOCASE ASC",
        ['active']
    );

    // If an activation code is given, resolve one service + creds
    $playlistsList = [];
    if ($device_code !== '') {
        $active = DB::one("SELECT * FROM nio_active_codes WHERE code=? LIMIT 1", [$device_code]);
        if ($active && !empty($active['service_id'])) {
            $svc = DB::one("SELECT id, name, url FROM nio_services WHERE id=? LIMIT 1", [(int)$active['service_id']]);
            if ($svc) {
                $playlistsList[] = [
                    "id"            => 1,
                    "playlist_name" => (string)$svc["name"],
                    "DNS"           => (string)$svc["url"],
                    "username"      => (string)($active["username"] ?? ''),
                    "password"      => (string)($active["password"] ?? ''),
                ];
            }
        }
    }

    // DNS list for app
    $dnsList = [];
    foreach ($services as $svc) {
        $dnsList[] = [
            'id'      => (int)$svc['id'],
            'title'   => (string)$svc['name'],
            'url'     => (string)$svc['url'],
            'user_id' => $unique_id,
        ];
    }

    // Settings (k/v)
    $logo_url       = db_setting('logo_url', '');
    $background_url = db_setting('background_url', '');
    $app_mode       = db_setting('app_mode', 'Xtream');
    $privacy_policy = db_setting('privacy_policy', 'https://pastebin.com/raw/JiimGEjk');
    $legal_msg      = db_setting('legal_msg', '');

} catch (Throwable $e) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()], JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---------------- Endpoints ---------------- */

if (strpos($endpoint, "/api/device-by-key") !== false) {
    echo json_encode([
        "status"        => "success",
        "message"       => "Device Found Successfully.",
        "data"          => [
            "device_code"   => $device_code,
            "licence"       => "Active",
            "is_expired"    => 0,
            "device_status" => 1,
            "playlist_name" => $playlistsList
        ]
    ], JSON_UNESCAPED_SLASHES);
    return;
}

if (strpos($endpoint, "/player/mobile/v1/an.json") !== false) {
    echo json_encode([
        "legal_config" => ["msg" => $legal_msg],
        "language"     => ["defult_language" => "EN", "firstime_select_language" => "true"],
        "version"      => [
            "version_check"        => "false",
            "version_code"         => "1",
            "version_name"         => "1.0",
            "version_download_url" => "",
            "version_download_url_apk" => "",
            "version_force_update" => "false",
            "version_update_msg"   => "Please Update App",
            "version_changelog"    => []
        ],
        "player" => [
            "live_tv"  => "Exo Player",
            "vod"      => "Exo Player",
            "series"   => "Exo Player",
            "catch_up" => "Exo Player"
        ],
        "content" => [
            "mackey_step" => "1) Register or Login at https://my.nioplayer.com\n\n2) Navigate To Playlist > Add New Playlist and then My Devices > Add New Device. \n\n3) Enter Device Id and Device Key and Choose your playlist and 'Submit'.\n",
            "activation_step" => "1) Register or Login at https://my.nioplayer.com\n\n2) Click To 'Add New Device' And 'Add New Playlist'\n\n3) Assign Devices To Playlist and 'Submit'\n\n4) Enter the 'Device Code' in the box below",
            "ads_txt"   => "",
            "legal_msg" => $legal_msg
        ],
        "api_key"   => ["analyt_key" => "", "analyt_server" => "", "appcenter" => ""],
        "agreement" => ["privacy_policy" => $privacy_policy],
        "ads"       => [
            "status" => false,
            "priority" => "fb",
            "ab" => ["ads_app_id"=>"","ads_banner"=>"","ads_intrestial"=>"","ads_rewarded"=>"","ads_open_app"=>"","ads_native"=>""],
            "fb" => ["ads_app_id"=>"","ads_banner"=>"","ads_intrestial"=>"","ads_rewarded"=>"","ads_rectangle"=>"","ads_native"=>""],
            "vast" => [
                "live"  => ["status"=>false,"main"=>"","backup"=>""],
                "movie" => ["status"=>false,"main"=>"","backup"=>""],
                "show"  => ["status"=>false,"main"=>"","backup"=>""]
            ]
        ]
    ], JSON_UNESCAPED_SLASHES);
    return;
}

if (strpos($endpoint, "/api/branding") !== false) {
    $branding_data = [
        "status"  => "success",
        "message" => "App Setting Get Successfully.",
        "data"    => [
            "app_mode"             => $app_mode,
            "dns"                  => $dnsList,
            "wide_logo"            => $logo_url,
            "background_image"     => $background_url,
            "privacy_policy"       => $privacy_policy,
            "version_check"        => false,
            "version_force_update" => false,
            "version_code"         => "1",
            "version_name"         => "1.0",
            "version_download_url" => "https://example.com/download",
            "version_download_url_apk" => "https://example.com/download.apk",
            "version_update_msg"   => "New version available.",
            "version_changelog"    => [
                ["title"=>"Bug Fixes","description"=>"Fixed various bugs.","version_name"=>"1.0","version_code"=>"1"]
            ],
        ],
    ];
    $json_response      = json_encode($branding_data, JSON_UNESCAPED_SLASHES);
    $encrypted_response = encrypt_response($json_response);
    echo $encrypted_response;
    return;
}

if (strpos($endpoint, "/api/announcement") !== false) {
    $data = [];

    if (db_table_exists('nio_announcements')) {
        $hasImage = db_column_exists('nio_announcements', 'image');
        $select = "SELECT id, title, message" . ($hasImage ? ", image" : ", NULL AS image")
                . " FROM nio_announcements WHERE status='active' ORDER BY id DESC";
        $rows = DB::all($select);
        foreach ($rows as $a) {
            $data[] = [
                "id"                 => (int)$a["id"],
                "user_id"            => "1",
                "title"              => (string)$a["title"],
                "description"        => (string)$a["message"],
                "announcement_image" => (string)($a["image"] ?? ''),
                "device_type"        => 1,
                "device_key"         => null,
                "status"             => 1
            ];
        }
    } elseif (db_table_exists('nio_announcement')) {
        // Legacy fallback
        $hasShort = db_column_exists('nio_announcement', 'short_description');
        $hasImage = db_column_exists('nio_announcement', 'image');
        $select = "SELECT id, title"
                . ($hasShort ? ", short_description AS message" : ", '' AS message")
                . ($hasImage ? ", image" : ", NULL AS image")
                . " FROM nio_announcement ORDER BY id DESC";
        $rows = DB::all($select);
        foreach ($rows as $a) {
            $data[] = [
                "id"                 => (int)$a["id"],
                "user_id"            => "1",
                "title"              => (string)$a["title"],
                "description"        => (string)($a["message"] ?? ''),
                "announcement_image" => (string)($a["image"] ?? ''),
                "device_type"        => 1,
                "device_key"         => null,
                "status"             => 1
            ];
        }
    }

    echo json_encode(["status" => "success", "message" => "Announcement Get Successfully.", "data" => $data], JSON_UNESCAPED_SLASHES);
    return;
}

if (strpos($endpoint, "/api/notifications") !== false) {
    $rows = DB::all("SELECT id, title, message FROM nio_notifications WHERE status='active' ORDER BY id DESC");
    $data = [];
    foreach ($rows as $n) {
        $data[] = [
            "id"          => (int)$n["id"],
            "user_id"     => null,
            "title"       => (string)$n["title"],
            "description" => (string)$n["message"],
            "device_type" => 2,
            "device_key"  => $device_key,
            "status"      => 1
        ];
    }
    echo json_encode(["status" => "success", "message" => "Notifications Get Successfully.", "data" => $data], JSON_UNESCAPED_SLASHES);
    return;
}

// Default
echo json_encode(["http_code" => "403", "status" => "failure", "licence" => "Inactive"], JSON_UNESCAPED_SLASHES);
