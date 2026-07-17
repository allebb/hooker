<?php

declare(strict_types=1);

const HOOKER_PANEL_ROOT = __DIR__ . '/..';
const HOOKER_PANEL_CONFIG_PATH = HOOKER_PANEL_ROOT . '/hooker.conf.php';
const HOOKER_PANEL_TEST_CONFIG_PATH = HOOKER_PANEL_ROOT . '/hooker.conf.test';
const HOOKER_PANEL_EXAMPLE_CONFIG_PATH = HOOKER_PANEL_ROOT . '/hooker.conf.example.php';
const HOOKER_CONDUCTOR_APPLICATION_PATHS = [
    '/var/conductor/applications',
    '/var/conductor/application',
];

$auth = require __DIR__ . '/auth.php';
if (empty($auth['enable_webadmin_feature'])) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}
if (isset($_GET['logout'])) {
    header('WWW-Authenticate: Basic realm="Hooker Panel"');
    header('HTTP/1.0 401 Unauthorized');
    header('Cache-Control: no-store');
    echo 'Logged out.';
    exit;
}
requireBasicAuth((string) ($auth['username'] ?? ''), (string) ($auth['password'] ?? ''));

$message = '';
$error = '';
$runOutput = null;
$runStatus = null;
$activePanelTab = 'applications';
$editingSite = '';
$editingSiteConfig = [];

try {
    $config = loadHookerConfig();

    if (isset($_GET['stream_deployment'])) {
        streamDeployment($config, (string) $_GET['stream_deployment'], isset($_GET['init']));
    }

    if (isset($_GET['path_status'])) {
        streamPathStatus((string) $_GET['path_status'], (string) ($_GET['value'] ?? ''), (string) ($_GET['app'] ?? ''));
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['new'])) {
        $activePanelTab = 'application-editor';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'add_site') {
            $config = addPostedSite($config);
            saveHookerConfig($config);
            $config = loadHookerConfig();
            $message = 'Application added.';
            $activePanelTab = 'applications';
        } elseif ($action === 'edit_site') {
            $requestedSite = trim((string) ($_POST['site'] ?? ''));
            validateSiteName($requestedSite);
            if (!isset($config['sites'][$requestedSite]) || !is_array($config['sites'][$requestedSite])) {
                throw new RuntimeException("Application not found: {$requestedSite}");
            }
            $editingSite = $requestedSite;
            $editingSiteConfig = $config['sites'][$editingSite];
            $activePanelTab = 'application-editor';
        } elseif ($action === 'update_site') {
            $config = updatePostedSite($config);
            saveHookerConfig($config);
            $config = loadHookerConfig();
            $message = 'Application updated.';
            $activePanelTab = 'applications';
        } elseif ($action === 'delete_site') {
            $config = deletePostedSite($config);
            saveHookerConfig($config);
            $config = loadHookerConfig();
            $message = 'Application removed.';
            $activePanelTab = 'applications';
        } elseif ($action === 'run_site') {
            [$runStatus, $runOutput] = runDeployment($config, (string) ($_POST['site'] ?? ''));
            $activePanelTab = 'applications';
        }
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
    $config = isset($config) && is_array($config) ? $config : ['sites' => []];
}

$sites = $config['sites'] ?? [];
if (!is_array($sites)) {
    $sites = [];
}
$defaults = $config;
unset($defaults['sites']);
$defaultConfig = loadDefaultHookerConfig();
$defaultBaseConfig = $defaultConfig;
unset($defaultBaseConfig['sites']);
$localBaseConfig = $defaults;
$mergedBaseConfig = array_merge($defaultBaseConfig, $localBaseConfig);
$editorMode = $editingSite !== '' ? 'edit' : 'add';
$editorSiteName = $editingSite;
$editorSiteConfig = $editingSiteConfig;
$hookerlessPaths = hookerlessConductorPaths($sites);
$siteFolderExists = [];
foreach (array_keys($sites) as $siteName) {
    $siteFolderExists[$siteName] = conductorApplicationFolderExists((string) $siteName);
}

function requireBasicAuth(string $username, string $password): void
{
    $givenUsername = (string) ($_SERVER['PHP_AUTH_USER'] ?? '');
    $givenPassword = (string) ($_SERVER['PHP_AUTH_PW'] ?? '');

    if ($username === '' || $password === '' || !hash_equals($username, $givenUsername) || !hash_equals($password, $givenPassword)) {
        header('WWW-Authenticate: Basic realm="Hooker Panel"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Authentication required.';
        exit;
    }
}

function loadHookerConfig(): array
{
    ensureLocalTestConfigExists();

    $configPath = activeHookerConfigPath();
    if (!file_exists($configPath)) {
        return ['sites' => []];
    }

    $config = require $configPath;
    if (!is_array($config)) {
        throw new RuntimeException(basename($configPath) . ' must return an array.');
    }

    if (!isset($config['sites']) || !is_array($config['sites'])) {
        $config['sites'] = [];
    }

    return $config;
}

function addPostedSite(array $config): array
{
    $siteName = trim((string) ($_POST['new_site_name'] ?? ''));
    validateSiteName($siteName);

    $config['sites'] = isset($config['sites']) && is_array($config['sites']) ? $config['sites'] : [];
    if (isset($config['sites'][$siteName])) {
        throw new RuntimeException("Application already exists: {$siteName}");
    }

    $config['sites'][$siteName] = decodeJsonObject((string) ($_POST['new_site_json'] ?? '{}'), "Application {$siteName}");

    return $config;
}

function updatePostedSite(array $config): array
{
    $originalSiteName = trim((string) ($_POST['original_site_name'] ?? ''));
    $siteName = trim((string) ($_POST['new_site_name'] ?? ''));
    validateSiteName($originalSiteName);
    validateSiteName($siteName);

    $config['sites'] = isset($config['sites']) && is_array($config['sites']) ? $config['sites'] : [];
    if (!isset($config['sites'][$originalSiteName])) {
        throw new RuntimeException("Application not found: {$originalSiteName}");
    }

    if ($siteName !== $originalSiteName && isset($config['sites'][$siteName])) {
        throw new RuntimeException("Application already exists: {$siteName}");
    }

    unset($config['sites'][$originalSiteName]);
    $config['sites'][$siteName] = decodeJsonObject((string) ($_POST['new_site_json'] ?? '{}'), "Application {$siteName}");

    return $config;
}

function deletePostedSite(array $config): array
{
    $siteName = trim((string) ($_POST['site'] ?? ''));
    validateSiteName($siteName);

    if (isset($config['sites']) && is_array($config['sites'])) {
        unset($config['sites'][$siteName]);
    }

    return $config;
}

function decodeJsonObject(string $json, string $label): array
{
    $json = trim($json);
    if ($json === '') {
        $json = '{}';
    }

    $decoded = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("{$label} JSON is invalid: " . json_last_error_msg());
    }

    if (!is_array($decoded) || array_is_list($decoded)) {
        throw new RuntimeException("{$label} JSON must be an object.");
    }

    return $decoded;
}

function validateSiteName(string $siteName): void
{
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $siteName)) {
        throw new RuntimeException('Application names may only contain letters, numbers, dots, underscores, and hyphens.');
    }
}

function saveHookerConfig(array $config): void
{
    $configPath = activeHookerConfigPath();
    $contents = "<?php\n\n/**\n * Hooker Configuration File\n * Managed by Hooker Panel.\n */\nreturn " . exportPhpValue($config) . ";\n";

    lintHookerConfigContents($contents);

    if (file_put_contents($configPath, $contents, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write ' . basename($configPath) . '. Check file permissions.');
    }

    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($configPath, true);
    }
}

function exportPhpValue(mixed $value, int $level = 0): string
{
    if (!is_array($value)) {
        return var_export($value, true);
    }

    if ($value === []) {
        return '[]';
    }

    $indent = str_repeat('    ', $level);
    $childIndent = str_repeat('    ', $level + 1);
    $isList = array_is_list($value);
    $lines = ['['];

    foreach ($value as $key => $item) {
        $prefix = $isList ? '' : var_export($key, true) . ' => ';
        $lines[] = $childIndent . $prefix . exportPhpValue($item, $level + 1) . ',';
    }

    $lines[] = $indent . ']';

    return implode("\n", $lines);
}

function lintHookerConfigContents(string $contents): void
{
    $tempFile = tempnam(sys_get_temp_dir(), 'hooker-conf-');
    if ($tempFile === false) {
        throw new RuntimeException('Unable to create a temporary file for PHP lint testing.');
    }

    $tempPhpFile = $tempFile . '.php';
    if (!rename($tempFile, $tempPhpFile)) {
        unlink($tempFile);
        throw new RuntimeException('Unable to prepare a temporary file for PHP lint testing.');
    }

    try {
        if (file_put_contents($tempPhpFile, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write a temporary file for PHP lint testing.');
        }

        $output = [];
        $exitCode = 0;
        exec(escapeshellarg(phpCliBinary()) . ' -l ' . escapeshellarg($tempPhpFile) . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException("The generated PHP configuration did not pass lint testing. Please fix the form values and try again.\n" . implode("\n", $output));
        }
    } finally {
        if (file_exists($tempPhpFile)) {
            unlink($tempPhpFile);
        }
    }
}

function phpCliBinary(): string
{
    $candidates = [];

    if (PHP_BINARY !== '' && !str_contains(basename(PHP_BINARY), 'php-fpm')) {
        $candidates[] = PHP_BINARY;
    }

    $candidates[] = PHP_BINDIR . '/php';
    $candidates[] = '/usr/bin/php';
    $candidates[] = '/usr/local/bin/php';

    foreach (array_unique($candidates) as $candidate) {
        if ($candidate !== '' && is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }

    $output = [];
    $exitCode = 0;
    exec('command -v php 2>/dev/null', $output, $exitCode);
    if ($exitCode === 0 && isset($output[0]) && is_executable($output[0])) {
        return $output[0];
    }

    throw new RuntimeException('Unable to find a PHP CLI binary for configuration lint testing.');
}

function activeHookerConfigPath(): string
{
    return isLocalhostRequest() ? HOOKER_PANEL_TEST_CONFIG_PATH : HOOKER_PANEL_CONFIG_PATH;
}

function ensureLocalTestConfigExists(): void
{
    if (!isLocalhostRequest() || file_exists(HOOKER_PANEL_TEST_CONFIG_PATH)) {
        return;
    }

    if (!file_exists(HOOKER_PANEL_EXAMPLE_CONFIG_PATH)) {
        throw new RuntimeException('Unable to create hooker.conf.test because hooker.conf.example.php is missing.');
    }

    if (!copy(HOOKER_PANEL_EXAMPLE_CONFIG_PATH, HOOKER_PANEL_TEST_CONFIG_PATH)) {
        throw new RuntimeException('Unable to create hooker.conf.test. Check file permissions.');
    }
}

function isLocalhostRequest(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $host = trim($host, '[]');
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;

    return $host === 'localhost';
}

function runDeployment(array $config, string $siteName): array
{
    validateSiteName($siteName);

    if (!isset($config['sites'][$siteName]) || !is_array($config['sites'][$siteName])) {
        throw new RuntimeException("Application not found: {$siteName}");
    }

    set_time_limit(0);

    [$url, $method, $headers, $body] = deploymentRequest($config, $siteName);
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 900,
        ],
    ]);

    $output = @file_get_contents($url, false, $context);
    $status = 'HTTP status unavailable';

    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+\d+#', $header)) {
            $status = $header;
            break;
        }
    }

    if ($output === false) {
        $output = 'Unable to call deployment URL: ' . $url;
    }

    return [$status, (string) $output];
}

function streamDeployment(array $config, string $siteName, bool $init = false): never
{
    validateSiteName($siteName);

    if (!isset($config['sites'][$siteName]) || !is_array($config['sites'][$siteName])) {
        http_response_code(404);
        echo "Application not found: {$siteName}";
        exit;
    }

    if ($init && applicationInitDisabled($config, $config['sites'][$siteName])) {
        http_response_code(403);
        header('Content-Type: text/plain');
        header('X-Hooker-Deployment-Status: 403');
        echo "ERROR: Initialisation is disabled for {$siteName}.";
        exit;
    }

    set_time_limit(0);
    while (ob_get_level() > 0) {
        ob_end_flush();
    }

    [$url, $method, $headers, $body] = deploymentRequest($config, $siteName, $init);
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 900,
        ],
    ]);

    $stream = @fopen($url, 'rb', false, $context);
    if ($stream === false) {
        http_response_code(502);
        header('Content-Type: text/plain');
        header('X-Hooker-Deployment-Status: 502');
        echo 'Unable to call deployment URL: ' . $url;
        exit;
    }

    $metadata = stream_get_meta_data($stream);
    $status = httpStatusFromHeaders($metadata['wrapper_data'] ?? []) ?? 200;
    http_response_code($status);
    header('Content-Type: text/plain');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    header('X-Hooker-Deployment-Status: ' . $status);

    while (!feof($stream)) {
        echo fread($stream, 8192);
        flush();
    }

    fclose($stream);
    exit;
}

function streamPathStatus(string $type, string $value, string $appName): never
{
    $type = trim($type);
    $value = trim($value);
    $appName = trim($appName);

    if (!in_array($type, ['local_repo', 'git_ssh_key_path'], true)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unknown path status type.']);
        exit;
    }

    if ($appName !== '' && !preg_match('/^[A-Za-z0-9._-]+$/', $appName)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Application names may only contain letters, numbers, dots, underscores, and hyphens.']);
        exit;
    }

    $path = resolvePanelPathValue($type, $value, $appName);
    $exists = $type === 'local_repo' ? is_dir($path) : is_file($path);

    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode([
        'path' => $path,
        'exists' => $exists,
        'kind' => $type === 'local_repo' ? 'directory' : 'file',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function resolvePanelPathValue(string $type, string $value, string $appName): string
{
    if ($type === 'local_repo' && $value === '@conductor') {
        return '/var/conductor/applications/' . ($appName !== '' ? $appName : '{appname}');
    }

    if ($type === 'git_ssh_key_path' && $value === '@conductor') {
        return '/var/www/.ssh/' . ($appName !== '' ? $appName : '{appname}') . '.deploykey';
    }

    return $value;
}

function deploymentRequest(array $config, string $siteName, bool $init = false): array
{
    $siteConfig = $config['sites'][$siteName];
    $query = ['app' => $siteName];
    if ($init) {
        $query['init'] = '1';
    }
    if (array_key_exists('key', $siteConfig) && $siteConfig['key'] !== false && $siteConfig['key'] !== '') {
        $query['key'] = (string) $siteConfig['key'];
    } elseif (array_key_exists('key', $config) && $config['key'] !== false && $config['key'] !== '') {
        $query['key'] = (string) $config['key'];
    }

    [$method, $headers, $body] = providerRequestOptions($config, $siteConfig);

    return [hookerBaseUrl() . '/hooker.php?' . http_build_query($query), $method, $headers, $body];
}

function deploymentUrl(array $config, string $siteName): string
{
    if (!isset($config['sites'][$siteName]) || !is_array($config['sites'][$siteName])) {
        return '';
    }

    $siteConfig = $config['sites'][$siteName];
    $query = ['app' => $siteName];
    if (array_key_exists('key', $siteConfig) && $siteConfig['key'] !== false && $siteConfig['key'] !== '') {
        $query['key'] = (string) $siteConfig['key'];
    } elseif (array_key_exists('key', $config) && $config['key'] !== false && $config['key'] !== '') {
        $query['key'] = (string) $config['key'];
    }

    return hookerBaseUrl() . '/hooker.php?' . http_build_query($query);
}

function applicationInitDisabled(array $config, array $siteConfig): bool
{
    return !empty($siteConfig['disable_init'] ?? $config['disable_init'] ?? false);
}

function httpStatusFromHeaders(array $headers): ?int
{
    foreach ($headers as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', (string) $header, $matches)) {
            return (int) $matches[1];
        }
    }

    return null;
}

function providerRequestOptions(array $config, array $siteConfig): array
{
    $effectiveConfig = array_merge($config, $siteConfig);
    unset($effectiveConfig['sites']);

    $branch = (string) ($effectiveConfig['branch'] ?? 'master');

    if (!empty($effectiveConfig['is_github'])) {
        return [
            'POST',
            ['X-Github-Event: push', 'Content-Type: application/json'],
            json_encode(['ref' => 'refs/heads/' . $branch]) ?: '{}',
        ];
    }

    if (!empty($effectiveConfig['is_gitlab'])) {
        return [
            'POST',
            ['X-Gitlab-Event: Push Hook', 'Content-Type: application/json'],
            json_encode(['ref' => 'refs/heads/' . $branch]) ?: '{}',
        ];
    }

    if (!empty($effectiveConfig['is_bitbucket'])) {
        return [
            'POST',
            ['X-Event-Key: repo:push', 'Content-Type: application/json'],
            json_encode([
                'ref' => [
                    'push' => [
                        'changes' => [
                            'old' => [
                                'name' => $branch,
                            ],
                        ],
                    ],
                ],
            ]) ?: '{}',
        ];
    }

    return ['GET', [], ''];
}

function hookerBaseUrl(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $basePath = str_replace('\\', '/', dirname(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/webadmin/index.php'))));
    $basePath = ($basePath === '/' || $basePath === '.') ? '' : rtrim($basePath, '/');

    return "{$scheme}://{$host}{$basePath}";
}

function jsonFor(mixed $value): string
{
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function availablePhpBinaries(): array
{
    $paths = glob('/usr/bin/php*') ?: [];
    $paths = array_values(array_filter($paths, static function (string $path): bool {
        return is_file($path) && preg_match('/\/php(\d+(\.\d+)?)?$/', $path);
    }));

    sort($paths, SORT_NATURAL);

    if ($paths === []) {
        $paths[] = '/usr/bin/php';
    }

    return $paths;
}

function hookerlessConductorPaths(array $sites): array
{
    $paths = [];

    foreach (HOOKER_CONDUCTOR_APPLICATION_PATHS as $root) {
        if (!is_dir($root)) {
            continue;
        }

        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || isset($sites[$entry])) {
                continue;
            }

            $path = $root . '/' . $entry;
            if (is_dir($path) && preg_match('/^[A-Za-z0-9._-]+$/', $entry)) {
                $paths[$entry] = $entry;
            }
        }
    }

    natcasesort($paths);

    return array_values($paths);
}

function conductorApplicationFolderExists(string $siteName): bool
{
    foreach (HOOKER_CONDUCTOR_APPLICATION_PATHS as $root) {
        if (is_dir($root . '/' . $siteName)) {
            return true;
        }
    }

    return false;
}

function loadDefaultHookerConfig(): array
{
    $source = file_get_contents(HOOKER_PANEL_ROOT . '/hooker.php');
    if ($source === false) {
        throw new RuntimeException('Unable to read hooker.php default configuration.');
    }

    $tokens = token_get_all($source);
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        if (is_array($tokens[$i]) && $tokens[$i][0] === T_VARIABLE && $tokens[$i][1] === '$config') {
            $j = $i + 1;
            while ($j < $count && isIgnorableToken($tokens[$j])) {
                $j++;
            }
            if (($tokens[$j] ?? null) !== '=') {
                continue;
            }

            $j++;
            while ($j < $count && isIgnorableToken($tokens[$j])) {
                $j++;
            }

            $expression = '';
            $depth = 0;
            for (; $j < $count; $j++) {
                $token = $tokens[$j];
                $text = is_array($token) ? $token[1] : $token;

                if ($text === '[' || $text === '(') {
                    $depth++;
                } elseif ($text === ']' || $text === ')') {
                    $depth--;
                } elseif ($text === ';' && $depth === 0) {
                    /** @var mixed $config */
                    $config = eval('return ' . $expression . ';');
                    if (!is_array($config)) {
                        throw new RuntimeException('The default Hooker configuration is not an array.');
                    }
                    return $config;
                }

                $expression .= $text;
            }
        }
    }

    throw new RuntimeException('Unable to locate default Hooker configuration in hooker.php.');
}

function isIgnorableToken(mixed $token): bool
{
    return is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
}

function renderConfigValue(mixed $value): string
{
    return e(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: var_export($value, true));
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hooker (Web Admin)</title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #0e1117;
            --panel: #151a23;
            --surface: #1b2230;
            --surface-strong: #222b3a;
            --ink: #edf2f7;
            --muted: #9aa7b8;
            --line: #2d3748;
            --accent: #38bdf8;
            --accent-strong: #0284c7;
            --danger: #f87171;
            --danger-strong: #b91c1c;
            --ok: #4ade80;
            --warning: #facc15;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at 20% 0%, rgba(56, 189, 248, 0.12), transparent 28rem),
                radial-gradient(circle at 85% 10%, rgba(74, 222, 128, 0.08), transparent 24rem),
                var(--bg);
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: var(--ink);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.45;
        }

        header,
        main,
        footer {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
        }

        header {
            padding: 28px 0 18px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 18px;
        }

        footer {
            color: #fff;
            padding: 18px 0 28px;
            text-align: center;
            font-size: 12px;
            font-weight: 400;
        }

        footer a {
            color: #fff;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        h1,
        h2,
        h3 {
            margin: 0;
            letter-spacing: 0;
        }

        h1 {
            font-size: 30px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand img {
            width: 58px;
            height: 58px;
            object-fit: contain;
            flex: 0 0 auto;
        }

        .header-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
        }

        h2 {
            font-size: 20px;
        }

        h3 {
            font-size: 16px;
        }

        p {
            color: var(--muted);
            margin: 6px 0 0;
        }

        form {
            margin: 0;
        }

        .panel,
        .notice,
        .site {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
        }

        .panel {
            padding: 18px;
            margin-bottom: 18px;
        }

        .notice {
            padding: 12px 14px;
            margin-bottom: 18px;
            font-weight: 650;
            opacity: 1;
            transition: opacity 700ms ease;
        }

        .notice.ok {
            color: var(--ok);
        }

        .notice.fading {
            opacity: 0;
        }

        .notice.error {
            color: var(--danger);
        }

        .toolbar,
        .site-head,
        .add-grid {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .toolbar,
        .site-head {
            justify-content: space-between;
        }

        .sites {
            display: grid;
            gap: 14px;
            margin-top: 14px;
        }

        .site {
            padding: 14px;
        }

        label {
            display: block;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        input,
        select,
        textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 6px;
            color: var(--ink);
            background: #0f141d;
            font: 14px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            padding: 10px;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: 2px solid rgba(56, 189, 248, 0.35);
            border-color: var(--accent);
        }

        input,
        select {
            max-width: 360px;
        }

        select {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        input[type="checkbox"] {
            width: auto;
            max-width: none;
            margin: 0;
        }

        textarea {
            min-height: 180px;
            resize: vertical;
            margin-bottom: 14px;
        }

        .line-number-editor {
            position: relative;
            margin-bottom: 14px;
        }

        .line-number-editor textarea {
            line-height: 1.45;
            margin-bottom: 0;
            overflow-x: auto;
            padding-left: 54px;
            white-space: pre;
            word-break: normal;
        }

        .line-numbers {
            position: absolute;
            top: 1px;
            left: 1px;
            width: 42px;
            overflow: hidden;
            border-right: 1px solid var(--line);
            border-radius: 6px 0 0 6px;
            background: #0a0f17;
            color: #64748b;
            font: 14px/1.45 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            padding: 10px 8px 10px 0;
            pointer-events: none;
            text-align: right;
            user-select: none;
            white-space: pre;
        }

        .defaults textarea {
            min-height: 260px;
        }

        button,
        .button-link {
            appearance: none;
            border: 0;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font: inherit;
            font-size: 13.3333px;
            line-height: normal;
            min-height: 36px;
            padding: 9px 13px;
            font-weight: 750;
            cursor: pointer;
            background: var(--accent-strong);
            color: #fff;
            text-decoration: none;
        }

        button.secondary,
        .button-link.secondary {
            background: var(--surface-strong);
            color: var(--ink);
            border: 1px solid var(--line);
        }

        button.danger {
            background: var(--danger-strong);
        }

        button:disabled {
            cursor: not-allowed;
            opacity: 0.45;
        }

        pre {
            overflow: auto;
            background: #070a10;
            color: #f8fafc;
            border-radius: 8px;
            padding: 14px;
            white-space: pre-wrap;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .add-grid {
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .add-grid textarea {
            flex: 1 1 420px;
            min-height: 120px;
            margin: 0;
        }

        .add-fields {
            display: grid;
            gap: 14px;
            flex: 0 1 360px;
        }

        .check-row {
            display: flex;
            align-items: center;
            gap: 9px;
            color: var(--ink);
            font-size: 14px;
            font-weight: 650;
        }

        .field-row {
            display: flex;
            align-items: flex-end;
            gap: 8px;
        }

        .field-row > div {
            flex: 1 1 auto;
        }

        .field-row button {
            flex: 0 0 auto;
            margin-bottom: 0;
        }

        .hint,
        .legend {
            color: var(--muted);
            font-size: 12px;
            margin-top: 6px;
        }

        .path-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .path-status::before {
            font-weight: 800;
        }

        .path-status.exists {
            color: var(--ok);
        }

        .path-status.exists::before {
            content: "✓";
        }

        .path-status.missing {
            color: var(--danger);
        }

        .path-status.missing::before {
            content: "✕";
        }

        .legend {
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 12px;
        }

        .placeholder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 8px;
            margin-top: 8px;
        }

        .placeholder-token {
            display: grid;
            gap: 3px;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 8px;
            background: #101620;
            cursor: help;
        }

        .placeholder-token code {
            color: var(--accent);
            font-weight: 750;
        }

        .placeholder-value {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .command-fields {
            display: grid;
            gap: 12px;
            margin-bottom: 14px;
        }

        .command-fields textarea {
            min-height: 86px;
        }

        .command-fields textarea.inherited-command {
            border-color: #f59e0b;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.18);
        }

        .tabs {
            flex: 1 1 420px;
            min-width: 280px;
        }

        .page-tabs {
            margin-bottom: 18px;
        }

        .tab-list {
            display: flex;
            gap: 8px;
            border-bottom: 1px solid var(--line);
            margin-bottom: 14px;
        }

        .tab-button {
            border-radius: 6px 6px 0 0;
            background: transparent;
            color: var(--muted);
            border-bottom: 3px solid transparent;
        }

        .tab-button[aria-selected="true"] {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }

        .tab-panel[hidden] {
            display: none;
        }

        .preview textarea {
            min-height: 470px;
        }

        .app-list {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }

        .app-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--surface);
        }

        .app-row.missing-folder {
            border-color: rgba(250, 204, 21, 0.7);
            background: rgba(250, 204, 21, 0.09);
        }

        .app-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .caution {
            color: var(--warning);
            font-weight: 900;
            cursor: help;
        }

        .config-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        .config-table th,
        .config-table td {
            border-bottom: 1px solid var(--line);
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        .config-table pre {
            margin: 0;
            background: transparent;
            color: inherit;
            padding: 0;
        }

        .config-source {
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
        }

        .override {
            color: #f59e0b;
            font-weight: 800;
        }

        .override .config-source {
            color: inherit;
        }

        .deployment-progress {
            margin-top: 16px;
            margin-bottom: 16px;
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
            padding: 16px 0;
        }

        .deployment-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .status-pill {
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 5px 10px;
            color: var(--muted);
            background: #101620;
            font-size: 12px;
            font-weight: 800;
        }

        .status-pill.success {
            color: #dcfce7;
            background: rgba(22, 101, 52, 0.55);
            border-color: #22c55e;
        }

        .status-pill.error {
            color: #fee2e2;
            background: rgba(127, 29, 29, 0.65);
            border-color: #ef4444;
        }

        .deployment-output {
            min-height: 220px;
            max-height: 520px;
            margin: 0 0 12px;
        }

        .modal-backdrop {
            position: fixed;
            inset: 0;
            display: grid;
            place-items: center;
            padding: 18px;
            background: rgba(2, 6, 23, 0.74);
            z-index: 50;
        }

        .modal-backdrop[hidden] {
            display: none;
        }

        .modal {
            width: min(680px, 100%);
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 18px;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.45);
        }

        .modal-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 14px;
        }

        @media (max-width: 760px) {
            header,
            .toolbar,
            .site-head {
                display: block;
            }

            .brand {
                display: flex;
                margin-bottom: 14px;
            }

            .actions {
                margin-top: 12px;
            }

            .header-actions {
                justify-content: flex-start;
            }

            .deployment-head {
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="brand">
            <img src="../assets/hooker.png" alt="Hooker">
            <div>
                <h1>Hooker</h1>
                <p>Editing <?= e(basename(activeHookerConfigPath())) ?></p>
            </div>
        </div>
        <div class="header-actions">
            <a class="button-link secondary" href="?new=1">New</a>
            <button class="secondary" type="button" id="logout-button">Logout</button>
        </div>
    </header>

    <main>
        <?php if ($message !== ''): ?>
            <div class="notice ok" data-auto-fade><?= e($message) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="notice error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($runOutput !== null): ?>
            <section class="panel">
                <h2>Deployment Output</h2>
                <p><?= e($runStatus) ?></p>
                <pre><?= e($runOutput) ?></pre>
            </section>
        <?php endif; ?>

        <div class="page-tabs">
            <div class="tab-list" role="tablist" aria-label="Hooker panel sections">
                <button class="tab-button" type="button" role="tab" aria-selected="<?= $activePanelTab === 'applications' ? 'true' : 'false' ?>" data-page-tab-target="applications-panel">Applications</button>
                <button class="tab-button" type="button" role="tab" aria-selected="<?= $activePanelTab === 'application-editor' ? 'true' : 'false' ?>" data-page-tab-target="application-editor-panel"><?= $editorMode === 'edit' ? 'Edit application' : 'Add application' ?></button>
                <button class="tab-button" type="button" role="tab" aria-selected="<?= $activePanelTab === 'base-config' ? 'true' : 'false' ?>" data-page-tab-target="base-config-panel">Base configuration</button>
            </div>
        </div>

        <section class="panel tab-panel" id="applications-panel" <?= $activePanelTab === 'applications' ? '' : 'hidden' ?>>
            <div class="toolbar">
                <div>
                    <h2>Applications</h2>
                    <p>Configured applications in <code><?= e(basename(activeHookerConfigPath())) ?></code>.</p>
                </div>
            </div>
            <section class="deployment-progress" id="deployment-progress" hidden>
                <div class="deployment-head">
                    <div>
                        <h2>Deployment Progress</h2>
                        <p id="deployment-progress-title"></p>
                    </div>
                    <span class="status-pill" id="deployment-status-pill" hidden></span>
                </div>
                <pre class="deployment-output" id="deployment-output"></pre>
                <button type="button" class="secondary" id="deployment-close">Close</button>
            </section>
            <div class="app-list">
                <?php if ($sites === []): ?>
                    <p>No applications configured.</p>
                <?php endif; ?>
                <?php foreach ($sites as $siteName => $siteConfig): ?>
                    <?php $missingFolder = empty($siteFolderExists[$siteName]); ?>
                    <?php $initDisabled = applicationInitDisabled($config, is_array($siteConfig) ? $siteConfig : []); ?>
                    <div class="app-row <?= $missingFolder ? 'missing-folder' : '' ?>">
                        <div class="app-title">
                            <?php if ($missingFolder): ?>
                                <span class="caution" title="No matching Conductor application folder found at /var/conductor/application/ or /var/conductor/applications/">&#9888;</span>
                            <?php endif; ?>
                            <h3><?= e($siteName) ?></h3>
                        </div>
                        <div class="actions">
                            <form method="post">
                                <input type="hidden" name="site" value="<?= e($siteName) ?>">
                                <button type="submit" name="action" value="edit_site">Edit</button>
                            </form>
                            <button class="secondary" type="button" data-run-site="<?= e($siteName) ?>">Run</button>
                            <button class="secondary" type="button" data-init-site="<?= e($siteName) ?>" <?= $initDisabled ? 'disabled title="Initialisation is disabled for this application."' : '' ?>>Init</button>
                            <button class="secondary" type="button" data-deploy-url="<?= e(deploymentUrl($config, (string) $siteName)) ?>">Deploy URL</button>
                            <form method="post">
                                <input type="hidden" name="site" value="<?= e($siteName) ?>">
                                <button class="danger" type="submit" name="action" value="delete_site" onclick="return confirm('Remove this application from <?= e(basename(activeHookerConfigPath())) ?>?')">Remove</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="panel tab-panel" id="application-editor-panel" <?= $activePanelTab === 'application-editor' ? '' : 'hidden' ?>>
            <h2><?= $editorMode === 'edit' ? 'Edit Application' : 'Add Application' ?></h2>
            <form method="post" class="add-grid" id="add-site-form">
                <?php if ($editorMode === 'edit'): ?>
                    <input type="hidden" name="original_site_name" value="<?= e($editorSiteName) ?>">
                <?php endif; ?>
                <div class="add-fields">
                    <div>
                        <label for="new_site_template">Template</label>
                        <select id="new_site_template">
                            <option value="html">HTML</option>
                            <option value="laravel">Laravel</option>
                            <option value="docker">Docker</option>
                        </select>
                    </div>
                    <?php if ($editorMode === 'add'): ?>
                        <div>
                            <label for="new_site_hookerless_path">Hookerless paths</label>
                            <select id="new_site_hookerless_path">
                                <option value="">Select an application folder</option>
                                <?php foreach ($hookerlessPaths as $hookerlessPath): ?>
                                    <option value="<?= e($hookerlessPath) ?>"><?= e($hookerlessPath) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <label for="new_site_name">Application name</label>
                    <input id="new_site_name" name="new_site_name" required pattern="[A-Za-z0-9._-]+">
                    <div class="field-row">
                        <div>
                            <label for="new_site_key">Key</label>
                            <input id="new_site_key" type="text">
                        </div>
                        <button type="button" class="secondary" id="new_site_key_generate">Generate</button>
                    </div>
                    <div>
                        <label for="new_site_remote_repo">Git repository</label>
                        <input id="new_site_remote_repo" type="text" placeholder="git@github.com:user/repo.git" pattern="[^@]+@[^:]+:.+">
                    </div>
                    <div>
                        <label for="new_site_branch">Branch</label>
                        <input id="new_site_branch" type="text" value="<?= e((string) ($mergedBaseConfig['branch'] ?? 'master')) ?>">
                    </div>
                    <div>
                        <label for="new_site_local_repo">Local repo</label>
                        <input id="new_site_local_repo" type="text" value="@conductor">
                        <div class="hint" id="new_site_local_repo_hint"></div>
                    </div>
                    <div>
                        <label for="new_site_git_ssh_key_path">Git SSH key path</label>
                        <input id="new_site_git_ssh_key_path" type="text" value="@conductor">
                        <div class="hint" id="new_site_git_ssh_key_path_hint"></div>
                    </div>
                    <label class="check-row" for="new_site_disable_init">
                        <input type="checkbox" id="new_site_disable_init" checked>
                        Disable init
                    </label>
                    <label class="check-row" for="new_site_debug">
                        <input type="checkbox" id="new_site_debug" checked>
                        Debug mode
                    </label>
                    <div>
                        <label for="new_site_php_bin">PHP binary</label>
                        <select id="new_site_php_bin">
                            <?php foreach (availablePhpBinaries() as $phpBinary): ?>
                                <option value="<?= e($phpBinary) ?>"><?= e($phpBinary) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="new_site_composer_bin">Composer binary</label>
                        <input id="new_site_composer_bin" type="text" value="/usr/bin/composer">
                    </div>
                    <label class="check-row" for="new_site_use_json">
                        <input type="checkbox" id="new_site_use_json">
                        Use repository .hooker.json config?
                    </label>
                    <button type="submit" name="action" value="<?= $editorMode === 'edit' ? 'update_site' : 'add_site' ?>"><?= $editorMode === 'edit' ? 'Save changes' : 'Add' ?></button>
                </div>
                <div class="tabs">
                    <div class="tab-list" role="tablist" aria-label="Add application editor">
                        <button class="tab-button" type="button" id="add-form-tab" role="tab" aria-selected="true" aria-controls="add-form-panel" data-tab-target="add-form-panel">Form</button>
                        <button class="tab-button" type="button" id="add-preview-tab" role="tab" aria-selected="false" aria-controls="add-preview-panel" data-tab-target="add-preview-panel">Preview</button>
                    </div>
                    <div id="add-form-panel" class="tab-panel" role="tabpanel" aria-labelledby="add-form-tab">
                        <div class="legend">
                            Placeholders. Add one command per line.
                            <div class="placeholder-grid">
                                <span class="placeholder-token" data-placeholder="local-repo"><code>{{local-repo}}</code><span class="placeholder-value"></span></span>
                                <span class="placeholder-token" data-placeholder="remote-repo"><code>{{remote-repo}}</code><span class="placeholder-value"></span></span>
                                <span class="placeholder-token" data-placeholder="branch"><code>{{branch}}</code><span class="placeholder-value"></span></span>
                                <span class="placeholder-token" data-placeholder="git-bin"><code>{{git-bin}}</code><span class="placeholder-value"></span></span>
                                <span class="placeholder-token" data-placeholder="git-ssh-key"><code>{{git-ssh-key}}</code><span class="placeholder-value"></span></span>
                                <span class="placeholder-token" data-placeholder="php-bin"><code>{{php-bin}}</code><span class="placeholder-value"></span></span>
                                <span class="placeholder-token" data-placeholder="composer-bin"><code>{{composer-bin}}</code><span class="placeholder-value"></span></span>
                                <span class="placeholder-token" data-placeholder="user"><code>{{user}}</code><span class="placeholder-value"></span></span>
                            </div>
                        </div>
                        <div class="command-fields">
                            <div>
                                <label for="new_site_init_commands">Init commands</label>
                                <div class="line-number-editor">
                                    <div class="line-numbers" aria-hidden="true"></div>
                                    <textarea id="new_site_init_commands" spellcheck="false" autocomplete="off" autocorrect="off" autocapitalize="off" data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false" data-lpignore="true" data-1p-ignore="true" wrap="off" data-line-numbered></textarea>
                                </div>
                            </div>
                            <div>
                                <label for="new_site_pre_commands">Pre commands</label>
                                <div class="line-number-editor">
                                    <div class="line-numbers" aria-hidden="true"></div>
                                    <textarea id="new_site_pre_commands" spellcheck="false" autocomplete="off" autocorrect="off" autocapitalize="off" data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false" data-lpignore="true" data-1p-ignore="true" wrap="off" data-line-numbered></textarea>
                                </div>
                            </div>
                            <div>
                                <label for="new_site_deploy_commands">Deploy commands</label>
                                <div class="line-number-editor">
                                    <div class="line-numbers" aria-hidden="true"></div>
                                    <textarea id="new_site_deploy_commands" spellcheck="false" autocomplete="off" autocorrect="off" autocapitalize="off" data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false" data-lpignore="true" data-1p-ignore="true" wrap="off" data-line-numbered></textarea>
                                </div>
                            </div>
                            <div>
                                <label for="new_site_post_commands">Post commands</label>
                                <div class="line-number-editor">
                                    <div class="line-numbers" aria-hidden="true"></div>
                                    <textarea id="new_site_post_commands" spellcheck="false" autocomplete="off" autocorrect="off" autocapitalize="off" data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false" data-lpignore="true" data-1p-ignore="true" wrap="off" data-line-numbered></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="add-preview-panel" class="tab-panel preview" role="tabpanel" aria-labelledby="add-preview-tab" hidden>
                        <label for="new_site_json">Application JSON preview</label>
                        <div class="line-number-editor">
                            <div class="line-numbers" aria-hidden="true"></div>
                            <textarea id="new_site_json" name="new_site_json" spellcheck="false" autocomplete="off" autocorrect="off" autocapitalize="off" data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false" data-lpignore="true" data-1p-ignore="true" wrap="off" readonly data-line-numbered>{
    "key": false,
    "local_repo": "@conductor",
    "git_ssh_key_path": "@conductor",
    "branch": "master"
}</textarea>
                        </div>
                    </div>
                </div>
            </form>
        </section>

        <section class="panel tab-panel" id="base-config-panel" <?= $activePanelTab === 'base-config' ? '' : 'hidden' ?>>
            <div class="toolbar">
                <div>
                    <h2>Base Configuration</h2>
                    <p>Merged defaults from <code>hooker.php</code> and top-level overrides from <code><?= e(basename(activeHookerConfigPath())) ?></code>. Overrides are bold.</p>
                </div>
            </div>
            <table class="config-table">
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Value</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mergedBaseConfig as $key => $value): ?>
                        <?php $isOverride = array_key_exists($key, $localBaseConfig) && (!array_key_exists($key, $defaultBaseConfig) || $localBaseConfig[$key] !== $defaultBaseConfig[$key]); ?>
                        <tr class="<?= $isOverride ? 'override' : '' ?>">
                            <td><code><?= e($key) ?></code></td>
                            <td><pre><?= renderConfigValue($value) ?></pre></td>
                            <td class="config-source"><?= $isOverride ? 'Local override' : 'Base config' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
    <footer>Powered by <a href="https://github.com/allebb/hooker" rel="noopener noreferrer" target="_blank">Hooker</a>!</footer>
    <div class="modal-backdrop" id="deploy-url-modal" hidden>
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="deploy-url-title">
            <h2 id="deploy-url-title">Deploy URL</h2>
            <p>Use this URL to trigger the deployment webhook.</p>
            <textarea id="deploy-url-value" readonly></textarea>
            <div class="modal-actions">
                <button type="button" class="secondary" id="deploy-url-close">Close</button>
                <button type="button" id="deploy-url-copy">Copy to clipboard</button>
            </div>
        </div>
    </div>
    <script>
        const addSiteJson = document.getElementById('new_site_json');
        const addSiteName = document.getElementById('new_site_name');
        const addSiteHookerlessPath = document.getElementById('new_site_hookerless_path');
        const addSiteKey = document.getElementById('new_site_key');
        const addSiteKeyGenerate = document.getElementById('new_site_key_generate');
        const addSiteRemoteRepo = document.getElementById('new_site_remote_repo');
        const addSiteBranch = document.getElementById('new_site_branch');
        const addSiteLocalRepo = document.getElementById('new_site_local_repo');
        const addSiteGitSshKeyPath = document.getElementById('new_site_git_ssh_key_path');
        const addSiteDisableInit = document.getElementById('new_site_disable_init');
        const addSiteDebug = document.getElementById('new_site_debug');
        const addSitePhpBin = document.getElementById('new_site_php_bin');
        const addSiteComposerBin = document.getElementById('new_site_composer_bin');
        const addSiteTemplate = document.getElementById('new_site_template');
        const addSiteUseJson = document.getElementById('new_site_use_json');
        const addSiteLocalRepoHint = document.getElementById('new_site_local_repo_hint');
        const addSiteGitSshKeyPathHint = document.getElementById('new_site_git_ssh_key_path_hint');
        const addSiteTabButtons = document.querySelectorAll('[data-tab-target]');
        const pageTabButtons = document.querySelectorAll('[data-page-tab-target]');
        const lineNumberedTextareas = document.querySelectorAll('[data-line-numbered]');
        const placeholderTokens = document.querySelectorAll('[data-placeholder]');
        const deploymentProgress = document.getElementById('deployment-progress');
        const deploymentProgressTitle = document.getElementById('deployment-progress-title');
        const deploymentOutput = document.getElementById('deployment-output');
        const deploymentStatusPill = document.getElementById('deployment-status-pill');
        const deploymentClose = document.getElementById('deployment-close');
        const runSiteButtons = document.querySelectorAll('[data-run-site]');
        const initSiteButtons = document.querySelectorAll('[data-init-site]');
        const autoFadeNotices = document.querySelectorAll('[data-auto-fade]');
        const deployUrlButtons = document.querySelectorAll('[data-deploy-url]');
        const deployUrlModal = document.getElementById('deploy-url-modal');
        const deployUrlValue = document.getElementById('deploy-url-value');
        const deployUrlClose = document.getElementById('deploy-url-close');
        const deployUrlCopy = document.getElementById('deploy-url-copy');
        const logoutButton = document.getElementById('logout-button');
        const addSiteCommandFields = {
            init_commands: document.getElementById('new_site_init_commands'),
            pre_commands: document.getElementById('new_site_pre_commands'),
            deploy_commands: document.getElementById('new_site_deploy_commands'),
            post_commands: document.getElementById('new_site_post_commands')
        };
        const editorMode = <?= json_encode($editorMode) ?>;
        const editorSiteName = <?= json_encode($editorSiteName) ?>;
        const editorSiteConfig = <?= json_encode($editorSiteConfig, JSON_UNESCAPED_SLASHES) ?: '{}' ?>;
        const defaultBranch = <?= json_encode((string) ($mergedBaseConfig['branch'] ?? 'master')) ?>;
        const inheritedCommandConfig = <?= json_encode(array_intersect_key($mergedBaseConfig, array_flip(['init_commands', 'pre_commands', 'deploy_commands', 'post_commands'])), JSON_UNESCAPED_SLASHES) ?: '{}' ?>;
        let pathStatusTimer = null;
        let pathStatusRequestId = 0;
        const lineNumberResizeObserver = 'ResizeObserver' in window
            ? new ResizeObserver(entries => {
                for (const entry of entries) {
                    updateLineNumbers(entry.target);
                }
            })
            : null;

        const templates = {
            html: {
                pre_commands: [],
                post_commands: []
            },
            laravel: {
                pre_commands: [
                    '{{php-bin}} {{local-repo}}/artisan down',
                    '{{php-bin}} {{local-repo}}/artisan config:clear'
                ],
                post_commands: [
                    'cd {{local-repo}} && {{php-bin}} {{composer-bin}} install --no-dev --no-progress --prefer-dist --optimize-autoloader',
                    'chmod 755 {{local-repo}}/storage',
                    '{{php-bin}} {{local-repo}}/artisan migrate --force',
                    '{{php-bin}} {{local-repo}}/artisan config:cache',
                    '{{php-bin}} {{local-repo}}/artisan cache:clear',
                    '{{php-bin}} {{local-repo}}/artisan route:cache',
                    '{{php-bin}} {{local-repo}}/artisan up'
                ]
            },
            docker: {
                init_commands: [
                    'docker login  user123 -p password123 registry.yourdomain.com',
                    'docker run --restart always -e VARIABLE1=127.0.0.1:6379 -e VARIALE2=2 -d --network="host" --name my-container registry.yourdomain.com/example/my-image:latest'
                ],
                pre_commands: [
                    'docker login -u user123 -p password123 registry.yourdomain.com',
                    `echo "Previous image hash: " && docker inspect --format={{'index .Image'}} my-container`,
                    'docker stop my-container -t 0'
                ],
                deploy_commands: [
                    'docker rm my-container',
                    'docker image prune -f',
                    'docker pull registry.yourdomain.com/example/my-image:latest'
                ],
                post_commands: [
                    'docker run --restart always -e VARIABLE1=127.0.0.1:6379 -e VARIALE2=2 -d --network="host" --name my-container registry.yourdomain.com/example/my-image:latest',
                    `echo "New image hash: " && docker inspect --format={{'index .Image'}} my-container`
                ]
            }
        };

        function generateRandomKey(length = 24) {
            const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            const bytes = new Uint8Array(length);
            window.crypto.getRandomValues(bytes);

            return Array.from(bytes, byte => alphabet[byte % alphabet.length]).join('');
        }

        function defaultLocalRepo() {
            const appName = addSiteName.value.trim();
            return appName === '' ? '/var/conductor/applications/{appname}' : `/var/conductor/applications/${appName}`;
        }

        function computedLocalRepo() {
            return addSiteLocalRepo.value.trim() === '@conductor' ? defaultLocalRepo() : addSiteLocalRepo.value.trim();
        }

        function computedGitSshKey() {
            if (addSiteGitSshKeyPath.value.trim() === '') {
                return '';
            }

            return `GIT_SSH_COMMAND='ssh -i "${computedGitSshKeyPath()}" -o IdentitiesOnly=yes' `;
        }

        function computedGitSshKeyPath() {
            return addSiteGitSshKeyPath.value.trim() === '@conductor'
                ? `/var/www/.ssh/${addSiteName.value.trim() || '{appname}'}.deploykey`
                : addSiteGitSshKeyPath.value.trim();
        }

        function placeholderValues() {
            return {
                'local-repo': computedLocalRepo(),
                'remote-repo': addSiteRemoteRepo.value.trim(),
                branch: addSiteBranch.value.trim(),
                'git-bin': '/usr/bin/git',
                'git-ssh-key': computedGitSshKey(),
                'php-bin': addSitePhpBin.value,
                'composer-bin': addSiteComposerBin.value.trim(),
                user: 'current web server user'
            };
        }

        function updatePlaceholderLegend() {
            const values = placeholderValues();

            for (const token of placeholderTokens) {
                const value = values[token.dataset.placeholder] || '(empty)';
                token.title = value;
                token.querySelector('.placeholder-value').textContent = value;
            }
        }

        function updateLocalRepoHint() {
            const localRepo = addSiteLocalRepo.value.trim();
            if (localRepo === '') {
                addSiteLocalRepoHint.textContent = '';
                return;
            }

            addSiteLocalRepoHint.textContent = localRepo === '@conductor'
                ? `Computed path: ${defaultLocalRepo()}`
                : `Directory: ${computedLocalRepo()}`;
        }

        function updateGitSshKeyPathHint() {
            const gitSshKeyPath = addSiteGitSshKeyPath.value.trim();
            if (gitSshKeyPath === '') {
                addSiteGitSshKeyPathHint.textContent = '';
                return;
            }

            addSiteGitSshKeyPathHint.textContent = gitSshKeyPath === '@conductor'
                ? `Computed path: ${computedGitSshKeyPath()}`
                : `File: ${computedGitSshKeyPath()}`;
        }

        function setPathStatusHint(element, prefix, path, status) {
            element.textContent = '';
            const wrapper = document.createElement('span');
            wrapper.className = 'path-status';
            if (status === true) {
                wrapper.classList.add('exists');
            } else if (status === false) {
                wrapper.classList.add('missing');
            }
            wrapper.textContent = `${prefix}: ${path}`;
            element.appendChild(wrapper);
        }

        async function fetchPathStatus(type, value, appName) {
            const params = new URLSearchParams({
                path_status: type,
                value,
                app: appName
            });
            const response = await fetch(`?${params.toString()}`, {
                cache: 'no-store',
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`Path check failed with HTTP ${response.status}`);
            }

            return response.json();
        }

        function queuePathStatusChecks() {
            window.clearTimeout(pathStatusTimer);
            pathStatusTimer = window.setTimeout(async () => {
                const requestId = ++pathStatusRequestId;
                const appName = addSiteName.value.trim();
                const localRepo = addSiteLocalRepo.value.trim();
                const gitSshKeyPath = addSiteGitSshKeyPath.value.trim();

                try {
                    if (localRepo !== '') {
                        const localStatus = await fetchPathStatus('local_repo', localRepo, appName);
                        if (requestId === pathStatusRequestId) {
                            setPathStatusHint(addSiteLocalRepoHint, localRepo === '@conductor' ? 'Computed path' : 'Directory', localStatus.path, localStatus.exists);
                        }
                    }

                    if (gitSshKeyPath !== '') {
                        const keyStatus = await fetchPathStatus('git_ssh_key_path', gitSshKeyPath, appName);
                        if (requestId === pathStatusRequestId) {
                            setPathStatusHint(addSiteGitSshKeyPathHint, gitSshKeyPath === '@conductor' ? 'Computed path' : 'File', keyStatus.path, keyStatus.exists);
                        }
                    }
                } catch (error) {
                    if (requestId === pathStatusRequestId) {
                        addSiteLocalRepoHint.title = error.message;
                        addSiteGitSshKeyPathHint.title = error.message;
                    }
                }
            }, 250);
        }

        function commandsFromTextarea(textarea) {
            return textarea.value
                .split(/\r?\n/)
                .map(command => command.trim())
                .filter(command => command !== '');
        }

        function updateLineNumbers(textarea) {
            const gutter = textarea.parentElement?.querySelector('.line-numbers');
            if (!gutter) {
                return;
            }

            const lineCount = Math.max(1, textarea.value.split(/\r?\n/).length);
            gutter.textContent = Array.from({length: lineCount}, (_, index) => String(index + 1)).join('\n');
            gutter.style.height = `${textarea.offsetHeight - 2}px`;
            gutter.scrollTop = textarea.scrollTop;
        }

        function updateAllLineNumbers() {
            for (const textarea of lineNumberedTextareas) {
                updateLineNumbers(textarea);
            }
        }

        function arraysEqual(left, right) {
            if (!Array.isArray(left) || !Array.isArray(right) || left.length !== right.length) {
                return false;
            }

            return left.every((value, index) => value === right[index]);
        }

        function inheritedCommandsFor(key) {
            return Array.isArray(inheritedCommandConfig[key]) ? inheritedCommandConfig[key] : [];
        }

        function commandConfig() {
            const config = {};

            for (const [key, textarea] of Object.entries(addSiteCommandFields)) {
                const commands = commandsFromTextarea(textarea);
                if (commands.length > 0 && !arraysEqual(commands, inheritedCommandsFor(key))) {
                    config[key] = commands;
                }
            }

            return config;
        }

        function writeCommandsWithInherited(config) {
            for (const [key, textarea] of Object.entries(addSiteCommandFields)) {
                const commands = Array.isArray(config[key]) && config[key].length > 0
                    ? config[key]
                    : inheritedCommandsFor(key);

                textarea.value = commands.join('\n');
                updateLineNumbers(textarea);
            }
        }

        function refreshInheritedCommandHighlights() {
            for (const [key, textarea] of Object.entries(addSiteCommandFields)) {
                const isInherited = !addSiteUseJson.checked
                    && commandsFromTextarea(textarea).length > 0
                    && arraysEqual(commandsFromTextarea(textarea), inheritedCommandsFor(key));

                textarea.classList.toggle('inherited-command', isInherited);
                textarea.title = isInherited ? 'Inherited from merged base configuration. It will be omitted when saved unless changed.' : '';
            }
        }

        function ensureSelectOption(select, value) {
            if (!value || Array.from(select.options).some(option => option.value === value)) {
                return;
            }

            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            select.appendChild(option);
        }

        function switchAddSiteTab(targetId) {
            for (const button of addSiteTabButtons) {
                const selected = button.dataset.tabTarget === targetId;
                button.setAttribute('aria-selected', selected ? 'true' : 'false');
            }

            for (const panel of document.querySelectorAll('#add-site-form .tab-panel')) {
                panel.hidden = panel.id !== targetId;
            }
            updateAllLineNumbers();
        }

        function switchPageTab(targetId) {
            for (const button of pageTabButtons) {
                const selected = button.dataset.pageTabTarget === targetId;
                button.setAttribute('aria-selected', selected ? 'true' : 'false');
            }

            for (const panelId of ['applications-panel', 'application-editor-panel', 'base-config-panel']) {
                document.getElementById(panelId).hidden = panelId !== targetId;
            }
        }

        function resetDeploymentProgress(siteName, mode = 'deploy') {
            deploymentProgress.hidden = false;
            deploymentProgressTitle.textContent = mode === 'init' ? `Initialising ${siteName}` : `Running ${siteName}`;
            deploymentOutput.textContent = mode === 'init'
                ? `WARNING: Initialisation requested for ${siteName}. Hooker will run this application's init_commands, which may remove or overwrite files depending on the configuration.\n\n`
                : '';
            deploymentStatusPill.hidden = true;
            deploymentStatusPill.className = 'status-pill';
            deploymentStatusPill.textContent = '';
            deploymentProgress.scrollIntoView({behavior: 'smooth', block: 'start'});
        }

        function finishDeploymentProgress(status) {
            deploymentStatusPill.textContent = `HTTP ${status}`;
            deploymentStatusPill.className = 'status-pill';

            if (status >= 200 && status < 300) {
                deploymentStatusPill.classList.add('success');
            } else if (status >= 400 && status < 600) {
                deploymentStatusPill.classList.add('error');
            }

            deploymentStatusPill.hidden = false;
        }

        async function runDeployment(siteName, mode = 'deploy') {
            resetDeploymentProgress(siteName, mode);

            try {
                const initQuery = mode === 'init' ? '&init=1' : '';
                const response = await fetch(`?stream_deployment=${encodeURIComponent(siteName)}${initQuery}`, {
                    credentials: 'same-origin'
                });
                const status = Number(response.headers.get('X-Hooker-Deployment-Status') || response.status);

                if (!response.body) {
                    deploymentOutput.textContent = await response.text();
                    finishDeploymentProgress(status);
                    return;
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();

                while (true) {
                    const {done, value} = await reader.read();
                    if (done) {
                        break;
                    }

                    deploymentOutput.textContent += decoder.decode(value, {stream: true});
                    deploymentOutput.scrollTop = deploymentOutput.scrollHeight;
                }

                deploymentOutput.textContent += decoder.decode();
                deploymentOutput.scrollTop = deploymentOutput.scrollHeight;
                finishDeploymentProgress(status);
            } catch (error) {
                deploymentOutput.textContent += `\nUnable to run deployment: ${error.message}`;
                finishDeploymentProgress(500);
            }
        }

        function buildBaseApplicationConfig() {
            return {
                key: addSiteKey.value,
                remote_repo: addSiteRemoteRepo.value,
                local_repo: addSiteLocalRepo.value,
                git_ssh_key_path: addSiteGitSshKeyPath.value,
                disable_init: addSiteDisableInit.checked,
                debug: addSiteDebug.checked,
                php_bin: addSitePhpBin.value,
                composer_bin: addSiteComposerBin.value,
                branch: addSiteBranch.value.trim()
            };
        }

        function writeAddSiteJson(config) {
            addSiteJson.value = JSON.stringify(config, null, 4);
            updateLineNumbers(addSiteJson);
        }

        function buildTemplateConfig() {
            const baseApplicationConfig = buildBaseApplicationConfig();

            if (addSiteUseJson.checked) {
                return {
                    ...baseApplicationConfig,
                    use_json: 'true'
                };
            }

            return {
                ...baseApplicationConfig,
                ...commandConfig()
            };
        }

        function refreshAddSiteTemplate() {
            addSiteTemplate.disabled = addSiteUseJson.checked;
            for (const textarea of Object.values(addSiteCommandFields)) {
                textarea.disabled = addSiteUseJson.checked;
            }
            updateLocalRepoHint();
            updateGitSshKeyPathHint();
            queuePathStatusChecks();
            updatePlaceholderLegend();
            refreshInheritedCommandHighlights();
            writeAddSiteJson(buildTemplateConfig());
        }

        function applyTemplate() {
            if (!addSiteUseJson.checked) {
                writeCommandsWithInherited(templates[addSiteTemplate.value]);
            }

            refreshAddSiteTemplate();
        }

        addSiteKeyGenerate.addEventListener('click', () => {
            addSiteKey.value = generateRandomKey();
            refreshAddSiteTemplate();
        });
        addSiteName.addEventListener('input', refreshAddSiteTemplate);
        if (addSiteHookerlessPath) {
            addSiteHookerlessPath.addEventListener('change', () => {
                if (addSiteHookerlessPath.value !== '') {
                    addSiteName.value = addSiteHookerlessPath.value;
                    refreshAddSiteTemplate();
                }
            });
        }
        addSiteKey.addEventListener('input', refreshAddSiteTemplate);
        addSiteRemoteRepo.addEventListener('input', refreshAddSiteTemplate);
        addSiteBranch.addEventListener('input', refreshAddSiteTemplate);
        addSiteLocalRepo.addEventListener('input', refreshAddSiteTemplate);
        addSiteGitSshKeyPath.addEventListener('input', refreshAddSiteTemplate);
        addSiteDisableInit.addEventListener('change', refreshAddSiteTemplate);
        addSiteDebug.addEventListener('change', refreshAddSiteTemplate);
        addSitePhpBin.addEventListener('change', refreshAddSiteTemplate);
        addSiteComposerBin.addEventListener('input', refreshAddSiteTemplate);
        for (const textarea of Object.values(addSiteCommandFields)) {
            textarea.addEventListener('input', refreshAddSiteTemplate);
        }
        for (const textarea of lineNumberedTextareas) {
            textarea.addEventListener('input', () => updateLineNumbers(textarea));
            textarea.addEventListener('scroll', () => updateLineNumbers(textarea));
            lineNumberResizeObserver?.observe(textarea);
        }
        for (const button of addSiteTabButtons) {
            button.addEventListener('click', () => switchAddSiteTab(button.dataset.tabTarget));
        }
        for (const button of pageTabButtons) {
            button.addEventListener('click', () => switchPageTab(button.dataset.pageTabTarget));
        }
        for (const button of runSiteButtons) {
            button.addEventListener('click', () => runDeployment(button.dataset.runSite));
        }
        for (const button of initSiteButtons) {
            button.addEventListener('click', () => runDeployment(button.dataset.initSite, 'init'));
        }
        for (const notice of autoFadeNotices) {
            window.setTimeout(() => {
                notice.classList.add('fading');
                window.setTimeout(() => notice.remove(), 800);
            }, 5000);
        }
        for (const button of deployUrlButtons) {
            button.addEventListener('click', () => {
                deployUrlValue.value = button.dataset.deployUrl;
                deployUrlModal.hidden = false;
                deployUrlValue.focus();
                deployUrlValue.select();
            });
        }
        deployUrlClose.addEventListener('click', () => {
            deployUrlModal.hidden = true;
        });
        deployUrlModal.addEventListener('click', event => {
            if (event.target === deployUrlModal) {
                deployUrlModal.hidden = true;
            }
        });
        deployUrlCopy.addEventListener('click', async () => {
            await navigator.clipboard.writeText(deployUrlValue.value);
            deployUrlCopy.textContent = 'Copied';
            window.setTimeout(() => {
                deployUrlCopy.textContent = 'Copy to clipboard';
            }, 1600);
        });
        deploymentClose.addEventListener('click', () => {
            deploymentProgress.hidden = true;
            deploymentOutput.textContent = '';
            deploymentStatusPill.hidden = true;
        });
        logoutButton.addEventListener('click', async () => {
            try {
                await fetch(`${window.location.pathname}?logout=1`, {
                    cache: 'no-store',
                    headers: {
                        Authorization: `Basic ${btoa('logout:logout')}`
                    }
                });
            } catch (error) {
                // The redirect still moves the user away from the protected admin page.
            }
            window.location.replace('/');
        });
        addSiteTemplate.addEventListener('change', applyTemplate);
        addSiteUseJson.addEventListener('change', refreshAddSiteTemplate);
        function populateEditor(config, siteName) {
            addSiteName.value = siteName || '';
            addSiteKey.value = config.key ?? generateRandomKey();
            addSiteRemoteRepo.value = config.remote_repo ?? '';
            addSiteBranch.value = config.branch ?? defaultBranch;
            addSiteLocalRepo.value = config.local_repo ?? '@conductor';
            addSiteGitSshKeyPath.value = config.git_ssh_key_path ?? '@conductor';
            addSiteDisableInit.checked = config.disable_init ?? true;
            addSiteDebug.checked = config.debug ?? true;
            ensureSelectOption(addSitePhpBin, config.php_bin);
            addSitePhpBin.value = config.php_bin ?? addSitePhpBin.value;
            addSiteComposerBin.value = config.composer_bin ?? '/usr/bin/composer';
            addSiteUseJson.checked = config.use_json === true || config.use_json === 'true';
            writeCommandsWithInherited(config);
            refreshAddSiteTemplate();
        }

        if (editorMode === 'edit') {
            populateEditor(editorSiteConfig, editorSiteName);
        } else {
            addSiteKey.value = generateRandomKey();
            applyTemplate();
        }
        updateAllLineNumbers();
    </script>
</body>
</html>
