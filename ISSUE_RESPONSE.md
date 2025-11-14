# Issue Response: 管理画面のContent-Security-Policy(CSP)の移行検討

## 調査結果

### Issue の妥当性評価

**✅ このIssueは適切であり、対応すべきです。**

### 調査内容

以下の包括的な調査を実施しました：

1. **現状のCSP設定の確認** (`src/Security/SecurityUtil.php`)
2. **inline script の使用箇所の洗い出し** (管理画面・公開ページ)
3. **eval() の使用箇所の特定**
4. **inline style の使用状況の把握**
5. **セキュリティリスクの評価**
6. **移行の実現可能性の検証**

### 主な発見事項

#### 1. 現在のCSP設定の問題点

**管理画面** (`src/Security/SecurityUtil.php` line 88-93):
```php
script-src 'self' 'unsafe-inline' 'unsafe-eval' cdn.jsdelivr.net code.jquery.com;
style-src 'self' 'unsafe-inline' cdn.jsdelivr.net fonts.googleapis.com;
```

**問題点**:
- `'unsafe-inline'`: 任意の inline script/style を許可 → **XSS攻撃リスク（高）**
- `'unsafe-eval'`: eval(), Function() を許可 → **インジェクション攻撃リスク（中）**

**公開ページ**:
```php
script-src 'self' 'unsafe-inline' cdn.jsdelivr.net code.jquery.com;
style-src 'self' 'unsafe-inline' cdn.jsdelivr.net fonts.googleapis.com;
```

**問題点**:
- `'unsafe-inline'`: 任意の inline script/style を許可 → **XSS攻撃リスク（高）**

#### 2. inline script の使用箇所（8箇所以上）

| ファイル | 行番号 | 内容 | 移行難易度 |
|---------|-------|------|-----------|
| `public/admin/index.php` | 1010-1013 | ADMIN_PATH 定数 | 低 |
| `public/admin/paint/index.php` | 527 | CSRF_TOKEN | 低 |
| `public/admin/paint/index.php` | 529-532 | PAINT_BASE_URL 定数 | 低 |
| `public/admin/paint/index.php` | 534-560 | Worker constructor shim (~26行) | 中 |
| `public/index.php` | 126-132 | 設定値 (AGE_VERIFICATION等) | 低 |
| その他公開ページ | - | 類似の設定値埋め込み | 低 |

#### 3. eval() の使用箇所（1箇所）

**ファイル**: `public/admin/js/admin.js` line 565

```javascript
const fn = (typeof eval(name) === 'function') ? eval(name) : null;
```

**目的**: 関数名の文字列から関数をグローバルスコープに公開

**代替方法**: オブジェクトマップによる参照（実装は容易）

#### 4. inline style の使用（50箇所以上）

- 管理画面: 各ページに25-26箇所の style 属性
- 公開ページ: 複数の style ブロック

### セキュリティリスク評価

| リスク項目 | レベル | 影響範囲 | 説明 |
|-----------|--------|---------|------|
| unsafe-inline (script) | 🔴 **高** | 全ページ | XSS攻撃による任意コード実行を許可 |
| unsafe-eval | 🟡 **中** | 管理画面のみ | インジェクション攻撃のリスク |
| inline style | 🟡 **中** | 全ページ | CSP保護の弱体化 |

**攻撃シナリオ例**:
1. 攻撃者がデータベースに `<script>` タグを注入
2. `'unsafe-inline'` により注入されたスクリプトが実行される
3. セッションハイジャック、データ窃取、アカウント乗っ取りの被害

**CSP強化後**:
1. 同じ注入を試みる
2. nonce がないスクリプトは CSP によりブロックされる
3. 攻撃は失敗 → **被害なし**

### 移行の実現可能性

**✅ 実現可能です**

**根拠**:

1. **技術的に可能**
   - nonce ベースの CSP は主要ブラウザで対応済み
   - PHP での nonce 生成は簡単 (`random_bytes(16)`)
   - inline script の外部化は比較的容易

2. **段階的な移行が可能**
   - report-only モードで影響を事前確認
   - フェーズごとに実装・テスト・デプロイを分割
   - ロールバック手段が確立されている

3. **影響範囲は限定的**
   - 主に管理画面（アクセス頻度は低い）
   - 既存の外部 JS ファイルはほぼ変更不要

**実装難易度**:

| タスク | 難易度 | 推定工数 |
|--------|-------|---------|
| eval() 廃止 | 🟢 低 | 0.5日 |
| 設定値の外部化 | 🟢 低 | 2-3日 |
| nonce ミドルウェア実装 | 🟡 中 | 3-5日 |
| Worker shim 外部化 | 🟡 中 | 2-3日 |
| inline style 外部化 | 🔴 高 | 10-15日 |

**合計推定工数（優先度高のみ）**: 約2-3週間

## 推奨事項

### ✅ このIssueを承認し、段階的な移行を実施すべきです

**理由**:

1. **高セキュリティリスク**: 現在の設定は XSS 攻撃のリスクを高める
2. **実現可能**: 技術的に実現可能で、段階的な移行が可能
3. **業界標準**: 現代的なセキュリティ標準に準拠すべき
4. **推奨事項に準拠**: `docs/SECURITY_REVIEW.md` の推奨事項に従うべき

### 推奨される移行スケジュール

#### Phase 1: 緊急対応（優先度: 🔴 高）
**期間**: 1-2週間

**対応項目**:
1. eval() の廃止
2. 設定値の inline script を data属性に移行
3. CSRF トークンを meta タグに移行

**成果**: `'unsafe-eval'` の完全廃止、inline script の大幅削減

#### Phase 2: CSP 基盤整備（優先度: 🔴 高）
**期間**: 2-3週間

**対応項目**:
1. CspMiddleware の実装
2. nonce 生成ロジック
3. report-only モードでの検証
4. Worker shim の外部化

**成果**: nonce ベース CSP の基盤確立

#### Phase 3: CSP 強化（優先度: 🟡 中）
**期間**: 3-4週間

**対応項目**:
1. 残存する inline script の外部化
2. enforce モードへの移行（段階的）
3. 本番環境での監視・調整

**成果**: `'unsafe-inline'` の完全廃止（script-src）

#### Phase 4: 完全対応（優先度: 🟢 低）
**期間**: 4-6週間

**対応項目**:
1. inline style の外部化
2. SRI (SubResource Integrity) 対応
3. CSP レポート分析の自動化

**成果**: 完全な CSP 対応

### 作成したドキュメント

本調査の結果、以下の詳細ドキュメントを作成しました：

1. **`docs/CSP_MIGRATION_PLAN.md`** (15KB)
   - 詳細な現状分析
   - セキュリティリスク評価
   - 6フェーズの移行戦略とタイムライン
   - nonce ベース CSP ミドルウェアの技術設計
   - コード例と移行パターン
   - テスト戦略とロールアウト計画
   - 成功基準とモニタリング方法

2. **`docs/CSP_INVESTIGATION_SUMMARY.md`** (11KB)
   - 調査結果サマリー
   - セキュリティリスク分析と攻撃シナリオ
   - 実現可能性の評価
   - コスト・ベネフィット分析
   - 推奨される次のステップ
   - Issue の評価と推奨事項

### 次のアクション

#### 即座に実施すべき項目

1. **✅ この調査結果のレビュー**
   - チーム内でレビュー・承認
   - 必要に応じて計画の調整

2. **📋 Phase 1 の実装 Issue を作成**
   - eval() 廃止
   - 設定値の外部化
   - 期限: 2週間

3. **🔧 開発環境での予備調査**
   - CSP report-only モードを有効化
   - violation レポートの収集・分析

4. **📊 移行管理表の作成**
   - 各タスクの進捗管理
   - リスク管理

#### 推奨される Issue/PR の分割

| Issue/PR | 内容 | 優先度 | 推定工数 |
|---------|------|--------|---------|
| Issue #1 | eval() の廃止 | 🔴 高 | 0.5日 |
| Issue #2 | 設定値の外部化 | 🔴 高 | 2-3日 |
| Issue #3 | CspMiddleware 実装 | 🔴 高 | 3-5日 |
| Issue #4 | Worker shim 外部化 | 🟡 中 | 2-3日 |
| Issue #5 | CSP enforce モード移行 | 🟡 中 | 1週間 |
| Issue #6 | inline style 外部化 | 🟢 低 | 2-3週間 |

## コスト・ベネフィット分析

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

**投資対効果**: 🟢 **高い**（セキュリティリスク軽減の価値は大きい）

## 結論

### Issue の評価

**✅ この Issue は適切かつ重要です。**

現在の CSP 設定は不十分であり、セキュリティリスクが存在します。段階的な移行により、リスクを最小化しながら、安全な CSP への移行が可能です。

### 最終的な推奨

**🔴 高優先度で段階的な CSP 移行を実施すべきです。**

具体的には：
1. **短期（1-2ヶ月）**: eval() 廃止 + 設定値外部化
2. **中期（3-4ヶ月）**: nonce ベース CSP の導入
3. **長期（6ヶ月以上）**: inline style の外部化、SRI 対応

### 参考資料

- **詳細な移行計画**: `docs/CSP_MIGRATION_PLAN.md`
- **調査結果サマリー**: `docs/CSP_INVESTIGATION_SUMMARY.md`
- **現在の実装**: `src/Security/SecurityUtil.php` (line 88-109)
- **セキュリティレビュー**: `docs/SECURITY_REVIEW.md`

---

**調査完了日**: 2025-11-14  
**調査者**: GitHub Copilot
