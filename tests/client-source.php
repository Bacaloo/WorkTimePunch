<?php
declare(strict_types=1);
namespace OCP { interface IRequest { public function getHeader(string $name): string; } interface IDBConnection {} interface IConfig {} }
namespace OCP\App { interface IAppManager {} }
namespace OCP\DB\QueryBuilder { interface IQueryBuilder { public const PARAM_INT = 1; } }
namespace Psr\Log { interface LoggerInterface {} }
namespace OCA\WorkTimePunch\AppInfo { class Application { public const APP_ID='worktimepunch'; } }
namespace {
require __DIR__.'/../lib/Service/ClientSource.php';
require __DIR__.'/../lib/Service/PunchException.php';
require __DIR__.'/../lib/Service/PunchService.php';
use OCA\WorkTimePunch\Service\ClientSource as C;
use OCA\WorkTimePunch\Service\PunchService;
function check(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
class Request implements \OCP\IRequest {
    public function __construct(private array $headers) {}
    public function getHeader(string $name): string { return $this->headers[strtolower($name)] ?? ''; }
}
$browser=['user-agent'=>'Mozilla/5.0 (Linux; Android 16)','requesttoken'=>'test-token','x-requested-with'=>'XMLHttpRequest','ocs-apirequest'=>'true'];
$cases=[[$browser,C::NEXTCLOUD],[['user-agent'=>'WorkTimePunchWeb/1.0'],C::WEB],
    [['user-agent'=>'Dalvik/2.1.0 (Linux; U; Android 16; SM-S942B Build/TEST)','ocs-apirequest'=>'true'],C::MOBILE],
    [['user-agent'=>'Mozilla/5.0 (Linux; Android 16)'],C::UNKNOWN],[[],C::UNKNOWN],
    [['user-agent'=>'FakeWorkTimePunchWeb/1.0'],C::UNKNOWN],[['user-agent'=>'Dalvik/2.1.0'],C::UNKNOWN]];
foreach ($cases as [$headers,$expected]) { check(C::fromRequest(new Request($headers))===$expected,'Client detection'); }
check(C::description('web','web')==='WorkTimePunch | Client: WEB-GUI','Same client');
check(C::description('nextcloud','mobile')==='WorkTimePunch | Beginn: Nextcloud | Abschluss: mobile APP','Mixed clients');
check(str_contains(C::description(null,'mobile'),'Beginn: nicht ermittelbar'),'Legacy session');
check(!str_contains(C::description('injected text','bad'),'injected'),'Untrusted text not persisted');
class DB implements \OCP\IDBConnection {
    public array $writes=[];
    public function getQueryBuilder(): QB { return new QB($this); }
}
class QB {
    public array $values=[]; public string $operation='';
    public function __construct(private DB $db) {}
    public function insert(string $table): self {$this->operation='insert';return $this;}
    public function update(string $table): self {$this->operation='update';return $this;}
    public function delete(string $table): self {$this->operation='delete';return $this;}
    public function values(array $values): self {$this->values=$values;return $this;}
    public function set(string $name,mixed $value): self {$this->values[$name]=$value;return $this;}
    public function createNamedParameter(mixed $value,mixed ...$rest): mixed {return $value;}
    public function __call(string $name,array $arguments): mixed {return $this;}
    public function executeQuery(): object {return new class {public function fetchOne(): int{return 17;} public function closeCursor(): void{}};}
    public function executeStatement(): int {$this->db->writes[]=[$this->operation,$this->values];return 1;}
}
class Config implements \OCP\IConfig {public function getUserValue(...$args): string{return 'Europe/Berlin';}}
class Apps implements \OCP\App\IAppManager {}
class Logger implements \Psr\Log\LoggerInterface {public function warning(...$args): void{}}
class TimeEntries {public array $entries=[];public bool $fail=false; public function create(...$args): void {if($this->fail)throw new RuntimeException('test failure');$this->entries[]=$args;}}
class OC {public static object $server;}
$entries=new TimeEntries();OC::$server=new class($entries) {public function __construct(private object $service){} public function get(string $id): object{return $this->service;}};
$db=new DB();$service=new PunchService($db,new Apps(),new Config(),new Logger());
$invoke=fn($name,...$args)=>(new ReflectionMethod($service,$name))->invoke($service,...$args);
$employee=['id'=>123,'user_id'=>'fixture'];$now=new DateTimeImmutable('2026-09-12 10:00:00',new DateTimeZone('Europe/Berlin'));
$invoke('kommen',$employee,null,'outside',$now,'web');
check($db->writes[0][1]['segment_client']==='web','Start source persisted');
$session=['id'=>1,'segment_started_at'=>'2026-09-12 10:00:00','work_date'=>'2026-09-12','segment_client'=>'web'];
$invoke('pausenanfang',$employee,$session,'working',$now->modify('+1 hour'),'fixture','mobile');
check($entries->entries[0][6]==='WorkTimePunch | Beginn: WEB-GUI | Abschluss: mobile APP','Pause writes actual sources');
check(!array_key_exists('segment_client',$db->writes[1][1]),'Pause does not replace segment start');
$invoke('pausenende',$employee,$session,'paused',$now->modify('+2 hours'),'nextcloud');
check($db->writes[2][1]['segment_client']==='nextcloud','Resume captures new start client');
$session['segment_client']='nextcloud';
$invoke('gehen',$employee,$session,'working',$now->modify('+3 hours'),'fixture','nextcloud');
check($entries->entries[1][6]==='WorkTimePunch | Client: Nextcloud','Completed note');
check($db->writes[3][0]==='delete','Successful completion removes helper session');
$entries->fail=true;$count=count($db->writes);
try {$invoke('gehen',$employee,$session,'working',$now->modify('+4 hours'),'fixture','mobile');throw new RuntimeException('Expected failure');}
catch (\OCA\WorkTimePunch\Service\PunchException $expected) {}
check(count($db->writes)===$count,'Failed completion keeps session');
echo "PASS: client detection, legacy/mixed provenance, start/pause/resume/completion, failure retention; no real time entries.\n";
}
