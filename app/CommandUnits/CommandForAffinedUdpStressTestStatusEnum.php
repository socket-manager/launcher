<?php
/**
 * コマンドUNITステータス名のENUMファイル
 * 
 * 接続型UDP用
 */

namespace App\CommandUnits;


use SocketManager\Library\StatusEnum;


/**
 * コマンドUNITステータス名定義
 * 
 * 接続型UDP用
 */
enum CommandForAffinedUdpStressTestStatusEnum: string
{
    /**
     * @var string 処理開始時のステータス名
     */
    case START = StatusEnum::START->value;

}
