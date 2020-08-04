---
layout: home
author_profile: true
permalink: /customize/query
---

## Query/Mutationの追加

特定のインタフェースを実装したクラスを作成することでQuery/Mutationの追加が可能です

| Method         | Interface                           |
|----------------|-------------------------------------|
| Query          | Plugin\Api\GraphQL\Query            |
| Mutation       | Plugin\Api\GraphQL\Mutation         |

`Hello Query!` の文字列を返す最小のQueryの実装例は以下です。

```php
<?php

namespace Customize\GraphQL\Query;

use GraphQL\Type\Definition\Type;
use Plugin\Api\GraphQL\Query;

class HelloQuery implements Query
{
    public function getName()
    {
        return 'hello';
    }

    public function getQuery()
    {
        return [
            'type' => Type::string(),
            'resolve' => function ($root) {
                return 'Hello Query!';
            },
        ];
    }
}
```

リクエスト

```graphql
query {
  hello
}
```

レスポンス

```json
{
  "data": {
    "hello": null
  }
}
```

同様に `Mutation` を実装し、 `resolve` 内で更新処理を記載すると Mutation を追加できます。

### 参考

プラグインのデフォルトの Query および Mutation の実装は `Api/GraphQL/Query` および `Api/GraphQL/Mutation` にあります。
