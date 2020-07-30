---
layout: home
author_profile: true
permalink: /
---
EC-CUBE4 対応の Web API プラグイン

外部サービスと連携するため、[GraphQL](https://graphql.org) による Web API 機能を実現します。

## システム要件

- PHP 7.2 or higher
- PostgreSQL or MySQL

- *Windows 環境での動作は未確認です*
- *SQLite3 には未対応です*
- *テスト環境の作成には Docker が必要です*

## Quick Start

1. EC-CUBE4 をインストールします。
    ```sh
    composer create-project ec-cube/ec-cube ec-cube "4.0.x-dev" --keep-vcs
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

1. [本プラグイン対応用のブランチ](#%E3%82%A4%E3%83%B3%E3%82%B9%E3%83%88%E3%83%BC%E3%83%AB%E6%96%B9%E6%B3%95)をチェックアウトします。
    ```sh
    cd ec-cube
    git fetch origin pull/4625/head:experimental/plugin_bundle
    git checkout experimental/plugin_bundle
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
    ## for PostgreSQL
    psql eccubedb -h 127.0.0.1 -U postgres -c "update dtb_base_info set authentication_key='test';"
    ```

    ```sh
    ## for MySQL
    mysql -h 127.0.0.1 --user=root --password=password -e "update dtb_base_info set authentication_key='test';" eccubedb
    ```

1. プラグインのパッケージを配置します。

    ``` sh
    cd repos
    # パッケージングしたプラグインを配置。例でリンクを記載していますが、パッケージは古い可能性があるので各自でパッケージしたものに置き換えてください。
    git clone https://github.com/EC-CUBE/eccube-api4.git
    cd eccube-api4
    tar cvzf ../Api-1.0.0.tgz *
    cd ../../
    ```

    
1. ビルトインウェブサーバーを起動
    ```sh
    bin/console server:run
    ```
1. プラグインをインストールします。
    ```sh
    bin/console eccube:composer:require ec-cube/Api
    bin/console eccube:plugin:enable --code=Api
    ```
    - 管理画面→オーナーズストア→プラグイン→ **プラグインを探す** からでもプラグインをインストールできます。


API プラグインの開発のため Git リポジトリで置き換える場合は以下のとおり。
*プラグインをアンインストールすると、 Git リポジトリごと削除されてしまうため注意すること*

```
cd app/Plugin/

rm -rf Api
git clone git@github.com:okazy/eccube-api4.git
mv eccube-api4 Api
```

## OAuth2.0 による認可



## 機能仕様

**この記事は GraphQL についての説明はしていませんので、GraphQL 自体の仕様について[GraphQL公式サイト](https://graphql.org/)等でご確認ください。**

GraphQL の Query で以下のデータの取得が可能です。

- 商品一覧
- 受注一覧
- 顧客一覧
