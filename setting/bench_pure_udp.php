<?php

return [

    /**
     * @var string ホスト名
     */
    'host' => '127.0.0.1',

    /**
     * @var int ポート番号
     */
    'port' => 10000,

    /**
     * @var int ローカルポート開始番号（耐久テスト用）
     */
    'local_host' => '127.0.0.1',

    /**
     * @var int 周期インターバル時間（μs）
     */
    'cycle_interval' => 10,

    /**
     * @var int アライブチェックタイムアウト時間（s）
     */
    'alive_interval' => 3600,

    /**
     * @var array OS送受信バッファサイズ
     */
    'os_buff_size' => [
        /** @var int OS送信バッファ */
        'send' => 4 * 1024 * 1024,   // 4MB
        /** @var int OS受信バッファ */
        'recv' => 4 * 1024 * 1024    // 4MB
    ],

    /**
     * @var int 内部受信バッファサイズ
     */
    'internal_recv_size' => 2048, // 2K（MTU + α）

    /**
     * @var int パケット件数上限
     */
    'internal_max_packets' => 64,

    /**
     * @var int サンプリング間隔（ms）
     */
    'sampling_interval' => 1,

    /**
     * @var int ping/pontタイムアウト（s）
     */
    'timeout' => 10,

    /**
     * @var string ペイロード
     */
    'payload_stress_test' => '0123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123',
    'payload_pure_latency' => '0123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789',

    /**
     * @var bool 測定中の標準出力フラグ true（出力あり） or false（出力なし）
     */
    'stdout' => false,
];
