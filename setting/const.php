<?php

return [

    /**
     * 周期インターバル時間（μs）
     */
    'cycle_interval' => 10,

    /**
     * デバッグ用ログファイルのパス
     */
    'log_path_for_debug' => './logs/runtime-manager',

    /**
     * ランチャー用プロセスIDファイルのパス
     */
    'pid_path_for_launcher' => './pids/launcher/launcher',

    /**
     * サービス用プロセスIDファイルのパス
     */
    'pid_path_for_service' => './pids',

];
