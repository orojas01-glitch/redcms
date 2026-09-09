<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/includes/runtime_config_helpers.php';
require_once dirname(__DIR__).'/includes/admin_password_helpers.php';
$name=(string)red_config_value('DBNAME',['RED_DB_NAME'],'');
if (!preg_match('/\Aredcms_acceptance_[A-Za-z0-9_]+\z/',$name)) { fwrite(STDERR,"Disposable acceptance database required.\n"); exit(64); }
$count=0;
function migration_check($ok,$message){global $count;if(!$ok)throw new RuntimeException($message);$count++;}
$db=mysqli_connect(red_config_value('DBHOST',['RED_DB_HOST'],'localhost'),red_config_value('DBUSER',['RED_DB_USER'],''),red_config_value('DBPASS',['RED_DB_PASS'],''),$name);
mysqli_set_charset($db,'utf8mb4');
$ids='2147000830,2147000831';
migration_check((int)mysqli_fetch_row(mysqli_query($db,"SELECT COUNT(*) FROM RED_Admin WHERE RecordID IN ($ids)"))[0]===0,'Fixture IDs must be unused');
function run_password_command(array $args, ?string $code = null): array {
    $runtime=getenv('FRANKENPHP_BIN');
    if (!$runtime) throw new RuntimeException('FrankenPHP launcher required');
    $command=$code === null ? array_merge([$runtime,'php-cli',__DIR__.'/admin-password-migration.php'],$args) : [$runtime,'php-cli','-r',$code];
    $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    return ['exit'=>proc_close($process),'json'=>json_decode($stdout,true),'output'=>$stdout.$stderr];
}
$plain='fixture-pass';$ambiguous='$1$obsolete';
try {
    $insert=mysqli_prepare($db,"INSERT INTO RED_Admin (RecordID,Username,Password,Administrator,Alias,AdminType,AdminComponents,AdminTools,Email,Contact_Form,Contact_Form_Pref,Donation_Form,Donation_Form_Pref) VALUES (?, ?, ?, 'Admin', 'HashQA', 'guest', '', '', '', 'N','to','N','to')");
    foreach ([2147000830=>$plain,2147000831=>$ambiguous] as $id=>$password){$username='hashqa_'.$id;mysqli_stmt_bind_param($insert,'iss',$id,$username,$password);mysqli_stmt_execute($insert);}mysqli_stmt_close($insert);
    $before=mysqli_fetch_row(mysqli_query($db,'SELECT Password FROM RED_Admin WHERE RecordID=2147000830'))[0];
    $guardCode = 'require_once ' . var_export(dirname(__DIR__).'/includes/bootstrap.php', true) . '; red_start_session(); $_SESSION=["alias"=>"HashQA","AdminRecordID"=>2147000830,"AdminPasswordFingerprint"=>hash("sha256","fixture-pass"),"AdminSessionIssuedAt"=>time(),"AdminSessionLastSeen"=>time()]; red_require_admin(); echo "unexpected";';
    migration_check(run_password_command([], $guardCode)['output'] === 'no', 'Previously issued plaintext-backed session is refused');
    $audit=run_password_command([]);
    migration_check($audit['exit']===2&&$audit['json']['counts']['legacy']===1&&$audit['json']['counts']['resetRequired']===1,'Audit reports readiness counts');
    migration_check(!str_contains($audit['output'],$plain)&&!str_contains($audit['output'],$ambiguous)&&!str_contains($audit['output'],'hashqa_'),'Audit discloses no values or identities');
    foreach ([['--apply'],['--apply','--confirm-database=wrong','--confirm-legacy-plaintext'],['--apply','--confirm-database='.$name]] as $args) migration_check(run_password_command($args)['exit']===1,'Missing/wrong confirmation refused');
    $args=['--apply','--confirm-database='.$name,'--confirm-legacy-plaintext'];
    migration_check(run_password_command($args)['exit']===1,'Ambiguous rows refuse the entire conversion');
    migration_check(mysqli_fetch_row(mysqli_query($db,'SELECT Password FROM RED_Admin WHERE RecordID=2147000830'))[0]===$before,'No partial conversion');
    $reset=password_hash('reset-pass',PASSWORD_DEFAULT);
    $stmt=mysqli_prepare($db,'UPDATE RED_Admin SET Password=? WHERE RecordID=2147000831');mysqli_stmt_bind_param($stmt,'s',$reset);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
    $applied=run_password_command($args);
    migration_check($applied['exit']===0&&$applied['json']['converted']===1&&$applied['json']['ready'],'Explicit conversion succeeds');
    $converted=mysqli_fetch_row(mysqli_query($db,'SELECT Password FROM RED_Admin WHERE RecordID=2147000830'))[0];
    migration_check(red_admin_password_verify($plain,$converted),'Conversion preserves the existing password');
    migration_check(run_password_command($args)['json']['converted']===0,'Conversion rerun is a no-op');
    migration_check(red_update_admin_password_hash($db,2147000830,$plain,$plain)==='','Runtime rehash refuses plaintext input');
    $stmt=mysqli_prepare($db,'UPDATE RED_Admin SET Password=? WHERE RecordID=2147000830');mysqli_stmt_bind_param($stmt,'s',$reset);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
    migration_check(red_update_admin_password_hash($db,2147000830,$plain,$converted)==='','Stale rehash cannot overwrite an intervening reset');
    migration_check(mysqli_fetch_row(mysqli_query($db,'SELECT Password FROM RED_Admin WHERE RecordID=2147000830'))[0]===$reset,'Reset hash preserved byte-for-byte');
    echo "Password migration/rehash database checks passed: $count assertions.\n";
} finally {mysqli_query($db,"DELETE FROM RED_Admin WHERE RecordID IN ($ids)");mysqli_close($db);}
