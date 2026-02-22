<?php
/**
 * メイン処理クラスのファイル
 * 
 * SocketManagerの実行
 */

namespace App\MainClass;

use SocketManager\Library\SocketManager;
use SocketManager\Library\FrameWork\Console;

use App\InitClass\InitForConcurrentLoadBenchmark;
use App\ProtocolUnits\ProtocolForConcurrentLoadBenchmark;
use App\UnitParameter\ParameterForConcurrentLoadBenchmark;


/**
 * メイン処理クラス
 * 
 * SocketManagerの初期化と実行
 */
class ConcurrentLoadBenchmark extends Console
{
    /**
     * @var string $identifer サーバー識別子
     */
    protected string $identifer = 'app:concurrent-load-benchmark {samples} {host?} {port?}';

    /**
     * @var string $description コマンド説明
     */
    protected string $description = '混雑状態ベンチマーク: {samples} {host?} {port?}';

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
        $param = new ParameterForConcurrentLoadBenchmark();

        // SocketManagerの設定値初期設定
        $init = new InitForConcurrentLoadBenchmark($param, $this->port);
        $manager->setInitSocketManager($init);

        // プロトコルUNITの設定
        $entry = new ProtocolForConcurrentLoadBenchmark($samples);
        $manager->setProtocolUnits($entry);

        //--------------------------------------------------------------------------
        // 接続開始
        //--------------------------------------------------------------------------


        //--------------------------------------------------------------------------
        // ノンブロッキングループ
        //--------------------------------------------------------------------------

        $total = $samples;
        while(true)
        {
            if($total-- > 0)
            {
                $ret = $manager->connect($this->host, $this->port);
                if($ret === false)
                {
                    goto finish;   // リッスン失敗
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
}
