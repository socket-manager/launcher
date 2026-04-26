<?php
/**
 * コマンド部のキュー名のENUMファイル
 * 
 * Websocket用
 */

namespace App\CommandUnits;


/**
 * コマンド部のキュー名定義
 * 
 * TCP用
 */
enum CommandForRawSocketPureLatencyBenchmarkQueueEnum: string
{
    case ECHO_START = 'echo_start';
}
