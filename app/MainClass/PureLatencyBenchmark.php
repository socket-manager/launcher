<?php
/**
 * メイン処理クラスのファイル
 * 
 * SocketManagerの実行
 */

namespace App\MainClass;

use App\CommandUnits\CommandForPureLatencyBenchmark;
use SocketManager\Library\SocketManager;
use SocketManager\Library\FrameWork\Console;

use App\InitClass\InitForPureLatencyBenchmark;
use App\ProtocolUnits\ProtocolForPureLatencyBenchmark;
use App\UnitParameter\ParameterForPureLatencyBenchmark;


/**
 * メイン処理クラス
 * 
 * SocketManagerの初期化と実行
 */
class PureLatencyBenchmark extends Console
{
    /**
     * @var string $identifer サーバー識別子
     */
    protected string $identifer = 'app:pure-latency-benchmark {samples} {sampling_interval?} {timeout?} {host?} {port?}';

    /**
     * @var string $description コマンド説明
     */
    protected string $description = '純粋性能ベンチマーク: {samples} {sampling_interval?} {timeout?} {host?} {port?}';

    /**
     * @var int $sampling_interval サンプリング間隔（ms）
     */
    private int $sampling_interval = 1;

    /**
     * @var int $timeout ping/pontタイムアウト（s）
     */
    private int $timeout = 10;

    /**
     * @var string $payload ペイロード
     */
    private string $payload = '';

    /**
     * @var string $host ホスト名（接続用）
     */
    private string $host = '127.0.0.1';

    /**
     * @var int $port ポート番号（接続用）
     */
    private int $port = 10000;

    /**
     * @var int $cycle_interval 周期インターバル時間（μs）
     */
    private int $cycle_interval = 10;

    /**
     * @var int $alive_interval アライブチェックタイムアウト時間（s）
     */
    private int $alive_interval = 3600;


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
        $this->host = config('benchmark.host', $this->host);

        // ポート番号の設定
        $this->port = config('benchmark.port', $this->port);

        // 周期インターバルの設定
        $this->cycle_interval = config('benchmark.cycle_interval', $this->cycle_interval);

        // アライブチェックタイムアウト時間の設定
        $this->alive_interval = config('benchmark.alive_interval', $this->alive_interval);

        // サンプリング間隔の設定
        $this->sampling_interval = config('pure_latency_benchmark.sampling_interval', $this->sampling_interval);

        // ping/pontタイムアウトの設定
        $this->timeout = config('pure_latency_benchmark.timeout', $this->timeout);

        // ペイロードの設定
        $this->payload = config('pure_latency_benchmark.payload', $this->payload);

        //--------------------------------------------------------------------------
        // 引数の反映
        //--------------------------------------------------------------------------

        // サンプル回数の取得
        $samples = $this->getParameter('samples');
        if($samples === false)
        {
            printf("サンプル回数が指定されていません\n");
            return;
        }

        // サンプリング間隔の取得
        $sampling_interval = $this->getParameter('sampling_interval');
        if($sampling_interval !== null)
        {
            $this->sampling_interval = $sampling_interval;
        }

        // ping/pontタイムアウトの取得
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

        //--------------------------------------------------------------------------
        // SocketManagerの初期化
        //--------------------------------------------------------------------------

        // ソケットマネージャーのインスタンス設定
        $manager = new SocketManager();

        // UNITパラメータインスタンスの設定
        $param = new ParameterForPureLatencyBenchmark();

        // SocketManagerの設定値初期設定
        $init = new InitForPureLatencyBenchmark($param, $this->port);
        $manager->setInitSocketManager($init);

        // プロトコルUNITの設定
        $entry = new ProtocolForPureLatencyBenchmark($samples, $this->payload);
        $manager->setProtocolUnits($entry);

        // コマンドUNITの設定
        $entry = new CommandForPureLatencyBenchmark($this->sampling_interval, $this->timeout);
        $manager->setCommandUnits($entry);

        //--------------------------------------------------------------------------
        // 接続開始
        //--------------------------------------------------------------------------

        $ret = $manager->connect($this->host, $this->port);
        if($ret === false)
        {
            goto finish;   // リッスン失敗
        }

        //--------------------------------------------------------------------------
        // ノンブロッキングループ
        //--------------------------------------------------------------------------

        while(true)
        {
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
}
