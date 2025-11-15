<?php

declare(strict_types=1);

/**
 * Asset Helper Functions
 * 
 * JavaScriptやCSSなどのアセットファイルのパスを環境に応じて返す
 */

namespace App\Utils;

class AssetHelper
{
    /**
     * JavaScriptファイルのパスを取得
     * 
     * 本番環境ではbundle版、開発環境ではソース版を返す
     * 
     * @param string $path アセットパス (例: '/admin/paint/js/paint.js')
     * @return string 実際に使用するパス
     */
    public static function js(string $path): string
    {
        $config = \App\Config\ConfigManager::getInstance()->getConfig();
        $useBundled = $config['app']['use_bundled_assets'] ?? false;
        
        if (!$useBundled) {
            return $path;
        }
        
        // .jsを.bundle.jsに変換
        return preg_replace('/\.js$/', '.bundle.js', $path);
    }
    
    /**
     * モジュールタイプを取得
     * 
     * bundle版の場合は通常のscript、開発版はmoduleを返す
     * 
     * @return string 'module' または ''
     */
    public static function scriptType(): string
    {
        $config = \App\Config\ConfigManager::getInstance()->getConfig();
        $useBundled = $config['app']['use_bundled_assets'] ?? false;
        
        return $useBundled ? '' : 'module';
    }
    
    /**
     * スクリプトタグを生成
     *
     * @param string $path JavaScriptファイルのパス
     * @param array $attributes 追加の属性
     * @param array $queryParams 追加のクエリパラメータ（例: ['v' => $version]）
     * @return string scriptタグ
     */
    public static function scriptTag(string $path, array $attributes = [], array $queryParams = []): string
    {
        $src = self::js($path);
        $type = self::scriptType();

        // クエリパラメータの構築
        $query = [];

        // 追加のクエリパラメータを優先
        if (!empty($queryParams)) {
            $query = $queryParams;
        }

        /*
        // 'v' パラメータが指定されていない場合のみ、ファイル更新時刻を使用
        if (!isset($query['v'])) {
            $fullPath = __DIR__ . '/../../public' . $src;
            if (file_exists($fullPath)) {
                $query['v'] = filemtime($fullPath);
            }
        }*/

        // クエリパラメータをURLに追加
        if (!empty($query)) {
            $src .= '?' . http_build_query($query);
        }

        $attrs = [];
        if ($type) {
            $attrs[] = 'type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '"';
        }
        $attrs[] = 'src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"';

        // If CSP is enabled and no explicit nonce was provided, attach nonce for script tags
        $config = \App\Config\ConfigManager::getInstance()->getConfig();
        $cspEnabled = $config['csp']['enabled'] ?? false;
        if ($cspEnabled && !isset($attributes['nonce'])) {
            try {
                $nonce = \App\Security\CspMiddleware::getInstance()->getNonce();
                $attributes['nonce'] = $nonce;
            } catch (Throwable $e) {
                // If CspMiddleware is not available for some reason, skip adding nonce
            }
        }

        foreach ($attributes as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $attrs[] = $key;
                }
            } else {
                $attrs[] = $key . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
            }
        }

        return '<script ' . implode(' ', $attrs) . '></script>';
    }

    /**
     * CSSファイルのパスを取得
     *
     * 本番環境ではbundle版、開発環境ではソース版を返す
     *
     * @param string $path アセットパス (例: '/res/css/main.css')
     * @return string 実際に使用するパス
     */
    public static function css(string $path): string
    {
        $config = \App\Config\ConfigManager::getInstance()->getConfig();
        $useBundled = $config['app']['use_bundled_assets'] ?? false;

        if (!$useBundled) {
            return $path;
        }

        // .cssを.bundle.cssに変換
        return preg_replace('/\.css$/', '.bundle.css', $path);
    }

    /**
     * linkタグを生成（CSS）
     *
     * @param string $path CSSファイルのパス
     * @param array $attributes 追加の属性
     * @param array $queryParams 追加のクエリパラメータ（例: ['v' => $version]）
     * @return string linkタグ
     */
    public static function linkTag(string $path, array $attributes = [], array $queryParams = []): string
    {
        $href = self::css($path);

        // クエリパラメータの構築
        $query = [];

        // 追加のクエリパラメータを優先
        if (!empty($queryParams)) {
            $query = $queryParams;
        }

        /*
        // 'v' パラメータが指定されていない場合のみ、ファイル更新時刻を使用
        if (!isset($query['v'])) {
            $fullPath = __DIR__ . '/../../public' . $href;
            if (file_exists($fullPath)) {
                $query['v'] = filemtime($fullPath);
            }
        }*/

        // クエリパラメータをURLに追加
        if (!empty($query)) {
            $href .= '?' . http_build_query($query);
        }

        $attrs = [];
        $attrs[] = 'rel="stylesheet"';
        $attrs[] = 'href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"';

        foreach ($attributes as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $attrs[] = $key;
                }
            } else {
                $attrs[] = $key . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
            }
        }

        return '<link ' . implode(' ', $attrs) . '>';
    }
}
