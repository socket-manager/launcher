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
EOS,
    'WARNING_CPU_ASSIGNMENT_FAILED' => ':service サービスのCPU割り当てに失敗しました。',

    'ERROR_SERVICE' => '正しい<サービス名> or <group:グループ名>を指定して下さい。',
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
EOS
];
