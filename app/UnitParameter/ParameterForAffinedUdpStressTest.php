<?php
/**
 * UNITパラメータクラスのファイル
 * 
 * 接続型 UDP 版耐久テスト用
 */

namespace App\UnitParameter;

use SocketManager\Library\AffinedUdp\SocketManagerParameter;


/**
 * UNITパラメータクラス
 * 
 * UNITパラメータクラスのSocketManagerParameterをオーバーライドする
 */
class ParameterForAffinedUdpStressTest extends SocketManagerParameter
{
    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * 同時接続数
     */
    public int $samples = 0;

    /**
     * サンプリング間隔
     */
    public int $sampling_interval = 0;

    /**
     * 次回タイムアウト
     */
    public int $next_timeout = 0;

    /**
     * サンプルカウンター
     */
    public int $sample_count = 0;

    /**
     * テスト開始時間
     */
    public int $test_start_time = 0;

    /**
     * ラウンド開始時間
     */
    public int $round_start_time = 0;


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     * @param int $p_samples 同時接続数
     * @param int $p_sampling_interval サンプリング間隔
     */
    public function __construct(int $p_samples, int $p_sampling_interval)
    {
        parent::__construct();

        $this->samples = $p_samples;
        $this->sampling_interval = $p_sampling_interval;
    }

}
