<?php

use Symfony\Component\Process\Process;

require dirname(__DIR__, 3) . '/autoload.php';
switch ($_GET['step']) {
    case 1:
        $composer = exec('composer --version');
        $extends = ['pdo_mysql','curl','gd','fileinfo','zlib'];
        $extensions = get_loaded_extensions();
        $check = true;
        $check = version_compare('7.4.0', phpversion()) <= 0;
        if($check && strpos($composer, 'Composer') ===  false){
            $check = false;
        }
        foreach ($extends as $extend){
            if(!in_array($extend,$extensions)){
                $check = false;
                break;
            }
        }
        $data = [
            'check'=>true,
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
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        set_time_limit(0);
        ob_end_clean();
        ob_implicit_flush(1);
        $projectDir = dirname(__DIR__, 4);
        @unlink($projectDir.'/composer.json');
        $cmd = ['composer','require', 'rockys/ex-admin-install','dev-master'];
        exec_run($cmd);
        if($_GET['frame'] == 'thinkphp'){
            $cmd = ['composer','require','topthink/think'];
        }elseif ($_GET['frame'] == 'laravel'){
            $cmd = ['composer','require','laravel/laravel'];
        }
        $cmd = ['composer','require','topthink/think'];

        exec_run($cmd);
       $filesystem = new \Symfony\Component\Filesystem\Filesystem();

       $filesystem->mirror( dirname(__DIR__, 3).'/topthink/think',$projectDir,null,['override'=>true]);
       $cmd = ['composer','remove','rockys/ex-admin-thinkphp','rockys/ex-admin-laravel'];
        exec_run($cmd);

        $cmd = ['composer','require', 'rockys/ex-admin-'.$_GET['frame']];
        exec_run($cmd);
        $cmd = ['composer','require', 'rockys/ex-admin-install','dev-master'];
        exec_run($cmd);
        break;
}
function exec_run($cmd){
    $process = new Process($cmd, dirname(__DIR__, 4));
    try {
        $process->mustRun(function ($type, $buffer)  {
//                if (Process::OUT === $type) {
//                    $content = "event:data" . PHP_EOL; //定义事件
//                } elseif (Process::ERR === $type) {
//                    $content = "event:dataError" . PHP_EOL; //定义事件
//                }
            $content = "event:data" . PHP_EOL; //定义事件
            $buffer = str_replace(PHP_EOL, '<br/>', $buffer);
            $content .= "data: $buffer" . PHP_EOL; //推送内容
            echo $content . PHP_EOL;
        });
    } catch (\Symfony\Component\Process\Exception\ProcessFailedException $e) {
        $content = "event:dataError" . PHP_EOL; //定义事件
        $buffer = str_replace(PHP_EOL, '<br/>', $process->getErrorOutput());
        $content .= "data: $buffer" . PHP_EOL; //推送内容
        echo $content . PHP_EOL;
    }
}