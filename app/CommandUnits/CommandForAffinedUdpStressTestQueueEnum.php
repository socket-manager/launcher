<?php
/**
 * コマンド部のキュー名のENUMファイル
 * 
 * 接続型 UDP 耐久テスト用
 */

namespace App\CommandUnits;


/**
 * コマンド部のキュー名定義
 * 
 * 接続型 UDP 耐久テスト用
 */
enum CommandForAffinedUdpStressTestQueueEnum: string
{
    case ALIVE_START = 'alive_start';
}
