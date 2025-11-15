# Content-Security-Policy (CSP) 移行計画

**作成日**: 2025-11-14  
**更新日**: 2025-11-14 (Phase 1 完了)  
**対象**: PixuGallery 管理画面および公開ページ  
**関連Issue**: 管理画面のContent-Security-Policy(CSP)の移行検討（unsafe-inline/unsafe-eval廃止計画）

---

## 残りの作業

### Phase 2: Inline style の外部化（優先度: 中）

**推定工数**: 2-3週間

**対象箇所**:
- `public/admin/index.php` - 25箇所の inline style 属性
- `public/admin/paint/index.php` - 26箇所の inline style 属性
- 公開ページ - 複数の style ブロック

**実装方針**:
1. 共通スタイルをCSSクラスに移行
2. 動的スタイルを最小限に抑える
3. 残存する動的スタイルにはnonceを付与

### Phase 3: SubResource Integrity (SRI) 対応（優先度: 低）

**推定工数**: 3-5日

**対象**:
- Bootstrap CDN
- jQuery CDN
- その他外部CDNリソース

**実装方針**:
```html
<script src="https://cdn.jsdelivr.net/..." 
        integrity="sha384-..." 
        crossorigin="anonymous"></script>
```

### Phase 4: CSP Violation レポート（優先度: 低）

**推定工数**: 1週間

**実装内容**:
1. `/api/csp-report` エンドポイントの作成
2. Violation ログの記録・分析機能
3. ダッシュボードでの可視化

---

## Phase 1 で解決した問題（完了済み）

### ✅ eval() の削除
- **ファイル**: `public/admin/js/admin.js`
- **変更**: Function map で置き換え
- **効果**: `'unsafe-eval'` 不要に

### ✅ 管理画面の inline script 削除
- **ファイル**: `public/admin/index.php`, `public/admin/paint/index.php`
- **変更**: Meta tag / data 属性 / 外部ファイル化
- **効果**: `'unsafe-inline'` 不要に（管理画面）

### ✅ nonce-based CSP の導入
- **新規ファイル**: `src/Security/CspMiddleware.php`
- **変更**: `src/Security/SecurityUtil.php` で使用
- **効果**: 安全な CSP ポリシー

**新しい CSP ポリシー** (Phase 1 後):
```
script-src 'self' 'nonce-XXXXX' cdn.jsdelivr.net code.jquery.com
style-src 'self' 'nonce-XXXXX' cdn.jsdelivr.net fonts.googleapis.com
```

---

## 次のステップ

1. **Phase 2 の着手判断** - inline style の優先度を再評価
2. **本番環境での監視** - CSP violation の発生状況を確認
3. **Phase 3/4 の実施判断** - 必要性に応じて実施

詳細な技術情報は `PHASE1_COMPLETION_REPORT.md` を参照してください。
