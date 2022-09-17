<?php

use Symfony\Component\Process\Process;

$rootPath = dirname(__DIR__);
require $rootPath . '/vendor/autoload.php';
$step = $_GET['step'] ?? 0;
switch ($step) {
    case 1:
        $composer = exec('composer --version');
        $extends = ['pdo_mysql', 'curl', 'gd', 'fileinfo', 'zlib'];
        $extensions = get_loaded_extensions();
        $check = true;
        $check = version_compare('7.4.0', phpversion()) <= 0;
        if ($check && strpos($composer, 'Composer') === false) {
            $check = false;
        }
        foreach ($extends as $extend) {
            if (!in_array($extend, $extensions)) {
                $check = false;
                break;
            }
        }
        $data = [
            'check' => true,
            'composer' => $composer,
            'composer_check' => strpos($composer, 'Composer') >= 0,
            'php' => phpversion(),
            'php_check' => version_compare('7.4.0', phpversion()) <= 0,
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'curl' => extension_loaded('curl'),
            'gd' => extension_loaded('gd'),
            'fileinfo' => extension_loaded('fileinfo'),
            'zlib' => extension_loaded('zlib'),
        ];
        echo json_encode($data);
        break;

    case 2:
        $data = [
            'code'=>0,
            'message'=>'',
        ];

        $content = file_get_contents('php://input');
        $post = (array)json_decode($content, true);
        $database = $post['database'];
        $user = $post['user'];
        $dsn="mysql:port={$database['port']};host={$database['hostname']}";

        try{
            $pdo=new \PDO($dsn,$database['username'],$database['password']);
            $res = $pdo->query( "select VERSION();");
            $res = $res->fetch(\PDO::FETCH_ASSOC);
            if( version_compare($res['VERSION()'], '5.7') == -1){
                throw new \Exception('数据库版本需>=5.7');
            }
            $res = $pdo->query( "show databases;");
            $res = $res->fetchAll(\PDO::FETCH_ASSOC);
            $database_list = [];
            foreach($res as $k => $v) {
                $database_list[] = $v['Database'];
            }
            if (!in_array($database['database'],$database_list)) {
                throw new \Exception('数据库不存在');
            }
        }catch(\Exception $e){
            $data['message'] = $e->getMessage();
            $data['code'] = 1;
            echo json_encode($data);
            return;
        }
        if(empty($user['username'])){
            $data['message'] = '管理员用户名不能为空';
            $data['code'] = 1;
        }
        if(empty($user['password'])){
            $data['message'] = '管理员密码不能为空';
            $data['code'] = 1;
        }
        if($user['password'] != $user['password_confim']){
            $data['message'] = '管理员密码不一致';
            $data['code'] = 1;
        }
        echo json_encode($data);
        break;
    case 3:
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        set_time_limit(0);
        ob_end_clean();
        ob_implicit_flush(1);
        $filesystem = new \Symfony\Component\Filesystem\Filesystem();
        $filesystem->remove([$rootPath . '/composer.json', $rootPath . '/composer.lock']);
        $database = json_decode($_GET['database'],true);
        $user = json_decode($_GET['user'],true);
        if ($_GET['frame'] == 'thinkphp') {
            $cmd = ['composer', 'require', 'topthink/think'];
        } elseif ($_GET['frame'] == 'laravel') {
            $cmd = ['composer', 'require', 'laravel/laravel'];
        }
        exec_run($cmd, __DIR__);
        if ($_GET['frame'] == 'thinkphp') {
            $filesystem->mirror(__DIR__ . '/vendor/topthink/think', $rootPath, null, ['override' => true]);
        } elseif ($_GET['frame'] == 'laravel') {
            $filesystem->mirror(__DIR__ . '/vendor/laravel/laravel', $rootPath, null, ['override' => true]);
        }
        if ($_GET['frame'] == 'thinkphp') {
            $env = file_get_contents($rootPath.'/.example.env');
            $env = str_replace([
                '127.0.0.1',
                'test',
                'username',
                'password',
                '3306',
            ],[
                $database['hostname'],
                $database['database'],
                $database['username'],
                $database['password'],
                $database['port'],
            ],$env);
        }elseif ($_GET['frame'] == 'laravel') {
            $env = file_get_contents($rootPath.'/.env.example');
            $env = str_replace([
                '127.0.0.1',
                'laravel',
                'root',
                'DB_PASSWORD=',
                '3306',
            ],[
                $database['hostname'],
                $database['database'],
                $database['username'],
                'DB_PASSWORD='.$database['password'],
                $database['port'],
            ],$env);
        }
        file_put_contents($rootPath.'/.env',$env);
        $filesystem->remove([__DIR__ . '/vendor/', __DIR__ . '/composer.json', __DIR__ . '/composer.lock']);
        $cmd = ['composer', 'require', 'rockys/ex-admin-' . $_GET['frame']];
        exec_run($cmd);
        $cmd = ['composer', 'require', 'symfony/process','*'];
        exec_run($cmd, null, false);

        $cmd = ['composer', 'require', 'symfony/filesystem','*'];
        exec_run($cmd, null, false);
        if ($_GET['frame'] == 'thinkphp') {
            $console = 'think';
        }elseif ($_GET['frame'] == 'laravel') {
            $console = 'artisan';
        }
        $cmd = ['php', $console, 'admin:install','--force','--username='.$_GET['username'],'--password='.$_GET['password']];
        exec_run($cmd);
        break;
    default:
        echo file_get_contents(__DIR__ . '/index.html');
}
function ouput($buffer)
{
    $content = "event:data" . PHP_EOL; //定义事件
    $buffer = str_replace(PHP_EOL, '<br/>', $buffer);
    $content .= "data: $buffer" . PHP_EOL; //推送内容
    echo $content . PHP_EOL;
}

function exec_run($cmd, $root = null, $out = true)
{
    if (is_null($root)) {
        $root = dirname(__DIR__);
    }
    $process = new Process($cmd, $root);
    $process->setTimeout(0);
    try {
        $process->mustRun(function ($type, $buffer) use ($out) {
//                if (Process::OUT === $type) {
//                    $content = "event:data" . PHP_EOL; //定义事件
//                } elseif (Process::ERR === $type) {
//                    $content = "event:dataError" . PHP_EOL; //定义事件
//                }
            if ($out) {
                ouput($buffer);

            }
        });
    } catch (\Symfony\Component\Process\Exception\ProcessFailedException $e) {
        ouput($process->getErrorOutput());
    }
}
