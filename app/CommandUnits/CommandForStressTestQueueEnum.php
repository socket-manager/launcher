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
 * Websocket用
 */
enum CommandForStressTestQueueEnum: string
{
    case PING_START = 'ping_start';
}
