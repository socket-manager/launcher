<?php

return [

    /**
     * 標準出力制御の設定（true:標準出力有り、false:標準出力無し、'auto':自動判別）
     * 
     * 自動判別時はデフォルトのtrueになります
     */
    'stdout_enabled' => 'auto',

    /**
     * 自動再起動の設定（true:自動再起動有り、false:自動再起動無し）
     * 
     * プロセスが停止していたら自動で再起動します
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
     * サービス構成ファイルのパス
     * 
     * 初期状態のサービス設定ファイル setting/service.json.sample にはサンプルが定義されています。
     * 内容を定義後はファイル名を service.json にしてからお使い下さい。
     * 
     * ※複数のサービスを起動する場合、サービス構成ファイルに定義されている並び順が実行順になります。
     * 
     * 以下はサービス構成ファイル内で定義が必要な項目の内訳です。
     * 
     * cores    割り当てるCPUコア番号のリスト（配列形式）⇒cpuinfoアクションを実行する事で割り当て可能なコア番号が確認できます
     * name     サービス名
     * group    グループ名（null or 空文字 で指定なし）
     * path     実行パス（絶対 or 相対）
     * command  コマンド文
     */
    'services_path' => './setting/services.json',

];
