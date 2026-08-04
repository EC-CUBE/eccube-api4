# EC-CUBE4 対応の Web API プラグイン

[![Packaging for EC-CUBE Plugin](https://github.com/EC-CUBE/eccube-api4/actions/workflows/main.yml/badge.svg)](https://github.com/EC-CUBE/eccube-api4/actions/workflows/main.yml)

外部サービスと連携するため、[GraphQL](https://graphql.org) による Web API 機能を実現します。

本プラグインの利用には EC-CUBE 4.0.5 以上へのアップデートが必要になる予定です。
ほんプラグインおよびEC-CUBEのリリーススケジュールは[EC-CUBE 4.1 Roadmap](https://github.com/EC-CUBE/ec-cube/issues/4603)をご確認ください。

より良い Web API とするため Issue や PullRequest にてフィードバック/開発協力を募集しています。


## インストール

### EC-CUBE 4.0のインストール方法

[EC-CUBE4 Web API プラグイン 開発ドキュメント](https://doc.ec-cube.net/eccube-api4/) の手順に従ってインストールしてください。

### 動作確認環境

* PHP 8.2 or higher
* PostgreSQL or MySQL
* SSLサーバー証明書(TLS) は必須

詳しくは [EC-CUBE4 Web API プラグイン 開発ドキュメント](https://doc.ec-cube.net/eccube-api4/) をご確認ください。

## MCP サーバ利用時のセキュリティ設定

MCP サーバ機能を本番で利用する場合、 環境変数 `TRUSTED_HOSTS` と `TRUSTED_PROXIES` の設定が必須です。

OAuth ディスカバリの `resource_metadata` などの URL はリクエストの Host 名から組み立てます。
`TRUSTED_HOSTS` が未設定だと Host ヘッダを偽装され、 クライアントを攻撃者の認可サーバへ誘導される恐れがあります。

- `TRUSTED_HOSTS` — 公開ドメインに一致する正規表現（例: `^(www\.)?example\.com$`）
- `TRUSTED_PROXIES` — リバースプロキシ配下ではプロキシの IP レンジ

未設定のまま本番で MCP メタデータを配信すると警告ログが出力されます。
