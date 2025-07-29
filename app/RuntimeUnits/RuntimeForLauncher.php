<?php
/**
 * ステータスUNIT登録クラスのファイル
 * 
 * RuntimeManagerのsetRuntimeUnitsメソッドへ引き渡されるクラスのファイル
 */

namespace App\RuntimeUnits;


use SocketManager\Library\IEntryUnits;
use App\RuntimeUnits\RuntimeQueueEnumForLauncher;

use App\RuntimeUnits\RuntimeStatusEnumForLauncher;
use App\UnitParameter\LauncherAction;
use App\UnitParameter\ParameterForLauncher;


/**
 * ランチャーUNIT登録クラス
 * 
 * IEntryUnitsインタフェースをインプリメントする
 */
class RuntimeForLauncher implements IEntryUnits
{
    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * @var int $timer_start スタートタイマー
     */
    private int $timer_start;

    /**
     * @var mixed $exit_code プロセス停止コード
     */
    private $exit_code;

    /**
     * @var int $stop_interval プロセス停止インターバル（ms）
     */
    private int $stop_interval = 3000;

    /**
     * @var const QUEUE_LIST キュー名のリスト
     */
    protected const QUEUE_LIST = [
        RuntimeQueueEnumForLauncher::STARTUP->value     // 起動処理のキュー
    ];


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     */
    public function __construct()
    {
    }

    /**
     * キューリストの取得
     * 
     * @return array キュー名のリスト
     */
    public function getQueueList(): array
    {
        return (array)static::QUEUE_LIST;
    }

    /**
     * ステータスUNITリストの取得
     * 
     * @param string $p_que キュー名
     * @return array キュー名に対応するUNITリスト
     */
    public function getUnitList(string $p_que): array
    {
        $ret = [];

        if($p_que === RuntimeQueueEnumForLauncher::STARTUP->value)
        {
            $ret[] = [
                'status' => RuntimeStatusEnumForLauncher::START->value,
                'unit' => $this->getStartupStart()
            ];
            if(PHP_OS_FAMILY === 'Windows')
            {
                $ret[] = [
                    'status' => RuntimeStatusEnumForLauncher::EXPLORE_START->value,
                    'unit' => $this->getStartupWindowsExploreForStarting()
                ];
                $ret[] = [
                    'status' => RuntimeStatusEnumForLauncher::PROCESS_START->value,
                    'unit' => $this->getStartupWindowsProcessStart()
                ];
                $ret[] = [
                    'status' => RuntimeStatusEnumForLauncher::EXPLORE_STOP->value,
                    'unit' => $this->getStartupWindowsExploreForStopping()
                ];
                $ret[] = [
                    'status' => RuntimeStatusEnumForLauncher::PROCESS_STOP->value,
                    'unit' => $this->getStartupWindowsProcessStop()
                ];
                $ret[] = [
                    'status' => RuntimeStatusEnumForLauncher::STOP_POLLING->value,
                    'unit' => $this->getStartupWindowsStopPolling()
                ];
                $ret[] = [
                    'status' => RuntimeStatusEnumForLauncher::EXPLORE_STATUS->value,
                    'unit' => $this->getStartupWindowsExploreForStatus()
                ];
                $ret[] = [
                    'status' => RuntimeStatusEnumForLauncher::CPU_CHECK->value,
                    'unit' => $this->getStartupWindowsCpuCheck()
                ];
                $ret[] = [
                    'status' => RuntimeStatusEnumForLauncher::MEMORY_CHECK->value,
                    'unit' => $this->getStartupWindowsMemoryCheck()
                ];
                $ret[] = [
                    'status' => RuntimeStatusEnumForLauncher::CPU_INFO->value,
                    'unit' => $this->getStartupWindowsCpuInfo()
                ];
            }
            else
            {
                $ret[] = [
                    'status' => RuntimeStatusEnumForLauncher::EXPLORE_START->value,
                    'unit' => $this->getStartupLinuxExploreForStarting()
                ];
                $ret[] = [
                    'status' => RuntimeStatusEnumForLauncher::PROCESS_START->value,
                    'unit' => $this->getStartupLinuxProcessStart()
                ];
                $ret[] = [
                    'status' => RuntimeStatusEnumForLauncher::EXPLORE_STOP->value,
                    'unit' => $this->getStartupLinuxExploreForStopping()
                ];
                $ret[] = [
                    'status' => RuntimeStatusEnumForLauncher::PROCESS_STOP->value,
                    'unit' => $this->getStartupLinuxProcessStop()
                ];
                $ret[] = [
                    'status' => RuntimeStatusEnumForLauncher::STOP_POLLING->value,
                    'unit' => $this->getStartupLinuxStopPolling()
                ];
                $ret[] = [
                    'status' => RuntimeStatusEnumForLauncher::EXPLORE_STATUS->value,
                    'unit' => $this->getStartupLinuxExploreForStatus()
                ];
                $ret[] = [
                    'status' => RuntimeStatusEnumForLauncher::CPU_CHECK->value,
                    'unit' => $this->getStartupLinuxCpuCheck()
                ];
                $ret[] = [
                    'status' => RuntimeStatusEnumForLauncher::MEMORY_CHECK->value,
                    'unit' => $this->getStartupLinuxMemoryCheck()
                ];
                $ret[] = [
                    'status' => RuntimeStatusEnumForLauncher::CPU_INFO->value,
                    'unit' => $this->getStartupLinuxCpuInfo()
                ];
            }
        }

        return $ret;
    }


    /**
     * 以降はステータスUNITの定義（"STARTUP"キュー）
     */

    /**
     * ステータス名： START
     * 
     * 処理名：起動処理開始
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupStart()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'START']);

            // オーダーアクションの取得
            $order_action = $p_param->getOrderAction();
            if($order_action === null)
            {
                $p_param->order_action_current = null;
                $p_param->emergencyShutdown();
                return null;
            }
            $p_param->order_action_current = $order_action;

            // actionオーダーによる処理の振り分け
            $next_status = null;
            if($p_param->order_action_current['action'] === LauncherAction::START->value)
            {
                $p_param->service_list_current = [];
                $service_key = $p_param->order_action_current['service_key'];
                $max = count($p_param->service_list_all);
                for($i = 0; $i < $max; $i++)
                {
                    if($p_param->service_list_all[$i][$service_key] === $p_param->order_action_current['service_name'])
                    {
                        $p_param->service_list_current[] = $p_param->service_list_all[$i];
                        $next_status = RuntimeStatusEnumForLauncher::EXPLORE_START->value;
                    }
                }
            }
            else
            if($p_param->order_action_current['action'] === LauncherAction::START_ALL->value)
            {
                $p_param->service_list_current = $p_param->service_list_all;
                $next_status = RuntimeStatusEnumForLauncher::EXPLORE_START->value;
            }
            else
            if($p_param->order_action_current['action'] === LauncherAction::AUTO_RESTART->value)
            {
                $next_status = RuntimeStatusEnumForLauncher::EXPLORE_START->value;
            }
            else
            if($p_param->order_action_current['action'] === LauncherAction::STOP->value)
            {
                $p_param->service_list_current = [];
                $service_key = $p_param->order_action_current['service_key'];
                $max = count($p_param->service_list_all);
                for($i = 0; $i < $max; $i++)
                {
                    if($p_param->service_list_all[$i][$service_key] === $p_param->order_action_current['service_name'])
                    {
                        $p_param->service_list_current[] = $p_param->service_list_all[$i];
                        $next_status = RuntimeStatusEnumForLauncher::EXPLORE_STOP->value;
                    }
                }
            }
            else
            if($p_param->order_action_current['action'] === LauncherAction::STOP_ALL->value)
            {
                $p_param->service_list_current = $p_param->service_list_all;
                $next_status = RuntimeStatusEnumForLauncher::EXPLORE_STOP->value;
            }
            else
            if($p_param->order_action_current['action'] === LauncherAction::RESTART->value)
            {
                if($p_param->order_action_current['service_key'] === 'group')
                {
                    $service_key = $p_param->order_action_current['service_key'];
                    $max = count($p_param->service_list_all);
                    for($i = 0; $i < $max; $i++)
                    {
                        if($p_param->service_list_all[$i][$service_key] === $p_param->order_action_current['service_name'])
                        {
                            $p_param->setOrderInternalAction(
                                LauncherAction::STOP->value,
                                'name',
                                $p_param->service_list_all[$i]['name'],
                                $p_param->order_action_current['via'],
                                $p_param->order_action_current['who']
                            );
                            $p_param->setOrderInternalAction(
                                LauncherAction::START->value,
                                'name',
                                $p_param->service_list_all[$i]['name'],
                                $p_param->order_action_current['via'],
                                $p_param->order_action_current['who']
                            );
                        }
                    }
                }
                else
                {
                    $p_param->setOrderInternalAction(
                        LauncherAction::STOP->value,
                        $p_param->order_action_current['service_key'],
                        $p_param->order_action_current['service_name'],
                        $p_param->order_action_current['via'],
                        $p_param->order_action_current['who']
                    );
                    $p_param->setOrderInternalAction(
                        LauncherAction::START->value,
                        $p_param->order_action_current['service_key'],
                        $p_param->order_action_current['service_name'],
                        $p_param->order_action_current['via'],
                        $p_param->order_action_current['who']
                    );
                }

                return RuntimeStatusEnumForLauncher::START->value;
            }
            else
            if($p_param->order_action_current['action'] === LauncherAction::RESTART_ALL->value)
            {
                $max = count($p_param->service_list_all);
                for($i = 0; $i < $max; $i++)
                {
                    $p_param->setOrderInternalAction(
                        LauncherAction::STOP->value,
                        'name',
                        $p_param->service_list_all[$i]['name'],
                        $p_param->order_action_current['via'],
                        $p_param->order_action_current['who']
                    );
                    $p_param->setOrderInternalAction(
                        LauncherAction::START->value,
                        'name',
                        $p_param->service_list_all[$i]['name'],
                        $p_param->order_action_current['via'],
                        $p_param->order_action_current['who']
                    );
                }
                return RuntimeStatusEnumForLauncher::START->value;
            }
            else
            if($p_param->order_action_current['action'] === LauncherAction::STATUS->value)
            {
                $p_param->service_list_current = [];
                $service_key = $p_param->order_action_current['service_key'];
                $max = count($p_param->service_list_all);
                for($i = 0; $i < $max; $i++)
                {
                    if($p_param->service_list_all[$i][$service_key] === $p_param->order_action_current['service_name'])
                    {
                        $p_param->service_list_current[] = $p_param->service_list_all[$i];
                        $next_status = RuntimeStatusEnumForLauncher::EXPLORE_STATUS->value;
                    }
                }
            }
            else
            if($p_param->order_action_current['action'] === LauncherAction::STATUS_ALL->value)
            {
                $p_param->service_list_current = $p_param->service_list_all;
                $next_status = RuntimeStatusEnumForLauncher::EXPLORE_STATUS->value;
            }
            else
            if($p_param->order_action_current['action'] === LauncherAction::CPU_INFO->value)
            {
                $next_status = RuntimeStatusEnumForLauncher::CPU_INFO->value;
            }

            // 処理中プロセス指標の初期化
            $p_param->max_process = count($p_param->service_list_current);
            $p_param->idx_process = 0;

            return $next_status;
        };
    }

    /**
     * ステータス名： EXPLORE_START
     * 
     * 処理名：[Windows]サービス調査
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupWindowsExploreForStarting()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'WINDOWS_EXPLORE_START']);

            $i = $p_param->idx_process;

            $name = $p_param->service_list_current[$i]['name'];
            $pid_file = $p_param->pid_path_for_service."/{$name}";

            $pid = $p_param->service_list_current[$i]['pid'];
            if($pid === null)
            {
                if(file_exists($pid_file))
                {
                    $pid = (int)file_get_contents($pid_file);

                    // サービスの死活確認
                    $w_ret = $p_param->getActivityForWindows($pid, $p_param->service_list_current[$i]['command']);
                    if($w_ret === false)
                    {
                        return RuntimeStatusEnumForLauncher::PROCESS_START->value;
                    }

                    // プロセスIDの設定
                    $p_param->service_list_current[$i]['pid'] = $pid;
                    $max = count($p_param->service_list_all);
                    for($j = 0; $j < $max; $j++)
                    {
                        if($p_param->service_list_all[$j]['name'] === $name)
                        {
                            $p_param->service_list_all[$j]['pid'] = $pid;
                        }
                    }
                }
                else
                {
                    return RuntimeStatusEnumForLauncher::PROCESS_START->value;
                }
            }
            else
            {
                if($p_param->order_action_current['action'] === LauncherAction::AUTO_RESTART->value)
                {
                    $output = [];
                    exec("powershell -Command \"Get-Process -Id {$pid}\"", $output, $status);
                    if($status === 0)
                    {
                        goto finish_explore_for_start;
                    }

                    // 停止検知メッセージ
                    $msg = __('launcher.ERROR_DETECT_STOP', ['service' => $name]);
                    $p_param->logWriter('error', ['type' => LauncherAction::DETECT_STOP->value, 'message' => $msg, 'via' => 'watchdog', 'who' => null, 'pid' => null]);

                    return RuntimeStatusEnumForLauncher::PROCESS_START->value;
                }
            }

            // メッセージ出力
            $msg = __('launcher.NOTICE_RUNNING_SERVICE', ['service' => $name]);
            $p_param->logWriter('notice', ['type' => 'operator-'.$p_param->order_action_current['action'], 'message' => $msg, 'via' => $p_param->order_action_current['via'], 'who' => null, 'pid' => $p_param->service_list_current[$i]['pid']]);

finish_explore_for_start:
            // 終了判定
            if(++$p_param->idx_process >= $p_param->max_process)
            {
                $p_param->logWriter('debug', ['プロセスリスト by EXPLORE_START' => print_r($p_param->service_list_all, true)]);
                return $p_param->getIdlingStatus($p_param->order_action_current);
            }

            return RuntimeStatusEnumForLauncher::EXPLORE_START->value;
        };
    }

    /**
     * ステータス名： PROCESS_START
     * 
     * 処理名：[Windows]プロセス起動
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupWindowsProcessStart()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'WINDOWS_PROCESS_START']);

            $i = $p_param->idx_process;

            // 起動パスの取得
            $path = $p_param->service_list_current[$i]['path'];
            // パラメータリストの初期化
            $param_list = '';
            // 設定ファイル内command項目のパラメータを抽出
            $param = explode(' ', $p_param->service_list_current[$i]['command']);
            // コマンド名の抽出
            $command = array_shift($param);
            // パラメータのみの件数を取得してループ
            $max_param = count($param);
            for($j = 0; $j < $max_param; $j++)
            {
                if(strlen($param_list))
                {
                    $param_list .= ', ';
                }
                $param_list .= "'{$param[$j]}'";
            }

            // シェル実行のコマンド文を生成
            $shell_command = "powershell -Command \"Set-Location {$path} ; Start-Process {$command} -ArgumentList {$param_list} -WindowStyle Hidden -PassThru | Select-Object -ExpandProperty Id\"";

            // コマンド実行
            $output = [];
            exec($shell_command, $output);
            $pid = trim($output[0] ?? '');
            $p_param->service_list_current[$i]['pid'] = $pid;
            $max = count($p_param->service_list_all);
            for($j = 0; $j < $max; $j++)
            {
                if($p_param->service_list_all[$j]['name'] === $p_param->service_list_current[$i]['name'])
                {
                    $p_param->service_list_all[$j]['pid'] = $pid;
                }
            }
            file_put_contents("./pids/{$p_param->service_list_current[$i]['name']}", $pid);

            // アフィニティ設定
            if($p_param->service_list_current[$i]['cores'] !== null)
            {
                $mask = 0;

                // アフィニティマスクの設定
                foreach($p_param->service_list_current[$i]['cores'] as $core)
                {
                    $w_mask = 1;
                    $w_mask <<= $core;
                    $mask |= $w_mask;
                }

                // アフィニティ設定のコマンド文を生成
                $affinity_command = __DIR__."/../bin/affinitysetter.exe {$p_param->service_list_current[$i]['pid']} {$mask}";

                // アフィニティ設定
                $output = [];
                exec($affinity_command, $output, $result);
            }

            if($p_param->order_action_current['action'] === LauncherAction::AUTO_RESTART->value)
            {
                // 停止検知メッセージ
                $msg = __('launcher.NOTICE_AUTO_RESTART', ['service' => $p_param->service_list_current[$i]['name']]);
                $p_param->logWriter('notice', ['type' => $p_param->order_action_current['action'], 'message' => $msg, 'via' => 'watchdog', 'who' => null, 'pid' => $p_param->service_list_current[$i]['pid']]);
            }
            else
            {
                // メッセージ出力
                $msg = __('launcher.INFO_STARTED_SERVICE', ['service' => $p_param->service_list_current[$i]['name']]);
                $p_param->logWriter('info', ['type' => 'operator-'.$p_param->order_action_current['action'], 'message' => $msg, 'via' => $p_param->order_action_current['via'], 'who' => null, 'pid' => $p_param->service_list_current[$i]['pid']]);
            }

            // 終了判定
            if(++$p_param->idx_process >= $p_param->max_process)
            {
                $p_param->logWriter('debug', ['プロセスリスト by PROCESS_START' => print_r($p_param->service_list_all, true)]);
                return $p_param->getIdlingStatus($p_param->order_action_current);
            }

            return RuntimeStatusEnumForLauncher::EXPLORE_START->value;
        };
    }

    /**
     * ステータス名： EXPLORE_STOP
     * 
     * 処理名：[Windows]サービス調査
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupWindowsExploreForStopping()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'WINDOWS_EXPLORE_STOP']);

            $i = $p_param->idx_process;

            $name = $p_param->service_list_current[$i]['name'];
            $pid_file = $p_param->pid_path_for_service."/{$name}";

            $pid = $p_param->service_list_current[$i]['pid'];
            if($pid === null)
            {
                if(file_exists($pid_file))
                {
                    $pid = (int)file_get_contents($pid_file);

                    // サービスの死活確認
                    $w_ret = $p_param->getActivityForWindows($pid, $p_param->service_list_current[$i]['command']);
                    if($w_ret === true)
                    {
                        // プロセスIDの設定
                        $p_param->service_list_current[$i]['pid'] = $pid;
                        $max = count($p_param->service_list_all);
                        for($j = 0; $j < $max; $j++)
                        {
                            if($p_param->service_list_all[$j]['name'] === $name)
                            {
                                $p_param->service_list_all[$j]['pid'] = $pid;
                            }
                        }
                        return RuntimeStatusEnumForLauncher::PROCESS_STOP->value;
                    }
                }
            }
            else
            {
                return RuntimeStatusEnumForLauncher::PROCESS_STOP->value;
            }

            // メッセージ出力
            $msg = __('launcher.NOTICE_STOPPING_SERVICE', ['service' => $name]);
            $p_param->logWriter('notice', ['type' => 'operator-'.$p_param->order_action_current['action'], 'message' => $msg, 'via' => $p_param->order_action_current['via'], 'who' => null, 'pid' => $p_param->service_list_current[$i]['pid']]);

            // 終了判定
            if(++$p_param->idx_process >= $p_param->max_process)
            {
                $p_param->logWriter('debug', ['プロセスリスト by EXPLORE_STOP' => print_r($p_param->service_list_all, true)]);
                return $p_param->getIdlingStatus($p_param->order_action_current);
            }

            return RuntimeStatusEnumForLauncher::EXPLORE_STOP->value;
        };
    }

    /**
     * ステータス名： PROCESS_STOP
     * 
     * 処理名：[Windows]プロセス停止
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupWindowsProcessStop()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'WINDOWS_PROCESS_STOP']);

            $i = $p_param->idx_process;
            $pid = $p_param->service_list_current[$i]['pid'];
            $this->timer_start = microtime(true);
            exec("taskkill /PID {$pid} /F", $output, $this->exit_code);

            return RuntimeStatusEnumForLauncher::STOP_POLLING->value;
        };
    }

    /**
     * ステータス名： STOP_POLLING
     * 
     * 処理名：[Windows]プロセス停止ポーリング
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupWindowsStopPolling()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'WINDOWS_STOP_POLLING']);

            if((microtime(true) - $this->timer_start) * 1000 < $this->stop_interval)
            {
                return RuntimeStatusEnumForLauncher::STOP_POLLING->value;
            }

            $i = $p_param->idx_process;
            $log_pid = null; $pid = $p_param->service_list_current[$i]['pid'];
            $name = $p_param->service_list_current[$i]['name'];
            exec("powershell -Command \"Get-Process -Id {$pid}\"", $output, $status);

            if($this->exit_code === 0 && $status !== 0)
            {

                // メッセージ出力
                $msg = __('launcher.INFO_STOPPED_SERVICE', ['service' => $name]);
                $level = 'info';
                $log_pid = $pid;
                $pid = null;
            }
            else
            if($this->exit_code !== 0 && $status !== 0)
            {

                // メッセージ出力
                $msg = __('launcher.NOTICE_NOT_FOUND_SERVICE', ['service' => $name]);
                $level = 'notice';
                $log_pid = $pid;
                $pid = null;
            }
            else
            if($status === 0)
            {

                // メッセージ出力
                $msg = __('launcher.ERROR_STOP_FAILED', ['service' => $name]);
                $level = 'error';
                $log_pid = $pid;
                $pid = null;
            }
            else
            {

                // メッセージ出力
                $msg = __('launcher.ERROR_NOT_DETECTED', ['service' => $name]);
                $level = 'error';
                $log_pid = $pid;
                $pid = null;
            }

            $max = count($p_param->service_list_all);
            for($j = 0; $j < $max; $j++)
            {
                if($p_param->service_list_all[$j]['name'] === $name)
                {
                    $p_param->service_list_all[$j]['pid'] = $pid;
                }
            }

            $p_param->logWriter($level, ['type' => 'operator-'.$p_param->order_action_current['action'], 'message' => $msg, 'via' => $p_param->order_action_current['via'], 'who' => null, 'pid' => $log_pid]);

            // 終了判定
            if(++$p_param->idx_process >= $p_param->max_process)
            {
                $p_param->logWriter('debug', ['プロセスリスト by STOP_POLLING' => print_r($p_param->service_list_all, true)]);
                return $p_param->getIdlingStatus($p_param->order_action_current);
            }

            return RuntimeStatusEnumForLauncher::EXPLORE_STOP->value;
        };
    }

    /**
     * ステータス名： EXPLORE_STATUS
     * 
     * 処理名：[Windows]サービス調査（statusアクション用）
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupWindowsExploreForStatus()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'WINDOWS_EXPLORE_STATUS']);

            $i = $p_param->idx_process;

            $name = $p_param->service_list_current[$i]['name'];
            $pid_file = $p_param->pid_path_for_service."/{$name}";

            $pid = $p_param->service_list_current[$i]['pid'];
            if($pid === null)
            {
                $p_param->service_list_current[$i]['timestamp'] = null;

                if(file_exists($pid_file))
                {
                    $pid = (int)file_get_contents($pid_file);

                    // サービスの死活確認
                    $w_ret = $p_param->getActivityForWindows($pid, $p_param->service_list_current[$i]['command']);
                    if($w_ret === false)
                    {
                        goto finish_explore_for_windows_status;
                    }

                    $timestamp = filemtime($pid_file);
                    $p_param->service_list_current[$i]['timestamp'] = date("Y-m-d H:i:s", $timestamp);
                }
                else
                {
                    goto finish_explore_for_windows_status;
                }
            }
            else
            {
                $timestamp = filemtime($pid_file);
                $p_param->service_list_current[$i]['timestamp'] = date("Y-m-d H:i:s", $timestamp);

                $output = [];
                exec("powershell -Command \"Get-Process -Id {$pid}\"", $output, $status);
                if($status === 0)
                {
                    goto finish_explore_for_windows_status;
                }
            }

            // プロセスIDの設定
            $p_param->service_list_current[$i]['pid'] = $pid;
            $max = count($p_param->service_list_all);
            for($j = 0; $j < $max; $j++)
            {
                if($p_param->service_list_all[$j]['name'] === $name)
                {
                    $p_param->service_list_all[$j]['pid'] = $pid;
                }
            }

finish_explore_for_windows_status:
            return RuntimeStatusEnumForLauncher::CPU_CHECK->value;
        };
    }

    /**
     * ステータス名： CPU_CHECK
     * 
     * 処理名：[Windows]CPU稼働率取得
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupWindowsCpuCheck()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'WINDOWS_CPU_CHECK']);

            $i = $p_param->idx_process;
            $pid = $p_param->service_list_current[$i]['pid'];
            $cmd = "powershell -Command \"Get-WmiObject Win32_PerfFormattedData_PerfProc_Process | Where-Object { \$_.IDProcess -eq {$pid} } | Select-Object -ExpandProperty PercentProcessorTime\"";
            exec($cmd, $output, $code);
            $cpu = isset($output[0]) ? round((float)$output[0], 2) : null;
            $p_param->service_list_current[$i]['cpu'] = $cpu;

            return RuntimeStatusEnumForLauncher::MEMORY_CHECK->value;
        };
    }

    /**
     * ステータス名： MEMORY_CHECK
     * 
     * 処理名：[Windows]メモリ使用率取得
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupWindowsMemoryCheck()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'WINDOWS_MEMORY_CHECK']);

            $i = $p_param->idx_process;
            $pid = $p_param->service_list_current[$i]['pid'];

            $w_memory = null;

            // PowerShell コマンド定義
            $used_cmd = "powershell -Command \"(Get-Process -Id {$pid}).WorkingSet64\"";
            $total_cmd = "powershell -Command \"(Get-CimInstance Win32_ComputerSystem).TotalPhysicalMemory\"";

            // Used メモリ取得（バイト）
            exec($used_cmd, $used_output, $used_exitcode);
            if($used_exitcode === 0 && !empty($used_output))
            {
                $used_bytes = (float)trim($used_output[0]);

                // Total メモリ取得（バイト）
                exec($total_cmd, $total_output, $total_exitcode);
                if($total_exitcode === 0 && !empty($total_output))
                {
                    $total_bytes = (float)trim($total_output[0]);

                    // 使用率（％）を計算
                    if($total_bytes > 0)
                    {
                        $w_memory = round(($used_bytes / $total_bytes) * 100, 2);
                    }
                }
            }

            $name = $p_param->service_list_current[$i]['name'];
            $status = $p_param->colorText('起動中').' ';
            $cpu = $p_param->service_list_current[$i]['cpu'];
            $memory = $w_memory;
            $timestamp = $p_param->service_list_current[$i]['timestamp'];
            $id = $pid;
            $cores = $p_param->service_list_current[$i]['cores'];
            $group = $p_param->service_list_current[$i]['group'];
            $path = $p_param->service_list_current[$i]['path'];
            $command = $p_param->service_list_current[$i]['command'];

            if($cores === null)
            {
                $cores = '自動';
            }
            else
            {
                $cores = json_encode($cores);
            }

            if($pid === null)
            {
                $status = $p_param->colorText('停止中', 31).' ';
                $cpu = null;
                $memory = null;
                $timestamp = '-------------------';
                $id = '-----';
                $cores = str_repeat('-', strlen($cores));
            }
            if($p_param->order_action_current['action'] === LauncherAction::STATUS->value && $p_param->order_action_current['service_key'] !== 'group')
            {
                $msg = __('launcher.STATUS_DETAIL', [
                    'service' => $name,
                    'status' => $status,
                    'cpu' => ($cpu !== null) ? sprintf('%d', $cpu): '---',
                    'memory' => ($memory !== null) ? sprintf('%.2f', $memory): '------',
                    'timestamp' => $timestamp,
                    'pid' => $id,
                    'cores' => $cores,
                    'group' => $group,
                    'path' => $path,
                    'command' => $command
                ]);
            }
            else
            {
                $len_prev = 0;
                $max_len = null;
                foreach($p_param->service_list_current as $services)
                {
                    $len = strlen($services['name']);
                    $max_len = max($len_prev, $len);
                    $len_prev = $len;
                }
                $msg = __('launcher.STATUS_LIST', [
                    'service' => sprintf("%-{$max_len}s", $name),
                    'status' => $status,
                    'cpu' => ($cpu !== null) ? sprintf('% 3d', $cpu): '---',
                    'memory' => ($memory !== null) ? sprintf('%6.2f', $memory): '------',
                    'timestamp' => $timestamp,
                    'pid' => $id
                ]);
            }
            printf($msg."\n");

            // 終了判定
            if(++$p_param->idx_process >= $p_param->max_process)
            {
                $p_param->logWriter('debug', ['プロセスリスト by MEMORY_CHECK' => print_r($p_param->service_list_current, true)]);
                return $p_param->getIdlingStatus($p_param->order_action_current);
            }

            return RuntimeStatusEnumForLauncher::EXPLORE_STATUS->value;
        };
    }

    /**
     * ステータス名： CPU_INFO
     * 
     * 処理名：[Windows]CPU情報表示
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupWindowsCpuInfo()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'WINDOWS_CPU_INFO']);

            $cmd = 'powershell -Command "Get-WmiObject Win32_Processor | Select-Object Name, SocketDesignation, NumberOfCores, NumberOfLogicalProcessors | ConvertTo-Json"';
            exec($cmd, $output, $exit_code);
            if($exit_code !== 0 || empty($output))
            {
                $msg = _('launcher.ERROR_CPU_INFO');
                printf($msg);
                goto finish_for_windows_cpuinfo;
            }

            $json = implode("\n", $output);
            $data = json_decode($json, true);

            // 単一CPUの場合でも配列化
            if (!isset($data[0]))
            {
                $data = [$data];
            }

            $total_sockets = count($data);
            $total_cores = 0;
            $total_logical = 0;
            $cpu_name = '';
            $logical_ids = [];

            foreach ($data as $cpu) {
                $total_cores += $cpu['NumberOfCores'];
                $total_logical += $cpu['NumberOfLogicalProcessors'];
                $cpu_name = $cpu['Name'];
            }

            // 論理CPU ID一覧（0〜N-1）
            for ($i = 0; $i < $total_logical; $i++) {
                $logical_ids[] = $i;
            }

            // HT 判定
            $ht_enabled = ($total_logical > $total_cores) ? '有効' : '無効';

            // アーキテクチャ取得
            $arch_cmd = 'powershell -Command "$env:PROCESSOR_ARCHITECTURE"';
            exec($arch_cmd, $arch_out, $arch_code);
            $arch = $arch_code === 0 && !empty($arch_out) ? trim($arch_out[0]) : '不明';

            $msg = __('launcher.CPU_INFO', [
                'sockets' => $total_sockets,
                'total_cores' => $total_cores,
                'cores' => $total_cores/$total_sockets,
                'times' => $total_sockets,
                'logical' => $total_logical,
                'id_range' => implode('-', [$logical_ids[0], end($logical_ids)]),
                'cpu_name' => $cpu_name,
                'ht' => $ht_enabled,
                'arch' => $arch,
            ]);
            printf($msg);

finish_for_windows_cpuinfo:
            return $p_param->getIdlingStatus($p_param->order_action_current);
        };
    }

    /**
     * ステータス名： EXPLORE_START
     * 
     * 処理名：[Linux]サービス調査
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupLinuxExploreForStarting()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'LINUX_EXPLORE_START']);

            $i = $p_param->idx_process;

            $name = $p_param->service_list_current[$i]['name'];
            $pid_file = $p_param->pid_path_for_service."/{$name}";

            $pid = $p_param->service_list_current[$i]['pid'];
            if($pid === null)
            {
                if(file_exists($pid_file))
                {
                    $pid = (int)file_get_contents($pid_file);

                    // サービスの死活確認
                    $w_ret = $p_param->getActivityForLinux($pid, $p_param->service_list_current[$i]['command']);
                    if($w_ret === false)
                    {
                        return RuntimeStatusEnumForLauncher::PROCESS_START->value;
                    }

                    // プロセスIDの設定
                    $p_param->service_list_current[$i]['pid'] = $pid;
                    $max = count($p_param->service_list_all);
                    for($j = 0; $j < $max; $j++)
                    {
                        if($p_param->service_list_all[$j]['name'] === $name)
                        {
                            $p_param->service_list_all[$j]['pid'] = $pid;
                        }
                    }
                }
                else
                {
                    return RuntimeStatusEnumForLauncher::PROCESS_START->value;
                }
            }
            else
            {
                if($p_param->order_action_current['action'] === LauncherAction::AUTO_RESTART->value)
                {
                    // サービスの死活確認
                    $w_ret = $p_param->getActivityForLinux($pid, $p_param->service_list_current[$i]['command']);
                    if($w_ret === true)
                    {
                        goto finish_explore_for_linux_starting;
                    }

                    // 停止検知メッセージ
                    $msg = __('launcher.ERROR_DETECT_STOP', ['service' => $name]);
                    $p_param->logWriter('error', ['type' => LauncherAction::DETECT_STOP->value, 'message' => $msg, 'via' => 'watchdog', 'who' => null, 'pid' => null]);

                    return RuntimeStatusEnumForLauncher::PROCESS_START->value;
                }
            }

            // メッセージ出力
            $msg = __('launcher.NOTICE_RUNNING_SERVICE', ['service' => $name]);
            $p_param->logWriter('notice', ['type' => 'operator-'.$p_param->order_action_current['action'], 'message' => $msg, 'via' => $p_param->order_action_current['via'], 'who' => null, 'pid' => $p_param->service_list_current[$i]['pid']]);

finish_explore_for_linux_starting:

            // 終了判定
            if(++$p_param->idx_process >= $p_param->max_process)
            {
                $p_param->logWriter('debug', ['プロセスリスト by EXPLORE_START' => print_r($p_param->service_list_all, true)]);
                return $p_param->getIdlingStatus($p_param->order_action_current);
            }

            return RuntimeStatusEnumForLauncher::EXPLORE_START->value;
        };
    }

    /**
     * ステータス名： PROCESS_START
     * 
     * 処理名：Linux上での起動処理
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupLinuxProcessStart()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'LINUX_PROCESS_START']);

            $i = $p_param->idx_process;
            $name = $p_param->service_list_current[$i]['name'];

            // bashコマンド構築（バックグラウンド起動＋PID出力）
            $command = escapeshellcmd($p_param->service_list_current[$i]['command']);
            $bash = "bash -c '{$command} > /dev/null 2>&1 & echo \$!'";

            // 実行してPID取得
            $dir_cur = getcwd();
            $path = $p_param->service_list_current[$i]['path'];
            chdir($path);
            exec($bash, $output, $code);
            chdir($dir_cur);

            if($code !== 0 || empty($output))
            {
                // メッセージ出力
                $msg = __('launcher.ERROR_STARTED_SERVICE_FAILED', ['service' => $p_param->order_action_current['name']]);
                $p_param->logWriter('error', ['type' => 'operator-'.$p_param->order_action_current['action'], 'message' => $msg, 'via' => $p_param->order_action_current['via'], 'who' => null, 'pid' => null]);
                goto finish_process_for_linux_start;
            }
            else
            {
                $p_param->service_list_current[$i]['pid'] = (int)trim($output[0]);
            }
            file_put_contents("./pids/{$p_param->service_list_current[$i]['name']}", $p_param->service_list_current[$i]['pid']);

            // アフィニティマスクの設定
            $mask = 0;
            foreach($p_param->service_list_current[$i]['cores'] ?? [] as $core)
            {
                $w_mask = 1;
                $w_mask <<= $core;
                $mask |= $w_mask;
            }

            if($mask !== 0)
            {
                // 16進数マスクに変換（例: 0b101 → 0x5）
                $hex_mask = dechex($mask);

                // アフィニティ設定
                $cmd = "taskset -p 0x{$hex_mask} {$p_param->service_list_current[$i]['pid']}";
                exec($cmd, $output, $code);
                if($code !== 0)
                {
                    // メッセージ出力
                    $msg = __('launcher.WARNING_CPU_ASSIGNMENT_FAILED', ['service' => $p_param->service_list_current[$i]['name']]);
                    $p_param->logWriter('warning', ['type' => 'operator-'.$p_param->order_action_current['action'], 'message' => $msg, 'via' => $p_param->order_action_current['via'], 'who' => null, 'pid' => $p_param->service_list_current[$i]['pid']]);
                }
            }

            $max = count($p_param->service_list_all);
            for($j = 0; $j < $max; $j++)
            {
                if($p_param->service_list_all[$j]['name'] === $name)
                {
                    $p_param->service_list_all[$j]['pid'] = $p_param->service_list_current[$i]['pid'];
                }
            }

            if($p_param->order_action_current['action'] === LauncherAction::AUTO_RESTART->value)
            {
                // 停止検知メッセージ
                $msg = __('launcher.NOTICE_AUTO_RESTART', ['service' => $p_param->service_list_current[$i]['name']]);
                $p_param->logWriter('notice', ['type' => $p_param->order_action_current['action'], 'message' => $msg, 'via' => 'watchdog', 'who' => null, 'pid' => $p_param->service_list_current[$i]['pid']]);
            }
            else
            {
                // メッセージ出力
                $msg = __('launcher.INFO_STARTED_SERVICE', ['service' => $p_param->service_list_current[$i]['name']]);
                $p_param->logWriter('info', ['type' => 'operator-'.$p_param->order_action_current['action'], 'message' => $msg, 'via' => $p_param->order_action_current['via'], 'who' => null, 'pid' => $p_param->service_list_current[$i]['pid']]);
            }

finish_process_for_linux_start:

            // 終了判定
            if(++$p_param->idx_process >= $p_param->max_process)
            {
                $p_param->logWriter('debug', ['プロセスリスト by PROCESS_START' => print_r($p_param->service_list_current, true)]);
                return $p_param->getIdlingStatus($p_param->order_action_current);
            }

            return RuntimeStatusEnumForLauncher::EXPLORE_START->value;
        };
    }

    /**
     * ステータス名： EXPLORE_STOP
     * 
     * 処理名：[Linux]サービス調査
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupLinuxExploreForStopping()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'LINUX_EXPLORE_STOP']);

            $i = $p_param->idx_process;

            $name = $p_param->service_list_current[$i]['name'];
            $pid_file = $p_param->pid_path_for_service."/{$name}";

            $pid = $p_param->service_list_current[$i]['pid'];
            if($pid === null)
            {
                if(file_exists($pid_file))
                {
                    $pid = (int)file_get_contents($pid_file);

                    // サービスの死活確認
                    $w_ret = $p_param->getActivityForLinux($pid, $p_param->service_list_current[$i]['command']);
                    if($w_ret === true)
                    {
                        // プロセスIDの設定
                        $p_param->service_list_current[$i]['pid'] = $pid;
                        $max = count($p_param->service_list_all);
                        for($j = 0; $j < $max; $j++)
                        {
                            if($p_param->service_list_all[$j]['name'] === $name)
                            {
                                $p_param->service_list_all[$j]['pid'] = $pid;
                            }
                        }
                        return RuntimeStatusEnumForLauncher::PROCESS_STOP->value;
                    }
                }
            }
            else
            {
                return RuntimeStatusEnumForLauncher::PROCESS_STOP->value;
            }

            // メッセージ出力
            $msg = __('launcher.NOTICE_STOPPING_SERVICE', ['service' => $name]);
            $p_param->logWriter('notice', ['type' => 'operator-'.$p_param->order_action_current['action'], 'message' => $msg, 'via' => $p_param->order_action_current['via'], 'who' => null, 'pid' => $p_param->service_list_current[$i]['pid']]);

            // 終了判定
            if(++$p_param->idx_process >= $p_param->max_process)
            {
                $p_param->logWriter('debug', ['プロセスリスト by EXPLORE_STOP' => print_r($p_param->service_list_all, true)]);
                return $p_param->getIdlingStatus($p_param->order_action_current);
            }

            return RuntimeStatusEnumForLauncher::EXPLORE_STOP->value;
        };
    }

    /**
     * ステータス名： PROCESS_STOP
     * 
     * 処理名：[Linux]プロセス停止
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupLinuxProcessStop()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'LINUX_PROCESS_STOP']);

            $i = $p_param->idx_process;
            $pid = $p_param->service_list_current[$i]['pid'];
            $this->timer_start = microtime(true);
            exec("kill {$pid}", $output, $this->exit_code);

            return RuntimeStatusEnumForLauncher::STOP_POLLING->value;
        };
    }

    /**
     * ステータス名： STOP_POLLING
     * 
     * 処理名：[Linux]プロセス停止ポーリング
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupLinuxStopPolling()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'LINUX_STOP_POLLING']);

            if((microtime(true) - $this->timer_start) * 1000 < $this->stop_interval)
            {
                return RuntimeStatusEnumForLauncher::STOP_POLLING->value;
            }

            $i = $p_param->idx_process;
            $log_pid = null; $pid = $p_param->service_list_current[$i]['pid'];
            $name = $p_param->service_list_current[$i]['name'];
            exec("kill -0 {$pid}", $output, $status);

            if($this->exit_code === 0 && $status !== 0)
            {

                // メッセージ出力
                $msg = __('launcher.INFO_STOPPED_SERVICE', ['service' => $name]);
                $level = 'info';
                $log_pid = $pid;
                $pid = null;
            }
            else
            if($this->exit_code !== 0 && $status !== 0)
            {

                // メッセージ出力
                $msg = __('launcher.NOTICE_NOT_FOUND_SERVICE', ['service' => $name]);
                $level = 'notice';
                $log_pid = $pid;
                $pid = null;
            }
            else
            if($status === 0)
            {

                // メッセージ出力
                $msg = __('launcher.ERROR_STOP_FAILED', ['service' => $name]);
                $level = 'error';
                $log_pid = $pid;
                $pid = null;
            }
            else
            {

                // メッセージ出力
                $msg = __('launcher.ERROR_NOT_DETECTED', ['service' => $name]);
                $level = 'error';
                $log_pid = $pid;
                $pid = null;
            }

            $max = count($p_param->service_list_all);
            for($j = 0; $j < $max; $j++)
            {
                if($p_param->service_list_all[$j]['name'] === $name)
                {
                    $p_param->service_list_all[$j]['pid'] = $pid;
                }
            }

            $p_param->logWriter($level, ['type' => 'operator-'.$p_param->order_action_current['action'], 'message' => $msg, 'via' => $p_param->order_action_current['via'], 'who' => null, 'pid' => $log_pid]);

            // 終了判定
            if(++$p_param->idx_process >= $p_param->max_process)
            {
                $p_param->logWriter('debug', ['プロセスリスト by STOP_POLLING' => print_r($p_param->service_list_all, true)]);
                return $p_param->getIdlingStatus($p_param->order_action_current);
            }

            return RuntimeStatusEnumForLauncher::EXPLORE_STOP->value;
        };
    }

    /**
     * ステータス名： EXPLORE_STATUS
     * 
     * 処理名：[Linux]サービス調査（statusアクション用）
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupLinuxExploreForStatus()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'LINUX_EXPLORE_STATUS']);

            $i = $p_param->idx_process;

            $name = $p_param->service_list_current[$i]['name'];
            $pid_file = $p_param->pid_path_for_service."/{$name}";

            $pid = $p_param->service_list_current[$i]['pid'];
            if($pid === null)
            {
                $p_param->service_list_current[$i]['timestamp'] = null;

                if(file_exists($pid_file))
                {
                    $pid = (int)file_get_contents($pid_file);

                    // サービスの死活確認
                    $w_ret = $p_param->getActivityForLinux($pid, $p_param->service_list_current[$i]['command']);
                    if($w_ret === false)
                    {
                        goto finish_explore_for_linux_status;
                    }

                    $timestamp = filemtime($pid_file);
                    $p_param->service_list_current[$i]['timestamp'] = date("Y-m-d H:i:s", $timestamp);
                }
                else
                {
                    goto finish_explore_for_linux_status;
                }
            }
            else
            {
                $timestamp = filemtime($pid_file);
                $p_param->service_list_current[$i]['timestamp'] = date("Y-m-d H:i:s", $timestamp);

                // サービスの死活確認
                $w_ret = $p_param->getActivityForLinux($pid, $p_param->service_list_current[$i]['command']);
                if($w_ret === false)
                {
                    goto finish_explore_for_linux_status;
                }
            }

            // プロセスIDの設定
            $p_param->service_list_current[$i]['pid'] = $pid;
            $max = count($p_param->service_list_all);
            for($j = 0; $j < $max; $j++)
            {
                if($p_param->service_list_all[$j]['name'] === $name)
                {
                    $p_param->service_list_all[$j]['pid'] = $pid;
                }
            }

finish_explore_for_linux_status:
            return RuntimeStatusEnumForLauncher::CPU_CHECK->value;
        };
    }

    /**
     * ステータス名： CPU_CHECK
     * 
     * 処理名：[Linux]CPU稼働率取得
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupLinuxCpuCheck()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'LINUX_CPU_CHECK']);

            $cpu = null;
            $i = $p_param->idx_process;
            $pid = $p_param->service_list_current[$i]['pid'];
            if($pid !== null)
            {
                $cmd = "ps -p {$pid} -o %cpu=";
                exec($cmd, $output, $code);
                if($code === 0 && !empty($output))
                {
                    $cpu = round((float)trim($output[0]), 2);
                }
            }
            $p_param->service_list_current[$i]['cpu'] = $cpu;

            return RuntimeStatusEnumForLauncher::MEMORY_CHECK->value;
        };
    }

    /**
     * ステータス名： MEMORY_CHECK
     * 
     * 処理名：[Linux]メモリ使用率取得
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupLinuxMemoryCheck()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'LINUX_MEMORY_CHECK']);

            $w_memory = null;
            $i = $p_param->idx_process;
            $pid = $p_param->service_list_current[$i]['pid'];
            if($pid !== null)
            {
                $cmd = "ps -p {$pid} -o %mem=";
                exec($cmd, $output, $code);
                if($code === 0 && !empty($output))
                {
                    $w_memory = round((float)trim($output[0]), 2);
                }
            }

            $name = $p_param->service_list_current[$i]['name'];
            $status = $p_param->colorText('起動中').' ';
            $cpu = $p_param->service_list_current[$i]['cpu'];
            $memory = $w_memory;
            $timestamp = $p_param->service_list_current[$i]['timestamp'];
            $id = $pid;
            $cores = $p_param->service_list_current[$i]['cores'];
            $group = $p_param->service_list_current[$i]['group'];
            $path = $p_param->service_list_current[$i]['path'];
            $command = $p_param->service_list_current[$i]['command'];

            if($cores === null)
            {
                $cores = '自動';
            }
            else
            {
                $cores = json_encode($cores);
            }

            if($pid === null)
            {
                $status = $p_param->colorText('停止中', 31).' ';
                $cpu = null;
                $memory = null;
                $timestamp = '-------------------';
                $id = '-----';
                $cores = str_repeat('-', strlen($cores));
            }
            if($p_param->order_action_current['action'] === LauncherAction::STATUS->value && $p_param->order_action_current['service_key'] !== 'group')
            {
                $msg = __('launcher.STATUS_DETAIL', [
                    'service' => $name,
                    'status' => $status,
                    'cpu' => ($cpu !== null) ? sprintf('%d', $cpu): '---',
                    'memory' => ($memory !== null) ? sprintf('%.2f', $memory): '------',
                    'timestamp' => $timestamp,
                    'pid' => $id,
                    'cores' => $cores,
                    'group' => $group,
                    'path' => $path,
                    'command' => $command
                ]);
            }
            else
            {
                $len_prev = 0;
                $max_len = null;
                foreach($p_param->service_list_current as $services)
                {
                    $len = strlen($services['name']);
                    $max_len = max($len_prev, $len);
                    $len_prev = $len;
                }
                $msg = __('launcher.STATUS_LIST', [
                    'service' => sprintf("%-{$max_len}s", $name),
                    'status' => $status,
                    'cpu' => ($cpu !== null) ? sprintf('% 3d', $cpu): '---',
                    'memory' => ($memory !== null) ? sprintf('%6.2f', $memory): '------',
                    'timestamp' => $timestamp,
                    'pid' => $id
                ]);
            }
            printf($msg."\n");

            // 終了判定
            if(++$p_param->idx_process >= $p_param->max_process)
            {
                $p_param->logWriter('debug', ['プロセスリスト by MEMORY_CHECK' => print_r($p_param->service_list_current, true)]);
                return $p_param->getIdlingStatus($p_param->order_action_current);
            }

            return RuntimeStatusEnumForLauncher::EXPLORE_STATUS->value;
        };
    }

    /**
     * ステータス名： CPU_INFO
     * 
     * 処理名：[Linux]CPU情報表示
     * 
     * @param ParameterForLauncher $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupLinuxCpuInfo()
    {
        return function(ParameterForLauncher $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'LINUX_CPU_INFO']);

            // lscpu 出力を取得
            exec('lscpu', $output, $code);
            if($code !== 0 || empty($output))
            {
                $msg = _('launcher.ERROR_CPU_INFO');
                printf($msg."\n");
                goto finish_for_linux_cpuinfo;
            }

            $info = [];
            foreach($output as $line)
            {
                if(strpos($line, ':') === false)
                {
                    continue;
                }
                [$key, $value] = explode(':', $line, 2);
                $info[trim($key)] = trim($value);
            }

            // 必要項目の抽出
            $sockets = (int)($info['Socket(s)'] ?? 1);

            $cores_per_socket = (int)($info['Core(s) per socket'] ?? 1);
            $threads_per_core = (int)($info['Thread(s) per core'] ?? 1);
            $logical_cpus = (int)($info['CPU(s)'] ?? 1);
            $arch = $info['Architecture'] ?? '不明';
            $model = $info['Model name'] ?? '不明';

            // HT判定（論理CPU数 > 物理コア数）
            $total_cores = $sockets * $cores_per_socket;
            $ht = ($logical_cpus > $total_cores) ? '有効' : '無効';

            // 論理CPU ID一覧
            $logical_ids = range(0, $logical_cpus - 1);

            $msg = __('launcher.CPU_INFO', [
                'sockets' => $sockets,
                'total_cores' => $total_cores,
                'cores' => $total_cores/$sockets,
                'times' => $sockets,
                'logical' => $logical_cpus,
                'id_range' => implode('-', [$logical_ids[0], end($logical_ids)]),
                'cpu_name' => $model,
                'ht' => $ht,
                'arch' => $arch,
            ]);
            printf($msg."\n");

finish_for_linux_cpuinfo:
            return $p_param->getIdlingStatus($p_param->order_action_current);
        };
    }

}
