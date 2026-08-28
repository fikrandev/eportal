<?php
/**
 * E-Portal API Configuration
 * Database connection, helpers, and security
 */

// --- MANUAL CONFIGURATION (ONLY CHANGE THIS) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'eportal_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Set to your full URL if automatic detection fails (e.g., 'https://myschool.com/')
// Leave empty for automatic detection
define('BASE_URL_OVERRIDE', ''); 
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

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            json_response(500, false, 'Database connection failed: ' . $e->getMessage());
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
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
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
        return $data ?: [];
    }

    return $_POST;
}

/**
 * Verify auth token and return user data
 */
function auth_check()
{
    $token = '';

    // Check via $_SERVER first (most reliable for CGI/FastCGI hosting)
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }
    // Fallback: getallheaders (works on Apache mod_php)
    elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
        } elseif (isset($headers['authorization'])) {
            $token = str_replace('Bearer ', '', $headers['authorization']);
        }
    }
    // Final fallback: query param
    if (empty($token) && isset($_GET['token'])) {
        $token = $_GET['token'];
    }

    if (empty($token)) {
        json_response(401, false, 'Token tidak ditemukan. Silakan login kembali.');
    }

    try {
        $stmt = db()->prepare("
            SELECT s.*, u.id as user_id, u.username, u.nama_lengkap, u.role, u.avatar
            FROM sessions s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.token = ? AND s.expired_at > NOW()
        ");
        $stmt->execute([$token]);
        $session = $stmt->fetch();

        if (!$session) {
            json_response(401, false, 'Sesi telah berakhir. Silakan login kembali.');
        }

        return [
            'user_id' => $session['user_id'],
            'username' => $session['username'],
            'nama_lengkap' => $session['nama_lengkap'],
            'role' => $session['role'],
            'avatar' => $session['avatar']
        ];
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Check if user is superadmin
 */
function require_superadmin()
{
    $user = auth_check();
    if ($user['role'] !== 'superadmin') {
        json_response(403, false, 'Akses ditolak. Hanya superadmin yang dapat mengakses.');
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
 * Get a specific setting value
 */
function get_setting($key, $default = null)
{
    try {
        $stmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Update a setting value
 */
function update_setting($key, $value)
{
    try {
        $stmt = db()->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$value, $key]);
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
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get active academic year shared by all modules
 */
function get_active_academic_year()
{
    try {
        $stmt = db()->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1");
        $active = $stmt->fetch();
        if ($active) {
            return $active;
        }
    } catch (PDOException $e) {
        // Fallback to settings below for old installations.
    }

    $label = get_setting('tahun_ajaran_aktif', '');
    return [
        'id' => null,
        'tahun_ajaran' => $label,
        'semester' => get_setting('semester_aktif', ''),
        'is_active' => !empty($label) ? 1 : 0
    ];
}

/**
 * Handle file upload
 */
function handle_upload($file, $directory, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'])
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload gagal.'];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_types)) {
        return ['success' => false, 'message' => 'Tipe file tidak diizinkan. Gunakan: ' . implode(', ', $allowed_types)];
    }

    $uploadPath = UPLOAD_DIR . $directory;
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }

    $filename = uniqid() . '_' . time() . '.' . $ext;
    $filepath = $uploadPath . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
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
    $info = getimagesize($source);
    if (!$info)
        return false;

    $mime = $info['mime'];
    $width = $info[0];
    $height = $info[1];

    // Create image from source
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($source);
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

    // Initial quality
    $quality = 90;
    $step = 5;

    // Loop to reach target size
    do {
        ob_start();
        if ($mime == 'image/png') {
            imagepng($image, null, round(9 - ($quality / 10))); // png quality 0-9
        } elseif ($mime == 'image/webp') {
            imagewebp($image, null, $quality);
        } else {
            imagejpeg($image, null, $quality);
        }
        $data = ob_get_clean();
        $size = strlen($data) / 1024;

        if ($size <= $target_size_kb)
            break;

        $quality -= $step;

        // If quality is too low and still too big, resize
        if ($quality < 30) {
            $new_width = round($width * 0.8);
            $new_height = round($height * 0.8);
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
            $quality = 80; // Reset quality for new size
        }

    } while ($quality > 10 && $size > $target_size_kb);

    // Save final image
    if ($mime == 'image/png') {
        $result = imagepng($image, $destination, round(9 - ($quality / 10)));
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
function paginate($query, $params = [], $page = 1, $perPage = 10)
{
    $page = max(1, (int) $page);
    $perPage = max(1, min(100, (int) $perPage));
    $offset = ($page - 1) * $perPage;

    // Count total
    $countQuery = "SELECT COUNT(*) as total FROM (" . $query . ") as counted";
    $stmt = db()->prepare($countQuery);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];

    // Get paginated data
    $dataQuery = $query . " LIMIT {$perPage} OFFSET {$offset}";
    $stmt = db()->prepare($dataQuery);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    return [
        'data' => $data,
        'total' => (int) $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($total / $perPage)
    ];
}
