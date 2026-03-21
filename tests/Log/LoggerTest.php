<?php

namespace SimplyIT\Fattura24SDK\Tests\Log;

use PHPUnit\Framework\TestCase;
use SimplyIT\Fattura24SDK\Log\FileLogger;
use SimplyIT\Fattura24SDK\Log\LogLevel;
use SimplyIT\Fattura24SDK\Log\NullLogger;

class LoggerTest extends TestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/fattura24-sdk-tests-' . uniqid();
        mkdir($this->logDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Pulizia completa della cartella temporanea dopo ogni test
        foreach (glob($this->logDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->logDir);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function logger(int $minLevel = LogLevel::DEBUG): FileLogger
    {
        return new FileLogger($this->logDir, $minLevel);
    }

    private function todayFile(): string
    {
        return $this->logDir . '/sdk-' . date('Y-m-d') . '.log';
    }

    private function readLog(): string
    {
        return file_exists($this->todayFile())
            ? file_get_contents($this->todayFile())
            : '';
    }

    // -------------------------------------------------------------------------
    // NullLogger
    // -------------------------------------------------------------------------

    public function testNullLoggerImplementsInterface(): void
    {
        $this->assertInstanceOf(
            \SimplyIT\Fattura24SDK\Log\LoggerInterface::class,
            new NullLogger()
        );
    }

    public function testNullLoggerDoesNothing(): void
    {
        $logger = new NullLogger();

        // Nessuna eccezione, nessun output, nessun file creato
        $logger->debug('debug');
        $logger->info('info');
        $logger->warning('warning');
        $logger->error('error');

        $this->assertTrue(true); // se siamo qui, non ha fatto danni
    }

    // -------------------------------------------------------------------------
    // FileLogger — creazione directory
    // -------------------------------------------------------------------------

    public function testCreatesDirectoryIfNotExists(): void
    {
        $nested = $this->logDir . '/a/b/c';
        new FileLogger($nested);
        $this->assertDirectoryExists($nested);
        // Pulizia
        rmdir($nested);
        rmdir($this->logDir . '/a/b');
        rmdir($this->logDir . '/a');
    }

    // -------------------------------------------------------------------------
    // FileLogger — scrittura
    // -------------------------------------------------------------------------

    public function testDebugWritesFile(): void
    {
        $this->logger()->debug('messaggio debug');
        $this->assertFileExists($this->todayFile());
    }

    public function testLogLineContainsTimestamp(): void
    {
        $this->logger()->info('con timestamp');
        $this->assertMatchesRegularExpression(
            '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/',
            $this->readLog()
        );
    }

    public function testLogLineContainsLevel(): void
    {
        $this->logger()->warning('attenzione');
        $this->assertStringContainsString('[WARNING]', $this->readLog());
    }

    public function testLogLineContainsMessage(): void
    {
        $this->logger()->info('testo del messaggio');
        $this->assertStringContainsString('testo del messaggio', $this->readLog());
    }

    public function testEachLevelWritesCorrectLabel(): void
    {
        $logger = $this->logger();
        $logger->debug('d');
        $logger->info('i');
        $logger->warning('w');
        $logger->error('e');

        $content = $this->readLog();
        $this->assertStringContainsString('[DEBUG]',   $content);
        $this->assertStringContainsString('[INFO]',    $content);
        $this->assertStringContainsString('[WARNING]', $content);
        $this->assertStringContainsString('[ERROR]',   $content);
    }

    public function testContextIsWrittenAsJson(): void
    {
        $this->logger()->info('con context', ['foo' => 'bar', 'n' => 42]);
        $this->assertStringContainsString('"foo":"bar"', $this->readLog());
        $this->assertStringContainsString('"n":42',      $this->readLog());
    }

    public function testEmptyContextProducesNoJsonInLine(): void
    {
        $this->logger()->info('senza context');
        // Senza context non deve esserci JSON allegato
        $line = trim($this->readLog());
        $this->assertStringNotContainsString('{', $line);
    }

    public function testMultipleCallsAppendLines(): void
    {
        $logger = $this->logger();
        $logger->info('prima riga');
        $logger->info('seconda riga');

        $content = $this->readLog();
        $this->assertStringContainsString('prima riga',  $content);
        $this->assertStringContainsString('seconda riga', $content);
        $this->assertSame(2, substr_count($content, PHP_EOL));
    }

    public function testLogFileNameContainsDate(): void
    {
        $this->logger()->info('data nel nome');
        $expected = 'sdk-' . date('Y-m-d') . '.log';
        $this->assertFileExists($this->logDir . '/' . $expected);
    }

    // -------------------------------------------------------------------------
    // FileLogger — filtraggio per livello
    // -------------------------------------------------------------------------

    public function testDebugSuppressedWhenMinLevelIsInfo(): void
    {
        $this->logger(LogLevel::INFO)->debug('non deve apparire');
        $this->assertStringNotContainsString('non deve apparire', $this->readLog());
    }

    public function testInfoWrittenWhenMinLevelIsInfo(): void
    {
        $this->logger(LogLevel::INFO)->info('deve apparire');
        $this->assertStringContainsString('deve apparire', $this->readLog());
    }

    public function testOnlyErrorWrittenWhenMinLevelIsError(): void
    {
        $logger = $this->logger(LogLevel::ERROR);
        $logger->debug('debug');
        $logger->info('info');
        $logger->warning('warning');
        $logger->error('errore finale');

        $content = $this->readLog();
        $this->assertStringNotContainsString('debug',   $content);
        $this->assertStringNotContainsString('info',    $content);
        $this->assertStringNotContainsString('warning', $content);
        $this->assertStringContainsString('errore finale', $content);
    }

    public function testNothingWrittenWhenAllBelowMinLevel(): void
    {
        $logger = $this->logger(LogLevel::ERROR);
        $logger->debug('d');
        $logger->info('i');
        $logger->warning('w');

        $this->assertFileDoesNotExist($this->todayFile());
    }

    // -------------------------------------------------------------------------
    // FileLogger — redazione API key
    // -------------------------------------------------------------------------

    public function testApiKeyInQueryStringIsRedacted(): void
    {
        $this->logger()->debug('chiamata', ['body' => 'apiKey=CHIAVE-SEGRETA-123&xml=test']);
        $content = $this->readLog();
        $this->assertStringNotContainsString('CHIAVE-SEGRETA-123', $content);
        $this->assertStringContainsString('[REDACTED]', $content);
    }

    public function testApiKeyContextValueIsRedacted(): void
    {
        $this->logger()->debug('init', ['apiKey' => 'CHIAVE-SEGRETA-456']);
        $content = $this->readLog();
        $this->assertStringNotContainsString('CHIAVE-SEGRETA-456', $content);
        $this->assertStringContainsString('[REDACTED]', $content);
    }

    public function testPasswordContextValueIsRedacted(): void
    {
        $this->logger()->info('credenziali', ['password' => 'supersecret']);
        $content = $this->readLog();
        $this->assertStringNotContainsString('supersecret', $content);
        $this->assertStringContainsString('[REDACTED]', $content);
    }

    public function testTokenContextValueIsRedacted(): void
    {
        $this->logger()->info('auth', ['token' => 'bearer-xyz']);
        $content = $this->readLog();
        $this->assertStringNotContainsString('bearer-xyz', $content);
        $this->assertStringContainsString('[REDACTED]', $content);
    }

    public function testNonSensitiveContextIsNotRedacted(): void
    {
        $this->logger()->info('risposta', ['docId' => 'DOC-123', 'duration' => 42.5]);
        $content = $this->readLog();
        $this->assertStringContainsString('DOC-123', $content);
        $this->assertStringContainsString('42.5',    $content);
    }

    public function testRedactionIsCaseInsensitiveOnKeys(): void
    {
        $this->logger()->info('varie', ['ApiKey' => 'SECRET', 'API_KEY' => 'SECRET2']);
        $content = $this->readLog();
        $this->assertStringNotContainsString('SECRET', $content);
    }

    public function testNestedContextArrayIsRedacted(): void
    {
        $this->logger()->debug('nested', ['auth' => ['apiKey' => 'NESTED-SECRET']]);
        $content = $this->readLog();
        $this->assertStringNotContainsString('NESTED-SECRET', $content);
        $this->assertStringContainsString('[REDACTED]', $content);
    }

    // -------------------------------------------------------------------------
    // FileLogger — clearAll
    // -------------------------------------------------------------------------

    public function testClearAllDeletesAllLogFiles(): void
    {
        $logger = $this->logger();
        $logger->info('riga 1');

        // Creo artificialmente un secondo file di log "passato"
        file_put_contents($this->logDir . '/sdk-2020-01-01.log', 'vecchio log');

        $logger->clearAll();

        $this->assertFileDoesNotExist($this->todayFile());
        $this->assertFileDoesNotExist($this->logDir . '/sdk-2020-01-01.log');
    }

    public function testClearAllDoesNotDeleteNonLogFiles(): void
    {
        $other = $this->logDir . '/non-log.txt';
        file_put_contents($other, 'dati');

        $this->logger()->clearAll();

        $this->assertFileExists($other);
    }

    public function testClearAllOnEmptyDirectoryDoesNotThrow(): void
    {
        $this->logger()->clearAll();
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // FileLogger — clearOlderThan
    // -------------------------------------------------------------------------

    public function testClearOlderThanDeletesOldFiles(): void
    {
        $old = $this->logDir . '/sdk-2000-06-15.log';
        file_put_contents($old, 'vecchissimo');

        $this->logger()->clearOlderThan(30);

        $this->assertFileDoesNotExist($old);
    }

    public function testClearOlderThanKeepsTodayFile(): void
    {
        $logger = $this->logger();
        $logger->info('oggi');

        $logger->clearOlderThan(0);

        $this->assertFileExists($this->todayFile());
    }

    public function testClearOlderThanKeepsRecentFile(): void
    {
        // File di ieri: deve sopravvivere con retention di 30 giorni
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $recent    = $this->logDir . "/sdk-{$yesterday}.log";
        file_put_contents($recent, 'recente');

        $this->logger()->clearOlderThan(30);

        $this->assertFileExists($recent);
    }

    public function testClearOlderThanDoesNotDeleteNonLogFiles(): void
    {
        $other = $this->logDir . '/altri-dati.txt';
        file_put_contents($other, 'dati');

        $this->logger()->clearOlderThan(0);

        $this->assertFileExists($other);
    }

    public function testClearOlderThanOnEmptyDirectoryDoesNotThrow(): void
    {
        $this->logger()->clearOlderThan(30);
        $this->assertTrue(true);
    }
}