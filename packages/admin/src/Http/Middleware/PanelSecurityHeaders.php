<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Sunrice\Admin\Assets\AdminAssets;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\ContentSecurityPolicy;
use Sunrice\Admin\Support\Branding;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Security headers on every panel response: no framing by other sites
 * (clickjacking), no referrer sent elsewhere, no MIME sniffing, and a
 * strict Content-Security-Policy (sunrice-admin.security.csp, on by
 * default): scripts and stylesheets from the panel's origin or with the
 * request's nonce (Vite::useCspNonce(), which the layout's inline scripts
 * and styles and Vite's tags carry), images from the panel, data:, blob:
 * and the media disk, no plugins, no framing. A policy string of the
 * app's own (sunrice-admin.security.headers.csp, SUNRICE_CSP) is sent as
 * is instead, and a policy the response already has is kept.
 *
 * @internal
 */
final class PanelSecurityHeaders
{
    public function __construct(private Panel $panel) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $custom = config('sunrice-admin.security.headers.csp');
        $custom = is_string($custom) ? trim($custom) : '';
        // A fresh nonce for every request (also under Octane), before the page is rendered.
        $nonce = $custom === '' && self::enabled() ? Vite::useCspNonce() : null;

        $response = $next($request);
        $headers = $response->headers;
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'same-origin');
        $headers->set('X-Content-Type-Options', 'nosniff');

        if ($headers->has(ContentSecurityPolicy::HEADER) || $headers->has(ContentSecurityPolicy::REPORT_ONLY_HEADER)) {
            return $response;
        }
        if ($custom !== '') {
            $headers->set(ContentSecurityPolicy::HEADER, $custom);
        } elseif ($nonce !== null && self::wantsPolicy($response)) {
            $headers->set(self::reportOnly() ? ContentSecurityPolicy::REPORT_ONLY_HEADER : ContentSecurityPolicy::HEADER, $this->policy($request, $nonce)->toString());
        }

        return $response;
    }

    /** Whether the panel sends its policy: `security.csp.enabled` (true when the app's config file has no such key). */
    public static function enabled(): bool
    {
        return filter_var(config('sunrice-admin.security.csp.enabled', true), FILTER_VALIDATE_BOOL);
    }

    private static function reportOnly(): bool
    {
        return filter_var(config('sunrice-admin.security.csp.report_only', false), FILTER_VALIDATE_BOOL);
    }

    /**
     * Pages only (HTML), where a policy means something: not the JSON of
     * Inertia's visits, files or downloads. And not the framework's error
     * page with the stack trace (a 500 with APP_DEBUG), whose inline
     * scripts carry no nonce.
     */
    private static function wantsPolicy(Response $response): bool
    {
        $type = (string) $response->headers->get('Content-Type', 'text/html');
        if (! str_starts_with(strtolower($type), 'text/html')) {
            return false;
        }

        return ! ($response->getStatusCode() >= 500 && config('app.debug') === true);
    }

    /** The panel's policy for this request. */
    public function policy(Request $request, string $nonce): ContentSecurityPolicy
    {
        $policy = ContentSecurityPolicy::make()
            ->add('default-src', "'self'")
            ->add('script-src', "'self'")
            ->add('style-src', "'self'")
            // React and Radix set style="" attributes (positions, sizes, CSS variables): allowed, <style> elements need the nonce.
            ->add('style-src-attr', "'unsafe-inline'")
            ->add('img-src', ["'self'", 'data:', 'blob:'])
            ->add('font-src', ["'self'", 'data:'])
            ->add('media-src', ["'self'", 'blob:'])
            ->add('connect-src', "'self'")
            ->add('frame-src', "'self'")
            ->add('worker-src', ["'self'", 'blob:'])
            ->add('object-src', "'none'")
            ->add('base-uri', "'self'")
            ->add('form-action', "'self'")
            ->add('frame-ancestors', "'none'")
            ->nonce($nonce);

        $self = ContentSecurityPolicy::origin($request->getSchemeAndHttpHost());
        // The app's own origin (APP_URL) and its assets' (ASSET_URL, a CDN), when the panel is opened at another.
        foreach ([config('app.url'), config('app.asset_url')] as $url) {
            if (($origin = ContentSecurityPolicy::origin(is_string($url) ? $url : null)) !== null && $origin !== $self) {
                $policy->add('script-src', $origin)->add('style-src', $origin)->add('img-src', $origin)->add('font-src', $origin)->add('media-src', $origin);
            }
        }
        // Plugin bundles served from elsewhere.
        foreach ($this->panel->getBundles() as $bundle) {
            $policy->add('script-src', ContentSecurityPolicy::origin($bundle->getModule()));
            foreach ($bundle->getStyles() as $style) {
                $policy->add('style-src', ContentSecurityPolicy::origin($style));
            }
        }
        // The media disk (S3, a CDN): its images and videos.
        if (($media = ContentSecurityPolicy::mediaOrigin()) !== null && $media !== $self) {
            $policy->add('img-src', $media)->add('media-src', $media);
        }
        // A web font from elsewhere (Settings → Branding, sunrice-admin.font.stylesheet).
        try {
            $font = Branding::make()->fontOrigin();
        } catch (Throwable) {
            $font = ContentSecurityPolicy::origin(AdminAssets::fontStylesheet());
        }
        if ($font !== null) {
            $policy->add('style-src', $font)->add('font-src', $font);
        }
        // The Vite dev server while developing the panel (its hot file): its scripts, styles and websocket.
        if (($dev = ContentSecurityPolicy::origin(AdminAssets::hotUrl())) !== null) {
            $socket = (string) preg_replace('/^http/', 'ws', $dev);
            $policy->add('script-src', $dev)->add('style-src', $dev)->add('img-src', $dev)->add('font-src', $dev)
                ->add('connect-src', [$dev, $socket]);
        }

        $policy->addMany(config('sunrice-admin.security.csp.sources', []));
        $report = config('sunrice-admin.security.csp.report_uri');
        if (is_string($report) && trim($report) !== '') {
            $policy->add('report-uri', $report);
        }

        return $policy;
    }
}
