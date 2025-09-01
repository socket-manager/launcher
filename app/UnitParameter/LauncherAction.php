<?php
/**
 * ランチャーアクション定義のEnumファイル
 * 
 * 起動・停止などの基本的なアクションの定義
 */

namespace App\UnitParameter;


/**
 * ランチャーアクション定義
 * 
 * 起動・停止などの基本的なアクションの定義
 */
enum LauncherAction: string
{
    /**
     * @var 起動アクション
     */
    case START = 'start';

    /**
     * @var 全起動アクション
     */
    case START_ALL = 'startall';

    /**
     * @var 停止アクション
     */
    case STOP = 'stop';

    /**
     * @var 全停止アクション
     */
    case STOP_ALL = 'stopall';

    /**
     * @var 再起動アクション
     */
    case RESTART = 'restart';

    /**
     * @var 全再起動アクション
     */
    case RESTART_ALL = 'restartall';

    /**
     * @var ステータス表示アクション
     */
    case STATUS = 'status';

    /**
     * @var 全ステータス表示アクション
     */
    case STATUS_ALL = 'statusall';

    /**
     * @var CPU情報表示
     */
    case CPU_INFO = 'cpuinfo';

    /**
     * @var ランチャーシャットダウン
     */
    case SHUTDOWN = 'shutdown';


    /**
     * 内部アクション
     */

    /**
     * @var 自動再起動
     */
    case AUTO_RESTART = 'auto-restart';

    /**
     * @var 停止検知
     */
    case DETECT_STOP = 'detect-stop';

    /**
     * @var GUI起動
     */
    case GUI_START = 'gui-start';

};
