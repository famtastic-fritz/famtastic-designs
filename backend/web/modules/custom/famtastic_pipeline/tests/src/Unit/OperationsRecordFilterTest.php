<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\OperationsRecordFilter;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/Service/OperationsRecordFilter.php';

/** Database-backed proof that filters apply across pages and treat input literally. */
final class OperationsRecordFilterTest extends TestCase {

  private Connection $db;

  protected function setUp(): void {
    $options = ['database' => ':memory:', 'prefix' => '', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite', 'driver' => 'sqlite'];
    $this->db = new Connection(Connection::open($options), $options);
    $this->db->query('CREATE TABLE requests (id INTEGER PRIMARY KEY, name TEXT, email TEXT, status TEXT, proof TEXT, changed INTEGER)');
    for ($i = 1; $i <= 61; $i++) {
      $this->db->insert('requests')->fields([
        'id' => $i, 'name' => $i === 61 ? 'Match beyond the first page' : 'Other business',
        'email' => 'request' . $i . '@example.invalid', 'status' => 'submitted',
        'proof' => 'not_started', 'changed' => strtotime('2026-09-14 12:00:00'),
      ])->execute();
    }
  }

  private function matchingIds(array $params): array {
    $query = $this->db->select('requests', 'r')->fields('r', ['id']);
    OperationsRecordFilter::apply($query, $this->db, OperationsRecordFilter::values($params), ['r.name', 'r.email'], ['status' => 'r.status', 'proof' => 'r.proof'], 'r.changed');
    return array_map('intval', $query->orderBy('id')->range(0, 50)->execute()->fetchCol());
  }

  public function testSearchFindsMatchBeyondFirstPage(): void {
    $this->assertSame([61], $this->matchingIds(['q' => 'beyond', 'status' => 'submitted']));
    $this->assertSame([61], $this->matchingIds(['q' => 'request61@']));
    $this->assertSame([], $this->matchingIds(['q' => 'beyond', 'proof' => 'selected']));
  }

  public function testWildcardsAndQuotesAreLiteral(): void {
    $this->db->update('requests')->fields(['name' => "20%_off O'Reilly"])->condition('id', 61)->execute();
    $this->assertSame([61], $this->matchingIds(['q' => '20%_off']));
    $this->assertSame([61], $this->matchingIds(['q' => "O'Reilly"]));
    $this->assertSame([], $this->matchingIds(['q' => "' OR 1=1 --"]));
  }

  public function testThroughDateIncludesEntireDay(): void {
    $this->db->update('requests')->fields(['changed' => strtotime('2026-09-14 23:59:59')])->condition('id', 61)->execute();
    $this->assertSame([61], $this->matchingIds(['q' => 'beyond', 'from' => '2026-09-14', 'to' => '2026-09-14']));
    $this->assertSame([], $this->matchingIds(['q' => 'beyond', 'to' => '2026-09-13']));
    $this->assertSame([], $this->matchingIds(['q' => 'beyond', 'from' => '2026-09-15']));
  }

  public function testMalformedQueryCannotBecomeExecutableFieldsOrDates(): void {
    $values = OperationsRecordFilter::values(['q' => ['bad'], 'status' => ['bad'], 'from' => '2026-02-30', 'to' => 'tomorrow']);
    $this->assertSame(['q' => '', 'status' => '', 'proof' => '', 'from' => '', 'to' => ''], $values);
    $this->assertSame(160, mb_strlen(OperationsRecordFilter::values(['q' => str_repeat('é', 200)])['q']));
  }

}
