<?php
/**
 * モジュール管理クラスの初期化クラスのファイル
 * 
 * モジュール管理クラスのinitManagerメソッドへ引き渡される初期化クラスのファイル
 */

namespace App\InitClass;

use SocketManager\Library\IInitManager;
use SocketManager\Library\PureUdp\SocketManagerParameter;

use App\UnitParameter\ParameterForPureUdpStressTest;


/**
 * モジュール管理クラスの初期化クラス
 * 
 * IInitManagerインタフェースをインプリメントする
 */
class InitForPureUdpStressTest implements IInitManager
{
    //--------------------------------------------------------------------------
    // 定数
    //--------------------------------------------------------------------------


    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * UNITパラメータインスタンス
     */
    protected ?ParameterForPureUdpStressTest $param = null;

    /**
     * ポート番号
     */
    protected ?int $port = null;


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     * @param SocketManagerParameter $p_param UNITパラメータ
     * @param int $p_port ポート番号
     */
    public function __construct(ParameterForPureUdpStressTest $p_param, int $p_port)
    {
        $this->param = $p_param;
        $this->port = $p_port;
    }

    /**
     * ログライター
     * 
     * @param string $p_level ログレベル
     * @param array $p_param ログパラメーター
     */
    private function logWriter(string $p_level, array $p_param): void
    {
        $filename = date('Ymd');
        $now = date('Y-m-d H:i:s');
        $log = $now." {$p_level} ".print_r($p_param, true)."\n";
        error_log($log, 3, "./logs/socket-manager/stress-test/{$filename}_PURE_UDP_{$this->port}.log");
    }

    /**
     * ログライターの取得
     * 
     * nullを返す場合は無効化（但し、ライブラリ内部で出力されているエラーメッセージも出力されない）
     * 
     * @return mixed "function(string $p_level, array $p_param): void" or null（ログ出力なし）
     */
    public function getLogWriter()
    {
        return null;
    }

    /**
     * シリアライザーの取得
     * 
     * nullを返す場合は無効化となる。
     * エラー発生時はUnitExceptionクラスで例外をスローして切断する。
     * 
     * @return mixed "function(mixed $p_data): mixed" or null（変更なし）
     */
    public function getSerializer()
    {
        return null;
    }

    /**
     * アンシリアライザーの取得
     * 
     * nullを返す場合は無効化となる。
     * エラー発生時はUnitExceptionクラスで例外をスローして切断する。
     * 
     * @return mixed "function(mixed $p_data): mixed" or null（変更なし）
     */
    public function getUnserializer()
    {
        return null;
    }

    /**
     * コマンドディスパッチャー
     * 
     * @param SocketManagerParameter $p_param UNITパラメータ
     * @param mixed $p_dat 受信データ
     * @return ?string コマンドUNITキュー名
     */
    private function commandDispatcher(ParameterForPureUdpStressTest $p_param, mixed $p_dat)
    {
        return $p_dat['cmd'];
    }

    /**
     * コマンドディスパッチャーの取得
     * 
     * 受信データからコマンドを解析して返す
     * 
     * コマンドUNIT実行中に受信データが溜まっていた場合でもコマンドUNITの処理が完了するまで
     * 待ってから起動されるため処理競合の調停役を兼ねる
     * 
     * nullを返す場合は無効化となる。エラー発生時はUnitExceptionクラスで例外をスローして切断する。
     * 
     * @return mixed "function(SocketManagerParameter $p_param, mixed $p_dat): ?string" or null（変更なし）
     */
    public function getCommandDispatcher()
    {
        return $this->commandDispatcher(...);
    }

    /**
     * 緊急停止時のコールバックの取得
     * 
     * 例外等の緊急切断時に実行される。nullを返す場合は無効化となる。
     * 
     * @return mixed "function(SocketManagerParameter $p_param)"
     */
    public function getEmergencyCallback()
    {
        return null;
    }

    /**
     * UNITパラメータインスタンスの取得
     * 
     * nullの場合はSocketManagerParameterのインスタンスが適用される
     * 
     * @return ?SocketManagerParameter SocketManagerParameterクラスのインスタンス（※1）
     * @see:RETURN （※1）当該クラス、あるいは当該クラスを継承したクラスも指定可
     */
    public function getUnitParameter(): ?SocketManagerParameter
    {
        return $this->param;
    }
}
