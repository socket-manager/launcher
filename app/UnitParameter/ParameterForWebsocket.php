<?php
/**
 * UNITパラメータクラスのファイル
 * 
 * Websocket版
 */

namespace App\UnitParameter;

use SocketManager\Library\SocketManagerParameter;
use App\CommandUnits\CommandForWebsocketQueueEnum;
use App\RuntimeUnits\RuntimeQueueEnumForLauncher;

/**
 * UNITパラメータクラス
 * 
 * UNITパラメータクラスのSocketManagerParameterをオーバーライドする
 */
class ParameterForWebsocket extends SocketManagerParameter
{
    //--------------------------------------------------------------------------
    // 定数（first byte）
    //--------------------------------------------------------------------------

    /**
     * 最後の断片
     */
    public const CHAT_FIN_BIT_MASK = 0x80;

    /**
     * テキストフレーム
     */
    public const CHAT_OPCODE_TEXT_MASK = 0x01;

    /**
     * 切断フレーム
     */
    public const CHAT_OPCODE_CLOSE_MASK = 0x08;

    /**
     * pingフレーム
     */
    public const CHAT_OPCODE_PING_MASK = 0x09;

    /**
     * pongフレーム
     */
    public const CHAT_OPCODE_PONG_MASK = 0x0A;


    //--------------------------------------------------------------------------
    // 定数（second byte）
    //--------------------------------------------------------------------------

    /**
     * データ長マスク
     */
    public const CHAT_PAYLOAD_LEN_MASK = 0x7f;

    /**
     * データ長サイズコード（2 byte）
     */
    public const CHAT_PAYLOAD_LEN_CODE_2 = 126;

    /**
     * データ長サイズコード（8 byte）
     */
    public const CHAT_PAYLOAD_LEN_CODE_8 = 127;


    //--------------------------------------------------------------------------
    // 定数（その他）
    //--------------------------------------------------------------------------

    /**
     * 対応プロトコルバージョン
     */
    public const CHAT_PROTOCOL_VERSION = 13;

    /**
     * openingハンドシェイクのリトライ件数
     */
    public const CHAT_HANDSHAKE_RETRY = 3;

    /**
     * 受信空振り時のリトライ回数
     */
    public const CHAT_RECEIVE_EMPTY_RETRY = 10;

    /**
     * クライアント起点の切断
     */
    public const CHAT_SELF_CLOSE_CODE = 10;

    /**
     * 共通日付フォーマット
     */
    public const DATETIME_FORMAT = 'Y-m-d H:i:s';


    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * 周期インターバル（μs）
     */
    protected ?int $cycle_interval = null;

    /**
     * TLSフラグ
     */
    protected bool $tls = false;

    /**
     * launcherのUNITパラメータクラス
     */
    public ParameterForLauncher $param_launcher;

    /**
     * ユーザーリスト
     */
    public array $user_list = [];

    /**
     * オプションメッセージ
     */
    public array $option_message = [];

    /**
     * ログリスト
     */
    public array $log_list = [];

    /**
     * サービスリスト
     */
    public ?array $service_list = null;

    /**
     * リソース情報（設定値）
     */
    private ?array $resource_setting = null;

    /**
     * 閾値のレベル
     */
    private array $threshold_level = [
        'critical' => 3,
        'alert' => 2,
        'warn' => 1
    ];

    /**
     * 前回の閾値レベル
     */
    private array $prev_threshold_level =
    [
        'cpu' => 0,
        'memory' => 0,
        'disk' => null
    ];

    /**
     * リソースタイプのラベル
     */
    private array $resource_type_label =
    [
        'cpu' => 'CPU',
        'memory' => 'メモリ',
        'disk' => 'ディスク'
    ];

    /**
     * CPUリソース情報
     */
    private ?array $resource_cpu = null;

    /**
     * メモリリソース情報
     */
    private ?array $resource_memory = null;

    /**
     * ディスクリソース情報
     */
    private ?array $resource_disk = null;

    /**
     * テスト使用率（'up','down','mix'）
     */
    private ?array $resource_tests =
    [
        // ['base' => 50, 'type' => 'mix']
        'cpu' => null,
        'memory' => null,
        'disk' => null
    ];

    /**
     * 保存されていないサービス設定の存在フラグ
     */
    public bool $setting_edit_flg = false;


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     * @param int $p_cycle_interval 周期インターバル（μs）
     * @param ?bool $p_tls TLSフラグ
     */
    public function __construct(int $p_cycle_interval, ?bool $p_tls = null)
    {
        parent::__construct();

        $this->cycle_interval = $p_cycle_interval;
        if($p_tls !== null)
        {
            $this->tls = $p_tls;
        }

        $this->option_message['admin_user'] = __('launcher.OPTION_ADMIN_USER');
        $this->option_message['leaving'] = __('launcher.OPTION_LEAVING');
        $this->option_message['server_close'] = __('launcher.OPTION_SERVER_CLOSE');
        $this->option_message['unexpected_close'] = __('launcher.OPTION_UNEXPECTED_CLOSE');
        $this->option_message['unexpected_error'] = __('launcher.OPTION_UNEXPECTED_ERROR');

        $this->resource_setting = config('launcher.resources');

        $os = 'linux';
        if(PHP_OS_FAMILY === 'Windows')
        {
            $os = 'windows';
        }
        foreach($this->resource_setting["disk_{$os}"] as $disk => $val)
        {
            $this->prev_threshold_level['disk'][$disk] = 0;
        }
    }


    //--------------------------------------------------------------------------
    // プロパティアクセス用
    //--------------------------------------------------------------------------

    /**
     * TLSフラグの取得
     * 
     * @return bool TLSフラグ
     */
    public function getTls()
    {
        $w_ret = $this->tls;
        return $w_ret;
    }

    /**
     * 受信空振り時のリトライ回数取得
     * 
     * @return int リトライ回数
     */
    public function getRecvRetry()
    {
        $w_ret = $this->getTempBuff(['recv_retry']);
        return $w_ret['recv_retry'];
    }

    /**
     * 受信空振り時のリトライ回数設定
     * 
     * @param int $p_cnt リトライ回数
     */
    public function setRecvRetry(int $p_cnt)
    {
        $this->setTempBuff(['recv_retry' => $p_cnt]);
        return;
    }


    //--------------------------------------------------------------------------
    // openinngハンドシェイク時のヘッダ情報管理用
    //--------------------------------------------------------------------------

    /**
     * ハンドシェイク時のヘッダ情報の取得
     * 
     * @param ?string $p_cid 接続ID
     * @return ?array ヘッダ情報
     */
    public function getHeaders(?string $p_cid = null): ?array
    {
        $cid = null;
        if($p_cid !== null)
        {
            $cid = $p_cid;
        }
        $w_ret = null;

        // ユーザープロパティの取得
        $w_ret = $this->getTempBuff(['headers'], $cid);
        if($w_ret === null)
        {
            return null;
        }

        return $w_ret['headers'];
    }

    /**
     * ハンドシェイク時のヘッダ情報の設定
     * 
     * @param array $p_prop プロパティのリスト
     */
    public function setHeaders(array $p_prop)
    {
        // ユーザープロパティの設定
        $this->setTempBuff(['headers' => $p_prop]);
        return;
    }


    //--------------------------------------------------------------------------
    // ユーザーリスト制御
    //--------------------------------------------------------------------------

    /**
     * ユーザーエントリー
     * 
     * @param ?string $p_user ユーザー名
     * @param ?string &$p_message エラーメッセージ
     * @return bool true（成功） or false（失敗）
     */
    public function entryUser(?string $p_user, ?string &$p_message)
    {
        if($p_user === null || $p_user === '')
        {
            $p_message = __('launcher.ERROR_USER_EMPTY');
            return false;
        }
        $users = array_values($this->user_list);
        foreach($users as $user)
        {
            if($user === $p_user)
            {
                $p_message = __('launcher.ERROR_USER_DUPLICATE');
                return false;
            }
        }
        $uid = $this->getConnectionId();
        $this->user_list[$uid] = $p_user;
        return true;
    }

    /**
     * ユーザーリストの取得
     * 
     * @return array ユーザーリスト
     */
    public function getUserList()
    {
        return $this->user_list;
    }

    /**
     * ユーザー名の取得
     * 
     * @param ?string $p_cid 接続ID
     * @return string|null ユーザー名 or null（該当なし）
     */
    public function getUserName(?string $p_cid = null)
    {
        if($p_cid === null)
        {
            $p_cid = $this->getConnectionId();
        }
        $user_name = null;
        foreach($this->user_list as $cid => $user)
        {
            if($p_cid === $cid)
            {
                $user_name = $user;
                break;
            }
        }
        return $user_name;
    }

    /**
     * ユーザーの削除
     * 
     * @param string $p_cid 接続ID
     */
    public function deleteUser(string $p_cid)
    {
        unset($this->user_list[$p_cid]);
    }


    //--------------------------------------------------------------------------
    // リソースモニタリング
    //--------------------------------------------------------------------------

    /**
     * リソースモニタリング
     * 
     */
    public function resourceMonitoring(): void
    {
        static $prev_time = 0.0;

        $now = microtime(true);
        if($now - $prev_time < 1.0)
        {
            usleep($this->cycle_interval);
            return;
        }
        $prev_time = $now;

        if($this->service_list === null)
        {
            usleep($this->cycle_interval);
            return;
        }

        if(PHP_OS_FAMILY === 'Windows')
        {
            // CPUリソース
            $this->cpuResourceMonitoringForWindows();
            // メモリリソース
            $this->memoryResourceMonitoringForWindows($this->service_list);
            // ディスクリソース
            $this->diskResourceMonitoringForWindows($this->resource_setting['disk_windows']);
        }
        else
        {
            // CPUリソース
            $this->cpuResourceMonitoringForLinux();
            // メモリリソース
            $this->memoryResourceMonitoringForLinux($this->service_list);
            // ディスクリソース
            $this->diskResourceMonitoringForLinux($this->resource_setting['disk_linux']);
        }

        $manager = $this->getSocketManager();
        $cids = $manager->getConnectionIdAll();
        if(count($cids) <= 0)
        {
            return;
        }

        if($this->resource_cpu !== null)
        {
            foreach($cids ?? [] as $cid)
            {
                $resource_cpu =
                [
                    'cmd' => CommandForWebsocketQueueEnum::RESOURCE_CPU->value,
                    'usage_rate' => $this->resource_cpu['usage_rate'],
                    'usage_color' => $this->resource_cpu['usage_color'],
                    'logical_cpus' => $this->resource_cpu['logical_cpus'],
                    'service_cpus' => $this->resource_cpu['service_cpus']
                ];
                $data =
                [
                    'data' => $resource_cpu
                ];
                $this->setSendStack($data, $cid);
            }
        }
        if($this->resource_memory !== null)
        {
            foreach($cids ?? [] as $cid)
            {
                $resource_mem =
                [
                    'cmd' => CommandForWebsocketQueueEnum::RESOURCE_MEMORY->value,
                    'types' => $this->resource_memory['types'],
                    'services' => $this->resource_memory['services']
                ];
                $data =
                [
                    'data' => $resource_mem
                ];
                $this->setSendStack($data, $cid);
            }
        }
        if($this->resource_disk !== null)
        {
            foreach($cids ?? [] as $cid)
            {
                $resource_disk =
                [
                    'cmd' => CommandForWebsocketQueueEnum::RESOURCE_DISK->value,
                    'disks' => $this->resource_disk
                ];
                $data =
                [
                    'data' => $resource_disk
                ];
                $this->setSendStack($data, $cid);
            }
        }
    }

    /**
     * レート値による閾値レベル名の取得（通知付き）
     * 
     * @param array $p_type リソースタイプ（cpu or memory or disk）
     * @param array $p_setting 閾値設定データ
     * @param int $p_rate レート値
     * @return string 閾値レベル名
     */
    private function getThresholdNameWithNotification(array $p_type, array $p_setting, int $p_rate): string
    {
        /**
         * @var string $level_name 閾値のレベル名称
         */
        $level_name = '';

        $email =
        [
            'to' => null,
            'subject' => null,
            'body' => null,
            'headers' => null
        ];

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';

        $type_label = '';

        /**
         * @var int $threshold 閾値の設定値
         */
        $threshold = '';

        // 閾値のレベルでループ
        foreach($this->threshold_level as $key => $level)
        {
            // 今の使用率が設定値以上の場合
            if($p_rate >= $p_setting[$key]['threshold'])
            {
                $level_name = $key;
                $threshold = $p_setting[$key]['threshold'];
                $prev_level = null;

                // CPU、あるいはメモリ
                if(count($p_type) === 1)
                {
                    $prev_level = &$this->prev_threshold_level[$p_type[0]];
                    $type_label = $this->resource_type_label[$p_type[0]];
                }
                else
                {
                    $prev_level = &$this->prev_threshold_level[$p_type[0]][$p_type[1]];
                    $type_label = $this->resource_type_label[$p_type[0]];
                    $type_label .= "（{$p_type[1]}）";
                }

                // 今回の閾値レベルが前回以下の場合
                if($level <= $prev_level)
                {
                    $email = null;
                    $prev_level = $level;
                    return $level_name;
                }

                // Emailの設定がない場合
                if($p_setting[$key]['email'] === null || $p_setting[$key]['email']['to'] === null)
                {
                    $email = null;
                }
                else
                {
                    $email['to'] = $p_setting[$key]['email']['to'];
                    if($p_setting[$key]['email']['from_address'] !== null)
                    {
                        if($p_setting[$key]['email']['from_name'] !== null)
                        {
                            $headers[] = 'From: ' . "{$p_setting[$key]['email']['from_name']} <{$p_setting[$key]['email']['from_address']}>";
                        }
                        else
                        {
                            $headers[] = 'From: ' . $p_setting[$key]['email']['from_address'];
                        }
                    }
                    if($p_setting[$key]['email']['reply_to'] !== null)
                    {
                        $headers[] = "Reply-To: {$p_setting[$key]['email']['reply_to']}";
                    }
                    $email['headers'] = implode("\r\n", $headers);
                }

                $prev_level = $level;
                break;
            }
        }

        // どの閾値も超えていなかった場合
        if($level_name === '')
        {
            // CPU、あるいはメモリ
            if(count($p_type) === 1)
            {
                $this->prev_threshold_level[$p_type[0]] = 0;
            }
            else
            {
                $this->prev_threshold_level[$p_type[0]][$p_type[1]] = 0;
            }
        }

        if($email !== null && $level_name !== '')
        {
            $host = gethostname();
            $port = $this->getAwaitPort();
            if($level_name === 'critical')
            {
                $email['subject'] = __('notification-email.MAIL_SUBJECT_CRITICAL', ['type' => $type_label, 'server' => "{$host}:{$port}"]);
                $email['body'] = __('notification-email.MAIL_BODY_CRITICAL', ['type' => $type_label, 'usage' => $p_rate, 'threshold' => $threshold, 'server' => "{$host}:{$port}", 'timestamp' => date('Y-m-d H:i:s')]);
            }
            else
            if($level_name === 'alert')
            {
                $email['subject'] = __('notification-email.MAIL_SUBJECT_ALERT', ['type' => $type_label, 'server' => "{$host}:{$port}"]);
                $email['body'] = __('notification-email.MAIL_BODY_ALERT', ['type' => $type_label, 'usage' => $p_rate, 'threshold' => $threshold, 'server' => "{$host}:{$port}", 'timestamp' => date('Y-m-d H:i:s')]);
            }
            else
            if($level_name === 'warn')
            {
                $email['subject'] = __('notification-email.MAIL_SUBJECT_WARN', ['type' => $type_label, 'server' => "{$host}:{$port}"]);
                $email['body'] = __('notification-email.MAIL_BODY_WARN', ['type' => $type_label, 'usage' => $p_rate, 'threshold' => $threshold, 'server' => "{$host}:{$port}", 'timestamp' => date('Y-m-d H:i:s')]);
            }

            mail($email['to'], $email['subject'], $email['body'], $email['headers']);
        }

        return $level_name;
    }


    //--------------------------------------------------------------------------
    // [Windows]リソースモニタリング
    //--------------------------------------------------------------------------

    /**
     * [Windows]CPUリソース
     * 
     */
    private function cpuResourceMonitoringForWindows(): void
    {
        static $prev_total = null;
        static $prev_logical = null;
        static $prev_service = null;

        /**
         * 全体稼働率の取得
         */

        $usage_rate = 0;
        $usage_color = '';
        $delta_total = null;

        // 実行してJSON取得
        $cmd = __DIR__.'/../bin/gettotalcpuresource.exe';
        exec($cmd, $output, $result);

        if($result === 0)
        {
            $json = implode("\n", $output);
            $data = json_decode($json, true);

            $curr = [
                'idle'   => (float)$data['cpu_total']['idle'],
                'kernel' => (float)$data['cpu_total']['kernel'],
                'user'   => (float)$data['cpu_total']['user'],
            ];

            if($prev_total !== null)
            {
                // 差分計算（kernelにはidle含むため、実CPU時間は user + (kernel - idle)）
                $delta_idle   = $curr['idle']   - $prev_total['idle'];
                $delta_kernel = $curr['kernel'] - $prev_total['kernel'];
                $delta_user   = $curr['user']   - $prev_total['user'];

                $delta_total = ($delta_kernel + $delta_user) - $delta_idle;
                $delta_used = $delta_kernel + $delta_user;

                $usage_rate = $this->getAdjustRate($this->resource_tests['cpu'], $delta_total, $delta_used, 2);
                $usage_color = $this->getThresholdNameWithNotification(['cpu'], $this->resource_setting['cpu'], $usage_rate);
            }

            $prev_total = $curr;
        }

        // 実行してJSON取得
        $cmd = __DIR__.'/../bin/getlogicalcpuresource.exe';
        $output = [];
        exec($cmd, $output, $result);

        $logical_cpus = [];
        if($result === 0)
        {
            $json = implode("\n", $output);
            $curr = json_decode($json, true);

            if($prev_logical !== null)
            {
                foreach($curr['logical_cpus'] as $index => $cpu)
                {
                    $prev = $prev_logical['logical_cpus'][$index];

                    $idle_delta   = $cpu['idle']   - $prev['idle'];
                    $kernel_delta = $cpu['kernel'] - $prev['kernel'];
                    $user_delta   = $cpu['user']   - $prev['user'];

                    $total_delta  = $kernel_delta + $user_delta;
                    $active_delta = $total_delta - $idle_delta;

                    $logical_cpus[$index]['rate'] = ($total_delta > 0)
                        ? round($active_delta / $total_delta * 100, 2)
                        : 0.0;
                    $logical_cpus[$index]['color'] = $this->getThresholdName($this->resource_setting['cpu'], $logical_cpus[$index]['rate']);
                }
            }

            $prev_logical = $curr;
        }

        // 有効なPIDの取得
        $filtered = array_filter($this->service_list, fn($item) => isset($item['pid']) && $item['pid'] !== null);
        $pids = array_column($filtered, 'pid');

        $services = null;
        if(count($pids) > 0 && $delta_total !== null)
        {
            $params = implode(' ', $pids);
            $cmd = __DIR__.'/../bin/getservicecpuresource.exe '.$params;
            $output = [];
            exec($cmd, $output, $result);

            if($result === 0)
            {
                $json = implode("\n", $output);
                $curr = json_decode($json, true);

                if($prev_service !== null)
                {
                    $services = [];
                    foreach($this->service_list as $idx_service => $service)
                    {
                        if($service['pid'] === null)
                        {
                            $services[$idx_service] = ['pid' => null, 'rate' => null, 'color' => ''];
                            continue;
                        }
                        foreach($curr['processes'] as $idx_pid => $pid_cpu)
                        {
                            if((int)$pid_cpu['pid'] === (int)$service['pid'])
                            {
                                if(!isset($prev_service['processes'][$idx_pid]))
                                {
                                    $services[$idx_service] = ['pid' => $pid_cpu['pid'], 'rate' => '', 'color' => ''];
                                    continue;
                                }
                                $prev = $prev_service['processes'][$idx_pid];

                                if(isset($pid_cpu['error']))
                                {
                                    $w_ret = $this->isOperatingLauncher();
                                    if($w_ret === true)
                                    {
                                        $services[$idx_service] = ['pid' => $pid_cpu['pid'], 'rate' => '', 'color' => ''];
                                    }
                                    else
                                    {
                                        $services[$idx_service] = ['pid' => $pid_cpu['pid'], 'rate' => false, 'color' => ''];
                                        $this->param_launcher->service_list_all[$idx_service]['pid'] = null;
                                        $this->service_list[$idx_service]['pid'] = null;
                                    }
                                    break;
                                }

                                if(isset($prev['error']))
                                {
                                    break;
                                }

                                $active_delta = ($pid_cpu['kernel'] + $pid_cpu['user']) - ($prev['kernel'] + $prev['user']);

                                $services[$idx_service]['pid'] = $pid_cpu['pid'];
                                $services[$idx_service]['rate'] = ($total_delta > 0)
                                    ? round($active_delta / $delta_total * 100, 2)
                                    : 0.0;
                                $services[$idx_service]['color'] = $this->getThresholdName($this->resource_setting['cpu'], $services[$idx_service]['rate']);
                                break;
                            }
                        }
                    }
                }

                $prev_service = $curr;
            }
        }

        $this->resource_cpu =
        [
            'usage_rate' => $usage_rate,
            'usage_color' => $usage_color,
            'logical_cpus' => $logical_cpus,
            'service_cpus' => $services
        ];

        return;
    }

    /**
     * [Windows]メモリリソース
     * 
     * @param array $p_service_list サービスリスト
     * @return array 物理メモリ／仮想メモリ／キャッシュの各総量と使用量
     */
    private function memoryResourceMonitoringForWindows(array $p_service_list): void
    {
        $stats =
        [
            'physical' => ['used' => 0, 'total' => 0, 'color' => ''],
            'virtual'  => ['used' => 0, 'total' => 0, 'color' => ''],
            'cache'    => ['used' => 0, 'total' => 0, 'color' => '']
        ];

        $cmd = __DIR__.'/../bin/getmemoryresource.exe';
        exec($cmd, $output, $result);

        if($result === 0)
        {
            $json = implode("\n", $output);
            $data = json_decode($json, true);

            // 物理メモリのサイズ設定
            $stats['physical']['used'] = $data['memory']['physical_used_mb'];
            $stats['physical']['total'] = $data['memory']['physical_total_mb'];

            // 物理メモリの閾値レベル設定
            $physical_rate = $this->getAdjustRate($this->resource_tests['memory'], $stats['physical']['used'], $stats['physical']['total']);
            $physical_color = $this->getThresholdNameWithNotification(['memory'], $this->resource_setting['memory'], $physical_rate);
            $stats['physical']['color'] = $physical_color;

            // 仮想メモリのサイズ設定
            $stats['virtual']['used'] = $data['memory']['virtual_used_mb'];
            $stats['virtual']['total'] = $data['memory']['virtual_total_mb'];

            // 仮想メモリのカラー設定
            $virtual_rate = $stats['virtual']['used'] / $stats['virtual']['total'] * 100;
            $virtual_color = $this->getThresholdName($this->resource_setting['memory'], $virtual_rate);
            $stats['virtual']['color'] = $virtual_color;

            // キャッシュメモリのサイズ設定
            $stats['cache']['used'] = $data['memory']['cache_used_mb'];
            $stats['cache']['total'] = $data['memory']['cache_total_mb'];

            // キャッシュメモリのカラー設定
            $cache_rate = $stats['cache']['used'] / $stats['cache']['total'] * 100;
            $cache_color = $this->getThresholdName($this->resource_setting['memory'], $cache_rate);
            $stats['cache']['color'] = $cache_color;
        }

        // 有効なPIDの取得
        $filtered = array_filter($p_service_list, fn($item) => isset($item['pid']) && $item['pid'] !== null);
        $pids = array_column($filtered, 'pid');
        $services = null;
        if(count($pids) > 0)
        {
            $params = implode(' ', $pids);
            $cmd = __DIR__.'/../bin/getservicememoryresource.exe '.$params;
            $output = [];
            exec($cmd, $output, $result);

            if($result === 0)
            {
                $json = implode("\n", $output);
                $datas = json_decode($json, true);

                $services = [];
                foreach($p_service_list as $idx_service => $service)
                {
                    if($service['pid'] === null)
                    {
                        $services[] = ['rate' => null, 'color' => ''];
                        continue;
                    }
                    foreach($datas as $data)
                    {
                        if((int)$data['pid'] === (int)$service['pid'])
                        {
                            if(isset($data['error']))
                            {
                                $w_ret = $this->isOperatingLauncher();
                                if($w_ret === true)
                                {
                                    $services[] = ['rate' => '', 'color' => ''];
                                }
                                else
                                {
                                    $services[] = ['rate' => false, 'color' => ''];
                                    $this->param_launcher->service_list_all[$idx_service]['pid'] = null;
                                    $this->service_list[$idx_service]['pid'] = null;
                                }
                                break;
                            }
                            $color = $this->getThresholdName($this->resource_setting['memory'], $data['usage_percent']);
                            $services[] = ['rate' => $data['usage_percent'], 'color' => $color];
                            break;
                        }
                    }
                }
            }
        }

        $this->resource_memory =
        [
            'types' => $stats,
            'services' => $services
        ];
        return;
    }

    /**
     * [Windows]ディスクリソース
     * 
     * @param array $p_settings 設定内容
     */
    private function diskResourceMonitoringForWindows(array $p_settings): void
    {
        $drives = array_keys($p_settings);
        $cmd = __DIR__.'/../bin/getdiskresource.exe ' . implode(' ', $drives);
        exec($cmd, $output, $result);

        $results = [];
        if($result === 0)
        {
            $json = implode("\n", $output);
            $data = json_decode($json, true);

            foreach($drives as $drive)
            {
                foreach($data['disks'] as $disk)
                {
                    if($disk['drive'] === $drive)
                    {
                        if(isset($disk['error']))
                        {
                            $results[$drive] =
                            [
                                'label' => $p_settings[$drive]['label'],
                                'used_mb' => 0,
                                'total_mb' => 0,
                                'active' => false,
                                'color' => ''
                            ];
                        }
                        else
                        {
                            $rate = $this->getAdjustRate($this->resource_tests['disk'], $disk['used_mb'], $disk['total_mb']);
                            $color = $this->getThresholdNameWithNotification(['disk', $drive], $p_settings[$drive], $rate);
                            $results[$drive] =
                            [
                                'label' => $p_settings[$drive]['label'],
                                'used_mb' => $disk['used_mb'],
                                'total_mb' => $disk['total_mb'],
                                'active' => true,
                                'color' => $color
                            ];
                        }
                    }
                }
            }
        }

        $this->resource_disk = $results;
        return;
    }


    //--------------------------------------------------------------------------
    // [Linux]リソースモニタリング
    //--------------------------------------------------------------------------

    /**
     * [Linux]CPUリソース
     * 
     * @return bool true（成功） or false（失敗）
     */
    private function cpuResourceMonitoringForLinux(): bool
    {
        static $prev_stats = [];
        static $prev_proc_stats = [];

        $this->resource_cpu = null;

        $lines = @file('/proc/stat');
        if(!$lines)
        {
            return false;
        }

        $current_stats = [];

        foreach($lines as $line)
        {
            if(preg_match('/^(cpu[0-9]*)(\s+.+)$/', $line, $matches))
            {
                $id = $matches[1];
                $parts = preg_split('/\s+/', trim($matches[2]));
                $user = (int)$parts[0];
                $nice = (int)$parts[1];
                $system = (int)$parts[2];
                $idle = (int)$parts[3];
                $iowait = (int)$parts[4];
                $irq = (int)$parts[5];
                $softirq = (int)$parts[6];

                $active = $user + $nice + $system + $irq + $softirq;
                $total = $active + $idle + $iowait;

                $current_stats[$id] = ['active' => $active, 'total' => $total];
            }
        }

        // 初回は前回値がないのでスキップ
        if(empty($prev_stats))
        {
            $prev_stats = $current_stats;
            $prev_proc_stats = $this->collectProcCpuStatsForLinux($this->service_list);
            return true;
        }

        $logical_rates = [];
        $total_active_delta = 0;
        $total_total_delta = 0;

        foreach($current_stats as $id => $curr)
        {
            if(!isset($prev_stats[$id]))
            {
                continue;
            }

            $prev = $prev_stats[$id];
            $delta_active = $curr['active'] - $prev['active'];
            $delta_total = $curr['total'] - $prev['total'];

            $rate = ($delta_total > 0) ? ($delta_active / $delta_total) * 100 : 0;

            if($id === 'cpu')
            {
                $total_active_delta = $delta_active;
                $total_total_delta = $delta_total;
            }
            else
            {
                $color = $this->getThresholdName($this->resource_setting['cpu'], $rate);
                if($color === '')
                {
                    $color = 'default-color';
                }

                $logical_rates[] =
                [
                    'rate' => round($rate, 2),
                    'color' => $color
                ];;
            }
        }

        $service_rates = null;
        if($this->service_list !== null)
        {
            // プロセスごとのCPU使用率
            $current_proc_stats = $this->collectProcCpuStatsForLinux($this->service_list);
            $service_rates = [];

            foreach($this->service_list as $idx => $service)
            {
                if(!isset($prev_proc_stats[$idx]))
                {
                    $prev_proc_stats[$idx]['pid'] = null;
                    $prev_proc_stats[$idx]['used'] = null;
                }

                if(
                    $prev_proc_stats[$idx]['pid'] === null
                ||  $current_proc_stats[$idx]['pid'] === null
                ||  $prev_proc_stats[$idx]['used'] === null
                ||  $current_proc_stats[$idx]['used'] === null
                )
                {
                    $w_rate = '';
                    if($service['pid'] === null)
                    {
                        $w_rate = null;
                    }
                    else
                    {
                        $w_ret = $this->isOperatingLauncher();
                        if($w_ret !== true)
                        {
                            $w_rate = false;
                        }
                    }
                    $service_rates[] =
                    [
                        'pid' => $prev_proc_stats[$idx]['pid'],
                        'rate' => $w_rate,
                        'color' => ''
                    ];
                    continue;
                }

                $prev = $prev_proc_stats[$idx]['used'];
                $curr = $current_proc_stats[$idx]['used'];

                $proc_delta = $curr - $prev;
                $rate = ($total_total_delta > 0) ? round(100 * $proc_delta / $total_total_delta, 2) : 0.0;

                $color = $this->getThresholdName($this->resource_setting['cpu'], $rate);

                $service_rates[] =
                [
                    'pid' => $current_proc_stats[$idx]['pid'],
                    'rate' => $rate,
                    'color' => $color
                ];
            }
        }

        $usage_rate = $this->getAdjustRate($this->resource_tests['cpu'], $total_active_delta, $total_total_delta, 2);
        $usage_color = $this->getThresholdNameWithNotification(['cpu'], $this->resource_setting['cpu'], $usage_rate);

        $this->resource_cpu =
        [
            'usage_rate' => $usage_rate,
            'usage_color' => $usage_color,
            'logical_cpus' => $logical_rates,
            'service_cpus' => $service_rates
        ];

        $prev_stats = $current_stats;
        $prev_proc_stats = $current_proc_stats;

        return true;
    }

    /**
     * [Linux]プロセスごとのCPUリソース
     * 
     * @param array サービスリスト
     * @return array サービスごとのプロセスID、使用率のリスト
     */
    private function collectProcCpuStatsForLinux(array $p_service_list): array
    {
        $stats = [];

        foreach($p_service_list as $service)
        {
            if($service['pid'] === null)
            {
                $stats[] =
                [
                    'pid' => null,
                    'used' => null
                ];
                continue;
            }

            $path = "/proc/{$service['pid']}/stat";
            if(!is_readable($path))
            {
                $stats[] =
                [
                    'pid' => $service['pid'],
                    'used' => null
                ];
                continue;
            }

            $line = @file_get_contents($path);
            if(!$line)
            {
                $stats[] =
                [
                    'pid' => $service['pid'],
                    'used' => null
                ];
                continue;
            }

            $parts = explode(' ', $line);
            if(count($parts) < 15)
            {
                $stats[] =
                [
                    'pid' => $service['pid'],
                    'used' => null
                ];
                continue;
            }

            $utime = (int)$parts[13];
            $stime = (int)$parts[14];
            $stats[] =
            [
                'pid' => $service['pid'],
                'used' => $utime + $stime
            ];
        }

        return $stats;
    }

    /**
     * [Linux]メモリリソース
     * 
     * @param array $p_service_list サービスリスト
     * @return array 物理メモリ／仮想メモリ／キャッシュの各総量と使用量
     */
    private function memoryResourceMonitoringForLinux(array $p_service_list): void
    {
        $meminfo = @file('/proc/meminfo');
        if(!$meminfo)
        {
            $this->resource_memory = null;
            return;
        }

        $stats =
        [
            'physical' => ['used' => 0, 'total' => 0, 'color' => ''],
            'virtual'  => ['used' => 0, 'total' => 0, 'color' => ''],
            'cache'    => ['used' => 0, 'total' => 0, 'color' => '']
        ];

        $services = [];
        $mem_total = 0;

        foreach($meminfo as $line)
        {
            if(preg_match('/^MemTotal:\s+(\d+)/', $line, $m))
            {
                $mem_total = (int)$m[1];
                $stats['physical']['total'] = $mem_total / 1024;
                $stats['cache']['total']    = $mem_total / 1024;
            }
            else
            if(preg_match('/^MemAvailable:\s+(\d+)/', $line, $m))
            {
                $available = (int)$m[1] / 1024;
                $stats['physical']['used'] = $stats['physical']['total'] - $available;
            }
            else
            if(preg_match('/^Cached:\s+(\d+)/', $line, $m))
            {
                $stats['cache']['used'] += (int)$m[1] / 1024;
            }
            else
            if(preg_match('/^Buffers:\s+(\d+)/', $line, $m))
            {
                $stats['cache']['used'] += (int)$m[1] / 1024;
            }
        }


        // 物理メモリの閾値レベル設定
        $physical_rate = $this->getAdjustRate($this->resource_tests['memory'], $stats['physical']['used'], $stats['physical']['total']);
        $physical_color = $this->getThresholdNameWithNotification(['memory'], $this->resource_setting['memory'], $physical_rate);
        $stats['physical']['color'] = $physical_color;

        // キャッシュメモリのカラー設定
        $cache_rate = $stats['cache']['used'] / $stats['cache']['total'] * 100;
        $cache_color = $this->getThresholdName($this->resource_setting['memory'], $cache_rate);
        $stats['cache']['color'] = $cache_color;

        // 仮想メモリ（VmSize合計）
        $vm_total = 0;
        foreach($p_service_list as $service)
        {
            if($service['pid'] === null)
            {
                $services[] = ['rate' => null, 'color' => ''];
                continue;
            }

            $path = "/proc/{$service['pid']}/status";
            if(!is_readable($path))
            {
                $w_ret = $this->isOperatingLauncher();
                if($w_ret === true)
                {
                    $services[] = ['rate' => '', 'color' => ''];
                }
                else
                {
                    $services[] = ['rate' => false, 'color' => ''];
                }
                continue;
            }

            $rss_kb = 0;
            $lines = @file($path);
            foreach($lines as $line)
            {
                if (preg_match('/^VmRSS:\s+(\d+)/', $line, $m)) {
                    $rss_kb = (int)$m[1];
                }
                else
                {
                    if(preg_match('/^VmSize:\s+(\d+)/', $line, $m))
                    {
                        $vm_total += (int)$m[1];
                    }
                }
            }

            $service_rate = round(100 * $rss_kb / $mem_total, 2);
            $service_color = $this->getThresholdName($this->resource_setting['memory'], $service_rate);
            $services[] = ['rate' => $service_rate, 'color' => $service_color];
        }

        $stats['virtual']['used'] = round($vm_total / 1024, 2); // MB
        $stats['virtual']['total'] = $stats['physical']['total'];
        $virtual_rate = $stats['virtual']['used'] / $stats['virtual']['total'] * 100;
        $virtual_color = $this->getThresholdName($this->resource_setting['memory'], $virtual_rate);
        $stats['virtual']['color'] = $virtual_color;

        $this->resource_memory =
        [
            'types' => $stats,
            'services' => $services
        ];
        return;
    }

    /**
     * [Linux]ディスクリソース
     * 
     * @param array $p_settings 設定内容
     */
    private function diskResourceMonitoringForLinux(array $p_settings): void
    {
        $results = [];
        foreach($p_settings as $disk => $setting)
        {
            $exists = is_dir($disk);
            $total = $exists ? @disk_total_space($disk) : null;
            $free  = $exists ? @disk_free_space($disk) : null;

            if($total && $free)
            {
                $used = $total - $free;
                $rate = $this->getAdjustRate($this->resource_tests['disk'], $used, $total);
                $color = $this->getThresholdNameWithNotification(['disk', $disk], $setting, $rate);
                $results[$disk] =
                [
                    'label' => $setting['label'],
                    'used_mb' => round($used / 1024 / 1024),
                    'total_mb' => round($total / 1024 / 1024),
                    'active' => true,
                    'color' => $color
                ];
            }
            else
            {
                $results[$disk] =
                [
                    'label' => $setting['label'],
                    'used_mb' => 0,
                    'total_mb' => 0,
                    'active' => false,
                    'color' => ''
                ];
            }
        }

        $this->resource_disk = $results;
        return;
    }


    //--------------------------------------------------------------------------
    // その他
    //--------------------------------------------------------------------------

    /**
     * クライアントからの強制切断時のコールバック
     * 
     * @param ParameterForWebsocket $p_param UNITパラメータ
     */
    public function forcedCloseFromClient(ParameterForWebsocket $p_param)
    {
    }

    /**
     * LauncherのUNITパラメータクラスの取得
     * 
     * @return ParameterForLauncher LauncherのUNITパラメータクラス
     */
    public function getParameterLauncher(): ParameterForLauncher
    {
        return $this->param_launcher;
    }

    /**
     * LauncherのUNITパラメータクラスの設定
     * 
     * @param ParameterForLauncher $p_param LauncherのUNITパラメータクラス
     */
    public function setParameterLauncher(ParameterForLauncher $p_param)
    {
        $this->param_launcher = $p_param;
        return;
    }

    /**
     * 調整使用率の取得
     * 
     * @param ?array &$p_type 調整タイプ
     * @param int &$p_used 使用量
     * @param int $p_total トータル使用量
     * @param ?int $p_decimal_digits 少数桁数指定の四捨五入
     * @return int 調整後の使用率
     */
    private function getAdjustRate(?array &$p_type, int &$p_used, int $p_total, ?int $p_decimal_digits = null)
    {
        if($p_type === null)
        {
            $rate = ($p_total > 0 ? $p_used / $p_total : 0);
            if($p_decimal_digits === null)
            {
                $rate *= 100;
            }
            else
            {
                $rate = round($rate * 100, $p_decimal_digits);
            }
            return $rate;
        }

        static $idx = 0;
        $convert = [
            'up' => [0],
            'down' => [1],
            'mix' => [0, 1]
        ];
        $adjust_rate = [1, -1];

        if($p_type['type'] === 'mix')
        {
            $idx = ($idx + 1) % 2;
        }

        $i = $convert[$p_type['type']][$idx];
        $p_type['base'] += $adjust_rate[$i];

        $p_used = $p_total * $p_type['base'] / 100;

        return $p_type['base'];
    }

    /**
     * レート値による閾値レベル名の取得
     * 
     * @param array $p_setting 閾値設定データ
     * @param int $p_rate レート値
     * @return string 閾値レベル名
     */
    private function getThresholdName(array $p_setting, int $p_rate)
    {
        /**
         * @var string $level_name 閾値のレベル名称
         */
        $level_name = '';

        // 閾値のレベルでループ
        foreach($this->threshold_level as $key => $level)
        {
            // 今の使用率が設定値以上の場合
            if($p_rate >= $p_setting[$key]['threshold'])
            {
                $level_name = $key;
                break;
            }
        }

        return $level_name;
    }

    /**
     * Launcher処理中判定
     * 
     * @return bool true（Launcher処理中） or false（Launcherアイドリング中）
     */
    public function isOperatingLauncher()
    {
        return $this->param_launcher->isExecutedQueue(RuntimeQueueEnumForLauncher::STARTUP->value);
    }

    /**
     * Launcherオーダーアクション存在判定
     * 
     * @return bool true（オーダーあり） or false（オーダーなし）
     */
    public function isOrderAction()
    {
        $cnt = 0;
        $cnt += count($this->param_launcher->order_action_stack);
        return ($cnt > 0);
    }

    /**
     * Launcherログの通知
     * 
     * @param array &$p_log ランチャーログ
     */
    public function noticeLauncherLog(array &$p_log)
    {
        // サービスリスト反映
        $this->service_list = $this->param_launcher->service_list_all;

        $max = count($this->service_list);
        for($i = 0; $i < $max; $i++)
        {
            $this->service_list[$i]['cpu'] = 'ー';
            $this->service_list[$i]['memory'] = 'ー';
            if(isset($this->service_list[$i]['pid']) && $this->service_list[$i]['pid'] !== null)
            {
                $this->service_list[$i]['status'] = '起動中';
            }
            else
            {
                $this->service_list[$i]['status'] = '停止中';
                $this->service_list[$i]['timestamp'] = 'ー';
            }
        }

        $manager = $this->getSocketManager();
        $cids = $manager->getConnectionIdAll();
        if(count($cids) <= 0)
        {
            return;
        }

        $is_busy = $this->isOrderAction();
        foreach($cids ?? [] as $cid)
        {
            if($is_busy === false)
            {
                $action_notice =
                [
                    'cmd' => CommandForWebsocketQueueEnum::ACTION_NOTICE->value,
                    'is_guard' => false
                ];
                $data =
                [
                    'data' => $action_notice
                ];
                $this->setSendStack($data, $cid);
            }

            $service_list =
            [
                'cmd' => CommandForWebsocketQueueEnum::SERVICE_LIST->value,
                'list' => $this->service_list,
                'enable_save_flg' => $this->setting_edit_flg
            ];
            $data =
            [
                'data' => $service_list
            ];
            $this->setSendStack($data, $cid);

            $launcher_log =
            [
                'cmd' => CommandForWebsocketQueueEnum::LAUNCHER_LOG->value,
                'log' => $p_log
            ];
            $data =
            [
                'data' => $launcher_log
            ];
            $this->setSendStack($data, $cid);
        }
    }
}
