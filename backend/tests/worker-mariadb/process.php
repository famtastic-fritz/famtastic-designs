<?php
declare(strict_types=1);

final class ProofAssertion extends RuntimeException {}
final class ProofGuard {
  public static float $deadline;
  public static int $checks = 0;
  public static function tick(): void {
    proofNeed(disk_free_space(sys_get_temp_dir()) >= 200 * 1024 * 1024, 'disk_below_200MiB');
    proofNeed(hrtime(TRUE) / 1e9 < self::$deadline, 'suite_timeout');
  }
  public static function check(bool $ok, string $tag): void {
    self::$checks++; if (!$ok) throw new ProofAssertion($tag);
  }
}
/** Pipes carry bounded synthetic facts only. Each child owns exactly one PDO. */
final class RemotePeer {
  private mixed $process;
  private array $pipes;
  private string $buffer = '';
  private array $messages = [];
  private int $sequence = 0;
  private ?int $pending = NULL;
  public array $hello;
  public function __construct(string $config, string $mode, string $hint, string $role) {
    $this->process = proc_open([PHP_BINARY, '-d', 'memory_limit=64M', __DIR__ . '/peer.php', $config, $mode, $hint, $role],
      [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $this->pipes, __DIR__,
      ['FAMTASTIC_BACKEND_VENDOR' => getenv('FAMTASTIC_BACKEND_VENDOR'), 'PATH' => '/nonexistent/famtastic-proof-no-programs', 'TMPDIR' => sys_get_temp_dir()]);
    proofNeed(is_resource($this->process), 'cannot_start_peer');
    try {
      stream_set_blocking($this->pipes[1], FALSE); stream_set_blocking($this->pipes[2], FALSE);
      $this->hello = $this->take(10);
      proofNeed(($this->hello['phase'] ?? '') === 'hello', 'peer_bootstrap_failed');
    } catch (Throwable $e) { $this->stop(); throw $e; }
  }
  private function pump(): void {
    $chunk = stream_get_contents($this->pipes[1]);
    if ($chunk !== FALSE) $this->buffer .= $chunk;
    proofNeed(strlen($this->buffer) < 65536 && count($this->messages) < 8, 'peer_output_exceeded');
    while (($newline = strpos($this->buffer, "\n")) !== FALSE) {
      $this->messages[] = json_decode(substr($this->buffer, 0, $newline), TRUE, flags: JSON_THROW_ON_ERROR);
      $this->buffer = substr($this->buffer, $newline + 1);
    }
    // Fail on any warning/fatal, without echoing environment/driver diagnostics.
    $error = stream_get_contents($this->pipes[2]);
    proofNeed($error === '' || $error === FALSE, 'peer_stderr_not_empty');
  }
  private function take(float $seconds): array {
    $end = hrtime(TRUE) / 1e9 + $seconds;
    do {
      ProofGuard::tick(); $this->pump();
      if ($this->messages) return array_shift($this->messages);
      proofNeed(proc_get_status($this->process)['running'], 'peer_exited_without_response');
      usleep(20000);
    } while (hrtime(TRUE) / 1e9 < $end);
    throw new RuntimeException('peer_response_timeout');
  }
  public function send(string $operation, array $arguments = []): void {
    proofNeed($this->pending === NULL, 'peer_already_busy');
    $this->pending = ++$this->sequence;
    $wire = json_encode(['id' => $this->pending, 'op' => $operation] + $arguments, JSON_THROW_ON_ERROR) . "\n";
    proofNeed(strlen($wire) < 16384 && fwrite($this->pipes[0], $wire) === strlen($wire), 'peer_command_write_failed');
    fflush($this->pipes[0]); $started = $this->take(10);
    proofNeed(($started['phase'] ?? '') === 'started' && $started['id'] === $this->pending, 'peer_start_protocol_failed');
  }
  public function poll(): ?array {
    ProofGuard::tick(); $this->pump();
    if (!$this->messages) return NULL;
    return $this->validate(array_shift($this->messages));
  }
  private function validate(array $reply): array {
    proofNeed(($reply['phase'] ?? '') === 'done' && $reply['id'] === $this->pending, 'peer_done_protocol_failed');
    $this->pending = NULL; return $reply;
  }
  public function receive(): array { return $this->validate($this->take(55)); }
  public function raw(string $operation, array $arguments = []): array {
    $this->send($operation, $arguments); return $this->receive();
  }
  public function call(string $operation, array $arguments = []): mixed {
    return self::value($this->raw($operation, $arguments));
  }
  public static function value(array $reply): mixed {
    proofNeed($reply['ok'] === TRUE, 'unexpected_operation_failure:' . ($reply['error']['message'] ?? 'unknown'));
    return $reply['value'];
  }
  public function stop(): void {
    if (!isset($this->process) || !is_resource($this->process)) return;
    // Only this exact proc_open child. Closing its connection rolls back in MariaDB.
    if (proc_get_status($this->process)['running']) proc_terminate($this->process, 15);
    foreach ($this->pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
    $end = microtime(TRUE) + 1;
    while (proc_get_status($this->process)['running'] && microtime(TRUE) < $end) usleep(10000);
    if (proc_get_status($this->process)['running']) proc_terminate($this->process, 9);
    proc_close($this->process);
  }
  public function __destruct() { $this->stop(); }
}
final class ProofPair {
  public RemotePeer $a;
  public RemotePeer $b;
  public const NOW = 1790010000;
  public function __construct(string $config, string $mode, string $hint, bool $seed = TRUE) {
    $this->a = new RemotePeer($config, $mode, $hint, 'a');
    try {
      $this->b = new RemotePeer($config, $mode, $hint, 'b');
      ProofGuard::check($this->a->hello['connection_id'] !== $this->b->hello['connection_id'], 'connections_not_independent');
      $this->a->call('schema');
      $this->a->call('reset', ['seed' => $seed]);
    } catch (Throwable $e) { $this->close(); throw $e; }
  }
  public function clock(int $now, bool $live = FALSE): void {
    foreach ([$this->a, $this->b] as $peer) $peer->call('clock', ['now' => $now, 'live' => $live]);
  }
  public function close(): void {
    // Stop the holder first; then a blocked peer can terminate without waiting
    // for the 45-second server timeout. No unrelated process IDs are touched.
    if (isset($this->a)) $this->a->stop();
    if (isset($this->b)) $this->b->stop();
  }
  public function __destruct() { $this->close(); }
}
