<?php
/**
 * コマンドUNITキュー名のENUMファイル
 * 
 * 接続型UDPベンチマーク用
 */

namespace App\CommandUnits;


/**
 * コマンド部のキュー名定義
 * 
 * 接続型UDPベンチマーク用
 */
enum CommandForAffinedUdpPureLatencyBenchmarkQueueEnum: string
{
    case ECHO_START = 'echo_start';
}
