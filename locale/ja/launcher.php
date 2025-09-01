<?php

return [
    'SERVICES_FILE_DECODE_FAILED' => 'サービス構成ファイルのデコードに失敗しました。',
    'SERVICES_FILE_READ_FAILED' => 'サービス構成ファイルの読み込みに失敗しました。',
    'NEED_SERVICES_FILE' => ':action アクションの実行にはサービスの登録が必要です。',

    'ERROR_ACTION' => <<<EOS
以下のいずれかを指定して下さい。
start <サービス名> or <group:グループ名>
startall
stop <サービス名> or <group:グループ名>
stopall
status <サービス名> or <group:グループ名>
statusall
cpuinfo
shutdown
EOS,
    'WARNING_CPU_ASSIGNMENT_FAILED' => ':service サービスのCPU割り当てに失敗しました。',

    'ERROR_SERVICE' => '正しい<サービス名> or <group:グループ名>を指定して下さい。',
    'ERROR_EXEC_INVALID' => 'exec() が無効化されています。',
    'ERROR_STARTUP_LAUNCHER' => 'ランチャーを多重で起動しようとしました。使用中のランチャーがないか確認して下さい。',
    'ERROR_DETECT_STOP' => ':service サービスが応答不能状態です。',
    'ERROR_STOP_FAILED' => ':service サービスの停止に失敗しました。',
    'ERROR_NOT_DETECTED' => ':service サービスを検知できませんでした。',
    'ERROR_CPU_INFO' => 'CPU情報を取得できませんでした。',
    'ERROR_STARTED_SERVICE_FAILED' => ':service サービスの起動に失敗しました。',

    'INFO_STARTED_SERVICE' => ':service サービスを起動しました。',
    'INFO_STOPPED_SERVICE' => ':service サービスを停止しました。',

    'NOTICE_RUNNING_SERVICE' => ':service サービスは起動中です。',
    'NOTICE_STOPPING_SERVICE' => ':service サービスは停止中です。',
    'NOTICE_NOT_FOUND_SERVICE' => ':service サービスは既に存在していません。',
    'NOTICE_AUTO_RESTART' => ':service サービスを自動再起動しました。',
    'NOTICE_LAUNCHER_SHUTDOWN' => 'ランチャーを終了しました。',
    'NOTICE_NO_RUNNING_LAUNCHER' => '起動中のランチャーはありません。',

    'STATUS_LIST' => ':service => 状態[:status] CPU[:cpu%%] MEM[:memory%%] 起動時間[:timestamp] PID[:pid]',
    'STATUS_DETAIL' => <<<EOS
サービス名      | :service
状態            | :status
CPU稼働率       | :cpu%%
メモリ使用率    | :memory%%
起動時間        | :timestamp
プロセスID      | :pid
論理CPU割当     | :cores
グループ名      | :group
起動パス        | :path
コマンドライン  | :command
EOS,

    'CPU_INFO' => <<<EOS
物理ソケット数  | :sockets
物理コア数      | :total_cores (:cores×:times)
論理CPU数       | :logical (:id_range)
CPU型番         | :cpu_name
HT              | :ht
アーキテクチャ  | :arch
EOS,

    'OPTION_ADMIN_USER' => 'システム',
    'OPTION_LEAVING' => '切断しました。',
    'OPTION_SERVER_CLOSE' => 'サーバーから切断されました。',
    'OPTION_UNEXPECTED_CLOSE' => '予期しない切断が発生しました。',
    'OPTION_UNEXPECTED_ERROR' => '予期しないエラーが発生しました。',

    'ERROR_USER_DUPLICATE' => 'オペレーター名が重複しています。',
    'ERROR_USER_EMPTY' => 'オペレーター名の入力が必要です。',
    'ERROR_LAUNCHER_BUSY' => '現在ランチャーが処理中ため操作できません',
    'ERROR_NO_TARGET_SERVICE' => ':action対象のサービスは存在しません',
    'ERROR_TARGET_SERVICE_EXISTS' => ':action対象のサービスは存在しています',
    'ERROR_LOAD_RUNNING_SERVICE' => '起動中のサービスが存在するためサービス構成ファイルをロードできません',
    'ERROR_LOAD_NO_FILE' => 'サービス構成ファイルが存在しません',
    'NOTICE_DELETE_SERVICE' => ':target サービスの設定を削除しました',
    'NOTICE_EDIT_SERVICE' => ':target サービスの設定を編集しました',
    'NOTICE_APPEND_SERVICE' => ':target サービスの設定を追加しました',
    'NOTICE_SETTING_SAVE' => '設定ファイルに保存しました',
    'NOTICE_SETTING_LOAD' => '設定ファイルをロードしました',
    'NOTICE_LAUNCHER_BUSY' => 'ランチャー処理中...',
    'INFO_ENTERING_SUCCESS' => '接続しました。',
    'INFO_LEAVING' => '切断しました。',

    'MAIL_SUBJECT_CRITICAL' => '【サーバー通知（critical）】:typeリソース使用率が閾値を超えました',
    'MAIL_BODY_CRITICAL' => '❌ :typeリソース使用率が閾値を超えました。即時対応が必要なレベルです：:usage%（閾値：:threshold%）',
    'MAIL_SUBJECT_ALERT' => '【サーバー通知（alert）】:typeリソース使用率が閾値を超えました',
    'MAIL_BODY_ALERT' => '🚨 :typeリソース使用率が閾値を超えました。高負荷状態です：:usage%（閾値：:threshold%）',
    'MAIL_SUBJECT_WARN' => '【サーバー通知（warn）】:typeリソース使用率が閾値を超えました',
    'MAIL_BODY_WARN' => '⚠️ :typeリソース使用率が閾値を超えたため注意が必要です：:usage%（閾値：:threshold%）'
];
