<?php
/**
 * コマンドUNITステータス名のENUMファイル
 * 
 * Websocket用
 */

namespace App\CommandUnits;


use SocketManager\Library\StatusEnum;


/**
 * コマンドUNITステータス名定義
 * 
 * Websocket用
 */
enum CommandForStressTestStatusEnum: string
{
    /**
     * @var 処理開始時のステータス名
     */
    case START = StatusEnum::START->value;

}
