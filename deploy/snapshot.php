<?php
/** Private database + media transfer. Never place the generated archive in public/. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);
putenv('COPYFILE_DISABLE=1');
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function runSnapshotProcess(array $command, ?string $input=null, ?string $output=null): void {
    $handles=[0=>$input?['file',$input,'r']:['file','/dev/null','r'],1=>$output?['file',$output,'w']:['file','/dev/null','w'],2=>['pipe','w']];
    $process=proc_open($command,$handles,$pipes,base_path());
    if(!is_resource($process))throw new RuntimeException('Impossible de lancer '.$command[0]);
    $error=stream_get_contents($pipes[2]);fclose($pipes[2]);
    if(proc_close($process)!==0)throw new RuntimeException($command[0].' : '.substr(trim($error),0,1500));
}
function snapshotCounts(): array {
    $counts=[];$transient=['sessions','cache','cache_locks','jobs','failed_jobs','job_batches','password_reset_tokens'];
    foreach(DB::select('SHOW FULL TABLES') as $row){$entry=array_values((array)$row);if($entry[1]!=='BASE TABLE'||in_array($entry[0],$transient,true))continue;
        $counts[$entry[0]]=DB::table($entry[0])->count();}
    ksort($counts);return $counts;
}
function validateSnapshotTar(string $archive, bool $assets=false): void {
    $process=proc_open(['tar','-tzf',$archive],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $listing=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0)throw new RuntimeException('Archive invalide : '.substr($error,0,200));
    $process=proc_open(['tar','-tvzf',$archive],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $types=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0)throw new RuntimeException('Archive illisible.');
    foreach(explode("\n",trim($types)) as $line){if($line!==''&&!in_array($line[0],['-','d'],true))throw new RuntimeException('Les liens et fichiers spéciaux sont interdits dans le transfert.');}
    foreach(explode("\n",trim($listing)) as $path){
        if(str_starts_with($path,'/')||in_array('..',explode('/',$path),true))throw new RuntimeException('Chemin interdit dans l’archive.');
        if($assets){if(!preg_match('~^(public/uploads|storage/app/public)(/|$)~',$path))throw new RuntimeException('Média hors des dossiers prévus.');}
        elseif(!in_array($path,['database.sql','assets.tar.gz','manifest.json'],true))throw new RuntimeException('Fichier inattendu : '.$path);
    }
}
function snapshotAssets(string $target): void {
    $paths=[];
    foreach(['public/uploads','storage/app/public'] as $path){
        if(!is_dir(base_path($path)))continue;
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($path),FilesystemIterator::SKIP_DOTS)) as $file){if($file->isLink())throw new RuntimeException('Lien symbolique non pris en charge : '.$file->getPathname());}
        $paths[]=$path;
    }
    if(!$paths)throw new RuntimeException('Aucun dossier média trouvé.');
    runSnapshotProcess(array_merge(['tar','-czf',$target,'-C',base_path()],$paths));
}
$mode=$argv[1]??'';$target=$argv[2]??'';$cnf=null;$maintenance=false;$backup=null;
try {
    if(!in_array($mode,['export','restore'],true)||!$target)throw new RuntimeException('Usage : php deploy/snapshot.php export|restore /chemin/archive.tar.gz');
    if($mode==='restore' && (!function_exists('posix_geteuid')||posix_geteuid()!==0))throw new RuntimeException('La restauration sur VPS doit être exécutée en root.');
    $db=DB::connection()->getConfig();
    if(($db['driver']??'')!=='mysql')throw new RuntimeException('Cette procédure nécessite MySQL.');
    $work=storage_path('app/deployment/'.date('Ymd-His').'-'.bin2hex(random_bytes(4)));
    if(!mkdir($work,0700,true))throw new RuntimeException('Dossier de transfert inaccessible.');
    $cnf=$work.'/mysql.cnf';
    $quote=fn($value)=>'"'.addcslashes((string)$value,"\\\"\n\r").'"';
    $options="[client]\nhost=".$quote($db['host'])."\nport=".(int)($db['port']??3306)."\nuser=".$quote($db['username'])."\npassword=".$quote($db['password'])."\n";
    if(!empty($db['unix_socket']))$options.='socket='.$quote($db['unix_socket'])."\n";
    file_put_contents($cnf,$options);chmod($cnf,0600);
    $database=$db['database'];
    $dump=function(string $path)use($cnf,$database){
        runSnapshotProcess(['mysqldump','--defaults-extra-file='.$cnf,'--single-transaction','--no-tablespaces','--set-gtid-purged=OFF','--column-statistics=0','--default-character-set=utf8mb4',$database],null,$path);
        // Views must work with the destination account, without a foreign root definer.
        $sql=file_get_contents($path);
        $sql=preg_replace('/DEFINER=`[^`]*`@`[^`]*`\s+SQL SECURITY DEFINER/','SQL SECURITY INVOKER',$sql);
        file_put_contents($path,$sql);
    };
    $import=fn(string $path)=>runSnapshotProcess(['mysql','--defaults-extra-file='.$cnf,'--default-character-set=utf8mb4',$database],$path);
    if($mode==='export'){
        $dump($work.'/database.sql');snapshotAssets($work.'/assets.tar.gz');
        $manifest=['format'=>1,'created_at'=>date(DATE_ATOM),'counts'=>snapshotCounts(),'database_sha256'=>hash_file('sha256',$work.'/database.sql'),'assets_sha256'=>hash_file('sha256',$work.'/assets.tar.gz')];
        file_put_contents($work.'/manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        runSnapshotProcess(['tar','-czf',$target,'-C',$work,'database.sql','assets.tar.gz','manifest.json']);chmod($target,0600);
        echo "Archive privée prête : $target\n";
        echo 'SHA256 : '.hash_file('sha256',$target)."\n";
    }else{
        if(!is_file($target))throw new RuntimeException('Archive introuvable.');
        validateSnapshotTar($target);
        runSnapshotProcess(['tar','-xzf',$target,'-C',$work]);
        $manifest=json_decode(file_get_contents($work.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
        if(($manifest['format']??null)!==1)throw new RuntimeException('Format non pris en charge.');
        foreach(['database.sql'=>'database_sha256','assets.tar.gz'=>'assets_sha256'] as $file=>$key){if(!hash_equals($manifest[$key]??'',hash_file('sha256',$work.'/'.$file)))throw new RuntimeException('Archive altérée : '.$file);}
        validateSnapshotTar($work.'/assets.tar.gz',true);
        $remoteAdmin=DB::table('users')->where('role',1)->where('del_status','Live')->first(['email','password']);
        if(!$remoteAdmin)throw new RuntimeException('Administrateur VPS introuvable ; import interrompu.');
        $backup='/root/inventory-backups/'.date('Ymd-His').'-'.bin2hex(random_bytes(3));mkdir($backup,0700,true);
        Artisan::call('down');$maintenance=true;
        $dump($backup.'/database.sql');snapshotAssets($backup.'/assets.tar.gz');
        file_put_contents($backup.'/environment',file_get_contents(base_path('.env')));
        file_put_contents($backup.'/READY','Sauvegarde complète avant import');
        echo "Sauvegarde VPS : $backup\n";
        $import($work.'/database.sql');
        $localAdmin=DB::table('users')->where('role',1)->where('del_status','Live')->first(['id']);
        if(!$localAdmin)throw new RuntimeException('Administrateur absent de la base importée.');
        DB::table('users')->where('id',$localAdmin->id)->update(['email'=>$remoteAdmin->email,'password'=>$remoteAdmin->password,'remember_token'=>null]);
        foreach(['sessions','cache','cache_locks','jobs','failed_jobs','job_batches','password_reset_tokens'] as $table){if(Schema::hasTable($table))DB::table($table)->delete();}
        DB::table('users')->update(['remember_token'=>null]);
        if(snapshotCounts()!=$manifest['counts'])throw new RuntimeException('Les nombres de lignes importées ne correspondent pas à l’export.');
        runSnapshotProcess(['tar','-xzf',$work.'/assets.tar.gz','--no-same-owner','-C',base_path()]);
        foreach(new FilesystemIterator(storage_path('framework/sessions'),FilesystemIterator::SKIP_DOTS) as $sessionFile){if($sessionFile->isFile() && $sessionFile->getFilename()!=='.gitignore')unlink($sessionFile->getPathname());}
        Artisan::call('migrate',['--force'=>true]);Artisan::call('optimize:clear');Artisan::call('config:cache');
        if(!file_exists(public_path('storage')))Artisan::call('storage:link');
        runSnapshotProcess(['chown','-R','www-data:www-data',storage_path(),base_path('bootstrap/cache'),public_path('uploads')]);
        runSnapshotProcess(['chmod','-R','u=rwX,g=rwX,o=',storage_path(),base_path('bootstrap/cache'),public_path('uploads')]);
        Artisan::call('up');
        runSnapshotProcess(['curl','--fail','--silent','--max-time','15','--output','/dev/null','http://127.0.0.1:5000/login']);
        $maintenance=false;
        echo "Import terminé. Base et médias transférés, modules conservés.\nIdentifiants administrateur : ceux du VPS, inchangés.\n";
    }
}catch(Throwable $error){
    fwrite(STDERR,$error->getMessage()."\n");
    if($maintenance){
        try{
            if($backup && is_file($backup.'/READY')){
                $import($backup.'/database.sql');
                runSnapshotProcess(['tar','-xzf',$backup.'/assets.tar.gz','--no-same-owner','-C',base_path()]);
                Artisan::call('optimize:clear');Artisan::call('config:cache');
                echo "La base et les médias précédents ont été restaurés.\n";
            }
            Artisan::call('up');$maintenance=false;
        }catch(Throwable $rollback){fwrite(STDERR,"Restauration automatique interrompue : ".$rollback->getMessage()."\nMaintenance conservée. Sauvegarde : $backup\n");}
    }
    if($cnf && is_file($cnf))unlink($cnf);
    exit(1);
}finally{if($cnf && is_file($cnf))unlink($cnf);}
