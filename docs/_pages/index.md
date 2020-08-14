---
permalink: /
---
EC-CUBE4 対応の Web API プラグイン

外部サービスと連携するため、[GraphQL](https://graphql.org) による Web API 機能を実現します。

## システム要件

- PHP 7.2 or higher
- PostgreSQL or MySQL
- SSLサーバー証明書(TLS) は必須

- *テスト環境の作成には Docker が必要です*

### 現在のバージョンでの制限事項

- *Windows 環境での動作は未確認です*
- *SQLite3 には未対応です*
- *システム間連携を想定したAPIを前提としています。 ネイティブアプリケーションや、 SPA(Single Page Application)、シングルサインオン(SSO)などの用途は想定されていません*
- *Authorization code Grant 以外の Grant には未対応です*
- *OAuth2.0(RFC6749) や OpenID Connect には完全に準拠していません。今後のバージョンアップで準拠を目指していく予定です*



## Quick Start

1. [本プラグイン対応用のブランチ](https://github.com/EC-CUBE/ec-cube/tree/experimental/plugin_bundle)をチェックアウトします。
    ```sh
    git clone https://github.com/EC-CUBE/ec-cube.git
    cd ec-cube
    git checkout experimental/plugin_bundle
    composer install
    ```

1. DATABASE_URL と DATABASE_SERVER_VERSION を適宜変更。*(実際の環境に合わせること)*
    ```sh
    ## for PostgreSQL
    sed -i.bak -e 's/DATABASE_URL=sqlite:\/\/\/var\/eccube.db/DATABASE_URL=postgres:\/\/postgres:password@127.0.0.1\/eccubedb/g' ./.env
    sed -i.bak -e 's/DATABASE_SERVER_VERSION=3/DATABASE_SERVER_VERSION=9/g' ./.env
    ```

    ```sh
    ## for MySQL
    sed -i.bak -e 's/DATABASE_URL=sqlite:\/\/\/var\/eccube.db/DATABASE_URL=mysql:\/\/root:password@127.0.0.1\/eccubedb/g' ./.env
    sed -i.bak -e 's/DATABASE_SERVER_VERSION=3/DATABASE_SERVER_VERSION=5.7/g' ./.env
    ```

1. EC-CUBE4 をインストールします。
    ```sh
    bin/console eccube:install --no-interaction
    ```

1. EC-CUBEオーナーズストアのモックサーバーをセットアップします。
    ``` sh
    # プラグインの保管ディレクトリを作成
    mkdir ${PWD}/repos
    # mockサーバを起動。ここでは9999をポート番号に設定していますが、必要に応じて変更してください
    docker run -d --rm -v ${PWD}/repos:/repos -e MOCK_REPO_DIR=/repos -p 9999:8080 eccube/mock-package-api
    # mockサーバを参照するように環境変数を定義
    echo ECCUBE_PACKAGE_API_URL=http://127.0.0.1:9999 >> .env
    ```

1. 認証キーを設定します。
    ```sh
    bin/console doctrine:query:sql "update dtb_base_info set authentication_key='dummy'"
    ```

1. プラグインのパッケージを配置します。
    ``` sh
    cd repos
    git clone https://github.com/EC-CUBE/eccube-api4.git
    cd eccube-api4
    tar cvzf ../Api-1.0.0.tgz *
    cd ../../
    ```

1. プラグインをインストールします。
    ```sh
    bin/console eccube:composer:require ec-cube/Api
    bin/console eccube:plugin:enable --code=Api
    ```
    - 管理画面→オーナーズストア→プラグイン→ **プラグインを探す** からでもプラグインをインストールできます。

1. ビルトインウェブサーバーを起動
    ```sh
    bin/console server:run
    ```

1. [OAuth2.0 による認可](#oauth20-%E3%81%AB%E3%82%88%E3%82%8B%E8%AA%8D%E5%8F%AF) より API クライアントの認可をしてください。
1. [機能仕様](#%E6%A9%9F%E8%83%BD%E4%BB%95%E6%A7%98) より API をコールしてみましょう！

API プラグインの開発のため Git リポジトリで置き換える場合は以下のとおり。
*プラグインをアンインストールすると、 Git リポジトリごと削除されてしまうため注意すること*

```sh
cd app/Plugin/

rm -rf Api
git clone https://github.com/EC-CUBE/eccube-api4.git
mv eccube-api4 Api
```

## OAuth2.0 による認可

EC-CUBE で Web API を実行する際、顧客情報を参照したり、受注情報を更新する場合などは API クライアントの認可が必要です。

このプラグインでは、 [OAuth2.0](http://openid-foundation-japan.github.io/rfc6749.ja.html) プロトコルをサポートしています。

### 対応するフロー

Authorization Code Flow のみに対応しています。

- [Authorization Code Flow](authZ_code_grant) の設定方法


## 機能仕様

**この記事は GraphQL についての説明はしていませんので、GraphQL 自体の仕様について[GraphQL公式サイト](https://graphql.org/)等でご確認ください。**

GraphQLの[スキーマ](schema)は `bin/console eccube:api:dump-schema` コマンドで出力できます。

GraphQL の Query で以下のデータの取得が可能です。

- [商品情報の取得](query/products)
- [受注情報の取得](query/orders)
- [顧客情報の取得](query/customers)

GraphQL の Mutation で以下のデータを更新可能です。

- [商品在庫の更新](mutation/product_stock)
- [出荷ステータスの更新](mutation/update_shipped)

商品/受注/会員情報の変更時(登録/更新/削除)に登録されたWebHookに対して変更を通知します。

- [WebHook による通知](webhook)

## 拡張機構

CustomizeディレクトリやプラグインでAPIを拡張できます。

- [取得可能なデータの追加](customize/allow_list)
- [Query/Mutationの追加](customize/query)
