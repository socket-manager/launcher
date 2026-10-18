<?php
/**
 * コマンドUNITキュー名のENUMファイル
 * 
 * 純粋UDPベンチマーク用
 */

namespace App\CommandUnits;


/**
 * コマンド部のキュー名定義
 * 
 * 純粋UDPベンチマーク用
 */
enum CommandForPureUdpPureLatencyBenchmarkQueueEnum: string
{
    case ECHO_START = 'echo_start';
}
