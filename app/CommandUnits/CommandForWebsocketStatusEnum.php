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
enum CommandForWebsocketStatusEnum: string
{
    /**
     * @var 処理開始時のステータス名
     */
    case START = StatusEnum::START->value;

    case LOG_COLLECTION = 'log_collection';

    case LOG_SEND = 'log_send';

    case SERVICE_LIST = 'service_list';

    case RESPONSE = 'response';

    case BROADCAST = 'broadcast';

    case CLOSE = 'close';
}
