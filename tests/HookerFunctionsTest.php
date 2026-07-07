<?php

use PHPUnit\Framework\TestCase;

class HookerFunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['log'] = [];
        unset($_GET['app']);
    }

    public function testReplaceCommandPlaceHoldersSubstitutesAllTags()
    {
        $config = [
            'local_repo' => '/var/www/site',
            'remote_repo' => 'git@github.com:allebb/site.git',
            'branch' => 'master',
            'user' => 'deployer',
            'git_bin' => '/usr/bin/git',
            'git_ssh_key_path' => '',
            'php_bin' => '/usr/bin/php',
            'composer_bin' => '/usr/bin/composer',
        ];
        $commands = [
            '{{git-bin}} -C {{local-repo}} clone {{remote-repo}} .',
            '{{git-bin}} -C {{local-repo}} checkout {{branch}}',
            '{{php-bin}} {{composer-bin}} install',
        ];

        $result = replaceCommandPlaceHolders($config, $commands);

        $this->assertSame([
            '/usr/bin/git -C /var/www/site clone git@github.com:allebb/site.git .',
            '/usr/bin/git -C /var/www/site checkout master',
            '/usr/bin/php /usr/bin/composer install',
        ], $result);
    }

    public function testReplaceCommandPlaceHoldersIncludesSshKeyExportWhenKeyFileExists()
    {
        $keyfile = tempnam(sys_get_temp_dir(), 'hooker-key');
        $config = [
            'local_repo' => '/var/www/site',
            'remote_repo' => '',
            'branch' => 'master',
            'user' => false,
            'git_bin' => '/usr/bin/git',
            'git_ssh_key_path' => $keyfile,
            'php_bin' => '/usr/bin/php',
            'composer_bin' => '/usr/bin/composer',
        ];

        $result = replaceCommandPlaceHolders($config, ['{{git-ssh-key}}{{git-bin}} pull']);

        unlink($keyfile);

        $this->assertSame(
            "GIT_SSH_COMMAND='ssh -i \"{$keyfile}\" -o IdentitiesOnly=yes' /usr/bin/git pull",
            $result[0]
        );
    }

    public function testBuildLocalRepoPathReturnsPathUnchangedByDefault()
    {
        $this->assertSame('/var/www/site', buildLocalRepoPath('/var/www/site'));
    }

    public function testBuildLocalRepoPathResolvesConductorPlaceholder()
    {
        $_GET['app'] = 'my_app';
        $this->assertSame('/var/conductor/applications/my_app', buildLocalRepoPath('@conductor'));
    }

    public function testBuildLocalRepoPathConductorPlaceholderIsCaseInsensitive()
    {
        $_GET['app'] = 'my_app';
        $this->assertSame('/var/conductor/applications/my_app', buildLocalRepoPath('@Conductor'));
    }

    public function testBuildSshKeyExportVariableReturnsEmptyStringWhenNoKeyFileGiven()
    {
        $this->assertSame('', buildSshKeyExportVariable(''));
    }

    public function testBuildSshKeyExportVariableReturnsEmptyStringWhenKeyFileMissing()
    {
        $this->assertSame('', buildSshKeyExportVariable('/path/does/not/exist'));
    }

    public function testBuildSshKeyExportVariableReturnsExportWhenKeyFileExists()
    {
        $keyfile = tempnam(sys_get_temp_dir(), 'hooker-key');

        $result = buildSshKeyExportVariable($keyfile);

        unlink($keyfile);

        $this->assertSame("GIT_SSH_COMMAND='ssh -i \"{$keyfile}\" -o IdentitiesOnly=yes' ", $result);
    }

    public function testBuildSshKeyExportVariableResolvesConductorPlaceholder()
    {
        $_GET['app'] = 'my_app';
        $this->assertSame('', buildSshKeyExportVariable('@conductor'));
    }

    public function testDebugLogAppendsToGlobalLogWhenOutputEnabled()
    {
        debugLog('hello world', true);

        $this->assertCount(1, $GLOBALS['log']);
        $this->assertStringEndsWith('hello world', $GLOBALS['log'][0]);
    }

    public function testDebugLogDoesNothingWhenOutputDisabled()
    {
        debugLog('hello world', false);

        $this->assertSame([], $GLOBALS['log']);
    }

    public function testOutputAsciiArtHeaderLogsEachLineWhenShown()
    {
        outputAsciiArtHeader(true);

        $this->assertNotEmpty($GLOBALS['log']);
        $this->assertStringContainsString(HOOKER_VERSION, implode(PHP_EOL, $GLOBALS['log']));
    }

    public function testOutputAsciiArtHeaderLogsNothingWhenHidden()
    {
        outputAsciiArtHeader(false);

        $this->assertSame([], $GLOBALS['log']);
    }

    public function testRequestHeaderReturnsDefaultWhenHeaderMissing()
    {
        $this->assertFalse(requestHeader('X-Not-Set'));
        $this->assertSame('fallback', requestHeader('X-Not-Set', 'fallback'));
    }

    public function testRequestHeaderReturnsValueFromServerSuperglobal()
    {
        $_SERVER['HTTP_X_GITHUB_EVENT'] = 'push';

        $this->assertSame('push', requestHeader('X-Github-Event'));

        unset($_SERVER['HTTP_X_GITHUB_EVENT']);
    }

    public function testCheckKeyAuthPassesSilentlyWhenNoKeyConfigured()
    {
        checkKeyAuth(['key' => false, 'debug' => false]);
        $this->addToAssertionCount(1);
    }

    public function testCheckKeyAuthPassesSilentlyWhenProvidedKeyMatches()
    {
        $_GET['key'] = 'secret';
        checkKeyAuth(['key' => 'secret', 'debug' => false]);
        unset($_GET['key']);
        $this->addToAssertionCount(1);
    }

    public function testCheckIpAuthPassesSilentlyWhenWhitelistEmpty()
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.5';
        checkIpAuth(['ip_whitelist' => [], 'debug' => false]);
        $this->addToAssertionCount(1);
    }

    public function testCheckIpAuthPassesSilentlyWhenIpIsWhitelisted()
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        checkIpAuth(['ip_whitelist' => ['127.0.0.1'], 'debug' => false]);
        $this->addToAssertionCount(1);
    }

    public function testLoadLocalHookerConfMergesJsonAndStripsDisallowedOverrideKeys()
    {
        $path = tempnam(sys_get_temp_dir(), 'hooker-json');
        file_put_contents($path, json_encode([
            'branch' => 'should-be-stripped',
            'post_commands' => ['echo hello'],
        ]));

        $result = loadLocalHookerConf($path, [
            'debug' => false,
            'branch' => 'master',
            'post_commands' => [],
        ]);

        unlink($path);

        // 'branch' is a disallowed override: it's dropped entirely from the
        // result so that array_merge()-ing it back onto the base config at
        // the call site in hooker.php leaves the original value untouched.
        $this->assertArrayNotHasKey('branch', $result);
        $this->assertSame(['echo hello'], $result['post_commands']);
    }
}
