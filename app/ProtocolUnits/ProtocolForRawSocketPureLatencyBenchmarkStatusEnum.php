<?php
/**
 * プロトコルUNITステータス名のENUMファイル
 * 
 * Websocket用
 */

namespace App\ProtocolUnits;


use SocketManager\Library\StatusEnum;


/**
 * プロトコルUNITステータス名定義
 * 
 * Websocket用
 */
enum ProtocolForRawSocketPureLatencyBenchmarkStatusEnum: string
{
    //--------------------------------------------------------------------------
    // 定数（共通）
    //--------------------------------------------------------------------------

    /**
     * @var string 処理開始時のステータス共通
     */
    case START = StatusEnum::START->value;


    //--------------------------------------------------------------------------
    // 定数（ProtocolQueueEnum::ALIVEキュー）
    //--------------------------------------------------------------------------

    /**
     * @var string 送信中のステータス名
     */
    case SENDING = 'sending';

    /**
     * @var string 受信中のステータス名
     */
    case RECEIVING = 'receiving';


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

}
