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
enum CommandForWebsocketQueueEnum: string
{
    case ENTERING = 'entering';
    case ENTERING_RESPONSE = 'entering_response';
    case MESSAGE = 'message';
    case PRIVATE_MESSAGE = 'private_message';
    case PRIVATE_MESSAGE_RESPONSE = 'private_message_response';
    case LEAVING = 'leaving';
    case LAUNCHER_LOG = 'launcher_log';
    case CORE_COUNT = 'core_count';
    case SERVICE_LIST = 'service_list';
    case RESOURCE_CPU = 'resource_cpu';
    case RESOURCE_MEMORY = 'resource_memory';
    case RESOURCE_DISK = 'resource_disk';
    case ACTION = 'action';
    case SETTING_ACTION = 'setting_action';
    case ACTION_RESPONSE = 'action_response';
    case SETTING_ACTION_RESPONSE = 'setting_action_response';
    case ACTION_NOTICE = 'action_notice';
    case CUSTOM_PARTS = 'custom_parts';
}
