<?php

declare(strict_types=1);

namespace Bbs\Core;

/**
 * Minimal beanstalkd client (protocol spec: beanstalkd/doc/protocol.txt).
 *
 * Only the verbs the BBS needs. Tube names are automatically namespaced with
 * config beanstalk.tube_prefix because the daemon is shared with other apps.
 * Web requests use put() only; contrib/worker.php uses the full set.
 */
final class Beanstalk
{
    /** @var resource|null */
    private $sock = null;
    private string $prefix;

    public function __construct(
        private string $host = '127.0.0.1',
        private int $port = 11300,
        private float $timeout = 2.0,
    ) {
        $this->prefix = (string) Config::get('beanstalk.tube_prefix', 'bbs/');
    }

    public static function fromConfig(): self
    {
        return new self(
            (string) Config::get('beanstalk.host', '127.0.0.1'),
            Config::int('beanstalk.port', 11300),
        );
    }

    public static function enabled(): bool
    {
        return Config::bool('beanstalk.enabled', false);
    }

    private function connect(): void
    {
        if (is_resource($this->sock)) {
            return;
        }
        $err = null;
        $errstr = '';
        $errno = 0;
        $sock = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
        if ($sock === false) {
            throw new \RuntimeException("beanstalkd unreachable: $errstr ($errno)");
        }
        stream_set_timeout($sock, (int) $this->timeout);
        $this->sock = $sock;
    }

    private function send(string $cmd): void
    {
        $this->connect();
        fwrite($this->sock, $cmd . "\r\n");
    }

    private function line(): string
    {
        $line = fgets($this->sock);
        if ($line === false) {
            throw new \RuntimeException('beanstalkd: connection closed');
        }
        return rtrim($line, "\r\n");
    }

    private function tube(string $name): string
    {
        return str_starts_with($name, $this->prefix) ? $name : $this->prefix . $name;
    }

    public function useTube(string $tube): void
    {
        $this->send('use ' . $this->tube($tube));
        $this->line();
    }

    public function watch(string $tube): void
    {
        $this->send('watch ' . $this->tube($tube));
        $this->line();
    }

    public function ignore(string $tube): void
    {
        $this->send('ignore ' . $this->tube($tube));
        $this->line();
    }

    /** Enqueue a job. $payload is JSON-encoded. Returns the job id. */
    public function put(string $tube, array $payload, int $priority = 1024, int $delay = 0, int $ttr = 60): int
    {
        $this->useTube($tube);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->send(sprintf('put %d %d %d %d', $priority, $delay, $ttr, strlen($body)));
        fwrite($this->sock, $body . "\r\n");
        $resp = explode(' ', $this->line());
        if (($resp[0] ?? '') !== 'INSERTED') {
            throw new \RuntimeException('beanstalkd put failed: ' . implode(' ', $resp));
        }
        return (int) $resp[1];
    }

    /**
     * Reserve a job (blocking up to $timeout seconds).
     * @return array{id:int,body:array}|null
     */
    public function reserve(int $timeout = 5): ?array
    {
        $this->send('reserve-with-timeout ' . $timeout);
        $resp = explode(' ', $this->line());
        if (($resp[0] ?? '') !== 'RESERVED') {
            return null; // TIMED_OUT / DEADLINE_SOON
        }
        $id   = (int) $resp[1];
        $len  = (int) $resp[2];
        $body = '';
        while (strlen($body) < $len) {
            $chunk = fread($this->sock, $len - strlen($body));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $body .= $chunk;
        }
        fread($this->sock, 2); // trailing CRLF
        $decoded = json_decode($body, true);
        return ['id' => $id, 'body' => is_array($decoded) ? $decoded : ['raw' => $body]];
    }

    public function delete(int $id): void
    {
        $this->send('delete ' . $id);
        $this->line();
    }

    public function release(int $id, int $priority = 1024, int $delay = 0): void
    {
        $this->send(sprintf('release %d %d %d', $id, $priority, $delay));
        $this->line();
    }

    public function bury(int $id, int $priority = 1024): void
    {
        $this->send(sprintf('bury %d %d', $id, $priority));
        $this->line();
    }

    public function close(): void
    {
        if (is_resource($this->sock)) {
            @fwrite($this->sock, "quit\r\n");
            @fclose($this->sock);
            $this->sock = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
