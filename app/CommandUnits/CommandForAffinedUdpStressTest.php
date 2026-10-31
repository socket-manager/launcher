<?php
/**
 * ステータスUNIT登録クラスのファイル
 * 
 * モジュール管理クラスのsetCommandUnitsメソッドへ引き渡されるクラスのファイル
 */

namespace App\CommandUnits;

use SocketManager\Library\IEntryUnits;

use App\UnitParameter\ParameterForAffinedUdpStressTest;
use SocketManager\Library\UnitKindEnum;

/**
 * コマンドUNIT登録クラス
 * 
 * IEntryUnitsインタフェースをインプリメントする
 */
class CommandForAffinedUdpStressTest implements IEntryUnits
{
    /**
     * @var array QUEUE_LIST キュー名のリスト
     */
    protected const QUEUE_LIST = [
        CommandForAffinedUdpStressTestQueueEnum::ALIVE_START->value
    ];

    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * @var int 測定中のタイムアウト（s）
     */
    private int $timeout = 10;

    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     * @param int $p_timeout ping/pontタイムアウト（s）
     */
    public function __construct(int $p_timeout)
    {
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

        if($p_que === CommandForAffinedUdpStressTestQueueEnum::ALIVE_START->value)
        {
            $ret[] = [
                'status' => CommandForAffinedUdpStressTestStatusEnum::START->value,
                'unit' => $this->aliveStart(...)
            ];
        }

        return $ret;
    }

    private function aliveStart(ParameterForAffinedUdpStressTest $p_param): ?string
    {
        // 次回タイムアウトまで保留
        $now = hrtime(true);
        if($now < $p_param->next_timeout)
        {
            return CommandForAffinedUdpStressTestStatusEnum::START->value;
        }
        else
        {
            // サンプル数が不足
            if($p_param->sample_count < $p_param->samples)
            {
                printf("\nサンプル数不足のため終了\n");
                exit;
            }
        }

        // アライブチェック実行
        $mgr = $p_param->getSocketManager();
        $cids = $p_param->getConnectionIdAll();
        if(count($cids) < $p_param->samples)
        {
            printf("\n接続数不足のため終了\n");
            exit;
        }
        foreach($cids as $cid)
        {
            $mgr->aliveCheck(UnitKindEnum::PROTOCOL, $cid, $this->timeout);
        }

        // サンプルカウンターのリセット
        $p_param->sample_count = 0;

        // 次回タイムアウトの設定
        $p_param->next_timeout = $now + ($p_param->sampling_interval * 1000000000);

        // サンプリング開始時間設定
        $p_param->round_start_time = hrtime(true);

        return null;
    }
}
