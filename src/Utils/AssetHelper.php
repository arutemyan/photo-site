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
     * @return string scriptタグ
     */
    public static function scriptTag(string $path, array $attributes = []): string
    {
        $src = self::js($path);
        $type = self::scriptType();
        
        $attrs = [];
        if ($type) {
            $attrs[] = 'type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '"';
        }
        $attrs[] = 'src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"';
        
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
     * @return string linkタグ
     */
    public static function linkTag(string $path, array $attributes = []): string
    {
        $href = self::css($path);

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
