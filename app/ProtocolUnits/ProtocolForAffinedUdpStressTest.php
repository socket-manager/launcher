<?php
/**
 * ステータスUNIT登録クラスのファイル
 * 
 * モジュール管理クラスのsetProtocolUnitsメソッドへ引き渡されるクラスのファイル
 */

namespace App\ProtocolUnits;

use SocketManager\Library\ProtocolQueueEnum;
use SocketManager\Library\IEntryUnits;
use App\UnitParameter\ParameterForAffinedUdpStressTest;
use App\CommandUnits\CommandForAffinedUdpStressTestQueueEnum;


/**
 * プロトコルUNIT登録クラス
 * 
 * IEntryUnitsインタフェースをインプリメントする
 */
class ProtocolForAffinedUdpStressTest implements IEntryUnits
{
    //--------------------------------------------------------------------------
    // 定数
    //--------------------------------------------------------------------------

    /**
     * @var array QUEUE_LIST キュー名のリスト
     */
    protected const QUEUE_LIST = [
        ProtocolQueueEnum::CONNECT->value,  // コネクションキュー
        ProtocolQueueEnum::ALIVE->value		// アライブチェック処理のキュー
    ];


    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * @var string 接続先ホスト
     */
    private string $host = '';

    /**
     * @var int 接続先ポート番号
     */
    private int $port = -1;

    /**
     * @var bool 測定中の標準出力フラグ
     */
    private bool $stdout = false;

    /**
     * @var string ペイロード
     */
    private string $payload = '';

    /**
     * @var int ペイロード長
     */
    private int $payload_len = 0;


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     */
    public function __construct(string $p_payload, string $p_host, int $p_port)
    {
        $this->host = $p_host;
        $this->port = $p_port;
        $this->stdout = config('bench_affined_udp.stdout', false);
        $this->payload = $p_payload;
        $this->payload_len = strlen($p_payload);
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
                'status' => ProtocolForAffinedUdpStressTestStatusEnum::START->value,
                'unit' => $this->connectStart(...)
            ];
            $ret[] = [
                'status' => ProtocolForAffinedUdpStressTestStatusEnum::WAITING->value,
                'unit' => $this->connectWaiting(...)
            ];
        }
        else
        if($p_que === ProtocolQueueEnum::ALIVE->value)
        {
            $ret[] = [
                'status' => ProtocolForAffinedUdpStressTestStatusEnum::START->value,
                'unit' => $this->echoSend(...)
            ];
            $ret[] = [
                'status' => ProtocolForAffinedUdpStressTestStatusEnum::SENDING->value,
                'unit' => $this->sending(...)
            ];
            $ret[] = [
                'status' => ProtocolForAffinedUdpStressTestStatusEnum::RECEIVE->value,
                'unit' => $this->echoReceive(...)
            ];
            $ret[] = [
                'status' => ProtocolForAffinedUdpStressTestStatusEnum::RECEIVING->value,
                'unit' => $this->echoReceiving(...)
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
     * 処理名：接続直後
     * 
     * @param ParameterForAffinedUdpStressTest $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    private function connectStart(ParameterForAffinedUdpStressTest $p_param): ?string
    {
        $p_param->sample_count++;
        if($this->stdout)
        {
            printf("session count[{$p_param->sample_count}]\r");
        }
        if($p_param->sample_count >= $p_param->samples)
        {
            if($this->stdout)
            {
                printf("\n");
            }
            return ProtocolForAffinedUdpStressTestStatusEnum::WAITING->value;
        }

        return null;
    }

    /**
     * ステータス名： WAITING
     * 
     * 処理名：待機中
     * 
     * @param ParameterForAffinedUdpStressTest $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    private function connectWaiting(ParameterForAffinedUdpStressTest $p_param): ?string
    {
        // 次回タイムアウトの設定
        $now = hrtime(true);
        $p_param->next_timeout = $now;

        $alive_start =
        [
            'cmd' => CommandForAffinedUdpStressTestQueueEnum::ALIVE_START->value
        ];
        $p_param->setRecvStack($alive_start, true);

        return null;
    }


    //--------------------------------------------------------------------------
    // 以降はステータスUNITの定義（"ALIVE"キュー）
    //--------------------------------------------------------------------------

    /**
     * ステータス名： START
     * 
     * 処理名：送信処理
     * 
     * @param ParameterForAffinedUdpStressTest $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    private function echoSend(ParameterForAffinedUdpStressTest $p_param): ?string
    {
        $p_param->setTempBuff(['wait' => 0]);
        $p_param->protocol()->setSendingData($this->payload);
        return $this->sending($p_param);
    }

    /**
     * ステータス名： SENDING
     * 
     * 処理名：送信中
     * 
     * @param ParameterForAffinedUdpStressTest $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    private function sending(ParameterForAffinedUdpStressTest $p_param): ?string
    {
        $wait = $p_param->getTempBuff(['wait'])['wait'];
        if(++$wait < 5)
        {
            $p_param->setTempBuff(['wait' => $wait]);
            return ProtocolForAffinedUdpStressTestStatusEnum::SENDING->value;
        }
        $w_ret = $p_param->protocol()->sending();
        if($w_ret === null)
        {
            $p_param->logWriter('debug', ['sendto']);
            return ProtocolForAffinedUdpStressTestStatusEnum::SENDING->value;
        }

        return ProtocolForAffinedUdpStressTestStatusEnum::RECEIVE->value;
    }

    /**
     * ステータス名： RECEIVE
     * 
     * 処理名：受信処理
     * 
     * @param ParameterForAffinedUdpStressTest $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    private function echoReceive(ParameterForAffinedUdpStressTest $p_param): ?string
    {
        $p_param->protocol()->setReceivingSize($this->payload_len);
        return $this->echoReceiving($p_param);
    }

    /**
     * ステータス名： RECEIVING
     * 
     * 処理名：受信中
     * 
     * @param ParameterForAffinedUdpStressTest $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    private function echoReceiving(ParameterForAffinedUdpStressTest $p_param): ?string
    {
        $data = $p_param->protocol()->receiving();
        if($data === null)
        {
            $p_param->logWriter('info', ['recv empty']);
            return ProtocolForAffinedUdpStressTestStatusEnum::RECEIVING->value;
        }

        $round_end = hrtime(true);

        if($data !== $this->payload)
        {
            printf("\nペイロード不正で終了\n");
            exit;
        }

        $p_param->sample_count++;
        if($this->stdout)
        {
            printf("sample count[{$p_param->sample_count}]total[{$p_param->samples}]\r");
        }

        if($p_param->sample_count >= $p_param->samples)
        {
            // 経過時間の算出
            $elapsed_ns = $round_end - $p_param->test_start_time;
            $elapsed_sec = intdiv($elapsed_ns, 1_000_000_000); // ナノ秒 → 秒（整数）

            $hours = intdiv($elapsed_sec, 3600);
            $minutes = intdiv($elapsed_sec % 3600, 60);
            $seconds = $elapsed_sec % 60;

            $time = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

            // ラウンドアベレージの算出
            $round_elapsed_ns = $round_end - $p_param->round_start_time;
            $round_elapsed_ms = intdiv($round_elapsed_ns, 1_000_000);
            $avg = $round_elapsed_ms / $p_param->samples;

            // セッション数算出
            $cids = $p_param->getConnectionIdAll();
            $sessions = count($cids);

            // ファイル保存
            $filename = date('Ymd');
            $path = "./logs/socket-manager/stress-test/{$filename}-Affined-Udp-{$this->host}-{$this->port}.log";
            $fp = fopen($path, 'a');
            fputcsv($fp, [$time, $avg, $round_elapsed_ms, $sessions], ',', '"', '\\');
            fclose($fp);

            printf("\n[{$time}]avg[{$avg}]ms total[{$round_elapsed_ms}]ms sessions[{$sessions}]\n");

            $alive_start =
            [
                'cmd' => CommandForAffinedUdpStressTestQueueEnum::ALIVE_START->value
            ];
            $p_param->setRecvStack($alive_start, true);

            return null;
        }

        return null;
    }
}
