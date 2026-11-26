<?php
/**
 * Calendar Day Entry & Daily Sum Tracker - PHP Configuration & Google Sheet DB Connector
 *
 * Place this file in the same root folder as index.html.
 * Set your default Google Apps Script Web App URL in TRACKER_CONFIG_DATA['sheetDbConfig']['webhookUrl']
 * or save it directly from the app's Google Sheet DB panel (persisted to config.override.json & config.php).
 */

const TRACKER_CONFIG_DATA = [
  'schemaVersion' => '2.1',
  'updatedAt' => '2026-11-26T01:57:33+00:00',
  'currency' => 'INR',
  'branding' => [
    'title' => 'Calendar Day Entry & Daily Sum Tracker',
    'subtitle' => 'Track daily bills & cheques with automatic separate daily sum totals',
    'footerText' => 'Calendar Day Entry & Daily Sum Tracker • Google Sheet database sync',
    'logoUrl' => '',
    'faviconValue' => '',
  ],
  'sheetDbConfig' => [
    'driver' => 'google_sheet',
    'webhookUrl' => 'https://script.google.com/macros/s/AKfycbyQK-fK_9sXNaSxBJjZb_xK3GdrhFhzxsf7n_ARg9gYvOzyirPf4wvJe5DwS4JLZeHA/exec',
    'entriesSheetName' => 'Entries',
    'settingsSheetName' => 'Data settings',
    'defaultLoadOnStart' => true,
    'autoSync' => true,
    'lastSyncAt' => '07:27:33 AM',
  ],
];

/**
 * Returns the path to the JSON override file in the same directory as config.php.
 */
function getOverrideConfigPath(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'config.override.json';
}

/**
 * Loads the default Google Sheet DB configuration merged with any saved config.override.json.
 */
function loadDefaultGoogleSheetDbConfig(): array
{
    $config = TRACKER_CONFIG_DATA;
    $overridePath = getOverrideConfigPath();

    if (is_file($overridePath) && is_readable($overridePath)) {
        $raw = @file_get_contents($overridePath);
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $config = normalizeTrackerConfig($decoded, $config);
            }
        }
    }

    $resolvedUrl = resolveConfiguredWebAppUrl($config);
    $config['sheetDbConfig']['webhookUrl'] = $resolvedUrl;
    $config['sheetDbConfig']['defaultLoadOnStart'] = true;

    return $config;
}

/**
 * Normalizes incoming configuration payloads against the baseline schema.
 */
function normalizeTrackerConfig(array $incoming, array $baseConfig = TRACKER_CONFIG_DATA): array
{
    $source = isset($incoming['settings']) && is_array($incoming['settings'])
        ? $incoming['settings']
        : $incoming;

    $result = $baseConfig;
    $result['updatedAt'] = gmdate('c');

    if (!empty($source['currency']) && is_string($source['currency'])) {
        $curr = strtoupper(trim($source['currency']));
        if (in_array($curr, ['INR', 'USD', 'EUR', 'GBP'], true)) {
            $result['currency'] = $curr;
        }
    }

    if (isset($source['branding']) && is_array($source['branding'])) {
        $b = $source['branding'];
        if (isset($b['title']) && trim((string) $b['title']) !== '') {
            $result['branding']['title'] = trim((string) $b['title']);
        }
        if (array_key_exists('subtitle', $b)) {
            $result['branding']['subtitle'] = (string) $b['subtitle'];
        }
        if (isset($b['footerText']) && trim((string) $b['footerText']) !== '') {
            $result['branding']['footerText'] = trim((string) $b['footerText']);
        }
        if (array_key_exists('logoUrl', $b)) {
            $result['branding']['logoUrl'] = trim((string) $b['logoUrl']);
        }
        if (array_key_exists('faviconValue', $b)) {
            $result['branding']['faviconValue'] = trim((string) $b['faviconValue']);
        }
    }

    $incomingSheet = isset($source['sheetDbConfig']) && is_array($source['sheetDbConfig'])
        ? $source['sheetDbConfig']
        : [];

    $urlCandidate = resolveConfiguredWebAppUrl(array_merge($source, ['sheetDbConfig' => $incomingSheet]));
    if ($urlCandidate !== '' || array_key_exists('webhookUrl', $incomingSheet) || array_key_exists('webAppUrl', $source)) {
        $result['sheetDbConfig']['webhookUrl'] = $urlCandidate;
    }

    if (!empty($incomingSheet['entriesSheetName'])) {
        $result['sheetDbConfig']['entriesSheetName'] = trim((string) $incomingSheet['entriesSheetName']);
    }
    if (!empty($incomingSheet['settingsSheetName'])) {
        $result['sheetDbConfig']['settingsSheetName'] = trim((string) $incomingSheet['settingsSheetName']);
    }
    if (array_key_exists('autoSync', $incomingSheet)) {
        $result['sheetDbConfig']['autoSync'] = (bool) $incomingSheet['autoSync'];
    }
    if (array_key_exists('lastSyncAt', $incomingSheet)) {
        $result['sheetDbConfig']['lastSyncAt'] = trim((string) $incomingSheet['lastSyncAt']);
    }
    $result['sheetDbConfig']['driver'] = 'google_sheet';
    $result['sheetDbConfig']['defaultLoadOnStart'] = true;

    return $result;
}

/**
 * Extracts a valid HTTP/HTTPS Web App URL from nested config structures or environment variables.
 */
function resolveConfiguredWebAppUrl(array $config): string
{
    $candidates = [
        $config['sheetDbConfig']['webhookUrl'] ?? '',
        $config['sheetDbConfig']['webAppUrl'] ?? '',
        $config['sheetDbConfig']['googleAppScriptUrl'] ?? '',
        $config['googleAppScriptUrl'] ?? '',
        $config['googleAppsScriptUrl'] ?? '',
        $config['webAppUrl'] ?? '',
        $config['webhookUrl'] ?? '',
        $config['scriptUrl'] ?? '',
        $config['branding']['webhookUrl'] ?? '',
        $config['branding']['webAppUrl'] ?? '',
        getenv('GOOGLE_SHEET_WEBAPP_URL') ?: '',
        $_ENV['GOOGLE_SHEET_WEBAPP_URL'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $url = trim((string) $candidate);
        if ($url !== '' && preg_match('#^https?://#i', $url)) {
            return $url;
        }
    }

    return '';
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

/**
 * Saves configuration to config.override.json and updates TRACKER_CONFIG_DATA in config.php.
 */
function saveConfigOverrideAndPhp(array $config): array
{
    $overridePath = getOverrideConfigPath();
    $jsonEncoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $overrideSaved = $jsonEncoded !== false && @file_put_contents($overridePath, $jsonEncoded, LOCK_EX) !== false;
    $phpSaved = writeConfigIntoConfigPhp(__FILE__, $config);

    return [
        'saved' => $overrideSaved || $phpSaved,
        'overrideSaved' => $overrideSaved,
        'phpSaved' => $phpSaved,
    ];
}

// Serve HTTP JSON API when accessed directly via browser/fetch
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    try {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $currentConfig = loadDefaultGoogleSheetDbConfig();

        if ($method === 'GET') {
            $webAppUrl = resolveConfiguredWebAppUrl($currentConfig);
            $currentConfig['sheetDbConfig']['webhookUrl'] = $webAppUrl;
            $currentConfig['sheetDbConfig']['defaultLoadOnStart'] = true;

            echo json_encode([
                'ok' => true,
                'source' => 'config.php',
                'defaultDb' => 'google_sheet',
                'googleAppScriptUrl' => $webAppUrl,
                'webAppUrl' => $webAppUrl,
                'webhookUrl' => $webAppUrl,
                'hasWebAppUrl' => $webAppUrl !== '',
                'settings' => $currentConfig,
                'config' => $currentConfig,
                'data' => [
                    'googleAppScriptUrl' => $webAppUrl,
                    'webAppUrl' => $webAppUrl,
                    'webhookUrl' => $webAppUrl,
                    'settings' => $currentConfig,
                ],
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
            $saveResult = saveConfigOverrideAndPhp($normalized);

            if (!$saveResult['saved']) {
                http_response_code(500);
                echo json_encode([
                    'ok' => false,
                    'error' => 'Unable to save configuration to config.override.json or config.php. Check folder/file permissions.',
                ]);
                exit;
            }

            echo json_encode([
                'ok' => true,
                'message' => $saveResult['overrideSaved']
                    ? 'Google Sheet DB & branding configuration saved to config.override.json'
                    : 'Google Sheet DB & branding configuration saved to config.php',
                'overrideFile' => 'config.override.json',
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