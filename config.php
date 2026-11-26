<?php
/**
 * Calendar Day Entry & Daily Sum Tracker - Google Sheet Sole Database Config
 *
 * Google Sheet is the sole database for entries, daily sum totals, and settings.
 * Connects to your Google Apps Script Web App URL.
 */

declare(strict_types=1);

const TRACKER_CONFIG_DATA = [
  'schemaVersion' => '2.0',
  'updatedAt' => '2026-11-26T00:59:46+00:00',
  'sheetDbConfig' => [
    'driver' => 'google_sheet',
    'webhookUrl' => 'https://script.google.com/macros/s/AKfycbyQK-fK_9sXNaSxBJjZb_xK3GdrhFhzxsf7n_ARg9gYvOzyirPf4wvJe5DwS4JLZeHA/exec',
    'entriesSheetName' => 'Entries',
    'settingsSheetName' => 'Data settings',
    'defaultLoadOnStart' => true,
    'autoSync' => true,
    'lastSyncAt' => '06:29:46 AM',
  ],
];

/**
 * Default fallback Google Sheet connection config.
 */
function defaultTrackerConfig(): array
{
    $envUrl = trim((string) (getenv('GOOGLE_SHEET_WEBAPP_URL') ?: ($_ENV['GOOGLE_SHEET_WEBAPP_URL'] ?? '')));
    if ($envUrl !== '' && !preg_match('#^https?://#i', $envUrl)) {
        $envUrl = '';
    }

    return [
        'schemaVersion' => '2.0',
        'updatedAt' => gmdate('c'),
        'sheetDbConfig' => [
            'driver' => 'google_sheet',
            'webhookUrl' => $envUrl,
            'entriesSheetName' => 'Entries',
            'settingsSheetName' => 'Data settings',
            'defaultLoadOnStart' => true,
            'autoSync' => true,
            'lastSyncAt' => '',
        ],
    ];
}

/**
 * Loads the default Google Sheet DB configuration from TRACKER_CONFIG_DATA,
 * applying optional environment/JSON overrides when present.
 */
function loadDefaultGoogleSheetDbConfig(): array
{
    $defaults = defaultTrackerConfig();
    $config = normalizeTrackerConfig(TRACKER_CONFIG_DATA, $defaults);

    $overrideFile = __DIR__ . '/config.sheet.json';
    if (is_readable($overrideFile)) {
        $rawOverride = @file_get_contents($overrideFile);
        if (is_string($rawOverride) && trim($rawOverride) !== '') {
            $decoded = json_decode($rawOverride, true);
            if (is_array($decoded)) {
                $config = normalizeTrackerConfig($decoded, $config);
            }
        }
    }

    return $config;
}

/**
 * Normalizes and validates incoming Google Sheet connection settings.
 */
function normalizeTrackerConfig($raw, array $fallback): array
{
    if (!is_array($raw)) {
        return $fallback;
    }

    $source = (isset($raw['settings']) && is_array($raw['settings'])) ? $raw['settings'] : $raw;
    $sheetIn = (isset($source['sheetDbConfig']) && is_array($source['sheetDbConfig']))
        ? $source['sheetDbConfig']
        : $source;

    $webhookUrl = trim((string) ($sheetIn['webhookUrl'] ?? ($fallback['sheetDbConfig']['webhookUrl'] ?? '')));
    if ($webhookUrl !== '' && !preg_match('#^https?://#i', $webhookUrl)) {
        $webhookUrl = '';
    }

    $settingsSheetName = trim((string) ($sheetIn['settingsSheetName'] ?? ($fallback['sheetDbConfig']['settingsSheetName'] ?? 'Data settings')));
    if ($settingsSheetName === '') {
        $settingsSheetName = 'Data settings';
    }

    $entriesSheetName = trim((string) ($sheetIn['entriesSheetName'] ?? ($fallback['sheetDbConfig']['entriesSheetName'] ?? 'Entries')));
    if ($entriesSheetName === '') {
        $entriesSheetName = 'Entries';
    }

    return [
        'schemaVersion' => '2.0',
        'updatedAt' => gmdate('c'),
        'sheetDbConfig' => [
            'driver' => 'google_sheet',
            'webhookUrl' => $webhookUrl,
            'entriesSheetName' => $entriesSheetName,
            'settingsSheetName' => $settingsSheetName,
            'defaultLoadOnStart' => isset($sheetIn['defaultLoadOnStart'])
                ? (bool) $sheetIn['defaultLoadOnStart']
                : (bool) ($fallback['sheetDbConfig']['defaultLoadOnStart'] ?? true),
            'autoSync' => isset($sheetIn['autoSync'])
                ? (bool) $sheetIn['autoSync']
                : (bool) ($fallback['sheetDbConfig']['autoSync'] ?? true),
            'lastSyncAt' => trim((string) ($sheetIn['lastSyncAt'] ?? ($fallback['sheetDbConfig']['lastSyncAt'] ?? ''))),
        ],
    ];
}

/**
 * Formats a PHP array as clean PHP source code for TRACKER_CONFIG_DATA.
 */
function exportPhpConfigConstant(array $config): string
{
    $phpArray = var_export($config, true);
    $phpArray = preg_replace('/^array \(/m', '[', $phpArray);
    $phpArray = preg_replace('/\)$/m', ']', $phpArray);
    $phpArray = str_replace('array (', '[', $phpArray);
    $phpArray = str_replace('),', '],', $phpArray);
    $phpArray = preg_replace('/=>\s*\n\s*\[/', '=> [', $phpArray);
    return "const TRACKER_CONFIG_DATA = " . $phpArray . ";";
}

/**
 * Persists updated Google Sheet connection settings directly into config.php.
 */
function writeConfigIntoConfigPhp(string $selfPath, array $config): bool
{
    $sourceCode = @file_get_contents($selfPath);
    if ($sourceCode === false || $sourceCode === '') {
        return false;
    }

    $newConstBlock = exportPhpConfigConstant($config);
    $pattern = '/const\s+TRACKER_CONFIG_DATA\s*=\s*\[.*?\];/s';
    if (!preg_match($pattern, $sourceCode)) {
        return false;
    }

    $updatedCode = preg_replace($pattern, $newConstBlock, $sourceCode, 1);
    if (!is_string($updatedCode) || $updatedCode === '') {
        return false;
    }

    return @file_put_contents($selfPath, $updatedCode, LOCK_EX) !== false;
}

// Serve HTTP JSON API when accessed directly via browser/fetch
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    try {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $currentConfig = loadDefaultGoogleSheetDbConfig();

        if ($method === 'GET') {
            echo json_encode([
                'ok' => true,
                'source' => 'config.php',
                'defaultDb' => 'google_sheet',
                'settings' => $currentConfig,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($method === 'POST') {
            $rawBody = file_get_contents('php://input');
            $payload = json_decode($rawBody ?: '{}', true);
            if (!is_array($payload)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Invalid JSON request body.']);
                exit;
            }

            $normalized = normalizeTrackerConfig($payload, $currentConfig);
            $saved = writeConfigIntoConfigPhp(__FILE__, $normalized);

            if (!$saved) {
                http_response_code(500);
                echo json_encode([
                    'ok' => false,
                    'error' => 'Unable to write Google Sheet URL config into config.php. Check file permissions.',
                ]);
                exit;
            }

            echo json_encode([
                'ok' => true,
                'message' => 'Google Sheet DB URL saved to config.php',
                'settings' => $normalized,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => $e->getMessage(),
        ]);
    }
}