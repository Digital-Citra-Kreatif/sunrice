<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Sunrice\Admin\Support\Branding;

/**
 * The browser icon when there is no favicon or square logo: the branded
 * name's first letter on the brand colour, as SVG. Public, because the
 * sign-in page uses it, and it shows nothing beyond that letter and
 * colour; no session, so browsers and proxies may cache it.
 *
 * @internal
 */
final class BrandingIconController
{
    public function __invoke(Request $request): Response
    {
        $branding = Branding::make();
        $svg = $branding->lettermarkSvg();
        // A versioned URL (the layout's) is the current icon for good; any other for a day.
        $current = $request->query('v') === $branding->lettermarkVersion();

        $response = new Response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => $current ? 'public, max-age=31536000, immutable' : 'public, max-age=86400',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setEtag(sha1($svg));
        $response->isNotModified($request);

        return $response;
    }
}
