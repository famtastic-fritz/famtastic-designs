<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
final class LocsBookingWorkerTest extends UnitTestCase {
  private Connection $db;
  private object $dispatcher;
  protected function setUp(): void {
    parent::setUp();
    $opts=['database'=>':memory:','prefix'=>'','namespace'=>'Drupal\\sqlite\\Driver\\Database\\sqlite','driver'=>'sqlite'];
    $this->db=new Connection(Connection::open($opts),$opts);
    foreach ([
      'famtastic_booking_site_owner'=>'site_key TEXT,customer_id INTEGER,organization_id INTEGER,website_request_id INTEGER,status TEXT',
      'famtastic_project_request'=>'id INTEGER,customer_id INTEGER,organization_id INTEGER,commerce_order_id INTEGER,project_id INTEGER,status TEXT',
      'famtastic_membership'=>'customer_id INTEGER,organization_id INTEGER,status TEXT',
      'famtastic_booking_request'=>'public_id TEXT,site_key TEXT',
      'famtastic_notification_outbox'=>'notification_key TEXT,category TEXT,recipient TEXT,status TEXT,available_at INTEGER,claimed_at INTEGER,created INTEGER',
      'famtastic_worker_heartbeat'=>'worker_key TEXT PRIMARY KEY,status TEXT,last_started INTEGER,last_finished INTEGER,next_due INTEGER,processed INTEGER,failed INTEGER,retried INTEGER,last_error TEXT,changed INTEGER',
    ] as $table=>$fields) $this->db->query('CREATE TABLE '.$table.' ('.$fields.')');
    $this->db->insert('famtastic_booking_site_owner')->fields(['site_key'=>'site-dffd4cb9c3aa47fd','customer_id'=>11,'organization_id'=>11,'website_request_id'=>12,'status'=>'active'])->execute();
    $this->db->insert('famtastic_project_request')->fields(['id'=>12,'customer_id'=>11,'organization_id'=>11,'commerce_order_id'=>19,'project_id'=>5,'status'=>'converted'])->execute();
    $this->db->insert('famtastic_membership')->fields(['customer_id'=>11,'organization_id'=>11,'status'=>'active'])->execute();
    $this->dispatcher=new class {public array $keys=[];public int $calls=0;public function dispatchNotifications(int $limit,array $keys):array {$this->calls++;$this->keys=$keys;return ['processed'=>count($keys),'sent'=>count($keys)];}};
    $container=new ContainerBuilder();$container->set('database',$this->db);$container->set('famtastic_pipeline.lifecycle_operations',$this->dispatcher);
    $container->set('famtastic_pipeline.customer_portal',new class {public function customerForId(int $id):array{return ['email'=>'owner@example.test','verified_at'=>1];}});
    \Drupal::setContainer($container);
  }
  private function runWorker():array {
    ob_start();try {include dirname(__DIR__,8).'/scripts/locs-booking-worker.php';$output=ob_get_contents();}finally{ob_end_clean();}return json_decode($output,TRUE,512,JSON_THROW_ON_ERROR);
  }
  private function add(int $id,string $site='site-dffd4cb9c3aa47fd',string $status='queued',int $due=0,string $recipient='owner@example.test'):string {
    $uuid=sprintf('12345678-1234-1234-1234-%012d',$id);$key='booking-request:'.$uuid.':owner';
    $this->db->insert('famtastic_booking_request')->fields(['public_id'=>$uuid,'site_key'=>$site])->execute();
    $this->db->insert('famtastic_notification_outbox')->fields(['notification_key'=>$key,'category'=>'transactional','recipient'=>$recipient,'status'=>$status,'available_at'=>$due,'claimed_at'=>time()-1900,'created'=>$id])->execute();return $key;
  }
  public function testOnlyExactSiteDueOwnerKeysAndExpiredClaimsAreSelected():void {
    $a=$this->add(1);$b=$this->add(2,'site-dffd4cb9c3aa47fd','dispatching');$this->add(3,'other-site');$this->add(4,'site-dffd4cb9c3aa47fd','retry',time()+1000);$this->add(5,'site-dffd4cb9c3aa47fd','queued',0,'other@example.test');
    $r=$this->runWorker();self::assertSame([$a,$b],$this->dispatcher->keys);self::assertSame(2,$r['selected']);self::assertStringNotContainsString('owner@example.test',json_encode($r));
  }
  public function testEmptyQueueStillWritesDedicatedHeartbeat():void {
    $r=$this->runWorker();self::assertSame([],$this->dispatcher->keys);self::assertSame(0,$r['processed']);self::assertSame('healthy',$this->db->select('famtastic_worker_heartbeat','h')->fields('h',['status'])->condition('worker_key','locs_booking_owner_dispatch')->execute()->fetchField());
  }
  public function testScopeFailureWritesErrorHeartbeatAndNeverDispatches():void {
    $this->db->update('famtastic_membership')->fields(['status'=>'removed'])->execute();
    try {$this->runWorker();self::fail('Expected guard failure');}catch(\RuntimeException){}
    self::assertSame(0,$this->dispatcher->calls);self::assertSame('degraded',$this->db->select('famtastic_worker_heartbeat','h')->fields('h',['status'])->execute()->fetchField());
  }
  public function testSelectionIsLimitedTo25():void {for($i=1;$i<=30;$i++)$this->add($i);self::assertSame(25,$this->runWorker()['selected']);}
}
