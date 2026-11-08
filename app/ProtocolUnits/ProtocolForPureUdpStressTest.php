<?php
/**
 * ステータスUNIT登録クラスのファイル
 * 
 * モジュール管理クラスのsetProtocolUnitsメソッドへ引き渡されるクラスのファイル
 */

namespace App\ProtocolUnits;

use SocketManager\Library\ProtocolQueueEnum;
use SocketManager\Library\IEntryUnits;
use App\UnitParameter\ParameterForPureUdpStressTest;
use App\CommandUnits\CommandForPureUdpStressTestQueueEnum;


/**
 * プロトコルUNIT登録クラス
 * 
 * IEntryUnitsインタフェースをインプリメントする
 */
class ProtocolForPureUdpStressTest implements IEntryUnits
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
     * @var string ローカルホスト
     */
    private string $local_host;

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
    public function __construct(string $p_payload, string $p_host, int $p_port, string $p_local_host)
    {
        $this->host = $p_host;
        $this->port = $p_port;
        $this->local_host = $p_local_host;
        $this->stdout = config('bench_pure_udp.stdout', false);
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
                'status' => ProtocolForPureUdpStressTestStatusEnum::START->value,
                'unit' => $this->connectStart(...)
            ];
            $ret[] = [
                'status' => ProtocolForPureUdpStressTestStatusEnum::WAITING->value,
                'unit' => $this->connectWaiting(...)
            ];
        }
        else
        if($p_que === ProtocolQueueEnum::ALIVE->value)
        {
            $ret[] = [
                'status' => ProtocolForPureUdpStressTestStatusEnum::START->value,
                'unit' => $this->echoSending(...)
            ];
            $ret[] = [
                'status' => ProtocolForPureUdpStressTestStatusEnum::RECEIVING->value,
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
     * @param ParameterForPureUdpStressTest $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    private function connectStart(ParameterForPureUdpStressTest $p_param): ?string
    {
        $p_param->sample_count++;
        if($this->stdout)
        {
            printf("session count[{$this->local_host}][{$p_param->sample_count}]\r");
        }
        if($p_param->sample_count >= $p_param->samples)
        {
            if($this->stdout)
            {
                printf("\n");
            }
            return ProtocolForPureUdpStressTestStatusEnum::WAITING->value;
        }

        return null;
    }

    /**
     * ステータス名： WAITING
     * 
     * 処理名：待機中
     * 
     * @param ParameterForPureUdpStressTest $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    private function connectWaiting(ParameterForPureUdpStressTest $p_param): ?string
    {
        // 次回タイムアウトの設定
        $now = hrtime(true);
        $p_param->next_timeout = $now + ($p_param->sampling_interval * 1000000000);

        $alive_start =
        [
            'cmd' => CommandForPureUdpStressTestQueueEnum::ALIVE_START->value
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
     * 処理名：送信中
     * 
     * @param ParameterForPureUdpStressTest $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    private function echoSending(ParameterForPureUdpStressTest $p_param): ?string
    {
        if(PHP_OS_FAMILY === 'Linux')
        {
            usleep(1);
        }
        $cid = $p_param->getConnectionId();
        $size = $p_param->protocol()->sendto($cid.$this->payload);
        if($size === null)
        {
            $p_param->logWriter('debug', ['sendto']);
            return ProtocolForPureUdpStressTestStatusEnum::START->value;
        }
        if($size < $this->payload_len)
        {
            $p_param->emergencyShutdown();
        }

        return ProtocolForPureUdpStressTestStatusEnum::RECEIVING->value;
    }

    /**
     * ステータス名： RECEIVING
     * 
     * 処理名：受信中
     * 
     * @param ParameterForPureUdpStressTest $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    private function echoReceiving(ParameterForPureUdpStressTest $p_param): ?string
    {
        $data = '';
        $host = $port = null;
        $size = $p_param->protocol()->recvfrom($data, $host, $port);
        if($size === null)
        {
            return ProtocolForPureUdpStressTestStatusEnum::RECEIVING->value;
        }

        $round_end = hrtime(true);

        $cid = $p_param->getConnectionId();
        if($data !== $cid.$this->payload)
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
            $path = "./logs/socket-manager/stress-test/{$filename}-Pure-Udp-{$this->host}-{$this->port}-{$this->local_host}.log";
            $fp = fopen($path, 'a');
            fputcsv($fp, [$time, $avg, $round_elapsed_ms, $sessions], ',', '"', '\\');
            fclose($fp);

            printf("\n[{$time}]avg[{$avg}]ms total[{$round_elapsed_ms}]ms sessions[{$sessions}]\n");

            $alive_start =
            [
                'cmd' => CommandForPureUdpStressTestQueueEnum::ALIVE_START->value
            ];
            $p_param->setRecvStack($alive_start, true);
            return null;
        }

        return null;
    }

}
