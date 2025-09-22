<?php

/**
 * 通知メール関連
 */
return [

    /**
     * criticalレベル
     */
    'MAIL_SUBJECT_CRITICAL' => '【サーバー通知（critical）】:typeリソース使用率が閾値を超えました（:server）',
    'MAIL_BODY_CRITICAL' => '❌ :typeリソース使用率が閾値を超えました。即時対応が必要なレベルです：:usage%（閾値：:threshold%）'."\r\n\r\n".'発生元サーバー：:server'."\r\n".'発生日時：:timestamp',

    /**
     * alertレベル
     */
    'MAIL_SUBJECT_ALERT' => '【サーバー通知（alert）】:typeリソース使用率が閾値を超えました（:server）',
    'MAIL_BODY_ALERT' => '🚨 :typeリソース使用率が閾値を超えました。高負荷状態です：:usage%（閾値：:threshold%）'."\r\n\r\n".'発生元サーバー：:server'."\r\n".'発生日時：:timestamp',

    /**
     * warnレベル
     */
    'MAIL_SUBJECT_WARN' => '【サーバー通知（warn）】:typeリソース使用率が閾値を超えました（:server）',
    'MAIL_BODY_WARN' => '⚠️ :typeリソース使用率が閾値を超えたため注意が必要です：:usage%（閾値：:threshold%）'."\r\n\r\n".'発生元サーバー：:server'."\r\n".'発生日時：:timestamp'
];
