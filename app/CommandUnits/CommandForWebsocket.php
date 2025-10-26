<?php
/**
 * ステータスUNIT登録クラスのファイル
 * 
 * SocketManagerのsetCommandUnitsメソッドへ引き渡されるクラスのファイル
 */

namespace App\CommandUnits;

use App\RuntimeUnits\RuntimeStatusEnumForLauncher;
use SocketManager\Library\IEntryUnits;

use App\UnitParameter\ParameterForWebsocket;


/**
 * コマンドUNIT登録クラス
 * 
 * IEntryUnitsインタフェースをインプリメントする
 */
class CommandForWebsocket implements IEntryUnits
{
    /**
     * @var const QUEUE_LIST キュー名のリスト
     */
    protected const QUEUE_LIST = [
        CommandForWebsocketQueueEnum::ENTERING->value,
        CommandForWebsocketQueueEnum::ACTION->value,
        CommandForWebsocketQueueEnum::SETTING_ACTION->value,
        CommandForWebsocketQueueEnum::MESSAGE->value,
        CommandForWebsocketQueueEnum::PRIVATE_MESSAGE->value,
        CommandForWebsocketQueueEnum::LEAVING->value
    ];


    /**
     * コンストラクタ
     * 
     */
    public function __construct()
    {
    }

    /**
     * キューリストの取得
     * 
     * @return array キュー名のリスト
     */
    public function getQueueList(): array
    {
        return (array)static::QUEUE_LIST;
    }

    /**
     * ステータスUNITリストの取得
     * 
     * @param string $p_que キュー名
     * @return array キュー名に対応するUNITリスト
     */
    public function getUnitList(string $p_que): array
    {
        $ret = [];

        if($p_que === CommandForWebsocketQueueEnum::ENTERING->value)
        {
            $ret[] = [
                'status' => CommandForWebsocketStatusEnum::START->value,
                'unit' => $this->getEnteringStart()
            ];
            $ret[] = [
                'status' => CommandForWebsocketStatusEnum::LOG_COLLECTION->value,
                'unit' => $this->getEnteringLogCollection()
            ];
            $ret[] = [
                'status' => CommandForWebsocketStatusEnum::LOG_SEND->value,
                'unit' => $this->getEnteringLogSend()
            ];
            $ret[] = [
                'status' => CommandForWebsocketStatusEnum::SERVICE_LIST->value,
                'unit' => $this->getEnteringServiceList()
            ];
            $ret[] = [
                'status' => CommandForWebsocketStatusEnum::RESPONSE->value,
                'unit' => $this->getEnteringResponse()
            ];
            $ret[] = [
                'status' => CommandForWebsocketStatusEnum::BROADCAST->value,
                'unit' => $this->getEnteringBroadcast()
            ];
        }
        if($p_que === CommandForWebsocketQueueEnum::ACTION->value)
        {
            $ret[] = [
                'status' => CommandForWebsocketStatusEnum::START->value,
                'unit' => $this->getActionStart()
            ];
            $ret[] = [
                'status' => CommandForWebsocketStatusEnum::BROADCAST->value,
                'unit' => $this->getActionNotice()
            ];
        }
        if($p_que === CommandForWebsocketQueueEnum::SETTING_ACTION->value)
        {
            $ret[] = [
                'status' => CommandForWebsocketStatusEnum::START->value,
                'unit' => $this->getSettingActionStart()
            ];
        }
        if($p_que === CommandForWebsocketQueueEnum::MESSAGE->value)
        {
            $ret[] = [
                'status' => CommandForWebsocketStatusEnum::START->value,
                'unit' => $this->getMessageStart()
            ];
        }
        if($p_que === CommandForWebsocketQueueEnum::PRIVATE_MESSAGE->value)
        {
            $ret[] = [
                'status' => CommandForWebsocketStatusEnum::START->value,
                'unit' => $this->getPrivateMessageStart()
            ];
        }
        if($p_que === CommandForWebsocketQueueEnum::LEAVING->value)
        {
            $ret[] = [
                'status' => CommandForWebsocketStatusEnum::START->value,
                'unit' => $this->getLeavingStart()
            ];
            $ret[] = [
                'status' => CommandForWebsocketStatusEnum::CLOSE->value,
                'unit' => $this->getLeavingClose()
            ];
        }

        return $ret;
    }


    //--------------------------------------------------------------------------
    // 以降はステータスUNITの定義（"ENTERING"キュー）
    //--------------------------------------------------------------------------

    /**
     * ステータス名： START
     * 
     * 処理名：コア数通知
     * 
     * @param ParameterForWebsocket $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getEnteringStart()
    {
        return function(ParameterForWebsocket $p_param): ?string
        {
            $p_param->logWriter('debug', ['COMMAND:ENTERING' => 'START']);

            $launcher = $p_param->getParameterLauncher();
            $core_count =
            [
                'cmd' => CommandForWebsocketQueueEnum::CORE_COUNT->value,
                'count' => $launcher->max_core
            ];
            $data =
            [
                'data' => $core_count
            ];
            $p_param->setSendStack($data);

            $w_ret = $p_param->getRecvData();
            $payload = $w_ret['data'];
            $p_param->setTempBuff([
                'recv_data' => $payload
            ]);

            return CommandForWebsocketStatusEnum::LOG_COLLECTION->value;
        };
    }

    /**
     * ステータス名： LOG_COLLECTION
     * 
     * 処理名：ログ収集
     * 
     * @param ParameterForWebsocket $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getEnteringLogCollection()
    {
        return function(ParameterForWebsocket $p_param): ?string
        {
            $p_param->logWriter('debug', ['COMMAND:ENTERING' => 'LOG_COLLECTION']);

            // ログファイル名
            $filename = 'Ymd';
            $log_cycle = config('launcher.log_cycle', 'daily');
            if($log_cycle === 'monthly')
            {
                $filename = 'Ym';
            }
            $filename = date($filename);

            // ランチャー用ログファイルのパス
            $log_path = config('launcher.log_path_for_launcher');

            $log_file_path = "{$log_path}/{$filename}.log";
            if(file_exists($log_file_path))
            {
                $lines = file($log_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $log_json = implode(',', $lines);
                $log_list = json_decode("[{$log_json}]", true);
                if(json_last_error() === JSON_ERROR_NONE)
                {
                    $p_param->log_list = array_slice($log_list, -100);
                }
            }

            return CommandForWebsocketStatusEnum::LOG_SEND->value;
        };
    }

    /**
     * ステータス名： LOG_SEND
     * 
     * 処理名：ログ送信
     * 
     * @param ParameterForWebsocket $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getServiceListLogSend()
    {
        return function(ParameterForWebsocket $p_param): ?string
        {
            $p_param->logWriter('debug', ['COMMAND:ENTERING' => 'LOG_SEND SUB']);

            $w_log = array_shift($p_param->log_list);
            if($w_log === null)
            {
                return null;
            }

            $log = $w_log;
            foreach($w_log as $key => $val)
            {
                if($val === null)
                {
                    $log[$key] = 'ー';
                }
            }

            $log['message'] = htmlspecialchars($log['message']);
            $launcher_log =
            [
                'cmd' => CommandForWebsocketQueueEnum::LAUNCHER_LOG->value,
                'log' => $log
            ];
            $data =
            [
                'data' => $launcher_log
            ];
            $p_param->setSendStack($data);

            $status = $p_param->getStatusName();
            return $status;
        };
    }

    /**
     * ステータス名： LOG_SEND
     * 
     * 処理名：ログ送信
     * 
     * @param ParameterForWebsocket $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getEnteringLogSend()
    {
        return function(ParameterForWebsocket $p_param): ?string
        {
            $p_param->logWriter('debug', ['COMMAND:ENTERING' => 'LOG_SEND']);

            $fnc = $this->getServiceListLogSend();
            $w_ret = $fnc($p_param);
            if($w_ret === null)
            {
                return CommandForWebsocketStatusEnum::SERVICE_LIST->value;
            }

            return $w_ret;
        };
    }

    /**
     * ステータス名： SERVICE_LIST
     * 
     * 処理名：サービスリスト再構築
     * 
     * @param ParameterForWebsocket $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getEnteringServiceList()
    {
        return function(ParameterForWebsocket $p_param): ?string
        {
            $p_param->logWriter('debug', ['COMMAND:ENTERING' => 'SERVICE_LIST']);

            $status = $p_param->getStatusName();
            if($p_param->service_list === null)
            {
                return $status;
            }

            $service_list = $p_param->service_list;
            $max = count($service_list);
            for($i = 0; $i < $max; $i++)
            {
                if($service_list[$i]['pid'] !== null)
                {
                    $service_list[$i]['status'] = '起動中';
                    $service_list[$i]['cpu'] = sprintf('% 3d%%', $service_list[$i]['cpu']);
                    $service_list[$i]['memory'] = sprintf('%6.2f%%', $service_list[$i]['memory']);
                }
                else
                {
                    $service_list[$i]['status'] = '停止中';
                    $service_list[$i]['cpu'] = 'ー';
                    $service_list[$i]['memory'] = 'ー';
                    $service_list[$i]['timestamp'] = 'ー';
                }
            }
            $p_param->setTempBuff(['service_list' => $service_list]);

            return CommandForWebsocketStatusEnum::RESPONSE->value;
        };
    }

    /**
     * ステータス名： RESPONSE
     * 
     * 処理名：接続処理開始
     * 
     * @param ParameterForWebsocket $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getEnteringResponse()
    {
        return function(ParameterForWebsocket $p_param): ?string
        {
            $p_param->logWriter('debug', ['COMMAND:ENTERING' => 'RESPONSE']);

            $w_ret = $p_param->getTempBuff(['recv_data', 'service_list']);
            $payload = $w_ret['recv_data'];

            $payload['user'] = str_replace('/', '', $payload['user']);
            $payload['user'] = htmlspecialchars($payload['user'], ENT_QUOTES, 'UTF-8');
            $user = __('launcher.OPTION_ADMIN_USER');
            $message = __('launcher.INFO_ENTERING_SUCCESS');
            $result = $p_param->entryUser($payload['user'], $message);
            if($result === true)
            {
                $user = $payload['user'];
            }
            $user_list = $p_param->getUserList();
            $response =
            [
                'cmd' => CommandForWebsocketQueueEnum::ENTERING_RESPONSE->value,
                'result' => $result,
                'message' => $message,
                'datetime' => date(ParameterForWebsocket::DATETIME_FORMAT),
                'uid' => $p_param->getConnectionId(),
                'user' => $user,
                'user_list' => $user_list,
                'service_list' => $w_ret['service_list'],
                'enable_save_flg' => $p_param->setting_edit_flg,
                'option_message' => $p_param->option_message
            ];
            $data =
            [
                'data' => $response
            ];
            $p_param->setSendStack($data);

            return CommandForWebsocketStatusEnum::BROADCAST->value;
        };
    }

    /**
     * ステータス名： BROADCAST
     * 
     * 処理名：接続通知
     * 
     * @param ParameterForWebsocket $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getEnteringBroadcast()
    {
        return function(ParameterForWebsocket $p_param): ?string
        {
            $p_param->logWriter('debug', ['COMMAND:ENTERING' => 'BROADCAST']);

            $cid = $p_param->getConnectionId();
            $datetime = date(ParameterForWebsocket::DATETIME_FORMAT);
            $user_name = $p_param->getUserName($cid);
            $broadcast =
            [
                'cmd' => CommandForWebsocketQueueEnum::ENTERING->value,
                'message' => __('launcher.INFO_ENTERING_SUCCESS', ['user' => $user_name]),
                'datetime' => $datetime,
                'uid' => $cid,
                'user' => $user_name
            ];
            $data =
            [
                'data' => $broadcast
            ];
            $p_param->setSendStackAll($data, true);

            $p_param->chatLogWriter($datetime, $user_name, $broadcast['message']);
            return null;
        };
    }


    //--------------------------------------------------------------------------
    // 以降はステータスUNITの定義（"ACTION"キュー）
    //--------------------------------------------------------------------------

    /**
     * ステータス名： START
     * 
     * 処理名：Launcherアクションの処理
     * 
     * @param ParameterForWebsocket $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getActionStart()
    {
        return function(ParameterForWebsocket $p_param): ?string
        {
            $p_param->logWriter('debug', ['COMMAND:ACTION' => 'START']);

            $w_ret = $p_param->getRecvData();
            $payload = $w_ret['data'];

            $w_ret = $p_param->isOperatingLauncher();
            $result = false;
            $message = null;
            if($w_ret !== true)
            {
                $result = true;
            }
            else
            {
                $message = __('launcher.ERROR_LAUNCHER_BUSY');
            }
            $response =
            [
                'cmd' => CommandForWebsocketQueueEnum::ACTION_RESPONSE->value,
                'result' => $result,
                'message' => $message
            ];
            $data =
            [
                'data' => $response
            ];
            $p_param->setSendStack($data);

            if($w_ret === true)
            {
                return null;
            }

            $service_key = 'name';
            if($payload['group'] !== false)
            {
                $service_key = 'group';
            }
            else
            if($payload['group'] === null)
            {
                $service_key = null;
            }

            $who = $p_param->getUserName();
            $p_param->param_launcher->setOrderInternalAction($payload['action'], $service_key, $payload['service'], 'GUI', $who);

            $p_param->param_launcher->setStatusName(RuntimeStatusEnumForLauncher::START->value);

            return CommandForWebsocketStatusEnum::BROADCAST->value;
        };
    }

    /**
     * ステータス名： BROADCAST
     * 
     * 処理名：Launcherアクション後の通知
     * 
     * @param ParameterForWebsocket $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getActionNotice()
    {
        return function(ParameterForWebsocket $p_param): ?string
        {
            $p_param->logWriter('debug', ['COMMAND:ACTION' => 'NOTICE']);

            $notice =
            [
                'cmd' => CommandForWebsocketQueueEnum::ACTION_NOTICE->value,
                'is_guard' => true,
                'message' => __('launcher.NOTICE_LAUNCHER_BUSY')
            ];
            $data =
            [
                'data' => $notice
            ];
            $p_param->setSendStackAll($data);

            return null;
        };
    }


    //--------------------------------------------------------------------------
    // 以降はステータスUNITの定義（"SETTING_ACTION"キュー）
    //--------------------------------------------------------------------------

    /**
     * ステータス名： START
     * 
     * 処理名：設定アクション
     * 
     * @param ParameterForWebsocket $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getSettingActionStart()
    {
        return function(ParameterForWebsocket $p_param): ?string
        {
            $p_param->logWriter('debug', ['COMMAND:SETTING_ACTION' => 'START']);

            $w_ret = $p_param->getRecvData();
            $payload = $w_ret['data'];

            $command_type = $payload['type'];

            if(isset($payload['items']['name']))
            {
                $payload['items']['name'] = htmlspecialchars($payload['items']['name'], ENT_QUOTES, 'UTF-8');
            }
            if(isset($payload['items']['group']))
            {
                $payload['items']['group'] = htmlspecialchars($payload['items']['group'], ENT_QUOTES, 'UTF-8');
            }
            if(isset($payload['items']['path']))
            {
                $payload['items']['path'] = htmlspecialchars($payload['items']['path'], ENT_QUOTES, 'UTF-8');
            }
            if(isset($payload['items']['command']))
            {
                $payload['items']['command'] = htmlspecialchars($payload['items']['command'], ENT_QUOTES, 'UTF-8');
            }

            // busyチェック
            $is_busy = $p_param->isOperatingLauncher();
            if($is_busy === true && $command_type !== 'save')
            {
                $response =
                [
                    'cmd' => CommandForWebsocketQueueEnum::SETTING_ACTION_RESPONSE->value,
                    'type' => $command_type,
                    'result' => false,
                    'message' => __('launcher.ERROR_LAUNCHER_BUSY')
                ];
                $data =
                [
                    'data' => $response
                ];
                $p_param->setSendStack($data);

                return null;
            }

            // 対象サービスの処理
            $type = $message = '';
            if($command_type === 'delete')
            {
                $max = count($p_param->param_launcher->service_list_all);
                for($i = 0; $i < $max; $i++)
                {
                    if($p_param->param_launcher->service_list_all[$i]['name'] === $payload['service'])
                    {
                        unset($p_param->param_launcher->service_list_all[$i]);
                        $p_param->param_launcher->service_list_all = array_values($p_param->param_launcher->service_list_all);
                        $p_param->setting_edit_flg = true;
                        $type = 'setting_delete';
                        $message = __('launcher.NOTICE_DELETE_SERVICE', ['target' => $payload['service']]);
                        break;
                    }
                }
                if($i > $max)
                {
                    $response =
                    [
                        'cmd' => CommandForWebsocketQueueEnum::SETTING_ACTION_RESPONSE->value,
                        'type' => $command_type,
                        'result' => false,
                        'message' => __('launcher.ERROR_NO_TARGET_SERVICE', ['action' => '削除'])
                    ];
                    $data =
                    [
                        'data' => $response
                    ];
                    $p_param->setSendStack($data);

                    return null;
                }
            }
            else
            if($command_type === 'edit')
            {
                $max = count($p_param->param_launcher->service_list_all);
                for($i = 0; $i < $max; $i++)
                {
                    if($p_param->param_launcher->service_list_all[$i]['name'] === $payload['service'])
                    {
                        $p_param->param_launcher->service_list_all[$i]['cores'] = $payload['items']['cores'];
                        $p_param->param_launcher->service_list_all[$i]['name'] = $payload['items']['name'];
                        $p_param->param_launcher->service_list_all[$i]['group'] = $payload['items']['group'];
                        $p_param->param_launcher->service_list_all[$i]['path'] = $payload['items']['path'];
                        $p_param->param_launcher->service_list_all[$i]['command'] = $payload['items']['command'];
                        $p_param->setting_edit_flg = true;
                        $type = 'setting_edit';
                        $message = __('launcher.NOTICE_EDIT_SERVICE', ['target' => $payload['service']]);
                        break;
                    }
                }
                if($i > $max)
                {
                    $response =
                    [
                        'cmd' => CommandForWebsocketQueueEnum::SETTING_ACTION_RESPONSE->value,
                        'type' => $command_type,
                        'result' => false,
                        'message' => __('launcher.ERROR_NO_TARGET_SERVICE', ['action' => '編集'])
                    ];
                    $data =
                    [
                        'data' => $response
                    ];
                    $p_param->setSendStack($data);

                    return null;
                }
            }
            else
            if($command_type === 'append')
            {
                $max = count($p_param->param_launcher->service_list_all);
                for($i = 0; $i < $max; $i++)
                {
                    if($p_param->param_launcher->service_list_all[$i]['name'] === $payload['items']['name'])
                    {
                        break;
                    }
                }
                if($i >= $max)
                {
                    $payload['items']['timestamp'] = null;
                    $payload['items']['pid'] = null;
                    $p_param->param_launcher->service_list_all[] = $payload['items'];
                    $p_param->setting_edit_flg = true;
                    $type = 'setting_append';
                    $message = __('launcher.NOTICE_APPEND_SERVICE', ['target' => $payload['items']['name']]);
                }
                else
                {
                    $response =
                    [
                        'cmd' => CommandForWebsocketQueueEnum::SETTING_ACTION_RESPONSE->value,
                        'type' => $command_type,
                        'result' => false,
                        'message' => __('launcher.ERROR_TARGET_SERVICE_EXISTS', ['action' => '追加'])
                    ];
                    $data =
                    [
                        'data' => $response
                    ];
                    $p_param->setSendStack($data);

                    return null;
                }
            }
            else
            if($command_type === 'save')
            {
                if($p_param->setting_edit_flg === true)
                {
                    $output = '';
                    foreach($p_param->param_launcher->service_list_all as $service)
                    {
                        $cores = null;
                        foreach($service['cores'] ?? [] as $val)
                        {
                            $cores[] = (int)$val;
                        }
                        $entry =
                        [
                            'cores' => $cores,
                            'name' => $service['name'],
                            'group' => $service['group'],
                            'path' => $service['path'],
                            'command' => $service['command']
                        ];
                        $json = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                        $output .= $json . ",\n";
                    }

                    // 最後のカンマと改行を削除
                    $output = rtrim($output, ",\n");

                    // ファイルに書き出し
                    $path = __DIR__.'/../../'.config('launcher.services_path');
                    file_put_contents($path, $output);

                    $p_param->setting_edit_flg = false;
                    $type = 'setting_save';
                    $message = __('launcher.NOTICE_SETTING_SAVE');
                }
                else
                {
                    $response =
                    [
                        'cmd' => CommandForWebsocketQueueEnum::SETTING_ACTION_RESPONSE->value,
                        'type' => $command_type,
                        'result' => false,
                        'message' => __('launcher.ERROR_NO_TARGET_SERVICE', ['target' => '保存'])
                    ];
                    $data =
                    [
                        'data' => $response
                    ];
                    $p_param->setSendStack($data);

                    return null;
                }
            }
            else
            if($command_type === 'load')
            {
                $error_message = null;

                // エラー検知ループ
                while(true)
                {
                    // サービス停止中の確認
                    $cnt_stop = 0;
                    $max = count($p_param->param_launcher->service_list_all);
                    foreach($p_param->param_launcher->service_list_all as $service)
                    {
                        if($service['pid'] === null)
                        {
                            $cnt_stop++;
                        }
                    }
                    if($cnt_stop < $max)
                    {
                        $error_message = __('launcher.ERROR_LOAD_RUNNING_SERVICE');
                        break;
                    }

                    $path = __DIR__.'/../../'.config('launcher.services_path');

                    // ファイルの存在確認
                    if(!file_exists($path))
                    {
                        $error_message = __('launcher.ERROR_LOAD_NO_FILE');
                        break;
                    }

                    // ファイル読み込み確認
                    $service_json = file_get_contents($path);
                    if($service_json === false)
                    {
                        $error_message = __('launcher.SERVICES_FILE_READ_FAILED');
                        break;
                    }

                    // JSONデコード確認
                    $service_list = json_decode("[{$service_json}]", true);
                    if(json_last_error() !== JSON_ERROR_NONE)
                    {
                        $error_message = __('launcher.SERVICES_FILE_DECODE_FAILED');
                        break;
                    }

                    $max = count($service_list);
                    for($i = 0; $i < $max; $i++)
                    {
                        $service_list[$i]['pid'] = null;
                    }

                    break;
                }

                if($error_message !== null)
                {
                    $response =
                    [
                        'cmd' => CommandForWebsocketQueueEnum::SETTING_ACTION_RESPONSE->value,
                        'type' => $command_type,
                        'result' => false,
                        'message' => $error_message
                    ];
                    $data =
                    [
                        'data' => $response
                    ];
                    $p_param->setSendStack($data);

                    return null;
                }

                $p_param->param_launcher->service_list_all = $service_list;
                $p_param->setting_edit_flg = false;
                $type = 'setting_load';
                $message = __('launcher.NOTICE_SETTING_LOAD');
            }

            $p_param->param_launcher->logWriter('info', ['type' => $type, 'message' => $message, 'via' => 'GUI', 'who' => $p_param->getUserName(), 'pid' => null]);

            $response =
            [
                'cmd' => CommandForWebsocketQueueEnum::SETTING_ACTION_RESPONSE->value,
                'type' => $command_type,
                'result' => true,
                'message' => null
            ];
            $data =
            [
                'data' => $response
            ];
            $p_param->setSendStack($data);

            return null;
        };
    }


    //--------------------------------------------------------------------------
    // 以降はステータスUNITの定義（"MESSAGE"キュー）
    //--------------------------------------------------------------------------

    /**
     * ステータス名： START
     * 
     * 処理名：メッセージ送信
     * 
     * @param ParameterForWebsocket $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getMessageStart()
    {
        return function(ParameterForWebsocket $p_param): ?string
        {
            $p_param->logWriter('debug', ['COMMAND:MESSAGE' => 'START']);

            $w_ret = $p_param->getRecvData();
            $payload = $w_ret['data'];

            $payload['message'] = htmlspecialchars($payload['message'], ENT_QUOTES, 'UTF-8');
            $cid = $p_param->getConnectionId();
            $datetime = date(ParameterForWebsocket::DATETIME_FORMAT);
            $message =
            [
                'cmd' => $payload['cmd'],
                'uid' => $cid,
                'user' => $p_param->getUserName($cid),
                'datetime' => $datetime,
                'message' => $payload['message']
            ];
            $data =
            [
                'data' => $message
            ];
            $p_param->setSendStackAll($data);

            $p_param->chatLogWriter($datetime, $p_param->getUserName(), $payload['message']);
            return null;
        };
    }


    //--------------------------------------------------------------------------
    // 以降はステータスUNITの定義（"PRIVATE_MESSAGE"キュー）
    //--------------------------------------------------------------------------

    /**
     * ステータス名： START
     * 
     * 処理名：プライベートメッセージ送信
     * 
     * @param ParameterForWebsocket $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getPrivateMessageStart()
    {
        return function(ParameterForWebsocket $p_param): ?string
        {
            $p_param->logWriter('debug', ['COMMAND:PRIVATE_MESSAGE' => 'START']);

            $w_ret = $p_param->getRecvData();
            $payload = $w_ret['data'];
            $datetime = date(ParameterForWebsocket::DATETIME_FORMAT);

            $payload['message'] = htmlspecialchars($payload['message'], ENT_QUOTES, 'UTF-8');
            $w_ret = $p_param->getUserName($payload['uid']);
            if($w_ret === null)
            {
                $response =
                [
                    'cmd' => CommandForWebsocketQueueEnum::PRIVATE_MESSAGE_RESPONSE->value,
                    'result' => false,
                    'message' => $payload['message'],
                    'datetime' => $datetime
                ];
                $data =
                [
                    'data' => $response
                ];
                $p_param->setSendStack($data);

                $p_param->privateLogWriter($datetime, $p_param->getUserName(), 'user left', $payload['message'], false);
                return null;
            }

            $cid = $p_param->getConnectionId();
            $message =
            [
                'cmd' => CommandForWebsocketQueueEnum::PRIVATE_MESSAGE->value,
                'message' => $payload['message'],
                'uid' => $cid,
                'user' =>  $p_param->getUserName($cid),
                'datetime' => $datetime
            ];
            $data =
            [
                'data' => $message
            ];
            $p_param->setSendStack($data, $payload['uid']);

            $response =
            [
                'cmd' => CommandForWebsocketQueueEnum::PRIVATE_MESSAGE_RESPONSE->value,
                'result' => true,
                'message' => $payload['message'],
                'datetime' => $datetime
            ];
            $data =
            [
                'data' => $response
            ];
            $p_param->setSendStack($data);

            $p_param->privateLogWriter($datetime, $message['user'], $p_param->getUserName($payload['uid']), $message['message'], true);
            return null;
        };
    }


    //--------------------------------------------------------------------------
    // 以降はステータスUNITの定義（"LEAVING"キュー）
    //--------------------------------------------------------------------------

    /**
     * ステータス名： START
     * 
     * 処理名：切断要求
     * 
     * @param ParameterForWebsocket $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getLeavingStart()
    {
        return function(ParameterForWebsocket $p_param): ?string
        {
            $p_param->logWriter('debug', ['COMMAND:LEAVING' => 'START']);

            $datetime = date(ParameterForWebsocket::DATETIME_FORMAT);
            $cid = $p_param->getConnectionId();
            $user = $p_param->getUserName($cid);
            $broadcast =
            [
                'cmd' => CommandForWebsocketQueueEnum::LEAVING->value,
                'datetime' => $datetime,
                'uid' => $cid,
                'user' => $user,
                'message' => __('launcher.INFO_LEAVING')
            ];

            // 自身を除く全コネクションへ配信
            $data =
            [
                'data' => $broadcast
            ];
            $p_param->setSendStackAll($data, true);

            $p_param->deleteUser($cid);

            $p_param->chatLogWriter($datetime, $user, $broadcast['message']);

            return CommandForWebsocketStatusEnum::CLOSE->value;
        };
    }

    /**
     * ステータス名： CLOSE
     * 
     * 処理名：切断フレームの送信
     * 
     * @param ParameterForWebsocket $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getLeavingClose()
    {
        return function(ParameterForWebsocket $p_param): ?string
        {
            $p_param->logWriter('debug', ['COMMAND:LEAVING' => 'CLOSE']);

            $datetime = date(ParameterForWebsocket::DATETIME_FORMAT);

            // 切断パラメータを設定
            $close_param =
            [
                // 切断コード
                'code' => ParameterForWebsocket::CHAT_SELF_CLOSE_CODE,
                // シリアライズ対象データ
                'data' =>
                [
                    // 切断時パラメータ（現在日時）
                    'datetime' => $datetime
                ]
            ];

            // 自身を切断
            $p_param->close($close_param);

            return null;
        };
    }
}
