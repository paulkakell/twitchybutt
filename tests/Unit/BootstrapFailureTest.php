<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class BootstrapFailureTest extends TestCase
{
    public function test_early_boot_failure_returns_service_unavailable_without_sensitive_details(): void
    {
        $this->checkBootstrap("<?php throw new RuntimeException('BOOT_PRIVATE_SENTINEL token=fixture-only');");
    }

    public function test_missing_autoloader_never_prints_filesystem_diagnostics(): void
    {
        $this->checkBootstrap(null);
    }

    public function test_boot_syntax_failure_does_not_print_source(): void
    {
        $this->checkBootstrap('<?php BOOT_PRIVATE_SENTINEL syntax will fail');
    }

    public function test_real_http_rejected_production_debug_does_not_render_diagnostics(): void
    {
        $this->checkRejectedConfiguration(['APP_ENV' => 'production', 'APP_DEBUG' => 'true', 'SESSION_SECURE_COOKIE' => 'true']);
    }

    public function test_real_http_unimplemented_payment_flag_fails_without_debug_details(): void
    {
        $this->checkRejectedConfiguration(['APP_ENV' => 'local', 'APP_DEBUG' => 'true', 'CMS_PAYMENTS_ENABLED' => 'true']);
    }

    private function checkRejectedConfiguration(array $environment): void
    {
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($listener);
        $address = stream_socket_get_name($listener, false);
        fclose($listener);
        $root = dirname(__DIR__, 2);
        $server = new Process([PHP_BINARY, '-S', $address, '-t', $root.'/public', $root.'/public/index.php'], $root, $environment);
        $server->disableOutput();
        $server->start();
        try {
            $body = false;
            $http_response_header = [];
            $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 2]]);
            for ($attempt = 0; $attempt < 30; $attempt++) {
                $body = @file_get_contents('http://'.$address.'/up', false, $context);
                if ($body !== false) {
                    break;
                }
                usleep(100000);
            }
            self::assertSame('Service unavailable.', $body);
            self::assertStringContainsString('503', $http_response_header[0] ?? '');
            self::assertStringContainsString('no-store', implode("\n", $http_response_header));
            self::assertStringContainsString('X-Content-Type-Options: nosniff', implode("\n", $http_response_header));
        } finally {
            $server->stop(1);
        }
    }

    private function checkBootstrap(?string $autoload): void
    {
        $root = sys_get_temp_dir().'/tb-boot-'.bin2hex(random_bytes(12));
        mkdir($root, 0700);
        mkdir($root.'/public', 0700);
        mkdir($root.'/vendor', 0700);
        copy(dirname(__DIR__, 2).'/public/index.php', $root.'/public/index.php');
        if ($autoload !== null) {
            file_put_contents($root.'/vendor/autoload.php', $autoload);
        }
        try {
            $script = 'require $argv[1]; fwrite(STDERR, "STATUS=".http_response_code());';
            $process = new Process([PHP_BINARY, '-d', 'display_errors=1', '-d', 'log_errors=0', '-r', $script, $root.'/public/index.php']);
            $process->setTimeout(5);
            $process->mustRun();
            self::assertSame('Service unavailable.', $process->getOutput());
            self::assertStringContainsString('STATUS=503', $process->getErrorOutput());
            self::assertStringContainsString('cms.bootstrap.failed', $process->getErrorOutput());
            self::assertStringNotContainsString('BOOT_PRIVATE_SENTINEL', $process->getOutput().$process->getErrorOutput());
            self::assertStringNotContainsString($root, $process->getOutput().$process->getErrorOutput());
        } finally {
            @unlink($root.'/vendor/autoload.php');
            unlink($root.'/public/index.php');
            rmdir($root.'/public');
            rmdir($root.'/vendor');
            rmdir($root);
        }
    }
}
