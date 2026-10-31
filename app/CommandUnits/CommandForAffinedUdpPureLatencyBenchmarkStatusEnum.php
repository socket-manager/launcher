<?php
/**
 * コマンドUNITステータス名のENUMファイル
 * 
 * 接続型UDPベンチマーク用
 */

namespace App\CommandUnits;


use SocketManager\Library\StatusEnum;


/**
 * コマンドUNITステータス名定義
 * 
 * 接続型UDPベンチマーク用
 */
enum CommandForAffinedUdpPureLatencyBenchmarkStatusEnum: string
{
    /**
     * @var string 処理開始時のステータス名
     */
    case START = StatusEnum::START->value;

}
