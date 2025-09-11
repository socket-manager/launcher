<?php
/**
 * メイン処理クラスのファイル
 * 
 * RuntimeManagerの実行
 */

namespace App\MainClass;

use SocketManager\Library\RuntimeManager;
use SocketManager\Library\FrameWork\Console;

use App\InitClass\InitForLauncher;
use App\RuntimeUnits\RuntimeForLauncher;
use App\UnitParameter\LauncherAction;
use App\UnitParameter\ParameterForLauncher;


/**
 * メイン処理クラス
 * 
 * RuntimeManagerの初期化と実行
 */
class MainForLauncher extends Console
{
    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * @var string $identifer サーバー識別子
     */
    protected string $identifer = 'app:cli {action} {service?}';

    /**
     * @var string $description コマンド説明
     */
    protected string $description = 'CLI サービス管理ランチャー';

    /**
     * @var string $via ランチャーモード（'CLI' or 'GUI'）
     */
    private string $via = 'CLI';

    /**
     * @var int $cycle_interval 周期インターバル時間（μs）
     */
    private int $cycle_interval = 1000;

    /**
     * @var string $pid_path_for_launcher ランチャーのプロセスIDファイル
     */
    private string $pid_path_for_launcher = '';

    /**
     * @var mixed $log_writer ログライター
     */
    private $log_writer = null;


    /**
     * ランチャー起動
     * 
     */
    public function exec()
    {
        // 変数初期化
        $error_message = null;
        $init_class = null;
        $unit_parameter = null;

        // コマンドライン引数の取得
        $action = $this->getParameter('action');
        $service = $this->getParameter('service');

        // 設定ファイル読み込み
        $this->pid_path_for_launcher = config('const.pid_path_for_launcher');

        // UNITパラメータクラスのインスタンスを取得
        $auto_restart = config('launcher.auto_restart', false);
        $unit_parameter = new ParameterForLauncher($this->via, $auto_restart);

        // exec関数有効確認
        if($unit_parameter->isExecAvailable() === false)
        {
            $error_message = __('launcher.ERROR_EXEC_INVALID');
            goto finish;
        }
        
        // 初期化クラスのインスタンスを取得
        $init_class = new InitForLauncher($this->via, $unit_parameter);
        $this->log_writer = $init_class->getLogWriter();

        // shutdownアクション実行
        if($action === LauncherAction::SHUTDOWN->value)
        {
            if(file_exists($this->pid_path_for_launcher))
            {
                $error_message = __('launcher.NOTICE_LAUNCHER_SHUTDOWN');
            }
            else
            {
                $error_message = __('launcher.NOTICE_NO_RUNNING_LAUNCHER');
            }
            goto finish;
        }

        // サービス構成ファイルの取得
        $service_json = '';
        $service_file = config('launcher.services_path');
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
        $w_ret = $unit_parameter->setOrderAction($action, $service, $this->via, null, $error_message);
        if($w_ret === false)
        {
            goto finish;
        }

        // 周期インターバルの設定
        $this->cycle_interval = config('const.cycle_interval', $this->cycle_interval);

        // ランタイムマネージャーのインスタンス設定
        $manager_runtime = new RuntimeManager();

        /***********************************************************************
         * ランタイムマネージャーの初期設定
         * 
         * ランタイムUNITクラス等のインスタンスをここで設定します
         **********************************************************************/

        /**
         * 初期化クラスの設定
         * 
         * $manager->setInitRuntimeManager()メソッドで初期化クラスを設定します
         */
        $manager_runtime->setInitRuntimeManager($init_class);

        /**
         * ランタイムUNITの設定
         * 
         * $manager->setRuntimeUnits()メソッドでランタイムUNITクラスを設定します
         */
        $manager_runtime->setRuntimeUnits(new RuntimeForLauncher());

        /***********************************************************************
         * ランタイムマネージャーの実行
         * 
         * 周期ドリブン処理を実行します
         **********************************************************************/

        // ノンブロッキングループ
        while(true)
        {
            // 周期ドリブン
            $ret = $manager_runtime->cycleDriven($this->cycle_interval);
            if($ret === false)
            {
                goto finish;
            }

            if(!file_exists($this->pid_path_for_launcher))
            {
                goto finish;
            }
        }

finish:
        if($error_message !== null)
        {
            $level = 'error';
            if($action === false)
            {
                $action = 'none-action';
            }
            else
            if($action === LauncherAction::SHUTDOWN->value)
            {
                $level = 'notice';
            }
            $log_writer = $this->log_writer;
            $log_writer($level, ['type' => $action, 'message' => $error_message, 'via' => 'CLI', 'who' => null, 'pid' => null]);
        }
        if(!isset($not_conflict) || (isset($not_conflict) && $not_conflict === true))
        {
            if(file_exists($this->pid_path_for_launcher))
            {
                unlink($this->pid_path_for_launcher);
            }
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
    }

}
