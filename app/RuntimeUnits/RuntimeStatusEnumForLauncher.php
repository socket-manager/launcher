<?php
/**
 * ステータス名のENUMファイル
 * 
 * StatusEnumの定義を除いて自由定義
 */

namespace App\RuntimeUnits;


use SocketManager\Library\StatusEnum;


/**
 * ランタイムUNITステータス名定義
 * 
 * ランタイムUNITのステータス予約名はSTART（処理開始）のみ
 */
enum RuntimeStatusEnumForLauncher: string
{
    //--------------------------------------------------------------------------
    // 定数（共通）
    //--------------------------------------------------------------------------

    /**
     * @var string 処理開始時のステータス共通
     */
    case START = StatusEnum::START->value;


    //--------------------------------------------------------------------------
    // 定数（RuntimeStatusEnumForLauncher::STARTUPキュー）
    //--------------------------------------------------------------------------

    /**
     * @var string サービス調査（起動用）
     */
    case EXPLORE_START = 'explore_start';

    /**
     * @var string プロセス起動
     */
    case PROCESS_START = 'process_start';

    /**
     * @var string サービス調査（停止用）
     */
    case EXPLORE_STOP = 'explore_stop';

    /**
     * @var string プロセス停止
     */
    case PROCESS_STOP = 'process_stop';

    /**
     * @var string プロセス停止ポーリング
     */
    case STOP_POLLING = 'stop_polling';

    /**
     * @var string 死活チェック（ステータス表示用）
     */
    case EXPLORE_STATUS = 'explore_status';

    /**
     * @var string CPU稼働率チェック（ステータス表示用）
     */
    case CPU_CHECK = 'cpu_check';

    /**
     * @var string メモリ使用率チェック（ステータス表示用）
     */
    case MEMORY_CHECK = 'memory_check';

    /**
     * @var string CPU情報の表示
     */
    case CPU_INFO = 'cpu_info';

    /**
     * @var string Linux上での起動処理ステータス名
     */
    case LINUX = 'linux';

}
