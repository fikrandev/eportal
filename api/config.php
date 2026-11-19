<?php
/**
 * E-Portal API Configuration
 * Database connection, helpers, and security
 */

// Load optional environment-specific overrides (never tracked in git)
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// --- DEFAULT LOCAL CONFIGURATION (OVERRIDE VIA api/config.local.php) ---
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'eportal_db');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Set to your full URL if automatic detection fails (e.g., 'https://myschool.com/')
// Leave empty for automatic detection
if (!defined('BASE_URL_OVERRIDE')) define('BASE_URL_OVERRIDE', '');
// ------------------------------------------------

// Error reporting (optimized for production)
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Timezone
date_default_timezone_set('Asia/Jakarta');

// App Configuration
define('APP_NAME', 'E-Portal');
define('APP_VERSION', '1.0.0');

/**
 * Calculate BASE_URL dynamically
 */
if (defined('BASE_URL_OVERRIDE') && BASE_URL_OVERRIDE !== '') {
    define('BASE_URL', rtrim(BASE_URL_OVERRIDE, '/') . '/');
} else {
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $script_file = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME']) ?: $_SERVER['SCRIPT_FILENAME']);
    $app_root = str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__));

    $base_dir = '';
    if (strpos($script_file, $app_root) === 0) {
        $filename_diff = substr($script_file, strlen($app_root));
        if (strlen($filename_diff) > 0 && substr($script_name, -strlen($filename_diff)) === $filename_diff) {
            $base_dir = substr($script_name, 0, -strlen($filename_diff));
        }
    }

    $base_dir = rtrim($base_dir, '/') . '/';
    if ($base_dir === '//') $base_dir = '/';
    define('BASE_URL', $base_dir);
}

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('SESSION_DURATION', 43200); // 12 hours in seconds
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// CORS Headers (only for HTTP requests)
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WA-Secret');
}

// Handle preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Database Connection (PDO Singleton)
 */
class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci, time_zone = '+07:00'"
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('DB connection error: ' . $e->getMessage());
            json_response(500, false, 'Koneksi database gagal.');
            exit;
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->pdo;
    }
}

/**
 * Get PDO connection
 */
function db()
{
    return Database::getInstance()->getConnection();
}

/**
 * Send JSON response
 */
function json_response($code = 200, $success = true, $message = '', $data = null)
{
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code($code);
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Sanitize input
 */
function sanitize($input)
{
    if ($input === null) {
        return '';
    }
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim((string)$input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Get POST data (JSON or form)
 */
function get_input()
{
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

    if (strpos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    return $_POST;
}

/**
 * Unified Bearer token extractor for Apache mod_php, CGI/FastCGI, and optional query param
 */
function get_bearer_token($allowQueryParam = true)
{
    $token = '';

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_ireplace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $token = str_ireplace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $token = str_ireplace('Bearer ', '', $headers['Authorization']);
        } elseif (isset($headers['authorization'])) {
            $token = str_ireplace('Bearer ', '', $headers['authorization']);
        }
    }

    if (trim($token) === '' && $allowQueryParam && isset($_GET['token'])) {
        $token = (string)$_GET['token'];
    }

    return trim((string)$token);
}

/**
 * Resolve project asset cache-buster using file modification timestamp
 */
function asset_ver($relativePath)
{
    static $verCache = [];
    if (isset($verCache[$relativePath])) {
        return $verCache[$relativePath];
    }
    $full = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    $ver = @is_file($full) ? (string)@filemtime($full) : APP_VERSION;
    $verCache[$relativePath] = $ver;
    return $ver;
}

/**
 * Get derived HMAC application secret backed by persisted random master key
 */
function app_secret($purpose = 'default')
{
    static $masterKey = null;
    if ($masterKey === null) {
        $masterKey = (string)get_setting('app_secret_key', '');
        if (strlen($masterKey) < 32) {
            $masterKey = bin2hex(random_bytes(32));
            upsert_setting('app_secret_key', $masterKey, 'text', 'Server signing secret');
        }
    }
    return hash_hmac('sha256', (string)$purpose, $masterKey);
}

/**
 * Safely delete an uploaded file confined strictly inside $allowedSubdir
 */
function safe_unlink_in_dir($relativePath, $allowedSubdir = 'uploads')
{
    $relativePath = str_replace("\0", '', trim((string)$relativePath));
    if ($relativePath === '' || strpos($relativePath, '..') !== false) {
        return false;
    }

    $rootDir = realpath(dirname(__DIR__));
    $allowedDir = realpath($rootDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($allowedSubdir, '/\\')));
    if (!$rootDir || !$allowedDir) {
        return false;
    }

    $candidate = $rootDir . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
    $realTarget = realpath($candidate);
    if (!$realTarget || !is_file($realTarget)) {
        return false;
    }

    $prefix = rtrim($allowedDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($realTarget, $prefix) !== 0) {
        return false;
    }

    return @unlink($realTarget);
}

/**
 * Neutralize CSV / Spreadsheet formula injection
 */
function csv_safe_cell($value)
{
    if ($value === null) return '';
    if (is_int($value) || is_float($value)) return $value;
    $str = (string)$value;
    if ($str === '') return '';
    // Preserve genuine numeric strings (e.g., "-5" or "+10" or "08123")
    if (is_numeric($str)) return $str;
    if (in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $str;
    }
    return $str;
}

/**
 * Verify auth token and return user data
 */
function auth_check()
{
    $token = get_bearer_token(true);

    if (empty($token)) {
        json_response(401, false, 'Token tidak ditemukan. Silakan login kembali.');
    }

    try {
        $stmt = db()->prepare("
            SELECT s.*, u.id as user_id, u.username, u.nama_lengkap, u.role, u.avatar, u.status,
                   pur.role_id as portal_role_id, prd.nama as portal_role_nama
            FROM sessions s 
            JOIN users u ON s.user_id = u.id 
            LEFT JOIN portal_user_roles pur ON pur.user_id = u.id
            LEFT JOIN portal_roles_def prd ON prd.id = pur.role_id
            WHERE s.token = ? AND s.expired_at > NOW() AND u.status = 1
        ");
        $stmt->execute([$token]);
        $session = $stmt->fetch();

        if (!$session) {
            json_response(401, false, 'Sesi telah berakhir. Silakan login kembali.');
        }

        $permissions = portal_get_user_permissions($session['user_id']);
        if ($session['role'] === 'superadmin') {
            $permissions[] = '*';
        }

        return [
            'id' => (int) $session['user_id'],
            'user_id' => (int) $session['user_id'],
            'username' => $session['username'],
            'nama_lengkap' => $session['nama_lengkap'],
            'role' => $session['role'],
            'avatar' => $session['avatar'],
            'portal_role_id' => (int) ($session['portal_role_id'] ?? 0),
            'portal_role_nama' => $session['portal_role_nama'] ?? '',
            'permissions' => array_values(array_unique($permissions))
        ];
    } catch (PDOException $e) {
        error_log('auth_check error: ' . $e->getMessage());
        json_response(500, false, 'Terjadi kesalahan pada server.');
    }
}

/**
 * Get current user if token exists and is valid, without failing or exiting if no token
 */
function auth_get_optional_user() {
    $token = get_bearer_token(true);
    if (empty($token)) {
        return null;
    }
    try {
        $stmt = db()->prepare("
            SELECT s.*, u.id as user_id, u.username, u.nama_lengkap, u.role, u.avatar, u.status,
                   pur.role_id as portal_role_id, prd.nama as portal_role_nama
            FROM sessions s 
            JOIN users u ON s.user_id = u.id 
            LEFT JOIN portal_user_roles pur ON pur.user_id = u.id
            LEFT JOIN portal_roles_def prd ON prd.id = pur.role_id
            WHERE s.token = ? AND s.expired_at > NOW() AND u.status = 1
        ");
        $stmt->execute([$token]);
        $session = $stmt->fetch();
        if (!$session) return null;

        $permissions = portal_get_user_permissions($session['user_id']);
        if ($session['role'] === 'superadmin') {
            $permissions[] = '*';
        }

        return [
            'id' => (int) $session['user_id'],
            'user_id' => (int) $session['user_id'],
            'username' => $session['username'],
            'nama_lengkap' => $session['nama_lengkap'],
            'role' => $session['role'],
            'avatar' => $session['avatar'],
            'portal_role_id' => (int) ($session['portal_role_id'] ?? 0),
            'portal_role_nama' => $session['portal_role_nama'] ?? '',
            'permissions' => array_values(array_unique($permissions))
        ];
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get user permissions array (cached per-request)
 */
function portal_get_user_permissions($userId) {
    static $permCache = [];
    $uid = (int)$userId;
    if ($uid <= 0) return [];
    if (isset($permCache[$uid])) return $permCache[$uid];
    try {
        $stmt = db()->prepare("
            SELECT prp.permission_key 
            FROM portal_user_roles pur
            JOIN portal_role_permissions prp ON prp.role_id = pur.role_id
            WHERE pur.user_id = ?
        ");
        $stmt->execute([$uid]);
        $permCache[$uid] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return $permCache[$uid];
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Check if user has a permission
 */
function portal_has_permission($user, $permissionKey) {
    if (!$user) return false;
    if (($user['role'] ?? '') === 'superadmin') return true;
    
    $userId = (int)($user['id'] ?? ($user['user_id'] ?? 0));
    if (!$userId) return false;
    
    $perms = $user['permissions'] ?? portal_get_user_permissions($userId);
    if (in_array('*', $perms, true) || in_array($permissionKey, $perms, true)) {
        return true;
    }
    return false;
}

/**
 * Require permission or 403
 */
function portal_require_permission($permissionKey) {
    $user = auth_check();
    if (!portal_has_permission($user, $permissionKey)) {
        json_response(403, false, 'Akses ditolak. Anda tidak memiliki izin untuk fitur ini.');
    }
    return $user;
}

/**
 * Require any of the given permissions or 403
 */
function portal_require_any_permission(array $permissionKeys) {
    $user = auth_check();
    foreach ($permissionKeys as $pk) {
        if (portal_has_permission($user, $pk)) {
            return $user;
        }
    }
    json_response(403, false, 'Akses ditolak. Anda tidak memiliki izin untuk fitur ini.');
}

/**
 * Check strict superadmin role
 */
function require_strict_superadmin() {
    $user = auth_check();
    if (($user['role'] ?? '') !== 'superadmin') {
        json_response(403, false, 'Akses ditolak. Hanya Super Administrator yang dapat mengakses.');
    }
    return $user;
}

/**
 * Check if user is superadmin (or explicitly holds wildcard '*')
 */
function require_superadmin()
{
    $user = auth_check();
    if (($user['role'] ?? '') !== 'superadmin' && !portal_has_permission($user, '*')) {
        json_response(403, false, 'Akses ditolak. Hanya administrator yang dapat mengakses.');
    }
    return $user;
}

/**
 * Generate secure random token
 */
function generate_token($length = 64)
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Load settings cache once per request
 */
function &_portal_settings_cache()
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $stmt = db()->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {
            $cache = [];
        }
    }
    return $cache;
}

/**
 * Get a specific setting value (cached per-request)
 */
function get_setting($key, $default = null)
{
    $cache = &_portal_settings_cache();
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    return $default;
}

/**
 * Darken a hex color (e.g. for generating gradients)
 */
function darken_hex($hex, $percent = 20) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (strlen($hex) != 6) return '#0D47A1'; // Fallback
    
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $r = max(0, $r - ($r * ($percent / 100)));
    $g = max(0, $g - ($g * ($percent / 100)));
    $b = max(0, $b - ($b * ($percent / 100)));
    
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

/**
 * Update a setting value
 */
function update_setting($key, $value)
{
    try {
        $stmt = db()->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$value, $key]);
        $cache = &_portal_settings_cache();
        $cache[$key] = $value;
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Insert or update a setting value
 */
function upsert_setting($key, $value, $type = 'text', $keterangan = '')
{
    try {
        $stmt = db()->prepare("
            INSERT INTO settings (setting_key, setting_value, setting_type, keterangan)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_type = VALUES(setting_type),
                keterangan = VALUES(keterangan)
        ");
        $stmt->execute([$key, $value, $type, $keterangan]);
        $cache = &_portal_settings_cache();
        $cache[$key] = $value;
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function &_portal_active_year_cache()
{
    static $cached = false;
    return $cached;
}

function reset_active_academic_year_cache()
{
    $cached = &_portal_active_year_cache();
    $cached = false;
}

/**
 * Get active academic year shared by all modules (cached per-request)
 */
function get_active_academic_year()
{
    $cached = &_portal_active_year_cache();
    if ($cached !== false) {
        return $cached;
    }
    try {
        $stmt = db()->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1");
        $active = $stmt->fetch();
        if ($active) {
            $cached = $active;
            return $cached;
        }
    } catch (PDOException $e) {
        // Fallback to settings below for old installations.
    }

    $label = get_setting('tahun_ajaran_aktif', '');
    $cached = [
        'id' => null,
        'tahun_ajaran' => $label,
        'semester' => get_setting('semester_aktif', ''),
        'is_active' => !empty($label) ? 1 : 0
    ];
    return $cached;
}

/**
 * Handle file upload (strictly raster/document allowlists, MIME & content verified)
 */
function handle_upload($file, $directory, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'])
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload gagal.'];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Sumber upload tidak valid.'];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    // Never allow executable or SVG uploads through generic upload helper
    $blocked = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar', 'pl', 'py', 'cgi', 'sh', 'exe', 'js', 'html', 'htm', 'svg'];
    if (in_array($ext, $blocked, true) || !in_array($ext, $allowed_types, true)) {
        return ['success' => false, 'message' => 'Tipe file tidak diizinkan. Gunakan: ' . implode(', ', $allowed_types)];
    }

    $mimeMap = [
        'jpg'  => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt'  => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
    ];

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) finfo_close($finfo);
        if (isset($mimeMap[$ext]) && $detectedMime && !in_array($detectedMime, $mimeMap[$ext], true)) {
            return ['success' => false, 'message' => 'Isi file tidak sesuai dengan ekstensi (.' . $ext . ').'];
        }
    }

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) && @getimagesize($file['tmp_name']) === false) {
        return ['success' => false, 'message' => 'File gambar rusak atau tidak valid.'];
    }

    $directory = trim(str_replace(['..', '\\'], ['', '/'], $directory), '/') . '/';
    $uploadPath = UPLOAD_DIR . $directory;
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }

    $filename = uniqid() . '_' . time() . '.' . $ext;
    $filepath = $uploadPath . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        @chmod($filepath, 0644);
        return [
            'success' => true,
            'filename' => $filename,
            'path' => 'uploads/' . $directory . $filename,
            'full_path' => $filepath,
            'ext' => $ext
        ];
    }

    return ['success' => false, 'message' => 'Gagal menyimpan file.'];
}

/**
 * Compress and resize image to target size
 */
function compress_image($source, $destination, $target_size_kb = 500)
{
    $info = @getimagesize($source);
    if (!$info)
        return false;

    $mime = $info['mime'];
    $width = (int)$info[0];
    $height = (int)$info[1];
    if ($width <= 0 || $height <= 0)
        return false;

    // Create image from source
    switch ($mime) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($source);
            break;
        case 'image/webp':
            $image = @imagecreatefromwebp($source);
            break;
        default:
            return false;
    }

    if (!$image)
        return false;

    // Preserve transparency for PNG
    if ($mime == 'image/png') {
        imagealphablending($image, false);
        imagesavealpha($image, true);
    }

    $quality = 90;
    $step = 10;
    $iterations = 0;
    $maxIterations = 15;
    $size = 0;

    do {
        $iterations++;
        ob_start();
        if ($mime == 'image/png') {
            $pngComp = min(9, max(0, (int)round((100 - $quality) / 10)));
            imagepng($image, null, $pngComp);
        } elseif ($mime == 'image/webp') {
            imagewebp($image, null, $quality);
        } else {
            imagejpeg($image, null, $quality);
        }
        $data = ob_get_clean();
        $size = strlen($data) / 1024;

        if ($size <= $target_size_kb || $iterations >= $maxIterations)
            break;

        $quality -= $step;

        // If quality is too low and still too big, resize down (keeping minimum dimension >= 200px)
        if ($quality < 30) {
            $new_width = max(1, (int)round($width * 0.8));
            $new_height = max(1, (int)round($height * 0.8));
            if (min($new_width, $new_height) < 200) {
                $quality = 30;
                break;
            }
            $new_image = imagecreatetruecolor($new_width, $new_height);

            if ($mime == 'image/png') {
                imagealphablending($new_image, false);
                imagesavealpha($new_image, true);
            }

            imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            imagedestroy($image);
            $image = $new_image;
            $width = $new_width;
            $height = $new_height;
            $quality = 80;
        }

    } while ($quality >= 10 && $size > $target_size_kb);

    // Save final image
    if ($mime == 'image/png') {
        $pngComp = min(9, max(0, (int)round((100 - $quality) / 10)));
        $result = imagepng($image, $destination, $pngComp);
    } elseif ($mime == 'image/webp') {
        $result = imagewebp($image, $destination, $quality);
    } else {
        $result = imagejpeg($image, $destination, $quality);
    }

    imagedestroy($image);
    return $result;
}

/**
 * Paginate query results
 */
function paginate($query, $params = [], $page = 1, $perPage = 10, $maxPerPage = 100)
{
    $page = max(1, (int) $page);
    $maxLimit = max(1, (int) $maxPerPage);
    $perPage = max(1, min($maxLimit, (int) $perPage));
    $offset = ($page - 1) * $perPage;

    // Count total
    $countQuery = "SELECT COUNT(*) as total FROM (" . $query . ") as counted";
    $stmt = db()->prepare($countQuery);
    $stmt->execute($params);
    $total = (int)($stmt->fetch()['total'] ?? 0);

    // Get paginated data
    $dataQuery = $query . " LIMIT {$perPage} OFFSET {$offset}";
    $stmt = db()->prepare($dataQuery);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    return [
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => (int)ceil($total / $perPage)
    ];
}

// Only load & run migrations when DB version is behind target
define('EPORTAL_TARGET_DB_VERSION', 26);
if ((int)get_setting('db_migration_version', 0) < EPORTAL_TARGET_DB_VERSION) {
    require_once __DIR__ . '/migration_helper.php';
    run_auto_migrations();
}
require_once __DIR__ . '/theme_helper.php';

