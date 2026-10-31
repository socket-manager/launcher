<?php
/**
 * ステータスUNIT登録クラスのファイル
 * 
 * モジュール管理クラスのsetProtocolUnitsメソッドへ引き渡されるクラスのファイル
 */

namespace App\ProtocolUnits;

use SocketManager\Library\ProtocolQueueEnum;
use SocketManager\Library\IEntryUnits;
use SocketManager\Library\AffinedUdp\SocketManagerParameter;

use App\CommandUnits\CommandForAffinedUdpPureLatencyBenchmarkQueueEnum;


/**
 * プロトコルUNIT登録クラス
 * 
 * IEntryUnitsインタフェースをインプリメントする
 */
class ProtocolForAffinedUdpPureLatencyBenchmark implements IEntryUnits
{
    //--------------------------------------------------------------------------
    // 定数
    //--------------------------------------------------------------------------

    /**
     * @var array QUEUE_LIST キュー名のリスト
     */
    protected const QUEUE_LIST = [
        ProtocolQueueEnum::CONNECT->value,  // コネクションキュー
        ProtocolQueueEnum::ALIVE->value		// アライブチェック処理のキュー
    ];


    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * @var bool 測定中の標準出力フラグ
     */
    private bool $stdout = false;

    /**
     * @var int サンプル回数
     */
    private int $samples_max = 1;

    /**
     * @var string ペイロード
     */
    private string $payload = '';

    /**
     * @var int ペイロード長
     */
    private int $payload_len = 0;

    /**
     * @var int サンプルカウンター
     */
    private int $samples_count = 0;

    /**
     * @var array サンプル値配列
     */
    private array $samples_array = [];

    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     */
    public function __construct(int $p_samples, string $p_payload)
    {
        $this->stdout = config('bench_affined_udp.stdout', false);
        $this->samples_max = $p_samples;
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
                'status' => ProtocolForAffinedUdpPureLatencyBenchmarkStatusEnum::START->value,
                'unit' => $this->connectStart(...)
            ];
        }
        else
        if($p_que === ProtocolQueueEnum::ALIVE->value)
        {
            $ret[] = [
                'status' => ProtocolForAffinedUdpPureLatencyBenchmarkStatusEnum::START->value,
                'unit' => $this->echoStart(...)
            ];
            $ret[] = [
                'status' => ProtocolForAffinedUdpPureLatencyBenchmarkStatusEnum::SENDING->value,
                'unit' => $this->echoSending(...)
            ];
            $ret[] = [
                'status' => ProtocolForAffinedUdpPureLatencyBenchmarkStatusEnum::RECEIVING->value,
                'unit' => $this->echoReceiving(...)
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
     * @param SocketManagerParameter $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    private function connectStart(SocketManagerParameter $p_param): ?string
    {
        $echo_start =
        [
            'cmd' => CommandForAffinedUdpPureLatencyBenchmarkQueueEnum::ECHO_START->value
        ];
        $p_param->setRecvStack($echo_start, true);

        return null;
    }


    //--------------------------------------------------------------------------
    // 以降はステータスUNITの定義（"ALIVE"キュー）
    //--------------------------------------------------------------------------

    /**
     * ステータス名： START
     * 
     * 処理名：送信開始
     * 
     * @param SocketManagerParameter $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    private function echoStart(SocketManagerParameter $p_param): ?string
    {
        $p_param->protocol()->setSendingData($this->payload);
        return $this->echoSending($p_param);
    }

    /**
     * ステータス名： SENDING
     * 
     * 処理名：送信中
     * 
     * @param SocketManagerParameter $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    private function echoSending(SocketManagerParameter $p_param): ?string
    {
        $size = $p_param->protocol()->sending();
        if($size === null)
        {
            return ProtocolForAffinedUdpPureLatencyBenchmarkStatusEnum::SENDING->value;
        }
        if($size < $this->payload_len)
        {
            $p_param->emergencyShutdown();
        }

        $start = hrtime(true);
        $alive_timer = [
            'alive_timer' => [
                'start' => $start,
                'end' => null
            ]
        ];
        $p_param->setTempBuff($alive_timer);

        $p_param->protocol()->setReceivingSize($this->payload_len);
        return ProtocolForAffinedUdpPureLatencyBenchmarkStatusEnum::RECEIVING->value;
    }

    /**
     * ステータス名： RECEIVING
     * 
     * 処理名：受信中
     * 
     * @param SocketManagerParameter $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    private function echoReceiving(SocketManagerParameter $p_param): ?string
    {
        $data = $p_param->protocol()->receiving();
        if($data === null)
        {
            return ProtocolForAffinedUdpPureLatencyBenchmarkStatusEnum::RECEIVING->value;
        }

        $end = hrtime(true);
        $timer = $p_param->getTempBuff(['alive_timer']);
        $this->samples_array[] = ($end - $timer['alive_timer']['start'])/1000000;

        if($data !== $this->payload)
        {
            printf("\nペイロード不正で終了\n");
            exit;
        }

        $this->samples_count++;
        if($this->stdout)
        {
            printf("sample count[{$this->samples_count}]\r");
        }
        if($this->samples_count >= $this->samples_max)
        {
            $vals = $this->samples_array;
            $bench = get_benchmark($vals);
            $bench['total'] = $bench['total']/1000;
            printf("\navg[{$bench['avg']}]ms mid[{$bench['mid']}]ms min[{$bench['min']}]ms max[{$bench['max']}]ms p90[{$bench['p90']}]ms p95[{$bench['p95']}]ms total[{$bench['total']}]s\n");
            return null;
        }

        $echo_start =
        [
            'cmd' => CommandForAffinedUdpPureLatencyBenchmarkQueueEnum::ECHO_START->value
        ];
        $p_param->setRecvStack($echo_start, true);

        return null;
    }
}
