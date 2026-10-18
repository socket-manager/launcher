<?php
/**
 * コマンド部のキュー名のENUMファイル
 * 
 * 純粋 UDP 耐久テスト用
 */

namespace App\CommandUnits;


/**
 * コマンド部のキュー名定義
 * 
 * 純粋 UDP 耐久テスト用
 */
enum CommandForPureUdpStressTestQueueEnum: string
{
    case ALIVE_START = 'alive_start';
}
