<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Psr\Http\Message\ResponseInterface;
use Sunrice\Admin\Actions\Media\UploadMedia as Upload;
use Sunrice\Admin\Ai\Tool;
use Sunrice\Admin\Media\MediaConfig;
use Sunrice\Admin\Media\MediaFolder;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Support\PublicAddress;
use Throwable;

#[Description('Add a file to the media library from a public web address ("url") or its content in base64 ("content_base64" with a "filename" that has the extension), optionally into a folder, with a title and alt text (describe images). The same checks as an upload in the panel: size, allowed types, no scripts. Returns the file with its id for media fields.')]
final class UploadMedia extends Tool
{
    protected string $name = 'upload_media';

    public function handle(Request $request): Response
    {
        if (! $this->can('sunrice.media.upload')) {
            return $this->denied('upload media');
        }
        /** @var array{url?: string|null, content_base64?: string|null, filename?: string|null, folder_id?: int|null, title?: string|null, alt?: string|null} $args */
        $args = $request->validate([
            'url' => ['nullable', 'required_without:content_base64', 'prohibits:content_base64', 'string', 'max:2048', 'url:http,https'],
            'content_base64' => ['nullable', 'string'],
            'filename' => ['nullable', 'required_with:content_base64', 'string', 'max:200'],
            'folder_id' => ['nullable', 'integer', Rule::exists(MediaFolder::class, 'id')],
            'title' => ['nullable', 'string', 'max:250'],
            'alt' => ['nullable', 'string', 'max:250'],
        ]);
        $limit = MediaConfig::maxKilobytes() * 1024;

        if (isset($args['url'])) {
            $contents = self::download($args['url'], $limit);
            if ($contents instanceof Response) {
                return $contents;
            }
            $filename = $args['filename'] ?? basename((string) parse_url($args['url'], PHP_URL_PATH));
        } else {
            $encoded = $args['content_base64'] ?? '';
            // Base64 is a third longer than the file.
            if (strlen($encoded) > (int) ceil($limit * 4 / 3) + 4) {
                return Response::error('The file is larger than the upload limit.');
            }
            $contents = base64_decode($encoded, true);
            if ($contents === false) {
                return Response::error('"content_base64" isn\'t valid base64.');
            }
            $filename = $args['filename'] ?? '';
        }
        $filename = basename(str_replace('\\', '/', $filename));
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return Response::error('Give a "filename" with its extension, like "photo.jpg".');
        }

        $path = tempnam(sys_get_temp_dir(), 'sunrice-ai-');
        if ($path === false) {
            return Response::error('The file could not be stored for checking.');
        }
        try {
            file_put_contents($path, $contents);
            $user = auth(app(Panel::class)->getGuard())->id();
            $media = app(Upload::class)->handle(new UploadedFile($path, $filename, null, null, true), [
                'folder_id' => $args['folder_id'] ?? null,
                'title' => isset($args['title']) && trim($args['title']) !== '' ? trim($args['title']) : null,
                'alt' => isset($args['alt']) && trim($args['alt']) !== '' ? trim($args['alt']) : null,
                'uploaded_by' => is_scalar($user) ? (string) $user : null,
            ]);
        } finally {
            @unlink($path);
        }

        return $this->json(['uploaded' => true, ...ManageMedia::describe($media)]);
    }

    /** The file at a public address (not this server or its network), at most $limit bytes. */
    private static function download(string $url, int $limit): string|Response
    {
        $host = parse_url($url, PHP_URL_HOST);
        $ip = is_string($host) ? PublicAddress::of($host) : null;
        if ($ip === null) {
            return Response::error('Only files on public web addresses can be fetched.');
        }
        $port = parse_url($url, PHP_URL_PORT) ?? (str_starts_with(strtolower($url), 'https') ? 443 : 80);

        try {
            // No redirects (they could lead inside), and the address checked is the one connected to.
            $response = Http::timeout(30)->withOptions([
                'allow_redirects' => false,
                'curl' => $ip === trim((string) $host, '[]') || ! defined('CURLOPT_RESOLVE') ? [] : [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]],
                'on_headers' => function (ResponseInterface $headers) use ($limit): void {
                    if ((int) $headers->getHeaderLine('Content-Length') > $limit) {
                        throw new \RuntimeException('too large');
                    }
                },
            ])->get($url);
        } catch (Throwable) {
            return Response::error('The file could not be downloaded, or it is larger than the upload limit.');
        }
        if (! $response->successful()) {
            return Response::error('The file could not be downloaded (HTTP '.$response->status().').');
        }
        $body = $response->body();
        if (strlen($body) > $limit) {
            return Response::error('The file is larger than the upload limit.');
        }

        return $body;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->description('A public address of the file.'),
            'content_base64' => $schema->string()->description('Or the file\'s content, base64.'),
            'filename' => $schema->string()->description('The file\'s name with its extension (needed with content_base64).'),
            'folder_id' => $schema->integer()->description('A folder (manage_media "folders" lists them); the top level when left out.'),
            'title' => $schema->string(),
            'alt' => $schema->string()->description('Alternative text: what the image shows.'),
        ];
    }
}
