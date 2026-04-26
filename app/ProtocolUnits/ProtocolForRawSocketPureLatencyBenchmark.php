<?php
/**
 * ステータスUNIT登録クラスのファイル
 * 
 * SocketManagerのsetProtocolUnitsメソッドへ引き渡されるクラスのファイル
 */

namespace App\ProtocolUnits;

use SocketManager\Library\ProtocolQueueEnum;
use SocketManager\Library\SocketManagerParameter;

use App\CommandUnits\CommandForRawSocketPureLatencyBenchmarkQueueEnum;

/**
 * プロトコルUNIT登録クラス
 * 
 * ParameterForBenchmarkインタフェースをインプリメントする
 */
class ProtocolForRawSocketPureLatencyBenchmark extends ProtocolForBenchmark
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
        $this->stdout = config('benchmark.stdout', false);
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
                'status' => ProtocolForRawSocketPureLatencyBenchmarkStatusEnum::START->value,
                'unit' => $this->getConnectStart()
            ];
        }
        else
        if($p_que === ProtocolQueueEnum::ALIVE->value)
        {
            $ret[] = [
                'status' => ProtocolForRawSocketPureLatencyBenchmarkStatusEnum::START->value,
                'unit' => $this->getEchoStart()
            ];
            $ret[] = [
                'status' => ProtocolForRawSocketPureLatencyBenchmarkStatusEnum::SENDING->value,
                'unit' => $this->getEchoSending()
            ];
            $ret[] = [
                'status' => ProtocolForRawSocketPureLatencyBenchmarkStatusEnum::RECEIVING->value,
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
     * @param SocketManagerParameter $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getConnectStart()
    {
        return function(SocketManagerParameter $p_param): ?string
        {
            $echo_start =
            [
                'cmd' => CommandForRawSocketPureLatencyBenchmarkQueueEnum::ECHO_START->value
            ];
            $p_param->setRecvStack($echo_start, true);

            return null;
        };
    }

    /**
     * 以降はステータスUNITの定義（"ALIVE"キュー）
     */

    /**
     * ステータス名： START
     * 
     * 処理名：送信開始
     * 
     * @param SocketManagerParameter $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getEchoStart()
    {
        return function(SocketManagerParameter $p_param): ?string
        {
            $p_param->protocol()->setSendingData($this->payload);

            $start = hrtime(true);
            $alive_timer = [
                'alive_timer' => [
                    'start' => $start,
                    'end' => null
                ]
            ];
            $p_param->setTempBuff($alive_timer);

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
     * @param SocketManagerParameter $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getEchoSending()
    {
        return function(SocketManagerParameter $p_param): ?string
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
     * @param SocketManagerParameter $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getEchoReceiving()
    {
        return function(SocketManagerParameter $p_param): ?string
        {
            $dat = $p_param->protocol()->receiving();
            if($dat === null)
            {
                return ProtocolForRawSocketPureLatencyBenchmarkStatusEnum::RECEIVING->value;
            }

            $end = hrtime(true);
            $timer = $p_param->getTempBuff(['alive_timer']);
            $this->samples_array[] = ($end - $timer['alive_timer']['start'])/1000000;

            if($dat !== $this->payload)
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
                'cmd' => CommandForRawSocketPureLatencyBenchmarkQueueEnum::ECHO_START->value
            ];
            $p_param->setRecvStack($echo_start, true);

            return null;
        };
    }
}
