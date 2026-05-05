<?php
/**
 * ステータスUNIT登録クラスのファイル
 * 
 * SocketManagerのsetProtocolUnitsメソッドへ引き渡されるクラスのファイル
 */

namespace App\ProtocolUnits;

use SocketManager\Library\ProtocolQueueEnum;
use App\UnitParameter\ParameterForRawSocketStressTest;
use App\CommandUnits\CommandForRawSocketStressTestQueueEnum;


/**
 * プロトコルUNIT登録クラス
 * 
 * ParameterForBenchmarkインタフェースをインプリメントする
 */
class ProtocolForRawSocketStressTest extends ProtocolForBenchmark
{
    //--------------------------------------------------------------------------
    // 定数
    //--------------------------------------------------------------------------

    /**
     * @var const QUEUE_LIST キュー名のリスト
     */
    protected const QUEUE_LIST = [
        ProtocolQueueEnum::CONNECT->value,  // コネクションキュー
        ProtocolQueueEnum::ALIVE->value		// アライブチェック処理のキュー
    ];


    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * @var string 接続先ホスト
     */
    private string $host = '';

    /**
     * @var int 接続先ポート番号
     */
    private int $port = -1;

    /**
     * @var string プロトコル（TCP or UDP）
     */
    private string $protocol = '';

    /**
     * @var bool 測定中の標準出力フラグ
     */
    private bool $stdout = false;

    /**
     * @var string ペイロード
     */
    private string $payload = '';

    /**
     * @var int ペイロード長
     */
    private int $payload_len = 0;


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     */
    public function __construct(string $p_payload, string $p_host, int $p_port, string $p_protocol)
    {
        $this->host = $p_host;
        $this->port = $p_port;
        $this->protocol = $p_protocol;
        $this->stdout = config('benchmark.stdout', false);
        $this->payload = $p_payload;
        $this->payload_len = strlen($p_payload);
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

        if($p_que === ProtocolQueueEnum::CONNECT->value)
        {
            $ret[] = [
                'status' => ProtocolForRawSocketStressTestStatusEnum::START->value,
                'unit' => $this->getConnectStart()
            ];
            $ret[] = [
                'status' => ProtocolForRawSocketStressTestStatusEnum::WAITING->value,
                'unit' => $this->getConnectWaiting()
            ];
        }
        else
        if($p_que === ProtocolQueueEnum::ALIVE->value)
        {
            $ret[] = [
                'status' => ProtocolForRawSocketStressTestStatusEnum::START->value,
                'unit' => $this->getEchoStart()
            ];
            $ret[] = [
                'status' => ProtocolForRawSocketStressTestStatusEnum::SENDING->value,
                'unit' => $this->getEchoSending()
            ];
            $ret[] = [
                'status' => ProtocolForRawSocketStressTestStatusEnum::RECEIVING->value,
                'unit' => $this->getEchoReceiving()
            ];
        }

        return $ret;
    }


    //--------------------------------------------------------------------------
    // 以降はステータスUNITの定義（"CONNECT"キュー）
    //--------------------------------------------------------------------------

    /**
     * ステータス名： START
     * 
     * 処理名：接続直後
     * 
     */
    protected function getConnectStart()
    {
        /**
         * @param ParameterForRawSocketStressTest $p_param UNITパラメータ
         * @return ?string 遷移先のステータス名
         */
        return function(ParameterForRawSocketStressTest $p_param): ?string
        {
            $p_param->sample_count++;
            if($this->stdout)
            {
                printf("session count[{$p_param->sample_count}]\r");
            }
            if($p_param->sample_count >= $p_param->samples)
            {
                if($this->stdout)
                {
                    printf("\n");
                }
                return ProtocolForRawSocketStressTestStatusEnum::WAITING->value;
            }

            return null;
        };
    }

    /**
     * ステータス名： WAITING
     * 
     * 処理名：待機中
     * 
     */
    protected function getConnectWaiting()
    {
        /**
         * @param ParameterForRawSocketStressTest $p_param UNITパラメータ
         * @return ?string 遷移先のステータス名
         */
        return function(ParameterForRawSocketStressTest $p_param): ?string
        {
            // 次回タイムアウトの設定
            $now = hrtime(true);
            $p_param->next_timeout = $now + ($p_param->sampling_interval * 1000000000);

            $alive_start =
            [
                'cmd' => CommandForRawSocketStressTestQueueEnum::ALIVE_START->value
            ];
            $p_param->setRecvStack($alive_start, true);

            return null;
        };
    }


    //--------------------------------------------------------------------------
    // 以降はステータスUNITの定義（"ALIVE"キュー）
    //--------------------------------------------------------------------------

    /**
     * ステータス名： START
     * 
     * 処理名：送信開始
     * 
     */
    protected function getEchoStart()
    {
        /**
         * @param ParameterForRawSocketStressTest $p_param UNITパラメータ
         * @return ?string 遷移先のステータス名
         */
        return function(ParameterForRawSocketStressTest $p_param): ?string
        {
            $p_param->protocol()->setSendingData($this->payload);
            $fnc = $this->getEchoSending();
            $sta = $fnc($p_param);

            return $sta;
        };
    }

    /**
     * ステータス名： SENDING
     * 
     * 処理名：送信中
     * 
     */
    protected function getEchoSending()
    {
        /**
         * @param ParameterForRawSocketStressTest $p_param UNITパラメータ
         * @return ?string 遷移先のステータス名
         */
        return function(ParameterForRawSocketStressTest $p_param): ?string
        {
            $ret = $p_param->protocol()->sending();
            if($ret === true)
            {
                $p_param->protocol()->setReceivingSize($this->payload_len);
                $fnc = $this->getEchoReceiving();
                $sta = $fnc($p_param);
                return $sta;
            }

            return ProtocolForRawSocketPureLatencyBenchmarkStatusEnum::SENDING->value;
        };
    }

    /**
     * ステータス名： RECEIVING
     * 
     * 処理名：受信中
     * 
     */
    protected function getEchoReceiving()
    {
        /**
         * @param ParameterForRawSocketStressTest $p_param UNITパラメータ
         * @return ?string 遷移先のステータス名
         */
        return function(ParameterForRawSocketStressTest $p_param): ?string
        {
            $dat = $p_param->protocol()->receiving();
            if($dat === null)
            {
                return ProtocolForRawSocketPureLatencyBenchmarkStatusEnum::RECEIVING->value;
            }

            $round_end = hrtime(true);

            if($dat !== $this->payload)
            {
                printf("\nペイロード不正で終了\n");
                exit;
            }

            $p_param->sample_count++;
            if($this->stdout)
            {
                printf("sample count[{$p_param->sample_count}]\r");
            }

            if($p_param->sample_count >= $p_param->samples)
            {
                // 経過時間の算出
                $elapsed_ns = $round_end - $p_param->test_start_time;
                $elapsed_sec = intdiv($elapsed_ns, 1_000_000_000); // ナノ秒 → 秒（整数）

                $hours = intdiv($elapsed_sec, 3600);
                $minutes = intdiv($elapsed_sec % 3600, 60);
                $seconds = $elapsed_sec % 60;

                $time = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

                // ラウンドアベレージの算出
                $round_elapsed_ns = $round_end - $p_param->round_start_time;
                $round_elapsed_ms = intdiv($round_elapsed_ns, 1_000_000);
                $avg = $round_elapsed_ms / $p_param->samples;

                // セッション数算出
                $cids = $p_param->getConnectionIdAll();
                $sessions = count($cids);

                // ファイル保存
                $filename = date('Ymd');
                $path = "./logs/socket-manager/stress-test/{$filename}-{$this->protocol}-{$this->host}-{$this->port}.log";
                $fp = fopen($path, 'a');
                fputcsv($fp, [$time, $avg, $round_elapsed_ms, $sessions], ',', '"', '\\');
                fclose($fp);

                printf("\n[{$time}]avg[{$avg}]ms total[{$round_elapsed_ms}]ms sessions[{$sessions}]\n");

                $alive_start =
                [
                    'cmd' => CommandForRawSocketStressTestQueueEnum::ALIVE_START->value
                ];
                $p_param->setRecvStack($alive_start, true);
                return null;
            }

            return null;
        };
    }

}
