<?php
/**
 * UNITパラメータクラスのファイル
 * 
 * Websocket版
 */

namespace App\UnitParameter;

use SocketManager\Library\SocketManagerParameter;


/**
 * UNITパラメータクラス
 * 
 * UNITパラメータクラスのSocketManagerParameterをオーバーライドする
 */
class ParameterForBenchmark extends SocketManagerParameter
{
    //--------------------------------------------------------------------------
    // 定数（first byte）
    //--------------------------------------------------------------------------

    /**
     * 最後の断片
     */
    public const CHAT_FIN_BIT_MASK = 0x80;

    /**
     * テキストフレーム
     */
    public const CHAT_OPCODE_TEXT_MASK = 0x01;

    /**
     * 切断フレーム
     */
    public const CHAT_OPCODE_CLOSE_MASK = 0x08;

    /**
     * pingフレーム
     */
    public const CHAT_OPCODE_PING_MASK = 0x09;

    /**
     * pongフレーム
     */
    public const CHAT_OPCODE_PONG_MASK = 0x0A;


    //--------------------------------------------------------------------------
    // 定数（second byte）
    //--------------------------------------------------------------------------

    /**
     * データ長マスク
     */
    public const CHAT_PAYLOAD_LEN_MASK = 0x7f;

    /**
     * データ長サイズコード（2 byte）
     */
    public const CHAT_PAYLOAD_LEN_CODE_2 = 126;

    /**
     * データ長サイズコード（8 byte）
     */
    public const CHAT_PAYLOAD_LEN_CODE_8 = 127;


    //--------------------------------------------------------------------------
    // 定数（その他）
    //--------------------------------------------------------------------------

    /**
     * 対応プロトコルバージョン
     */
    public const CHAT_PROTOCOL_VERSION = 13;

    /**
     * openingハンドシェイクのリトライ件数
     */
    public const CHAT_HANDSHAKE_RETRY = 3;

    /**
     * 受信空振り時のリトライ回数
     */
    public const CHAT_RECEIVE_EMPTY_RETRY = 10;


    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * TLSフラグ
     */
    protected bool $tls = false;


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     * @param ?bool $p_tls TLSフラグ
     */
    public function __construct(?bool $p_tls = null)
    {
        parent::__construct();

        if($p_tls !== null)
        {
            $this->tls = $p_tls;
        }
    }


    //--------------------------------------------------------------------------
    // プロパティアクセス用
    //--------------------------------------------------------------------------

    /**
     * TLSフラグの取得
     * 
     * @return bool TLSフラグ
     */
    public function getTls()
    {
        $w_ret = $this->tls;
        return $w_ret;
    }

    /**
     * 受信空振り時のリトライ回数取得
     * 
     * @return int リトライ回数
     */
    public function getRecvRetry()
    {
        $w_ret = $this->getTempBuff(['recv_retry']);
        return $w_ret['recv_retry'];
    }

    /**
     * 受信空振り時のリトライ回数設定
     * 
     * @param int $p_cnt リトライ回数
     */
    public function setRecvRetry(int $p_cnt)
    {
        $this->setTempBuff(['recv_retry' => $p_cnt]);
        return;
    }


    //--------------------------------------------------------------------------
    // openinngハンドシェイク時のヘッダ情報管理用
    //--------------------------------------------------------------------------

    /**
     * ハンドシェイク時のヘッダ情報の取得
     * 
     * @param ?string $p_cid 接続ID
     * @return ?array ヘッダ情報
     */
    public function getHeaders(?string $p_cid = null): ?array
    {
        $cid = null;
        if($p_cid !== null)
        {
            $cid = $p_cid;
        }
        $w_ret = null;

        // ユーザープロパティの取得
        $w_ret = $this->getTempBuff(['headers'], $cid);
        if($w_ret === null)
        {
            return null;
        }

        return $w_ret['headers'];
    }

    /**
     * ハンドシェイク時のヘッダ情報の設定
     * 
     * @param array $p_prop プロパティのリスト
     */
    public function setHeaders(array $p_prop)
    {
        // ユーザープロパティの設定
        $this->setTempBuff(['headers' => $p_prop]);
        return;
    }


    //--------------------------------------------------------------------------
    // その他
    //--------------------------------------------------------------------------

    /**
     * クライアントからの強制切断時のコールバック
     * 
     * @param ParameterForBenchMark $p_param UNITパラメータ
     */
    public function forcedCloseFromClient(ParameterForBenchMark $p_param)
    {
    }
}
