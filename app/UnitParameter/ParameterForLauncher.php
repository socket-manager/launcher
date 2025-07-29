<?php
/**
 * UNITパラメータクラスのファイル
 * 
 * UNITパラメータとしての利用と共にグローバル領域としても活用
 */

namespace App\UnitParameter;

use SocketManager\Library\RuntimeManagerParameter;

use App\RuntimeUnits\RuntimeQueueEnumForLauncher;
use App\RuntimeUnits\RuntimeStatusEnumForLauncher;


/**
 * ランチャーアクション定義
 * 
 * 起動・停止などの基本的なアクションの定義
 */
enum LauncherAction: string
{
    /**
     * @var 起動アクション
     */
    case START = 'start';

    /**
     * @var 全起動アクション
     */
    case START_ALL = 'startall';

    /**
     * @var 停止アクション
     */
    case STOP = 'stop';

    /**
     * @var 全停止アクション
     */
    case STOP_ALL = 'stopall';

    /**
     * @var 再起動アクション
     */
    case RESTART = 'restart';

    /**
     * @var 全再起動アクション
     */
    case RESTART_ALL = 'restartall';

    /**
     * @var ステータス表示アクション
     */
    case STATUS = 'status';

    /**
     * @var 全ステータス表示アクション
     */
    case STATUS_ALL = 'statusall';

    /**
     * @var CPU情報表示
     */
    case CPU_INFO = 'cpuinfo';


    /**
     * 内部アクション
     */

    /**
     * @var 自動再起動
     */
    case AUTO_RESTART = 'auto-restart';

    /**
     * @var 停止検知
     */
    case DETECT_STOP = 'detect-stop';

};

/**
 * UNITパラメータクラス
 * 
 * UNITパラメータクラスのRuntimeManagerParameterをオーバーライドする
 */
class ParameterForLauncher extends RuntimeManagerParameter
{
    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * @var bool $cli_flg CLI起動フラグ（true:CLI起動 or false:GUI起動）
     */
    public bool $cli_flg = true;

    /**
     * @var bool $auto_restart 自動再起動フラグ
     */
    public bool $auto_restart = false;

    /**
     * オーダーアクションスタック
     * 
     * @var array $order_action_stack FIFO形式で管理
     * 
     * ---
     * 連想配列の項目名は次の通り
     * 
     * - action         アクション名（start、stopなど）
     * - service_key    サービス項目名（name、groupなど）
     * - service_name   サービス名
     * - via            経由（CLI、GUIなど）
     * - who            一人称
     */
    private array $order_action_stack = [];

    /**
     * @var array $order_action_current 処理中のオーダーアクション
     */
    public ?array $order_action_current = null;

    /**
     * @var array $service_list_all プロセス監視リスト（全量）
     */
    public array $service_list_all = [];

    /**
     * @var array $service_list_current 処理中のプロセス監視リスト
     */
    public array $service_list_current = [];

    /**
     * @var int $max_process プロセス件数
     */
    public int $max_process = 0;

    /**
     * @var int $idx_process 処理中プロセス指標
     */
    public int $idx_process = 0;

    /**
     * @var ?int $max_core 最大コア数
     */
    public ?int $max_core = null;

    /**
     * @var ?int $max_mask 全コアのアフィニティマスク
     */
    public ?int $max_mask = null;

    /**
     * @var string $pid_path_for_service サービスのプロセスIDファイルの所在
     */
    public string $pid_path_for_service = '';


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     * @param string $p_via ランチャーモード
     * @param bool $p_auto_restart 自動再起動フラグ
     * @param ?array $p_service_list サービスリスト
     * @param ?string $p_lang 言語コード
     */
    public function __construct(string $p_via, bool $p_auto_restart, ?array $p_service_list = null, ?string $p_lang = null)
    {
        parent::__construct($p_lang);

        // サービスのプロセスIDファイルの所在を取得
        $this->pid_path_for_service = config('const.pid_path_for_service');

        // CLI起動フラグの設定
        $this->cli_flg = false;
        if($p_via === 'CLI')
        {
            $this->cli_flg = true;
        }

        // 自動再起動フラグの設定
        $this->auto_restart = $p_auto_restart;

        // サービスリストの設定
        $this->setServiceList($p_service_list);
    }

    /**
     * プロパティセッター
     */

    /**
     * サービスリストの設定
     * 
     * @param ?array $p_service_list サービスリスト
     */
    public function setServiceList(?array $p_service_list)
    {
        if($p_service_list !== null)
        {
            $this->service_list_all = $p_service_list;
            $max = count($this->service_list_all);
            for($i = 0; $i < $max; $i++)
            {
                $this->service_list_all[$i]['pid'] = null;
            }
        }
    }

    /**
     * コマンドライン引数のチェック
     */

    /**
     * アクション指定の検査
     * 
     * @param string|false $p_action actionパラメータ
     * @param ?string &$p_message エラーメッセージ
     * @return bool true（成功） or false（失敗）
     */
    public function checkAction(string|false $p_action, ?string &$p_message): bool
    {
        // パラメータの存在確認
        if($p_action === false)
        {
            $p_message = __('launcher.ERROR_ACTION');
            goto fail;
        }

        // パラメータ名一致確認
        switch($p_action)
        {
            case LauncherAction::START->value:
            case LauncherAction::START_ALL->value:
            case LauncherAction::STOP->value:
            case LauncherAction::STOP_ALL->value:
            case LauncherAction::RESTART->value:
            case LauncherAction::RESTART_ALL->value:
            case LauncherAction::STATUS->value:
            case LauncherAction::STATUS_ALL->value:
                if(count($this->service_list_all) <= 0)
                {
                    $p_message = __('launcher.NEED_SERVICES_FILE', ['action' => $p_action]);
                    goto fail;
                }
            case LauncherAction::CPU_INFO->value:
                goto succeed;
            default:
                break;
        }

fail:
        return false;

succeed:
        return true;
    }

    /**
     * サービス指定の検査
     * 
     * @param string|null $p_service serviceパラメータ
     * @param ?string &$p_key サービス項目名（name、groupなど）
     * @param ?string &$p_name サービス名
     * @param ?string &$p_message エラーメッセージ
     * @return bool true（成功） or false（失敗）
     */
    public function checkService(string|null $p_service, ?string &$p_key, ?string &$p_name, ?string &$p_message): bool
    {
        // パラメータの存在確認
        if($p_service === null)
        {
            goto fail;
        }

        // サービス名／グループ名の判別
        $p_key = 'name';
        $p_name = $p_service;
        if(preg_match('/^group:(.+)$/', $p_service, $matches))
        {
            $p_key = 'group';
            $p_name = $matches[1];
        }

        // 設定ファイル上の存在チェック
        $exist_flg = false;
        $service_list = $this->service_list_all;
        foreach($service_list as $service)
        {
            if($service[$p_key] === $p_name)
            {
                $exist_flg = true;
            }
        }
        if($exist_flg !== true)
        {
            goto fail;
        }

succeed:
        return true;

fail:
        $p_message = __('launcher.ERROR_SERVICE');
        return false;
    }

    /**
     * オーダーされているアクション情報へのアクセスメソッド
     */

    /**
     * オーダーアクション情報の取得
     * 
     * @return ?array オーダーアクション情報 or null（空の場合）
     */
    public function getOrderAction(): ?array
    {
        $order_action = array_shift($this->order_action_stack);
        return $order_action;
    }

    /**
     * オーダーアクション情報の設定
     * 
     * @param string|false $p_action アクション名
     * @param ?string $p_service サービス名
     * @param ?string $p_via 経由（CLI or GUI）
     * @param ?string $p_who 一人称
     * @param ?string &$p_message エラーメッセージ
     * @return bool true（成功） or false（失敗）
     */
    public function setOrderAction(string|false $p_action, ?string $p_service, ?string $p_via, ?string $p_who, ?string &$p_message): bool
    {
        // actionパラメータをチェック
        $w_ret = $this->checkAction($p_action, $p_message);
        if($w_ret === false)
        {
            return false;
        }

        // serviceパラメータをチェック
        $service_key = $service_name = null;
        if(
                $p_action !== LauncherAction::START_ALL->value
            &&  $p_action !== LauncherAction::STOP_ALL->value
            &&  $p_action !== LauncherAction::RESTART_ALL->value
            &&  $p_action !== LauncherAction::STATUS_ALL->value
            &&  $p_action !== LauncherAction::CPU_INFO->value
        )
        {
            $w_ret = $this->checkService($p_service, $service_key, $service_name, $p_message);
            if($w_ret === false)
            {
                return false;
            }
        }

        // オーダーアクションの設定
        $this->order_action_stack[] =
        [
            'action' => $p_action,
            'service_key' => $service_key,
            'service_name' => $service_name,
            'via' => $p_via,
            'who' => $p_who
        ];

        return true;
    }

    /**
     * オーダー内部アクション情報の設定
     * 
     * @param string $p_action アクション名
     * @param ?string $p_service_key サービス項目（'name' or 'group'）
     * @param ?string $p_service_name サービス名
     * @param ?string $p_via 経由（CLI or GUI）
     * @param ?string $p_who 一人称
     */
    public function setOrderInternalAction(string $p_action, ?string $p_service_key, ?string $p_service_name, ?string $p_via, ?string $p_who)
    {
        // オーダーアクションの設定
        $this->order_action_stack[] =
        [
            'action' => $p_action,
            'service_key' => $p_service_key,
            'service_name' => $p_service_name,
            'via' => $p_via,
            'who' => $p_who
        ];

        return;
    }

    /**
     * その他
     */

    /**
     * ランチャーの終了
     * 
     */
    public function finishLauncher(): void
    {
        if($this->cli_flg === true && $this->auto_restart !== true)
        {
            $this->emergencyShutdown();
        }
    }

    /**
     * アイドリングステータスの取得
     * 
     * @param array $p_order_action オーダーアクションの連想配列
     */
    public function getIdlingStatus(array $p_order_action): ?string
    {
        if(
                $this->auto_restart === true
            &&  $p_order_action['action'] !== LauncherAction::STOP->value
            &&  $p_order_action['action'] !== LauncherAction::STOP_ALL->value
            &&  $p_order_action['action'] !== LauncherAction::STATUS->value
            &&  $p_order_action['action'] !== LauncherAction::STATUS_ALL->value
            &&  $p_order_action['action'] !== LauncherAction::CPU_INFO->value
        )
        {
            $this->setOrderInternalAction(
                LauncherAction::AUTO_RESTART->value,
                $p_order_action['service_key'],
                $p_order_action['service_name'],
                $p_order_action['via'],
                $p_order_action['who']
            );
        }
        return RuntimeStatusEnumForLauncher::START->value;
    }

    /**
     * [Windows]サービス死活確認
     * 
     * @param int $p_pid プロセスID
     * @param string $p_command コマンド文
     * @return bool true（活性） or false（非活性）
     */
    public function getActivityForWindows(int $p_pid, string $p_command): bool
    {
        preg_match('/^[^\s]+\s+(.*)$/', $p_command, $matches);
        $keyword = ".*{$matches[1]}.*";
        $cmd = "powershell -Command \"Get-WmiObject Win32_Process | Where-Object { \$_.CommandLine -match '{$keyword}' } | Select-Object ProcessId, ParentProcessId, CommandLine\"";
        $output = [];
        exec($cmd, $output);
        foreach($output as $row)
        {
            if(preg_match('/^\s*(\d+)\s+(\d+)\s+(.*)$/', $row, $matches))
            {
                $match_pid = (int)$matches[1];
                if($p_pid === $match_pid)
                {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * [Linux]サービス死活確認
     * 
     * @param int $p_pid プロセスID
     * @param string $p_command コマンド文
     * @return bool true（活性） or false（非活性）
     */
    public function getActivityForLinux(int $p_pid, string $p_command): bool
    {
        $output = [];
        exec('ps -eo pid,args', $output);
        foreach($output as $line)
        {
            if(preg_match('/^\s*(\d+)\s+(.*)$/', $line, $matches))
            {
                $pid = (int)$matches[1];
                $command = $matches[2];
                if(strpos($command, $p_command) !== false && $pid === $p_pid)
                {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * テキスト色の設定
     * 
     * @param string $p_text テキスト
     * @param int $p_fg フォアグランドカラー
     * @param ?int $p_bg バックグランドカラー
     * @param bool $p_bold 太字設定
     * @return string 色設定後のテキスト
     */
    public function colorText(string $p_text, int $p_fg = 32, ?int $p_bg = null, bool $p_bold = false) {
        $seq = "\033[" . ($p_bold ? "1;" : "") . $p_fg;
        if($p_bg !== null)
        {
            $seq .= ";" . $p_bg;
        }
        return $seq . "m" . $p_text . "\033[0m";
    }
}
