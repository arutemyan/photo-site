<?php
declare(strict_types=1);

namespace App\Security;

/**
 * CSP Middleware
 * 
 * Manages Content-Security-Policy headers with nonce support
 * Part of the Phase 1 CSP migration to remove unsafe-inline and unsafe-eval
 */
class CspMiddleware
{
    private string $nonce;
    private static ?self $instance = null;
    
    private function __construct()
    {
        $this->nonce = base64_encode(random_bytes(16));
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get the current nonce value
     */
    public function getNonce(): string
    {
        return $this->nonce;
    }
    
    /**
     * Send CSP header with nonce support
     * 
     * @param bool $isAdmin Whether this is an admin page
     * @param bool $reportOnly Whether to use report-only mode
     * @return void
     */
    public function sendCspHeader(bool $isAdmin = false, bool $reportOnly = false): void
    {
        $nonce = $this->nonce;
        
        if ($isAdmin) {
            // Admin pages - nonce-based CSP (no unsafe-inline, no unsafe-eval)
            $csp = "default-src 'self'; " .
                   "script-src 'self' 'nonce-{$nonce}' cdn.jsdelivr.net code.jquery.com; " .
                   "style-src 'self' 'nonce-{$nonce}' cdn.jsdelivr.net fonts.googleapis.com; " .
                   "img-src 'self' data: blob: https:; " .
                   "font-src 'self' fonts.gstatic.com cdn.jsdelivr.net; " .
                   "connect-src 'self'";
        } else {
            // Public pages - nonce-based CSP (no unsafe-inline)
            $csp = "default-src 'self'; " .
                   "script-src 'self' 'nonce-{$nonce}' cdn.jsdelivr.net code.jquery.com; " .
                   "style-src 'self' 'nonce-{$nonce}' cdn.jsdelivr.net fonts.googleapis.com; " .
                   "img-src 'self' data: blob:; " .
                   "font-src 'self' fonts.gstatic.com; " .
                   "connect-src 'self'";
        }
        
        $headerName = $reportOnly ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
        header($headerName . ': ' . $csp);
    }
    
    /**
     * Generate nonce attribute for inline scripts/styles
     * 
     * @return string The nonce attribute (e.g., 'nonce="abc123..."')
     */
    public function getNonceAttribute(): string
    {
        return 'nonce="' . htmlspecialchars($this->nonce, ENT_QUOTES, 'UTF-8') . '"';
    }
}
