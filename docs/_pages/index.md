---
layout: home
author_profile: true
permalink: /
---
EC-CUBE4 対応の API プラグイン

仕様の詳細は以下の Issue を参照。

https://github.com/EC-CUBE/ec-cube/issues/4447

## Quick Start

```sh
composer create-project --no-scripts ec-cube/ec-cube ec-cube "4.0.x-dev" --keep-vcs
cd ec-cube

git fetch origin pull/4625/head:experimental/plugin_bundle
git checkout experimental/plugin_bundle

# DATABASE_URL と DATABASE_SERVER_VERSION を適宜変更
# sed -i -e 's/DATABASE_URL=sqlite:\/\/\/var\/eccube.db/DATABASE_URL=postgres:\/\/postgres@127.0.0.1\/eccube/g' ./.env
# sed -i -e 's/DATABASE_SERVER_VERSION=3/DATABASE_SERVER_VERSION=9/g' ./.env

bin/console eccube:install --no-interaction

# プラグインの保管ディレクトリを作成
mkdir ${PWD}/repos

# mockサーバを起動。ここでは9999をポート番号に設定していますが、必要に応じて変更してください
docker run -d --rm -v ${PWD}/repos:/repos -e MOCK_REPO_DIR=/repos -p 9999:8080 eccube/mock-package-api

# mockサーバを参照するように環境変数を定義
echo ECCUBE_PACKAGE_API_URL=http://127.0.0.1:9999 >> .env

# 認証キーを設定
psql eccube -h 127.0.0.1 -U postgres -c "update dtb_base_info set authentication_key='test';"

# パッケージングしたプラグインを配置。例でリンクを記載していますが、パッケージは古い可能性があるので各自でパッケージしたものに置き換えてください。
# cd repos
# wget https://github.com/okazy/eccube-api4/releases/download/beta1/eccube-api4-beta1.tar.gz

# reposディレクトリにプラグインを設置。拡張子はtgzに変更してください
# mv eccube-api4-beta1.tar.gz eccube-api4-beta1.tgz
# cd ..

bin/console server:run --env=dev
```

管理画面→オーナーズストア→プラグイン→ **プラグインを探す** からプラグインをインストールできる。

コマンドラインでインストールする場合は以下を実行する
```
bin/console eccube:composer:require ec-cube/Api
bin/console eccube:plugin:enable --code=Api
```

API プラグインの開発のため Git リポジトリで置き換える場合は以下のとおり。
*プラグインをアンインストールすると、 Git リポジトリごと削除されてしまうため注意すること*

```
cd app/Plugin/

rm -rf Api
git clone git@github.com:okazy/eccube-api4.git
mv eccube-api4 Api
```


## インストール方法

本プラグインを利用するには EC-CUBE 4.0.4 から若干ファイルを変更する必要がある。

変更内容は以下のプルリクの内容となる。

https://github.com/EC-CUBE/ec-cube/pull/4625

初回インストールはパッケージAPI経由でインストールする必要がある。

パッケージ API 経由でのインストール方法は以下のドキュメントを参照のこと。

https://doc4.ec-cube.net/plugin_mock_package_api

また本体の機能として開発していた際のインストール手順や動作確認方法も参考になるかもしれません。

https://doc4.ec-cube.net/api_quickstart_guide


## OAuth2.0 による認可



## 機能仕様

**この記事は GraphQL についての説明はしていませんので、GraphQL 自体の仕様について[GraphQL公式サイト](https://graphql.org/)等でご確認ください。**

GraphQL の Query で以下のデータの取得が可能です。

- 商品一覧
- 受注一覧
- 顧客一覧
