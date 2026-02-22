<?php
/**
 * ステータスUNIT登録クラスのファイル
 * 
 * SocketManagerのsetProtocolUnitsメソッドへ引き渡されるクラスのファイル
 */

namespace App\ProtocolUnits;

use SocketManager\Library\ProtocolQueueEnum;
use SocketManager\Library\UnitException;
use SocketManager\Library\UnitExceptionEnum;
use App\UnitParameter\ParameterForPureLatencyBenchmark;


/**
 * プロトコルUNIT登録クラス
 * 
 * ParameterForBenchmarkインタフェースをインプリメントする
 */
class ProtocolForPureLatencyBenchmark extends ProtocolForBenchmark
{
    //--------------------------------------------------------------------------
    // 定数
    //--------------------------------------------------------------------------

    /**
     * @var const QUEUE_LIST キュー名のリスト
     */
    protected const QUEUE_LIST = [
        ProtocolQueueEnum::CONNECT->value,  // コネクションキュー
        ProtocolQueueEnum::RECV->value,		// 受信処理のキュー
        ProtocolQueueEnum::SEND->value,		// 送信処理のキュー
        ProtocolQueueEnum::CLOSE->value,	// 切断処理のキュー
        ProtocolQueueEnum::ALIVE->value		// アライブチェック処理のキュー
    ];


    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * @var int サンプル回数
     */
    private int $samples_max = 1;

    /**
     * @var string ペイロード
     */
    private string $payload = '';

    /**
     * @var int サンプルカウンター
     */
    private int $samples_count = 0;

    /**
     * @var array サンプル値配列
     */
    private array $samples_array = [];

    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     */
    public function __construct(int $p_samples, string $p_payload)
    {
        $this->samples_max = $p_samples;
        $this->payload = $p_payload;
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

        if($p_que === ProtocolQueueEnum::CONNECT->value)
        {
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::START->value,
                'unit' => $this->getConnectStart()
            ];
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::SEND->value,
                'unit' => $this->getConnectSend()
            ];
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::RECV->value,
                'unit' => $this->getConnectRecv()
            ];
        }
        else
        if($p_que === ProtocolQueueEnum::RECV->value)
        {
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::START->value,
                'unit' => $this->getRecvStart()
            ];
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::LENGTH->value,
                'unit' => $this->getRecvLength()
            ];
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::PAYLOAD->value,
                'unit' => $this->getRecvPayload()
            ];
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::PONG_CREATE->value,
                'unit' => $this->getRecvPongCreate()
            ];
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::PONG_SENDING->value,
                'unit' => $this->getSendSending()
            ];
        }
        else
        if($p_que === ProtocolQueueEnum::SEND->value)
        {
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::START->value,
                'unit' => $this->getSendStart()
            ];
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::SENDING->value,
                'unit' => $this->getSendSending()
            ];
        }
        else
        if($p_que === ProtocolQueueEnum::CLOSE->value)
        {
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::START->value,
                'unit' => $this->getCloseStart()
            ];
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::SENDING->value,
                'unit' => $this->getCloseSending()
            ];
        }
        else
        if($p_que === ProtocolQueueEnum::ALIVE->value)
        {
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::START->value,
                'unit' => $this->getAliveStart()
            ];
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::SENDING->value,
                'unit' => $this->getAliveSending()
            ];
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::RECV->value,
                'unit' => $this->getRecvStart()
            ];
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::LENGTH->value,
                'unit' => $this->getRecvLength()
            ];
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::PAYLOAD->value,
                'unit' => $this->getRecvPayload()
            ];
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::PONG_CREATE->value,
                'unit' => $this->getRecvPongCreate()
            ];
            $ret[] = [
                'status' => ProtocolForPureLatencyBenchmarkStatusEnum::PONG_SENDING->value,
                'unit' => $this->getSendSending()
            ];
        }

        return $ret;
    }


    //--------------------------------------------------------------------------
    // 以降はステータスUNITの定義（"CONNECT"キュー）
    //--------------------------------------------------------------------------

    /**
     * ステータス名： START
     * 
     * 処理名：データ作成（ハンドシェイク用）
     * 
     * @param ParameterForPureLatencyBenchmark $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getConnectStart()
    {
        return function(ParameterForPureLatencyBenchmark $p_param): ?string
        {
            $host = $port = null;
            $p_param->getRemoteAddr($host, $port);

            // ハンドシェイクデータの生成
            $ver = ParameterForPureLatencyBenchmark::CHAT_PROTOCOL_VERSION;
            $key = base64_encode(random_bytes(16));
            $hdrs  =
                "GET / HTTP/1.1\r\n" .
                "Host: {$host}:{$port}\r\n" .
                "Connection: Upgrade\r\n" .
                "Upgrade: websocket\r\n" .
                "Sec-WebSocket-Version: {$ver}\r\n" .
                "Sec-WebSocket-Key: {$key}\r\n\r\n";

            // 送信データの設定
            $p_param->protocol()->setSendingData($hdrs);

            $fnc = $this->getConnectSend();
            $sta = $fnc($p_param);

            if($sta === ProtocolForPureLatencyBenchmarkStatusEnum::START->value)
            {
               return ProtocolForPureLatencyBenchmarkStatusEnum::SEND->value; 
            }
            return ProtocolForPureLatencyBenchmarkStatusEnum::RECV->value;
        };
    }

    /**
     * ステータス名： SEND
     * 
     * 処理名：ハンドシェイクデータ送信
     * 
     * @param ParameterForPureLatencyBenchmark $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getConnectSend()
    {
        return function(ParameterForPureLatencyBenchmark $p_param): ?string
        {
            // データ送信
            $w_ret = $p_param->protocol()->sending();

            // 送信中の場合は再実行
            if($w_ret === null)
            {
                $sta = $p_param->getStatusName();
                return $sta;
            }

            return ProtocolForPureLatencyBenchmarkStatusEnum::RECV->value;
        };
    }

    /**
     * ステータス名： RECV
     * 
     * 処理名：レスポンス受信
     * 
     * @param ParameterForPureLatencyBenchmark $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getConnectRecv()
    {
        return function(ParameterForPureLatencyBenchmark $p_param): ?string
        {
            // ソケット受信
            $rcv = '';
            $p_param->protocol()->recv($rcv);
    
            // データがある時
            if(strlen($rcv) > 0)
            {
                // 前回まで受信したデータを取得
                $old = '';
                $w_ret = $p_param->getHeaders();
                if($w_ret !== null)
                {
                    $old = $w_ret['buffer'];
                }
        
                // 途中まで受信したデータを結合
                $buf = $old.$rcv;
    
                // ヘッダの末尾を受信したら終わり
                $matches = array();
                if(preg_match('/\r\n\r\n/', $buf, $matches))
                {
                    $w_ret['buffer'] = $buf;
                    $hdrs = $w_ret;
                    $rows = preg_split('/\r\n/', $rcv);
                    foreach($rows as $row)
                    {
                        $row = chop($row);
                        if(preg_match('/\A(\S+): (.*)\z/', $row, $matches))
                        {
                            $hdrs[$matches[1]] = $matches[2];
                        }
                        else
                        {
                            $parts = explode(' ', $row);
                            if(trim($parts[0]) !== '')
                            {
                                $key = $parts[0];
                                array_shift($parts);
                                $hdrs[$key] = $parts;
                            }
                        }
                    }
    
                    // ヘッダ情報を格納
                    $p_param->setHeaders($hdrs);

                    $ping_start =
                    [
                        'data' => [
                            'cmd' => 'ping_start'
                        ]
                    ];
                    $p_param->setRecvStack($ping_start, true);

                    return null;
                }
    
                // 受信中のデータを格納
                $p_param->setHeaders(['buffer' => $buf]);
            }
    
            // ステータス名の取得
            $sta = $p_param->getStatusName();
    
            return $sta;
        };
    }


    //--------------------------------------------------------------------------
    // 以降はステータスUNITの定義（"RECV"キュー）
    //--------------------------------------------------------------------------

    /**
     * ステータス名： START
     * 
     * 処理名：受信開始
     * 
     * @param ParameterForPureLatencyBenchmark $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getRecvStart()
    {
        return function(ParameterForPureLatencyBenchmark $p_param): ?string
        {
            // 現在のステータス名を取得
            $sta = $p_param->getStatusName();
    
            /**
             * ヘッダの先頭の２バイトを取り込む
             * 
             */
    
            // 受信中かどうか
            $w_ret = $p_param->isReceiving();
            if($w_ret === false)
            {
                // 受信サイズを設定
                $p_param->protocol()->setReceivingSize(2);

                // リトライ回数初期化
                $p_param->setRecvRetry(0);
            }
    
            // データ受信
            $w_ret = $p_param->protocol()->receiving();
            if($w_ret === null)
            {
                return null;
            }
            $buf = $w_ret;
    
            /**
             * ２バイト取れたらヘッダ情報を格納する
             * 
             */
    
            // 受信データのアンシリアライズ化
            $dat_ary = unpack('c2chars', $buf);
    
            // データ長種別を設定
            $len_knd = $dat_ary['chars2'] & ParameterForPureLatencyBenchmark::CHAT_PAYLOAD_LEN_MASK;
    
            $entry_data = array();
            if($len_knd == ParameterForPureLatencyBenchmark::CHAT_PAYLOAD_LEN_CODE_2)
            {
                $entry_data = [
                      'length_byte'  => 2
                    , 'length'       => null
                    , 'data'         => null
                    , 'first_byte'   => $dat_ary['chars1']
                    , 'close_code'   => null
                ];
    
                $sta = ProtocolForPureLatencyBenchmarkStatusEnum::LENGTH->value;
    
                // 受信サイズを設定
                $p_param->protocol()->setReceivingSize($entry_data['length_byte']);
            }
            elseif($len_knd == ParameterForPureLatencyBenchmark::CHAT_PAYLOAD_LEN_CODE_8)
            {
                $entry_data = [
                      'length_byte'  => 8
                    , 'length'       => null
                    , 'data'         => null
                    , 'first_byte'   => $dat_ary['chars1']
                    , 'close_code'   => null
                ];
    
                $sta = ProtocolForPureLatencyBenchmarkStatusEnum::LENGTH->value;
    
                // 受信サイズを設定
                $p_param->protocol()->setReceivingSize($entry_data['length_byte']);
            }
            else
            {
                $entry_data = [
                      'length_byte'  => 1
                    , 'length'       => $len_knd
                    , 'data'         => null
                    , 'first_byte'   => $dat_ary['chars1']
                    , 'close_code'   => null
                ];
    
                $sta = ProtocolForPureLatencyBenchmarkStatusEnum::PAYLOAD->value;
    
                // 受信サイズを設定
                $p_param->protocol()->setReceivingSize($entry_data['length']);
            }
    
            // テンポラリバッファにセット
            $p_param->setTempBuff(['recv_buff' => $entry_data]);
    
            return $sta;
        };
    }

    /**
     * ステータス名： LENGTH
     * 
     * 処理名：データ長受信
     * 
     * @param ParameterForPureLatencyBenchmark $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getRecvLength()
    {
        return function(ParameterForPureLatencyBenchmark $p_param): ?string
        {
            // データ受信
            $w_ret = $p_param->protocol()->receiving();
            if($w_ret === null)
            {
                // 現在のステータス名を取得
                $sta = $p_param->getStatusName();
    
                return $sta;
            }
            $buf = $w_ret;
    
            // 受信バッファからデータ取得
            $entry_data = $p_param->getTempBuff(['recv_buff']);
            $entry_data = $entry_data['recv_buff'];
    
            // データ長を展開
            $unpack_data = array();
            if($entry_data['length_byte'] == 2)
            {
                $unpack_data = unpack('nlength', $buf);
            }
            else
            {
                $unpack_data = unpack('NNlength', $buf);
            }
            $entry_data['length'] = $unpack_data['length'];
    
            // 受信バッファにセット
            $p_param->setTempBuff(['recv_buff' => $entry_data]);
    
            // 受信サイズを設定
            $p_param->protocol()->setReceivingSize($entry_data['length']);

            return ProtocolForPureLatencyBenchmarkStatusEnum::PAYLOAD->value;
        };
    }

    /**
     * ステータス名： PAYLOAD
     * 
     * 処理名：ペイロードデータ受信
     * 
     * @param ParameterForPureLatencyBenchmark $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getRecvPayload()
    {
        return function(ParameterForPureLatencyBenchmark $p_param): ?string
        {
            // データ受信
            $w_ret = $p_param->protocol()->receiving();
            if($w_ret === null)
            {
                // 現在のステータス名を取得
                $sta = $p_param->getStatusName();
    
                return $sta;
            }
            $buf = $w_ret;
    
            // 受信バッファからデータ取得
            $entry_data = $p_param->getTempBuff(['recv_buff']);
    
            $entry_data = $entry_data['recv_buff'];
            $entry_data['data'] = $buf;
    
            // 切断フレームの場合は切断コードを取得する
            if(($entry_data['first_byte'] & 0x0f) === ParameterForPureLatencyBenchmark::CHAT_OPCODE_CLOSE_MASK)
            {
                // 切断パラメータを取得
                $close_param = $p_param->getCloseParameter();
                $recv_data_ary = unpack('nshort', $entry_data['data']);
                $entry_data['close_code'] = intval($recv_data_ary['short']);
    
                // コマンド送信による切断
                if(isset($close_param['code']) && $entry_data['close_code'] === $close_param['code'])
                {
                    // 例外を投げて切断する
                    throw new UnitException(
                        UnitExceptionEnum::ECODE_REQUEST_CLOSE->message(),
                        UnitExceptionEnum::ECODE_REQUEST_CLOSE->value,
                        $p_param
                    );
                }
                else
                {
                    // クライアントからの強制切断時のコールバック
                    $p_param->forcedCloseFromClient($p_param);

                    // 例外を投げて切断する
                    throw new UnitException(
                        UnitExceptionEnum::ECODE_FORCE_CLOSE->message(),
                        UnitExceptionEnum::ECODE_FORCE_CLOSE->value,
                        $p_param
                    );
                }
            }
    
            // PONGの場合は抜ける
            if(($entry_data['first_byte'] & 0x0f) === ParameterForPureLatencyBenchmark::CHAT_OPCODE_PONG_MASK)
            {
                $end = hrtime(true);
                $timer = $p_param->getTempBuff(['alive_timer']);
                $this->samples_array[] = ($end - $timer['alive_timer']['start'])/1000000;

                $this->samples_count++;
                printf("sample count[{$this->samples_count}]\r");
                if($this->samples_count >= $this->samples_max)
                {
                    $vals = $this->samples_array;
                    $bench = get_benchmark($vals);
                    $bench['total'] = $bench['total']/1000;
                    printf("\navg[{$bench['avg']}]ms mid[{$bench['mid']}]ms min[{$bench['min']}]ms max[{$bench['max']}]ms p90[{$bench['p90']}]ms p95[{$bench['p95']}]ms total[{$bench['total']}]s\n");
                    return null;
                }
                $ping_start =
                [
                    'data' => [
                        'cmd' => 'ping_start'
                    ]
                ];
                $p_param->setRecvStack($ping_start, true);

                return null;
            }
            else
            // PINGの場合はPONGを返す
            if(($entry_data['first_byte'] & 0x0f) === ParameterForPureLatencyBenchmark::CHAT_OPCODE_PING_MASK)
            {
                $p_param->setTempBuff(['recv_buff' => $entry_data]);
                return ProtocolForPureLatencyBenchmarkStatusEnum::PONG_CREATE->value;
            }

            // データを受信バッファスタックに設定
            $p_param->setRecvStack($entry_data);

            return null;
        };
    }

    /**
     * ステータス名： PONG_CREATE
     * 
     * 処理名：PONGフレーム生成
     * 
     * @param ParameterForPureLatencyBenchmark $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getRecvPongCreate()
    {
        return function(ParameterForPureLatencyBenchmark $p_param): ?string
        {
            // 受信バッファからデータ取得
            $entry_data = $p_param->getTempBuff(['recv_buff']);
            $entry_data = $entry_data['recv_buff'];
            $b1 = ParameterForPureLatencyBenchmark::CHAT_FIN_BIT_MASK | (ParameterForPureLatencyBenchmark::CHAT_OPCODE_PONG_MASK & 0x0f);  // maskあり

            $mask = random_bytes(4);
            $payload = $this->maskPayload($entry_data['data'], $mask);
            $length = strlen($payload);
    
            $header = '';
    
            // データ長１バイト
            if($length <= 125)
            {
                $header = pack('CC', $b1, $length);
            }
            // データ長２バイト
            else
            if($length > 125 && $length < 65536)
            {
                $header = pack('CCn', $b1, ParameterForPureLatencyBenchmark::CHAT_PAYLOAD_LEN_CODE_2, $length);
            }
            //データ長８バイト
            else
            if($length >= 65536)
            {
                $header = pack('CCNN', $b1, ParameterForPureLatencyBenchmark::CHAT_PAYLOAD_LEN_CODE_8, $length);
            }
        
            $p_param->protocol()->setSendingData($header.$mask.$payload);

            return ProtocolForPureLatencyBenchmarkStatusEnum::PONG_SENDING->value;
        };
    }


    //--------------------------------------------------------------------------
    // 以降はステータスUNITの定義（"SEND"キュー）
    //--------------------------------------------------------------------------

    /**
     * ステータス名： START
     * 
     * 処理名：送信開始
     * 
     * @param ParameterForPureLatencyBenchmark $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getSendStart()
    {
        return function(ParameterForPureLatencyBenchmark $p_param): ?string
        {
            // 送信データスタックから取得
            $w_ret = $p_param->protocol()->getSendData();
            $payload = $w_ret['data'];

            //--------------------------------------------------------------------------
            // ヘッダ部の構築
            //--------------------------------------------------------------------------

            $b1 = ParameterForPureLatencyBenchmark::CHAT_FIN_BIT_MASK | (ParameterForPureLatencyBenchmark::CHAT_OPCODE_TEXT_MASK & 0x0f);  // maskあり
            $mask = random_bytes(4);
            $payload = $this->maskPayload($payload, $mask);
            $length = strlen($payload);

            $header = '';

            // データ長１バイト
            if($length <= 125)
            {
                $header = pack('CC', $b1, $length);
            }
            // データ長２バイト
            else
            if($length > 125 && $length < 65536)
            {
                $header = pack('CCn', $b1, ParameterForPureLatencyBenchmark::CHAT_PAYLOAD_LEN_CODE_2, $length);
            }
            //データ長８バイト
            else
            if($length >= 65536)
            {
                $header = pack('CCNN', $b1, ParameterForPureLatencyBenchmark::CHAT_PAYLOAD_LEN_CODE_8, $length);
            }
        
            //--------------------------------------------------------------------------
            // 送信データの設定
            //--------------------------------------------------------------------------

            $p_param->protocol()->setSendingData($header.$mask.$payload);

            return ProtocolForPureLatencyBenchmarkStatusEnum::SENDING->value;
        };
    }

    //--------------------------------------------------------------------------
    // 以降はステータスUNITの定義（"CLOSE"キュー）
    //--------------------------------------------------------------------------

    /**
     * ステータス名： START
     * 
     * 処理名：切断開始
     * 
     * @param ParameterForPureLatencyBenchmark $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getCloseStart()
    {
        return function(ParameterForPureLatencyBenchmark $p_param): ?string
        {
            // 切断パラメータの取得
            $close_param = $p_param->getCloseParameter();
    
            // 切断コード
            $payload = pack('n', $close_param['code']);
    
            // 切断時のデータ
            $payload .= $close_param['data'];

            //--------------------------------------------------------------------------
            // ヘッダ部の構築
            //--------------------------------------------------------------------------
    
            $b1 = ParameterForPureLatencyBenchmark::CHAT_FIN_BIT_MASK | (ParameterForPureLatencyBenchmark::CHAT_OPCODE_CLOSE_MASK & 0x0f);  // maskあり
            $mask = random_bytes(4);
            $payload = $this->maskPayload($payload, $mask);
            $length = strlen($payload);
    
            $header = '';
    
            // データ長１バイト
            if($length <= 125)
            {
                $header = pack('CC', $b1, $length);
            }
            // データ長２バイト
            else
            if($length > 125 && $length < 65536)
            {
                $header = pack('CCn', $b1, ParameterForPureLatencyBenchmark::CHAT_PAYLOAD_LEN_CODE_2, $length);
            }
            //データ長８バイト
            else
            if($length >= 65536)
            {
                $header = pack('CCNN', $b1, ParameterForPureLatencyBenchmark::CHAT_PAYLOAD_LEN_CODE_8, $length);
            }
        
            //--------------------------------------------------------------------------
            // 送信データの設定
            //--------------------------------------------------------------------------
    
            $p_param->protocol()->setSendingData($header.$mask.$payload);

            return ProtocolForPureLatencyBenchmarkStatusEnum::SENDING->value;
        };
    }


    //--------------------------------------------------------------------------
    // 以降はステータスUNITの定義（"ALIVE"キュー）
    //--------------------------------------------------------------------------

    /**
     * ステータス名： START
     * 
     * 処理名：アライブチェック開始
     * 
     * @param ParameterForPureLatencyBenchmark $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getAliveStart()
    {
        return function(ParameterForPureLatencyBenchmark $p_param): ?string
        {
            $start = hrtime(true);
            $alive_timer = [
                'alive_timer' => [
                    'start' => $start,
                    'end' => null
                ]
            ];
            $p_param->setTempBuff($alive_timer);

            // ペイロード設定
            $payload = $this->payload;
    
            //--------------------------------------------------------------------------
            // ヘッダ部の構築
            //--------------------------------------------------------------------------
    
            $b1 = ParameterForPureLatencyBenchmark::CHAT_FIN_BIT_MASK | (ParameterForPureLatencyBenchmark::CHAT_OPCODE_PING_MASK & 0x0f);  // maskあり
            $mask = random_bytes(4);
            $payload = $this->maskPayload($payload, $mask);
            $length = strlen($payload);
    
            $header = '';
    
            // データ長１バイト
            if($length <= 125)
            {
                $header = pack('CC', $b1, $length | ~ParameterForPureLatencyBenchmark::CHAT_PAYLOAD_LEN_MASK);
            }
            // データ長２バイト
            else
            if($length > 125 && $length < 65536)
            {
                $header = pack('CCn', $b1, ParameterForPureLatencyBenchmark::CHAT_PAYLOAD_LEN_CODE_2 | ~ParameterForPureLatencyBenchmark::CHAT_PAYLOAD_LEN_MASK, $length);
            }
            //データ長８バイト
            else
            if($length >= 65536)
            {
                $header = pack('CCNN', $b1, ParameterForPureLatencyBenchmark::CHAT_PAYLOAD_LEN_CODE_8 | ~ParameterForPureLatencyBenchmark::CHAT_PAYLOAD_LEN_MASK, $length);
            }
        
            //--------------------------------------------------------------------------
            // 送信データの設定
            //--------------------------------------------------------------------------
    
            $p_param->protocol()->setSendingData($header.$mask.$payload);
    
            return ProtocolForPureLatencyBenchmarkStatusEnum::SENDING->value;
        };
    }

    /**
     * マスク済みペイロードの作成
     * 
     * @param string $p_payload ペイロードデータ
     * @param string $p_mask_key マスクキー
     * @return string マスク済みペイロードデータ
     */
    private function maskPayload(string $p_payload, string $p_mask_key): string
    {
        $len = strlen($p_payload);
        $masked = '';

        for($i = 0; $i < $len; $i++)
        {
            $masked .= $p_payload[$i] ^ $p_mask_key[$i % 4];
        }

        return $masked;
    }
}
