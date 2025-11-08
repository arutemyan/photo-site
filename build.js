#!/usr/bin/env node
/**
 * Build script for JavaScript files
 * Bundles and minifies ES6 modules for production
 */

const esbuild = require('esbuild');
const fs = require('fs');

const isProduction = process.env.NODE_ENV === 'production';
const isWatch = process.argv.includes('--watch');

// ビルド対象の定義
const builds = [
  {
    name: 'Paint Application',
    entryPoints: ['public/admin/paint/js/paint.js'],
    outfile: 'public/admin/paint/js/paint.bundle.js',
  },
  {
    name: 'Admin',
    entryPoints: ['public/admin/js/admin.js'],
    outfile: 'public/admin/js/admin.bundle.js',
  },
  {
    name: 'Main Site',
    entryPoints: ['public/res/js/main.js'],
    outfile: 'public/res/js/main.bundle.js',
  },
  {
    name: 'Detail Page',
    entryPoints: ['public/res/js/detail.js'],
    outfile: 'public/res/js/detail.bundle.js',
  },
  {
    name: 'Paint Gallery',
    entryPoints: ['public/paint/js/gallery.js'],
    outfile: 'public/paint/js/gallery.bundle.js',
  },
  {
    name: 'Paint Detail',
    entryPoints: ['public/paint/js/detail.js'],
    outfile: 'public/paint/js/detail.bundle.js',
  },
  {
    name: 'Timelapse Player',
    entryPoints: ['public/paint/js/timelapse_player.js'],
    outfile: 'public/paint/js/timelapse_player.bundle.js',
  },
  // CSS bundles (for production we minify these)
  {
    name: 'Main CSS',
    entryPoints: ['public/res/css/main.css'],
    outfile: 'public/res/css/main.bundle.css',
  },
  {
    name: 'Admin CSS',
    entryPoints: ['public/res/css/admin.css'],
    outfile: 'public/res/css/admin.bundle.css',
  },
  {
    name: 'Paint CSS',
    entryPoints: ['public/admin/paint/css/style.css'],
    outfile: 'public/admin/paint/css/style.bundle.css',
  },
  {
    name: 'Paint Gallery CSS',
    entryPoints: ['public/paint/css/gallery.css'],
    outfile: 'public/paint/css/gallery.bundle.css',
  },
  {
    name: 'Paint Detail CSS',
    entryPoints: ['public/paint/css/detail.css'],
    outfile: 'public/paint/css/detail.bundle.css',
  },
];

// 共通のビルド設定
const baseConfig = {
  bundle: true,
  format: 'iife', // ブラウザ用の即時実行関数
  target: ['es2020', 'chrome90', 'firefox88', 'safari14'],
  minify: isProduction,
  sourcemap: !isProduction,
  logLevel: 'info',
};

async function buildAll() {
  console.log(`🔨 Building ${builds.length} bundles...`);
  console.log(`📦 Mode: ${isProduction ? 'PRODUCTION' : 'DEVELOPMENT'}`);
  console.log('');

  const results = await Promise.allSettled(
    builds.map(async (build) => {
      const { name, ...buildConfig } = build; // nameを除外
      
      try {
        let config = {
          ...baseConfig,
          ...buildConfig,
        };

        // esbuildのJS用オプションはCSSビルドには不要/拒否されるため除去
        if (config.outfile && config.outfile.endsWith('.css')) {
          // CSS出力時はformat/targetを削除し、formatは無効
          delete config.format;
          delete config.target;
        }

        if (isWatch) {
          const ctx = await esbuild.context(config);
          await ctx.watch();
          console.log(`👀 Watching: ${name}`);
        } else {
          await esbuild.build(config);
          
          // ファイルサイズを表示
          const stats = fs.statSync(build.outfile);
          const sizeKB = (stats.size / 1024).toFixed(2);
          console.log(`✅ ${name}: ${sizeKB} KB`);
        }
      } catch (error) {
        console.error(`❌ Failed to build ${name}:`, error);
        throw error;
      }
    })
  );

  const failed = results.filter((r) => r.status === 'rejected');
  if (failed.length > 0) {
    console.error(`\n❌ ${failed.length} build(s) failed`);
    process.exit(1);
  }

  if (!isWatch) {
    console.log('\n✨ All builds completed successfully!');
  } else {
    console.log('\n👀 Watching for changes...');
  }
}

// エラーハンドリング
process.on('unhandledRejection', (error) => {
  console.error('Unhandled error:', error);
  process.exit(1);
});

// 実行
buildAll().catch((error) => {
  console.error('Build failed:', error);
  process.exit(1);
});
