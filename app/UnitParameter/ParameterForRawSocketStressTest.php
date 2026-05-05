<?php
/**
 * UNITパラメータクラスのファイル
 * 
 * Websocket版
 */

namespace App\UnitParameter;


/**
 * UNITパラメータクラス
 * 
 * UNITパラメータクラスのSocketManagerParameterをオーバーライドする
 */
class ParameterForRawSocketStressTest extends ParameterForBenchmark
{
    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * TLSフラグ
     */
    protected bool $tls = false;

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
     * @param ?bool $p_tls TLSフラグ
     */
    public function __construct(int $p_samples, int $p_sampling_interval)
    {
        parent::__construct();

        $this->samples = $p_samples;
        $this->sampling_interval = $p_sampling_interval;
    }

}
