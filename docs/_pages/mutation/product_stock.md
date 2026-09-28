---
permalink: /mutation/product_stock
---

## 商品在庫の更新

updateProductStock で商品在庫の更新ができます。
`stock_unlimited: false` の場合は `stock` が必須、`stock_unlimited: true` の場合は `stock` を指定できません。

戻り値は更新後の `ProductClass` です。在庫数は `ProductStock { stock }`、在庫の有無は `in_stock` で取得します。
`in_stock` は、在庫無制限または在庫数が 1 以上のときに `true` になります。

### スキーマ

```graphql
type Mutation {
  updateProductStock(
    """商品コード"""
    code: String!

    """在庫数（在庫無制限の場合、0以上の数値を指定）"""
    stock: Int

    """在庫無制限（無制限は true 、制限は false を指定）"""
    stock_unlimited: Boolean!
  ): ProductClass
}
```

### 実行例

#### リクエスト（在庫有限）

```graphql
mutation {
  updateProductStock (
    code: "sand-01",
    stock: 10,
    stock_unlimited: false
  ) {
    code
    in_stock
    stock_unlimited
    ProductStock {
      stock
    }
  }
}
```

#### レスポンス（在庫有限）

```json
{
  "data": {
    "updateProductStock": {
      "code": "sand-01",
      "in_stock": true,
      "stock_unlimited": false,
      "ProductStock": {
        "stock": 10
      }
    }
  }
}
```

#### リクエスト（在庫無限）

```graphql
mutation {
  updateProductStock (
    code: "sand-01",
    stock_unlimited: true
  ) {
    code
    in_stock
    stock_unlimited
    ProductStock {
      stock
    }
  }
}
```

#### レスポンス（在庫無限）

```json
{
  "data": {
    "updateProductStock": {
      "code": "sand-01",
      "in_stock": true,
      "stock_unlimited": true,
      "ProductStock": {
        "stock": null
      }
    }
  }
}
```

#### Variables を利用したリクエスト例

```graphql
mutation UpdateProductStock(
    $code: String!,
    $stock: Int,
    $unlimited: Boolean!
  ){
  updateProductStock (
    code: $code,
    stock: $stock,
    stock_unlimited: $unlimited
  ) {
    code
    in_stock
    stock_unlimited
    ProductStock {
      stock
    }
  }
}
```

```json
{
	"code": "sand-01",
	"stock": 10,
	"unlimited": false
}
```
