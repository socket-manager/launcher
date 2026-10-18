<?php
/**
 * コマンドUNITステータス名のENUMファイル
 * 
 * 純粋UDPベンチマーク用
 */

namespace App\CommandUnits;


use SocketManager\Library\StatusEnum;


/**
 * コマンドUNITステータス名定義
 * 
 * 純粋UDPベンチマーク用
 */
enum CommandForPureUdpPureLatencyBenchmarkStatusEnum: string
{
    /**
     * @var string 処理開始時のステータス名
     */
    case START = StatusEnum::START->value;

}
