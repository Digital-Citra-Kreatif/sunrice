<?php

declare(strict_types=1);

namespace Sunrice\Admin\Security;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * A Content-Security-Policy built directive by directive, for the panel's
 * header (PanelSecurityHeaders) and a plugin's own (the CMS's site
 * policy). Sources are added as a list or a space-separated string;
 * duplicates are dropped, and so is anything that could break out of the
 * header (`;`, `,`, quotes in the wrong place, control characters).
 *
 *     ContentSecurityPolicy::make()
 *         ->add('default-src', "'self'")
 *         ->add('img-src', ["'self'", 'data:', ContentSecurityPolicy::origin($cdnUrl)])
 *         ->nonce($nonce)          // 'nonce-…' in script-src and style-src
 *         ->toString();            // "default-src 'self'; img-src 'self' data: https://cdn.example.com; …"
 *
 * @api
 */
final class ContentSecurityPolicy
{
    public const HEADER = 'Content-Security-Policy';

    public const REPORT_ONLY_HEADER = 'Content-Security-Policy-Report-Only';

    /** @var array<string, list<string>> */
    private array $directives = [];

    public static function make(): self
    {
        return new self;
    }

    /**
     * Sources for a directive ("img-src"), added after those it has; a
     * directive with no sources (`upgrade-insecure-requests`) is printed
     * by its name alone. Null and invalid entries are skipped.
     *
     * @param  string|iterable<mixed>|null  $sources
     */
    public function add(string $directive, string|iterable|null $sources = []): self
    {
        $directive = strtolower(trim($directive));
        if (preg_match('/^[a-z][a-z-]*$/', $directive) !== 1) {
            return $this;
        }
        $list = $this->directives[$directive] ?? [];
        foreach (is_string($sources) ? [$sources] : ($sources ?? []) as $entry) {
            foreach (is_string($entry) ? (preg_split('/\s+/', trim($entry)) ?: []) : [] as $source) {
                if ($source !== '' && self::validSource($source) && ! in_array($source, $list, true)) {
                    $list[] = $source;
                }
            }
        }
        $this->directives[$directive] = $list;

        return $this;
    }

    /**
     * Sources from config: `['img-src' => ['https://cdn.example.com'], 'connect-src' => 'https://api.example.com']`.
     * Anything that isn't a directive name with sources is ignored.
     */
    public function addMany(mixed $directives): self
    {
        foreach (is_array($directives) ? $directives : [] as $directive => $sources) {
            if (is_string($directive) && (is_string($sources) || is_array($sources) || $sources === null)) {
                $this->add($directive, $sources);
            }
        }

        return $this;
    }

    /** `'nonce-…'` in script-src and style-src (each created when missing). */
    public function nonce(string $nonce): self
    {
        if (preg_match('/^[A-Za-z0-9+\/=_-]+$/', $nonce) !== 1) {
            return $this;
        }

        return $this->add('script-src', "'nonce-{$nonce}'")->add('style-src', "'nonce-{$nonce}'");
    }

    public function has(string $directive): bool
    {
        return array_key_exists(strtolower($directive), $this->directives);
    }

    /** @return list<string> */
    public function sources(string $directive): array
    {
        return $this->directives[strtolower($directive)] ?? [];
    }

    /** @return array<string, list<string>> */
    public function toArray(): array
    {
        return $this->directives;
    }

    /** The header's value: "default-src 'self'; img-src 'self' data:". */
    public function toString(): string
    {
        $parts = [];
        foreach ($this->directives as $directive => $sources) {
            $parts[] = trim($directive.' '.implode(' ', $sources));
        }

        return implode('; ', $parts);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * The origin (scheme, host and port) of an absolute http(s) or ws(s)
     * URL, "https://cdn.example.com"; null for a path or anything else.
     */
    public static function origin(?string $url): ?string
    {
        $parts = is_string($url) && $url !== '' ? parse_url(trim($url)) : false;
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || ! in_array(strtolower($parts['scheme']), ['http', 'https', 'ws', 'wss'], true)) {
            return null;
        }
        $origin = strtolower($parts['scheme']).'://'.strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');

        return self::validSource($origin) ? $origin : null;
    }

    /**
     * The origin of the media library's files (the `sunrice-admin.media.disk`
     * disk's URL: S3, a CDN, or the app's own /storage), or null when it has
     * no absolute URL.
     */
    public static function mediaOrigin(): ?string
    {
        $disk = config('sunrice-admin.media.disk', 'public');
        if (! is_string($disk) || $disk === '') {
            return null;
        }
        $url = config("filesystems.disks.{$disk}.url");
        if (! is_string($url) || $url === '') {
            try {
                $url = Storage::disk($disk)->url('sunrice-csp');
            } catch (Throwable) {
                return null;
            }
        }

        return self::origin($url);
    }

    /** A keyword ('self'), a scheme (data:), a host source, a nonce or hash, or report-uri's path: never what could end the directive or the header. */
    private static function validSource(string $source): bool
    {
        if (preg_match("/^'[A-Za-z0-9+\\/=_-]+(?:-[A-Za-z0-9+\\/=_-]+)*'$/", $source) === 1) {
            return true;
        }

        return preg_match('/^[A-Za-z0-9*\/][A-Za-z0-9+.\-*:\/\[\]%@_~!$&()=?]*$/', $source) === 1;
    }
}
