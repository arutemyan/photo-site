#!/bin/bash
# リリース作成スクリプト（ビルド+パッケージング）

set -e

VERSION=${1:-$(date +%Y%m%d-%H%M%S)}
RELEASE_NAME="photo-site-${VERSION}"
OUTPUT_DIR="releases"

echo "🚀 リリース作成中: ${RELEASE_NAME}"
echo ""

# Node.jsの確認
if ! command -v node &> /dev/null; then
    echo "⚠️  Warning: Node.js not found. Skipping JavaScript build."
    echo "   Install Node.js to enable minification."
    SKIP_BUILD=true
else
    SKIP_BUILD=false
fi

# JavaScriptのビルド
if [ "$SKIP_BUILD" = false ]; then
    echo "📦 Building JavaScript bundles..."
    
    # pnpmの確認
    if ! command -v pnpm &> /dev/null; then
        echo "⚠️  Warning: pnpm not found. Trying npm..."
        PKG_MANAGER="npm"
    else
        PKG_MANAGER="pnpm"
    fi
    
    # node_modulesがない場合はインストール
    if [ ! -d "node_modules" ]; then
        echo "📥 Installing dependencies with ${PKG_MANAGER}..."
        ${PKG_MANAGER} install
    fi
    
    # プロダクションビルド
    NODE_ENV=production ${PKG_MANAGER} run build
    echo "✅ JavaScript build complete"
    echo ""
fi

# リリースディレクトリの作成
mkdir -p "${OUTPUT_DIR}"

# .gitattributesの設定に従って自動的に除外
echo "📦 Creating archive..."

# 一時ディレクトリを作成して git archive を展開
TMPDIR=$(mktemp -d)
trap 'rm -rf "${TMPDIR}"' EXIT

echo "-> Extracting tracked files to temporary dir: ${TMPDIR}"
git archive --format=tar HEAD | tar -x -C "${TMPDIR}"

# コピー: ワーキングツリーの bundle ファイルを一時ディレクトリへ追加
echo "-> Copying built bundle files into archive tree"
while IFS= read -r -d '' file; do
    relpath="${file#./}"
    destdir="${TMPDIR}/$(dirname "${relpath}")"
    mkdir -p "${destdir}"
    cp "${file}" "${destdir}/"
done < <(find . -type f -name '*.bundle.*' -print0)

# production 用に config/config.default.php をパッチ（開発->本番へ）
echo "-> Patching config/config.default.php to set production and enable bundled assets"
DEFAULT_CFG="${TMPDIR}/config/config.default.php"
# If working-tree has an updated config.default.php (uncommitted), copy it into the archive tree
if [ -f "config/config.default.php" ]; then
    cp "config/config.default.php" "${DEFAULT_CFG}"
fi
if [ -f "${DEFAULT_CFG}" ]; then
    # set environment => 'production'
    perl -0777 -pe "s/'environment'\s*=>\s*'[^']*'/'environment' => 'production'/s" -i "${DEFAULT_CFG}"
    # set use_bundled_assets => true if present, otherwise insert into 'app' array
    if grep -q "use_bundled_assets" "${DEFAULT_CFG}"; then
        perl -0777 -pe "s/'use_bundled_assets'\s*=>\s*(true|false)/'use_bundled_assets' => true/s" -i "${DEFAULT_CFG}"
    else
        # insert after 'app' => [ line
        perl -0777 -pe "s/('app'\s*=>\s*\[)/\1\n        'use_bundled_assets' => true,/s" -i "${DEFAULT_CFG}"
    fi
else
    # fallback: create config.local.php to force production setting
    echo "-> Warning: ${DEFAULT_CFG} not found. Creating config/config.local.php instead."
    mkdir -p "${TMPDIR}/config"
    cat > "${TMPDIR}/config/config.local.php" <<'PHP'
<?php
return [
        'app' => [
                'environment' => 'production',
                'use_bundled_assets' => true,
        ],
];
PHP
fi

# 確認: 一時ツリー内で設定が反映されているかをPHPでチェック
echo "-> Verifying production config in archive tree"
php -r "chdir('${TMPDIR}'); \$c = require 'config/config.php'; if (empty(\$c['app']['use_bundled_assets'])) { fwrite(STDERR, 'ERROR: packaged config does not enable use_bundled_assets\n'); exit(2); } echo '✅ Packaged config verified\n';"

# 最終的なアーカイブを作成（tmpdir の内容を RELEASE_NAME/ プレフィックス付きで圧縮）
echo "-> Creating final tar.gz: ${OUTPUT_DIR}/${RELEASE_NAME}.tar.gz"
cd "${TMPDIR}"
tar --transform "s,^,${RELEASE_NAME}/,S" -czf "${OLDPWD}/${OUTPUT_DIR}/${RELEASE_NAME}.tar.gz" .

# return to original working directory so relative paths like ${OUTPUT_DIR}/... resolve
cd "${OLDPWD}"

echo ""
echo "✅ 完了: ${OUTPUT_DIR}/${RELEASE_NAME}.tar.gz"
echo "📊 サイズ: $(du -h ${OUTPUT_DIR}/${RELEASE_NAME}.tar.gz | cut -f1)"
echo ""
echo "📝 リリース内容:"
echo "   - Minified JS/CSS bundles"
echo "   - PHP source code (tracked)"
echo "   - Production config (patched config.default.php or config.local.php)"
echo ""
echo "🎉 Ready to deploy!"
