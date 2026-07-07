<?php

use PHPUnit\Framework\TestCase;

/**
 * Drives the real hooker.php script as an actual HTTP request (via PHP's
 * built-in web server) so that exit()-terminated code paths (auth
 * rejection, branch mismatches, etc.) can be exercised end-to-end exactly
 * as they run in production.
 */
class HookerIntegrationTest extends TestCase
{
    private static string $docroot;
    private static string $repoDir;
    private static int $port;
    private static $serverProcess;

    public static function setUpBeforeClass(): void
    {
        self::$port = 8000 + random_int(1000, 8999);
        self::$docroot = sys_get_temp_dir() . '/hooker-it-' . uniqid();
        self::$repoDir = self::$docroot . '/repo';
        mkdir(self::$repoDir, 0777, true);

        copy(__DIR__ . '/../hooker.php', self::$docroot . '/hooker.php');

        file_put_contents(self::$docroot . '/hooker.conf.php', '<?php return ' . var_export([
            'ip_whitelist' => [],
            'git_bin' => trim(shell_exec('command -v git')),
            'php_bin' => PHP_BINARY,
            'composer_bin' => trim(shell_exec('command -v composer') ?: PHP_BINARY),
            'sites' => [
                'basic_app' => [
                    'key' => false,
                    'local_repo' => self::$repoDir,
                    'remote_repo' => '',
                    'branch' => 'master',
                ],
                'keyed_app' => [
                    'key' => 'super-secret',
                    'local_repo' => self::$repoDir,
                    'remote_repo' => '',
                    'branch' => 'master',
                ],
                'github_app' => [
                    'key' => false,
                    'local_repo' => self::$repoDir,
                    'remote_repo' => '',
                    'branch' => 'master',
                    'is_github' => true,
                ],
                'no_init_app' => [
                    'key' => false,
                    'local_repo' => self::$repoDir,
                    'remote_repo' => '',
                    'branch' => 'master',
                    'disable_init' => true,
                ],
            ],
        ], true) . ';', LOCK_EX);

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        self::$serverProcess = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, '-t', self::$docroot],
            $descriptors,
            $pipes
        );
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        self::waitForServer();
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }
        exec('rm -rf ' . escapeshellarg(self::$docroot));
    }

    private static function waitForServer(): void
    {
        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            $conn = @fsockopen('127.0.0.1', self::$port, $errno, $errstr, 0.2);
            if ($conn) {
                fclose($conn);
                return;
            }
            usleep(50000);
        }
        self::fail('Built-in PHP server did not start in time.');
    }

    /**
     * @return array{status:int, body:string}
     */
    private function request(string $query, array $headers = [], string $body = ''): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => $body === '' ? 'GET' : 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]);

        $result = @file_get_contents(
            'http://127.0.0.1:' . self::$port . '/hooker.php?' . $query,
            false,
            $context
        );

        $status = 500;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
                $status = (int) $m[1];
            }
        }

        return ['status' => $status, 'body' => (string) $result];
    }

    public function testPingRespondsWithPong()
    {
        $response = $this->request('ping=1');

        $this->assertSame(200, $response['status']);
        $this->assertSame('PONG', $response['body']);
    }

    public function testUnknownApplicationReturns404()
    {
        $response = $this->request('app=does_not_exist');

        $this->assertSame(404, $response['status']);
    }

    public function testMissingKeyReturns401()
    {
        $response = $this->request('app=keyed_app');

        $this->assertSame(401, $response['status']);
    }

    public function testWrongKeyReturns401()
    {
        $response = $this->request('app=keyed_app&key=wrong');

        $this->assertSame(401, $response['status']);
    }

    public function testCorrectKeyIsAcceptedAndDeploys()
    {
        $response = $this->request('app=keyed_app&key=super-secret');

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('done!', $response['body']);
    }

    public function testBasicAppDeploysSuccessfully()
    {
        $response = $this->request('app=basic_app');

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('done!', $response['body']);
    }

    public function testDisableInitForbidsInitRequest()
    {
        $response = $this->request('app=no_init_app&init=1');

        $this->assertSame(403, $response['status']);
    }

    public function testInitRunsInitCommandsSuccessfully()
    {
        $response = $this->request('app=basic_app&init=1');

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('initialisation has been completed', $response['body']);
    }

    public function testGithubHookSkipsNonDeployEvent()
    {
        $response = $this->request('app=github_app', [
            'X-Github-Event: ping',
            'Content-Type: text/plain',
        ]);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('skipping the deployment', $response['body']);
    }

    public function testGithubHookSkipsBranchMismatch()
    {
        $payload = json_encode(['ref' => 'refs/heads/develop']);
        $response = $this->request('app=github_app', [
            'X-Github-Event: push',
            'Content-Type: application/json',
        ], $payload);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Branch matching failed', $response['body']);
    }

    public function testGithubHookDeploysOnMatchingBranchPush()
    {
        $payload = json_encode(['ref' => 'refs/heads/master']);
        $response = $this->request('app=github_app', [
            'X-Github-Event: push',
            'Content-Type: application/json',
        ], $payload);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('done!', $response['body']);
    }
}
