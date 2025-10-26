<?php

return [

    /**
     * 標準出力制御の設定（true:標準出力有り、false:標準出力無し、'auto':自動判別）
     * 
     * 自動判別時、CLIモードではtrue、GUIモードではfalseになります
     */
    'stdout_enabled' => 'auto',

    /**
     * 自動再起動の設定（true:自動再起動有り、false:自動再起動無し）
     * 
     * プロセスが停止していたら自動で再起動します。GUIモードでは常にfalseになります
     */
    'auto_restart' => false,

    /**
     * ログ保存期間の設定（logs/launcher内に保存）
     * 
     * ファイル単位での保存期間を指定します（'daily' or 'monthly'）
     */
    'log_cycle' => 'daily',

    /**
     * ランチャー用ログファイルのパス
     */
    'log_path_for_launcher' => './logs/launcher',

    /**
     * チャット用ログファイルのパス
     */
    'log_path_for_chat' => './logs/chat',

    /**
     * サービス設定ファイルのパス
     * 
     * 初期状態のサービス設定ファイル setting/service.json.sample にはサンプルが定義されています。
     * 内容を定義後はファイル名を service.json にしてからお使い下さい。
     * 
     * ※複数のサービスを起動する場合、サービス設定ファイルに定義されている並び順が実行順になります。
     * 
     * 以下はサービス設定ファイル内で定義が必要な項目の内訳です。
     * 
     * cores    割り当てるCPUコア番号のリスト（配列形式）⇒cpuinfoアクションを実行する事で割り当て可能なコア番号が確認できます
     * name     サービス名（ユニークにする必要があります）
     * group    グループ名（null or 空文字 で指定なし）
     * path     実行パス（絶対 or 相対）
     * command  コマンド文
     */
    'services_path' => './setting/services.json',


    //--------------------------------------------------------------------------
    // GUIモード用
    //--------------------------------------------------------------------------

    // 各種リソース設定
    'resources' => [

        // CPUリソース
        'cpu' => [

            // 警告レベルの設定
            'warn' => [

                // 閾値（設定値以上で通知）
                'threshold' => 50,

                // 通知メール設定（全てnullで無効設定）
                'email' => [
                    'to' => null,
                    'from_address' => null, // Fromヘッダを指定する時は必須
                    'from_name' => null,
                    'reply_to' => null
                ]
            ],

            // アラートレベルの設定
            'alert' => [ 'threshold' => 75, 'email' => null /* 'email'がnullでも無効設定 */ ],

            // クリティカルレベルの設定
            'critical' => [ 'threshold' => 90, 'email' => null ]
        ],

        // メモリリソース
        'memory' => [

            // 警告レベルの設定
            'warn' => [ 'threshold' => 50, 'email' => null ],

            // アラートレベルの設定
            'alert' => [ 'threshold' => 75, 'email' => null ],

            // クリティカルレベルの設定
            'critical' => [ 'threshold' => 90, 'email' => null ]
        ],

        // [Linux用]ディスクリソース
        'disk_linux' => [

            // ルート
            '/' => [

                // ディスクラベル
                'label' => 'ルート',

                // 警告レベルの設定
                'warn' => [ 'threshold' => 50, 'email' => null ],

                // アラートレベルの設定
                'alert' => [ 'threshold' => 75, 'email' => null ],

                // クリティカルレベルの設定
                'critical' => [ 'threshold' => 90, 'email' => null ]
            ],

            // ホーム
            '/home' => [

                // ディスクラベル
                'label' => 'ホーム',

                // 警告レベルの設定
                'warn' => [ 'threshold' => 50, 'email' => null ],

                // アラートレベルの設定
                'alert' => [ 'threshold' => 75, 'email' => null ],

                // クリティカルレベルの設定
                'critical' => [ 'threshold' => 90, 'email' => null ]
            ],

            // ログ
            '/var' => [

                // ディスクラベル
                'label' => 'ログ',

                // 警告レベルの設定
                'warn' => [ 'threshold' => 50, 'email' => null ],

                // アラートレベルの設定
                'alert' => [ 'threshold' => 75, 'email' => null ],

                // クリティカルレベルの設定
                'critical' => [ 'threshold' => 90, 'email' => null ]
            ]
        ],

        // [Windows用]ディスクリソース
        'disk_windows' => [

            // Cドライブ
            'C:' => [

                // ディスクラベル
                'label' => 'Cドライブ',

                // 警告レベルの設定
                'warn' => [ 'threshold' => 50, 'email' => null ],

                // アラートレベルの設定
                'alert' => [ 'threshold' => 75, 'email' => null ],

                // クリティカルレベルの設定
                'critical' => [ 'threshold' => 90, 'email' => null ]
            ],

            // Dドライブ
            'D:' => [

                // ディスクラベル
                'label' => 'Dドライブ',

                // 警告レベルの設定
                'warn' => [ 'threshold' => 50, 'email' => null ],

                // アラートレベルの設定
                'alert' => [ 'threshold' => 75, 'email' => null ],

                // クリティカルレベルの設定
                'critical' => [ 'threshold' => 90, 'email' => null ]
            ],

            // Eドライブ
            'E:' => [

                // ディスクラベル
                'label' => 'Eドライブ',

                // 警告レベルの設定
                'warn' => [ 'threshold' => 50, 'email' => null ],

                // アラートレベルの設定
                'alert' => [ 'threshold' => 75, 'email' => null ],

                // クリティカルレベルの設定
                'critical' => [ 'threshold' => 90, 'email' => null ]
            ]
        ]
    ]
];
