<?php
/**
 * ステータスUNIT登録クラスのファイル
 * 
 * SocketManagerのsetCommandUnitsメソッドへ引き渡されるクラスのファイル
 */

namespace App\CommandUnits;

use SocketManager\Library\IEntryUnits;

use App\UnitParameter\ParameterForPureLatencyBenchmark;


/**
 * コマンドUNIT登録クラス
 * 
 * IEntryUnitsインタフェースをインプリメントする
 */
class CommandForPureLatencyBenchmark implements IEntryUnits
{
    /**
     * @var const QUEUE_LIST キュー名のリスト
     */
    protected const QUEUE_LIST = [
        CommandForPureLatencyBenchmarkQueueEnum::PING_START->value
    ];

    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * @var int サンプリング間隔（ms）
     */
    private int $sampling_interval = 1;

    /**
     * @var int ping/pontタイムアウト（s）
     */
    private int $timeout = 10;

    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     */
    public function __construct(int $p_sampling_interval, int $p_timeout)
    {
        $this->sampling_interval = $p_sampling_interval;
        $this->timeout = $p_timeout;
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

        if($p_que === CommandForPureLatencyBenchmarkQueueEnum::PING_START->value)
        {
            $ret[] = [
                'status' => CommandForPureLatencyBenchmarkStatusEnum::START->value,
                'unit' => $this->getPingStart()
            ];
        }

        return $ret;
    }

    protected function getPingStart()
    {
        return function(ParameterForPureLatencyBenchmark $p_param): ?string
        {
            $p_param->setTimeout($this->sampling_interval);

            $p_param->aliveCheck($this->timeout);

            return null;
        };
    }
}
