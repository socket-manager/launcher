# SOCKET-MANAGER Launcher

SOCKET-MANAGER Framework を基盤に構築された本ランチャーは、CLIモードによる明確な操作体系を実現し、軽量設計で堅牢なサービス管理ツールとしての役割を担います。

---

## ランチャーの特長

- **操作と構成の明快な分離**  
  CLIモードでの統一操作・設定ファイルによる柔軟な制御

- **軽量設計**  
  最小限の依存関係でランチャー自身が負荷を及ぼさないよう配慮

- **CPUリソースの明示的な割り当て機能**  
  論理CPUへの配置指定とプロセス状態の可視化

- **ログ収集と運用透明性の両立**  
  アクション単位での追跡・監視・記録機能を実装

- **SOCKET-MANAGER Framework推奨構成への親和性**  
  プロセスの絶対数によるマルチサーバー管理に最適化

- **サービス種別の柔軟な対応**  
  コマンドライン起動型であれば Framework 製以外のサービスにも対応可能

- **グループ単位でのサービス管理**  
  任意のサービス群に対して一括操作・監視が可能な柔軟なグルーピング機能を搭載

- **クロスプラットフォーム対応**  
  Windows / Linux（Ubuntuによる動作確認）

---

## アクションの種類

以下のアクションはサービス設定ファイル（JSON形式）と連携して動作します。

- start  
  "サービス名"、あるいは"group:サービス名"を指定して起動

- startall  
  サービス設定ファイルで定義済みの全サービスを起動

- stop  
  "サービス名"、あるいは"group:サービス名"を指定して停止

- stopall  
  サービス設定ファイルで定義済みの全サービスを停止

- status  
  "サービス名"、あるいは"group:サービス名"を指定して状態表示

- statusall  
  サービス設定ファイルで定義済みの全サービスを状態表示

- cpuinfo  
  稼働中物理サーバーのCPU構成を表示（論理CPU割当用の参照情報）

※ group の設定はサービス設定ファイル内で行います。

---

## インストールと起動方法

以下の composer コマンドでインストールできます。

```
> composer create-project socket-manager/launcher <インストール先のディレクトリ名>
```

インストールが終わったらプロジェクトのルートディレクトリで `php worker app:cli` コマンドを入力し、以下のように Usage が表示されれば正常にインストールされています。

```php
> php worker app:cli

以下のいずれかを指定して下さい。
start <サービス名> or <group:グループ名>
startall
stop <サービス名> or <group:グループ名>
stopall
status <サービス名> or <group:グループ名>
statusall
cpuinfo
```

※初期状態のサービス設定ファイル `setting/service.json.sample` にはサンプルが定義されています。内容を定義後はファイル名を `service.json` にしてからお使い下さい。

---

## Contact Us
バグ報告やご要望などは<a href="mailto:lib.tech.engineer@gmail.com">`こちら`</a>から受け付けております。

---

## License
MIT, see <a href="https://github.com/socket-manager/new-project/blob/main/LICENSE">LICENSE file</a>.