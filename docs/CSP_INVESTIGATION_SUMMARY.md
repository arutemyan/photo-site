# CSP 移行検討 - 調査結果サマリ

**調査日**: 2025-11-14  
**対象**: PixuGallery 管理画面および公開ページの Content-Security-Policy  
**関連Issue**: 管理画面のContent-Security-Policy(CSP)の移行検討（unsafe-inline/unsafe-eval廃止計画）

---

## エグゼクティブサマリー

### 調査結果

✅ **Issue は正当です。CSP 移行は実施すべきです。**

現在の実装では、以下の重大なセキュリティ上の問題が確認されました：

- **管理画面**: `'unsafe-inline'` と `'unsafe-eval'` を許可（高リスク）
- **公開ページ**: `'unsafe-inline'` を許可（高リスク）
- **eval() の使用**: 1箇所で確認（中リスク）
- **inline script**: 8箇所以上で使用（高リスク）
- **inline style**: 50箇所以上で使用（中リスク）

### 推奨事項

**🔴 高優先度: 段階的な CSP 移行を実施すべきです**

理由：
1. XSS 攻撃リスクの大幅な軽減が可能
2. 技術的に実現可能（nonce ベースの CSP）
3. 段階的な移行により、リスクを最小化できる
4. docs/SECURITY_REVIEW.md の推奨事項に準拠

---

## 詳細な調査結果

### 1. 現在の CSP 設定

**ファイル**: `src/Security/SecurityUtil.php` (line 88-109)

#### 管理画面 (isAdmin = true)
```php
script-src 'self' 'unsafe-inline' 'unsafe-eval' cdn.jsdelivr.net code.jquery.com;
style-src 'self' 'unsafe-inline' cdn.jsdelivr.net fonts.googleapis.com;
```

**問題点**:
- `'unsafe-inline'`: 任意の inline script/style を許可（XSS リスク）
- `'unsafe-eval'`: eval(), Function() を許可（インジェクションリスク）

#### 公開ページ (isAdmin = false)
```php
script-src 'self' 'unsafe-inline' cdn.jsdelivr.net code.jquery.com;
style-src 'self' 'unsafe-inline' cdn.jsdelivr.net fonts.googleapis.com;
```

**問題点**:
- `'unsafe-inline'`: 任意の inline script/style を許可（XSS リスク）
- `'unsafe-eval'` は不使用（✅ 良い点）

### 2. inline script の使用状況

#### 管理画面

| ファイル | 行番号 | 内容 | 代替方法 |
|---------|-------|------|---------|
| `public/admin/index.php` | 1010-1013 | ADMIN_PATH 定数の定義 | data属性 or meta タグ |
| `public/admin/paint/index.php` | 527 | CSRF_TOKEN の設定 | meta タグ |
| `public/admin/paint/index.php` | 529-532 | PAINT_BASE_URL 定数 | data属性 |
| `public/admin/paint/index.php` | 534-560 | Worker constructor shim (~26行) | 外部 JS ファイル |

#### 公開ページ

| ファイル | 行番号 | 内容 | 代替方法 |
|---------|-------|------|---------|
| `public/index.php` | 126-132 | 設定値 (AGE_VERIFICATION等) | data属性 |
| `public/detail.php` | - | 設定値の埋め込み | data属性 |
| `public/paint/index.php` | - | 設定値の埋め込み | data属性 |
| `public/paint/detail.php` | - | 設定値の埋め込み | data属性 |

**合計**: 約8ファイル、複数の inline script ブロック

### 3. eval() の使用状況

**ファイル**: `public/admin/js/admin.js` (line 565)

```javascript
const fn = (typeof eval(name) === 'function') ? eval(name) : null;
```

**目的**: 関数名の文字列から関数をグローバルスコープに公開

**代替方法**: オブジェクトマップによる関数参照
```javascript
const functionMap = {
    'loadPosts': loadPosts,
    'loadThemeSettings': loadThemeSettings,
    // ...
};
const fn = functionMap[name] || null;
```

**リスク評価**: 中リスク（管理画面のみ、限定的な使用）

### 4. inline style の使用状況

| ファイル | 箇所数 | 影響 |
|---------|-------|------|
| `public/admin/index.php` | 25箇所 | style属性による直接指定 |
| `public/admin/paint/index.php` | 26箇所 | style属性による直接指定 |
| その他の公開ページ | 複数 | style ブロック + PHP include |

**合計**: 50箇所以上

**リスク評価**: 中リスク（inline style は inline script より安全だが、CSP の保護を弱める）

---

## セキュリティリスク分析

### 現在のリスク

| リスク項目 | レベル | 影響範囲 | 説明 |
|-----------|--------|---------|------|
| unsafe-inline (script) | 🔴 高 | 全ページ | XSS 攻撃による任意コード実行 |
| unsafe-eval | 🟡 中 | 管理画面のみ | インジェクション攻撃のリスク |
| inline style | 🟡 中 | 全ページ | CSP 保護の弱体化 |
| 外部 CDN (SRI 未対応) | 🟡 中 | 全ページ | CDN 侵害時のリスク |

### CSP がない場合の攻撃シナリオ例

1. **XSS 攻撃**: データベースに `<script>alert('XSS')</script>` を注入
2. **inline script 許可**: 注入されたスクリプトが実行される
3. **被害**: セッションハイジャック、データ窃取、管理者アカウント乗っ取り

### CSP 強化後の防御

1. **XSS 攻撃**: 同じ注入を試みる
2. **nonce なし script**: CSP によりブロックされる
3. **被害**: なし（攻撃は失敗）

---

## 移行の実現可能性

### ✅ 実現可能です

**理由**:

1. **技術的に可能**
   - nonce ベースの CSP は主要ブラウザで対応済み
   - PHP で nonce 生成は簡単（`random_bytes(16)`）
   - inline script の外部化は比較的容易

2. **段階的な移行が可能**
   - report-only モードで影響を事前確認できる
   - フェーズごとに実装・テスト・デプロイを分割可能
   - ロールバック手段が確立されている

3. **影響範囲は限定的**
   - 主に管理画面（アクセス頻度は低い）
   - 公開ページは inline script が少ない
   - 既存の外部 JS ファイルはほぼ変更不要

### 実装難易度の評価

| タスク | 難易度 | 推定工数 | 備考 |
|--------|-------|---------|------|
| eval() 廃止 | 🟢 低 | 0.5日 | 1箇所のみ |
| 設定値の外部化 | 🟢 低 | 2-3日 | パターン化されている |
| nonce ミドルウェア実装 | 🟡 中 | 3-5日 | 新規実装が必要 |
| Worker shim 外部化 | 🟡 中 | 2-3日 | 複雑なロジック |
| inline style 外部化 | 🔴 高 | 10-15日 | 数が多い |

**合計推定工数（優先度高のみ）**: 約2-3週間

---

## 推奨される移行計画

### Phase 1: 緊急対応（優先度: 🔴 高）

**期間**: 1-2週間

**対応項目**:
1. eval() の廃止（admin.js）
2. 設定値の inline script を data属性に移行
3. CSRF トークンを meta タグに移行

**成果物**:
- `'unsafe-eval'` の完全廃止
- inline script の大幅削減

### Phase 2: CSP 基盤整備（優先度: 🔴 高）

**期間**: 2-3週間

**対応項目**:
1. CspMiddleware の実装
2. nonce 生成ロジック
3. report-only モードでの検証
4. Worker shim の外部化

**成果物**:
- nonce ベース CSP の基盤
- report-only モードでの CSP 有効化

### Phase 3: CSP 強化（優先度: 🟡 中）

**期間**: 3-4週間

**対応項目**:
1. 残存する inline script の外部化
2. enforce モードへの移行（段階的）
3. 本番環境での監視・調整

**成果物**:
- `'unsafe-inline'` の完全廃止（script-src）
- CSP enforce モード稼働

### Phase 4: 完全な CSP 対応（優先度: 🟢 低）

**期間**: 4-6週間

**対応項目**:
1. inline style の外部化
2. SRI (SubResource Integrity) 対応
3. CSP レポート分析の自動化

**成果物**:
- `'unsafe-inline'` の完全廃止（style-src）
- 完全な CSP 対応

---

## コストとベネフィットの分析

### コスト

| 項目 | 影響 |
|------|------|
| 開発工数 | 約2-3ヶ月（段階的） |
| テスト工数 | 約1ヶ月 |
| 学習コスト | nonce ベース CSP の理解 |
| 運用変更 | 開発時に nonce を意識する必要 |

### ベネフィット

| 項目 | 効果 |
|------|------|
| セキュリティ向上 | XSS 攻撃リスクの大幅な軽減 |
| 脆弱性スキャン | セキュリティツールの警告解消 |
| ベストプラクティス | 現代的なセキュリティ標準に準拠 |
| 信頼性向上 | ユーザーの信頼獲得 |

**投資対効果**: 🟢 高い（セキュリティリスク軽減の価値は大きい）

---

## Issue に対する回答

### Q: このIssueは適切か？

**A: ✅ はい、適切です。**

理由：
1. docs/SECURITY_REVIEW.md で指摘された問題は実在する
2. 現在の CSP 設定は不十分である
3. 段階的な移行計画が立案可能である
4. セキュリティ向上のメリットが明確である

### Q: 修正の是非は？

**A: ✅ 修正すべきです。**

理由：
1. **高リスク**: unsafe-inline と unsafe-eval は XSS 攻撃のリスクを高める
2. **実現可能**: 技術的に実現可能で、段階的な移行が可能
3. **業界標準**: 現代的なセキュリティ標準に準拠すべき
4. **推奨事項**: docs/SECURITY_REVIEW.md の推奨事項に従うべき

### Q: 優先度は？

**A: 🔴 高優先度**

理由：
1. セキュリティに直結する問題
2. 攻撃者に悪用される可能性がある
3. 早期対応により、リスクを早く軽減できる

---

## 次のステップ

### 推奨される即座のアクション

1. **✅ この Issue を承認**
   - 移行計画の妥当性を確認
   - チーム内でレビュー

2. **📋 Phase 1 の Issue を作成**
   - eval() 廃止
   - 設定値の外部化
   - 期限: 2週間

3. **🔧 開発環境で予備調査**
   - CSP report-only モードを有効化
   - violation レポートの収集

4. **📝 移行管理表の作成**
   - 各タスクの進捗管理
   - リスク管理

### 推奨されるIssue/PRの分割

| Issue/PR | 内容 | 優先度 | 推定工数 |
|---------|------|--------|---------|
| #1 | eval() の廃止 | 🔴 高 | 0.5日 |
| #2 | 設定値の外部化 | 🔴 高 | 2-3日 |
| #3 | CspMiddleware 実装 | 🔴 高 | 3-5日 |
| #4 | Worker shim 外部化 | 🟡 中 | 2-3日 |
| #5 | CSP enforce モード移行 | 🟡 中 | 1週間 |
| #6 | inline style 外部化 | 🟢 低 | 2-3週間 |

---

## 結論

### Issue の評価

**✅ この Issue は適切かつ重要です。**

現在の CSP 設定は不十分であり、セキュリティリスクが存在します。段階的な移行により、リスクを最小化しながら、安全な CSP への移行が可能です。

### 推奨される対応

**🔴 高優先度で段階的な移行を実施すべきです。**

具体的には：
1. **短期（1-2ヶ月）**: eval() 廃止 + 設定値外部化
2. **中期（3-4ヶ月）**: nonce ベース CSP の導入
3. **長期（6ヶ月以上）**: inline style の外部化

### 最終的な推奨事項

**✅ このIssueを承認し、別Issue/PRで段階的に実装することを推奨します。**

詳細な移行計画は `docs/CSP_MIGRATION_PLAN.md` を参照してください。

---

**作成者**: GitHub Copilot  
**調査完了日**: 2025-11-14
