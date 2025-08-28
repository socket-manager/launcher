<?php
/**
 * RuntimeManager初期化クラスのファイル
 * 
 * RuntimeManagerのsetInitRuntimeManagerメソッドへ引き渡される初期化クラスのファイル
 */

namespace App\InitClass;

use SocketManager\Library\IInitRuntimeManager;
use SocketManager\Library\RuntimeManagerParameter;

use App\UnitParameter\ParameterForLauncher;


/**
 * RuntimeManager初期化クラス
 * 
 * IInitRuntimeManagerインタフェースをインプリメントする
 */
class InitForLauncher implements IInitRuntimeManager
{
    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * @var bool $stdout_enabled 標準出力制御
     */
    private bool $stdout_enabled = false;

    /**
     * @var string $log_cycle ログ保存期間
     */
    private string $log_cycle = 'daily';

    /**
     * @var ?string $log_path_for_launcher ランチャー用ログファイルのパス
     */
    private ?string $log_path_for_launcher = null;

    /**
     * @var ?string $log_path_for_debug デバッグ用ログファイルのパス
     */
    private ?string $log_path_for_debug = null;

    /**
     * @var ?ParameterForLauncher $unit_parameter UNITパラメータインスタンス
     */
    private ?ParameterForLauncher $unit_parameter = null;


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     * @param string $p_via ランチャーモード
     * @param ParameterForLauncher $p_unit_parameter UNITパラメータインスタンス
     */
    public function __construct(string $p_via, ParameterForLauncher $p_unit_parameter)
    {
        // 標準出力制御の取得
        $stdout_enabled = config('launcher.stdout_enabled');
        if(is_string($stdout_enabled) && $stdout_enabled === 'auto')
        {
            if($p_via === 'CLI')
            {
                $this->stdout_enabled = true;
            }
        }
        else
        {
            $this->stdout_enabled = $stdout_enabled;
        }

        // ログ保存期間の取得
        $log_cycle = config('launcher.log_cycle', 'daily');
        if($log_cycle === 'daily')
        {
            $this->log_cycle = 'Ymd';
        }
        else
        if($log_cycle === 'monthly')
        {
            $this->log_cycle = 'Ym';
        }

        // ランチャー用ログファイルのパス
        $this->log_path_for_launcher = config('launcher.log_path_for_launcher');

        // デバッグ用ログファイルのパス
        $this->log_path_for_debug = config('const.log_path_for_debug');

        // UNITパラメータインスタンスの取得
        $this->unit_parameter = $p_unit_parameter;
    }

    /**
     * ログライターの取得
     * 
     * nullを返す場合は無効化（但し、ライブラリ内部で出力されているエラーメッセージも出力されない）
     * 
     * @return mixed "function(string $p_level, array $p_param): void" or null（ログ出力なし）
     */
    public function getLogWriter()
    {
        return function(string $p_level, array $p_param)
        {
            if($p_level === 'debug')
            {
                // return;@@@
            }
            if(isset($p_param['type']) && isset($p_param['message']))
            {
                $filename = date($this->log_cycle);
                $timestamp = date('Y-m-d H:i:s');
                if($p_param['pid'] === null)
                {
                    $p_param['pid'] = 'ー';
                }
                $add_item = 
                [
                    'timestamp' => $timestamp,
                    'level' => $p_level
                ];
                $all_item = array_merge($add_item, $p_param);

                if($this->unit_parameter->cli_flg === false)
                {
                    $this->unit_parameter->param_websocket->noticeLauncherLog($all_item);
                }

                $log = json_encode($all_item)."\n";
                error_log($log, 3, "{$this->log_path_for_launcher}/{$filename}.log");

                if($this->stdout_enabled)
                {
                    printf($p_param['message']."\n");
                }
            }
            else
            {
                $filename = date('Ymd');
                $now = date('Y-m-d H:i:s');
                $log = $now." {$p_level} ".print_r($p_param, true)."\n";
                error_log($log, 3, "{$this->log_path_for_debug}/{$filename}.log");
            }
        };
    }

    /**
     * 緊急停止時のコールバックの取得
     * 
     * 例外等の緊急停止時に実行される。nullを返す場合は無効化となる。
     * 
     * @return mixed "function(RuntimeManagerParameter $p_param)"
     */
    public function getEmergencyCallback()
    {
        return null;
    }

    /**
     * UNITパラメータインスタンスの取得
     * 
     * nullの場合はRuntimeManagerParameterのインスタンスが適用される
     * 
     * @return ?RuntimeManagerParameter RuntimeManagerParameterクラスのインスタンス（※1）
     * @see:RETURN （※1）当該クラス、あるいは当該クラスを継承したクラスも指定可
     */
    public function getUnitParameter(): ?RuntimeManagerParameter
    {
        return $this->unit_parameter;
    }
}
