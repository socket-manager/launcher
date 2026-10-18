<?php
/**
 * メイン処理クラスのファイル
 * 
 * 純粋 UDP 耐久テスト
 */

namespace App\MainClass;

use SocketManager\Library\PureUdpManager;
use SocketManager\Library\FrameWork\Console;

use App\InitClass\InitForPureUdpStressTest;
use App\UnitParameter\ParameterForPureUdpStressTest;
use App\ProtocolUnits\ProtocolForPureUdpStressTest;
use App\CommandUnits\CommandForPureUdpStressTest;


/**
 * メイン処理クラス
 * 
 * モジュール管理クラスの初期化と実行
 */
class PureUdpStressTest extends Console
{
    /**
     * @var string $identifer サーバー識別子
     */
    protected string $identifer = 'app:pure-udp-stress-test {samples} {sampling_interval?} {timeout?} {host?} {port?} {local_port?}';

    /**
     * @var string $description コマンド説明
     */
    protected string $description = '純粋 UDP 耐久テスト: {samples} {sampling_interval?} {timeout?} {host?} {port?} {local_port?}';

    /**
     * @var string $host ホスト名（接続用）
     */
    private string $host = '127.0.0.1';

    /**
     * @var int $port ポート番号（接続用）
     */
    private int $port = 10000;

    /**
     * @var int $port ローカルポート番号
     */
    private int $local_port = 15000;

    /**
     * @var int $cycle_interval 周期インターバル時間（μs）
     */
    private int $cycle_interval = 10;

    /**
     * @var int $alive_interval アライブチェックタイムアウト時間（s）
     */
    private int $alive_interval = 3600;

    /**
     * @var array $os_buff_size OSバッファサイズ
     */
    private array $os_buff_size = ['send' => 131072, 'recv' => 262144];

    // 内部受信バッファサイズ
    private int $internal_recv_size = 2048;

    // パケット件数上限
    private int $internal_max_packets = 64;

    /**
     * @var int $sampling_interval サンプリング間隔（s）
     */
    private int $sampling_interval = 30;

    /**
     * @var int $timeout 測定中のタイムアウト（s）
     */
    private int $timeout = 10;

    /**
     * @var string $payload ペイロード
     */
    private string $payload = '';


    /**
     * サーバー起動
     * 
     */
    public function exec()
    {
        //--------------------------------------------------------------------------
        // 設定値の反映
        //--------------------------------------------------------------------------

        // ホスト名の設定
        $this->host = config('bench_pure_udp.host', $this->host);

        // ポート番号の設定
        $this->port = config('bench_pure_udp.port', $this->port);

        // ローカルポート番号の設定
        $this->local_port = config('bench_pure_udp.local_port', $this->local_port);

        // 周期インターバルの設定
        $this->cycle_interval = config('bench_pure_udp.cycle_interval', $this->cycle_interval);

        // アライブチェックタイムアウト時間の設定
        $this->alive_interval = config('bench_pure_udp.alive_interval', $this->alive_interval);

        // OSバッファサイズ
        $this->os_buff_size = config('bench_pure_udp.os_buff_size', $this->os_buff_size);

        // 内部受信バッファサイズ
        $this->internal_recv_size = config('bench_pure_udp.internal_recv_size', $this->internal_recv_size);

        // パケット件数上限
        $this->internal_max_packets = config('bench_pure_udp.internal_max_packets', $this->internal_max_packets);

        // サンプリング間隔の設定
        $this->sampling_interval = config('bench_pure_udp.sampling_interval', $this->sampling_interval);

        // ping/pontタイムアウトの設定
        $this->timeout = config('bench_pure_udp.timeout', $this->timeout);

        // ペイロードの設定
        $this->payload = config('bench_pure_udp.payload', $this->payload);

        //--------------------------------------------------------------------------
        // 引数の反映
        //--------------------------------------------------------------------------

        // 同時接続数の取得
        $samples = $this->getParameter('samples');
        if($samples === false)
        {
            printf("同時接続数が指定されていません\n");
            return;
        }

        // サンプリング間隔の取得
        $sampling_interval = $this->getParameter('sampling_interval');
        if($sampling_interval !== null)
        {
            $this->sampling_interval = $sampling_interval;
        }

        // 測定中のタイムアウトの取得
        $timeout = $this->getParameter('timeout');
        if($timeout !== null)
        {
            $this->timeout = $timeout;
        }

        // ホスト名の取得
        $host = $this->getParameter('host');
        if($host !== null)
        {
            $this->host = $host;
        }

        // ポート番号の取得
        $port = $this->getParameter('port');
        if($port !== null)
        {
            $this->port = $port;
        }

        // ローカルポート番号の取得
        $local_port = $this->getParameter('local_port');
        if($local_port !== null)
        {
            $this->local_port = $local_port;
        }

        //--------------------------------------------------------------------------
        // モジュール管理クラスの初期化
        //--------------------------------------------------------------------------

        // モジュール管理クラスのインスタンス設定
        $manager = new PureUdpManager(
            $this->host,
            $this->port,
            null,
            $this->os_buff_size['send'],
            $this->os_buff_size['recv'],
            $this->internal_recv_size,
            $this->internal_max_packets
        );

        // UNITパラメータインスタンスの設定
        $param = new ParameterForPureUdpStressTest($samples, $this->sampling_interval);

        // モジュール管理クラスの設定値初期設定
        $init = new InitForPureUdpStressTest($param, $this->port);
        $manager->initManager($init);

        // プロトコルUNITの設定
        $entry = new ProtocolForPureUdpStressTest($this->payload, $this->host, $this->port, $this->local_port);
        $manager->setProtocolUnits($entry);

        // コマンドUNITの設定
        $entry = new CommandForPureUdpStressTest($this->timeout);
        $manager->setCommandUnits($entry);

        $manager->setKeyResolveCallback($this->keyResolveCallback(...));

        //--------------------------------------------------------------------------
        // ノンブロッキングループ
        //--------------------------------------------------------------------------

        $local_port = $this->local_port;
        $total = 0;
        $param->test_start_time = hrtime(true);
        while(true)
        {
            if($total < $samples)
            {
                $rid = $manager->connect($this->host, $this->port, $local_port++);
                if($rid === false)
                {
                    goto finish;   // リッスン失敗
                }
                for($i = 0; $i < 20; $i++)
                {
                    $cid_str = sprintf("#%05d", $total);
                    $ret = $manager->openSession($rid, $cid_str);
                    if($ret === false)
                    {
                        goto finish;   // リッスン失敗
                    }
                    $total++;
                }
            }

            // 周期ドリブン
            $ret = $manager->cycleDriven($this->cycle_interval, $this->alive_interval);
            if($ret === false)
            {
                goto finish;
            }
        }

finish:
        // 全接続クローズ
        $manager->shutdownAll();
    }

    private function keyResolveCallback(string $p_buf, int $p_len, string $p_host, int $p_port, array &$p_prop): ?string
    {
        return substr($p_buf, 0, 6);
    }
}
