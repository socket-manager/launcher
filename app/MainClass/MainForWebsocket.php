<?php
/**
 * メイン処理クラスのファイル
 * 
 * Websocketプロトコル対応
 */

namespace App\MainClass;


use SocketManager\Library\SocketManager;
use SocketManager\Library\FrameWork\Console;

use App\UnitParameter\ParameterForWebsocket;
use App\InitClass\InitForWebsocket;
use App\ProtocolUnits\ProtocolForWebsocket;
use App\CommandUnits\CommandForWebsocket;

use SocketManager\Library\RuntimeManager;
use App\UnitParameter\ParameterForLauncher;
use App\InitClass\InitForLauncher;
use App\UnitParameter\LauncherAction;
use App\RuntimeUnits\RuntimeForLauncher;


/**
 * メイン処理クラス
 * 
 * Websocketプロトコル対応
 */
class MainForWebsocket extends Console
{
    //--------------------------------------------------------------------------
    // 定数
    //--------------------------------------------------------------------------


    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * @var string コマンド処理の識別子
     */
    protected string $identifer = 'app:gui {port_no?}';

    /**
     * @var string コマンド説明
     */
    protected string $description = 'GUI サービス管理ランチャー';

    /**
     * @var string $host ホスト名（リッスン用）
     */
    private string $host = 'localhost';

    /**
     * @var int $port ポート番号（リッスン用）
     */
    private int $port = 10000;

    /**
     * @var int $cycle_interval 周期インターバル時間（μs）
     */
    private int $cycle_interval = 1000;

    /**
     * @var int $alive_interval アライブチェックタイムアウト時間（s）
     */
    private int $alive_interval = 3600;

    /**
     * @var string $via ランチャーモード（'CLI' or 'GUI'）
     */
    private string $via = 'GUI';

    /**
     * @var string $pid_path_for_launcher ランチャーのプロセスIDファイル
     */
    private string $pid_path_for_launcher = '';

    /**
     * @var mixed $log_writer ログライター
     */
    private $log_writer = null;


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * サーバー起動
     * 
     */
    public function exec()
    {
        //--------------------------------------------------------------------------
        // 変数初期化
        //--------------------------------------------------------------------------

        $error_message = null;
        $init_class = null;
        $unit_parameter = null;

        //--------------------------------------------------------------------------
        // 設定値の反映
        //--------------------------------------------------------------------------

        // ホスト名の設定
        $this->host = config('const.host', $this->host);

        // ポート番号の設定
        $this->port = config('const.port', $this->port);

        // 周期インターバルの設定
        $this->cycle_interval = config('const.cycle_interval', $this->cycle_interval);

        // アライブチェックタイムアウト時間の設定
        $this->alive_interval = config('const.alive_interval', $this->alive_interval);

        // ランチャーのPIDファイル
        $this->pid_path_for_launcher = config('const.pid_path_for_launcher');

        // サービス構成ファイル
        $service_file = config('launcher.services_path');

        // 自動再起動フラグ
        $auto_restart = false;

        //--------------------------------------------------------------------------
        // 引数の反映
        //--------------------------------------------------------------------------

        // 引数の取得
        $port = $this->getParameter('port_no');
        if($port !== null)
        {
            $this->port = $port;
        }

        //--------------------------------------------------------------------------
        // SocketManagerの初期化
        //--------------------------------------------------------------------------

        // ソケットマネージャーのインスタンス設定
        $manager = new SocketManager($this->host, $this->port);

        // UNITパラメータインスタンスの設定
        $param = new ParameterForWebsocket($this->cycle_interval);

        // SocketManagerの設定値初期設定
        $init = new InitForWebsocket($param, $this->port);
        $manager->setInitSocketManager($init);

        // プロトコルUNITの設定
        $entry = new ProtocolForWebsocket();
        $manager->setProtocolUnits($entry);

        // コマンドUNITの設定
        $entry = new CommandForWebsocket();
        $manager->setCommandUnits($entry);

        //--------------------------------------------------------------------------
        // RuntimeManagerの初期化
        //--------------------------------------------------------------------------

        // UNITパラメータクラスのインスタンスを取得
        $unit_parameter = new ParameterForLauncher($this->via, $auto_restart);

        // UNITパラメータクラスの交換設定
        $param->setParameterLauncher($unit_parameter);
        $unit_parameter->setParameterWebsocket($param);

        // exec関数有効確認
        if($unit_parameter->isExecAvailable() === false)
        {
            $error_message = __('launcher.ERROR_EXEC_INVALID');
            goto finish;
        }

        // 初期化クラスのインスタンスを取得
        $init_class = new InitForLauncher($this->via, $unit_parameter);
        $this->log_writer = $init_class->getLogWriter();

        // サービス構成ファイルの取得
        $service_json = '';
        if(file_exists($service_file))
        {
            $service_json = file_get_contents($service_file);
            if($service_json === false)
            {
                $error_message = __('launcher.SERVICES_FILE_READ_FAILED');
                goto finish;
            }
        }
        $service_list = json_decode("[{$service_json}]", true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = __('launcher.SERVICES_FILE_DECODE_FAILED');
            goto finish;
        }
        $unit_parameter->setServiceList($service_list);

        // ランチャーの競合チェック
        $not_conflict = $this->checkStartupLauncher($error_message);
        if($not_conflict === false)
        {
            goto finish;
        }

        // オーダーアクションの設定
        $w_ret = $unit_parameter->setOrderAction(
            LauncherAction::GUI_START->value,
            null,
            $this->via,
            null,
            $error_message
        );
        if($w_ret === false)
        {
            goto finish;
        }

        // ランタイムマネージャーのインスタンス設定
        $manager_runtime = new RuntimeManager();

        // 初期化クラスの設定
        $manager_runtime->setInitRuntimeManager($init_class);

        // ランタイムUNITの設定
        $manager_runtime->setRuntimeUnits(new RuntimeForLauncher());

        //--------------------------------------------------------------------------
        // リッスンポートで待ち受ける
        //--------------------------------------------------------------------------

        $ret = $manager->listen();
        if($ret === false)
        {
            goto finish;   // リッスン失敗
        }

        //--------------------------------------------------------------------------
        // ノンブロッキングループ
        //--------------------------------------------------------------------------

        while(true)
        {
            $param->resourceMonitoring();

            // 周期ドリブン
            $ret = $manager->cycleDriven($this->cycle_interval, $this->alive_interval);
            if($ret === false)
            {
                goto finish;
            }

            // 周期ドリブン
            $ret = $manager_runtime->cycleDriven($this->cycle_interval);
            if($ret === false)
            {
                goto finish;
            }
        }

finish:
        // 全接続クローズ
        $manager->shutdownAll();

        if($error_message !== null)
        {
            $log_writer = $this->log_writer;
            $log_writer('error', ['type' => 'none-action', 'message' => $error_message, 'via' => 'CLI', 'who' => null, 'pid' => null]);
        }
        if(file_exists($this->pid_path_for_launcher))
        {
            unlink($this->pid_path_for_launcher);
        }

        return;
    }

    /**
     * ランチャーの競合チェック
     * 
     * @param ?string &$p_message エラーメッセージ
     * @return bool true（成功） or false（失敗）
     */
    private function checkStartupLauncher(?string &$p_message): bool
    {
        if(file_exists($this->pid_path_for_launcher))
        {
            $pid = (int)file_get_contents($this->pid_path_for_launcher);

            $keyword_cli = '.*worker app:cli.*';
            $keyword_gui = '.*worker app:gui.*';
            if(PHP_OS_FAMILY === 'Windows')
            {
                $cmd = "powershell -Command \"Get-WmiObject Win32_Process | Where-Object { \$_.CommandLine -match '{$keyword_cli}|{$keyword_gui}' } | Select-Object ProcessId, ParentProcessId, CommandLine\"";
                $output = [];
                exec($cmd, $output);
                foreach($output as $row)
                {
                    if(preg_match('/^\s*(\d+)\s+(\d+)\s+(.*)$/', $row, $matches))
                    {
                        $match_pid = (int)$matches[1];
                        if($pid === $match_pid)
                        {
                            $p_message = __('launcher.ERROR_STARTUP_LAUNCHER');
                            return false;
                        }
                    }
                }
            }
            else
            {
                $cmd = "ps -eo pid,cmd | grep -E 'worker app:(cli|gui)' | grep -v grep | awk '{print $1}'";
                $output = [];
                exec($cmd, $output);
                foreach($output as $row)
                {
                    if($pid == $row)
                    {
                        $p_message = __('launcher.ERROR_STARTUP_LAUNCHER');
                        return false;
                    }
                }
            }
        }
        $pid = getmypid();
        file_put_contents($this->pid_path_for_launcher, (string)$pid);
        return true;

        // if(file_exists($this->pid_path_for_launcher))
        // {
        //     $pid = (int)file_get_contents($this->pid_path_for_launcher);

        //     $keyword_cli = '.*worker app:cli.*';
        //     $keyword_gui = '.*worker app:gui.*';
        //     $cmd = "powershell -Command \"Get-WmiObject Win32_Process | Where-Object { \$_.CommandLine -match '{$keyword_cli}|{$keyword_gui}' } | Select-Object ProcessId, ParentProcessId, CommandLine\"";
        //     $output = [];
        //     exec($cmd, $output);
        //     foreach($output as $row)
        //     {
        //         if(preg_match('/^\s*(\d+)\s+(\d+)\s+(.*)$/', $row, $matches))
        //         {
        //             $match_pid = (int)$matches[1];
        //             if($pid === $match_pid)
        //             {
        //                 $p_message = __('launcher.ERROR_STARTUP_LAUNCHER');
        //                 return false;
        //             }
        //         }
        //     }
        // }
        // $pid = getmypid();
        // file_put_contents($this->pid_path_for_launcher, (string)$pid);
        // return true;
    }

}
