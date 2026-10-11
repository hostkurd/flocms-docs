# Storage disks

The uploader validates and processes files before saving them. Its storage layer manages files already identified by a disk and relative path. See [uploads](/docs/{{version}}/uploader) for accepting visitor uploads.

## Opening a disk

Use the same configuration that you pass to `Uploader::configure()`:

```php
use FloCMS\Uploader\Storage\StorageManager;

$config = require ROOT . '/config/upload.php';
$storage = new StorageManager($config);
$disk = $storage->disk('public');
```

`disk()` without a name uses `default_disk`, or `public` if omitted. `diskConfig($name = null)` returns that disk's configuration. An unknown disk or unsupported driver throws `StorageException`. The bundled drivers are `local` and `s3`.

## Operations

Every disk implements `FloCMS\Uploader\Storage\StorageInterface`:

| Method | Behavior |
|---|---|
| `put($path, $sourceFile, $options = [])` | Store a local source file; return `path`, `url`, `absolute_path` and `visibility` metadata |
| `exists($path)` | Check for a stored file/object |
| `delete($path)` | Delete it; return a boolean |
| `url($path)` | Obtain a configured/object URL, or `null` for a local disk without a URL |
| `path($path)` | Obtain a local filesystem path, or `null` when unavailable; always `null` on S3 |

```php
$path = 'documents/2026/10/report.pdf';
if ($disk->exists($path)) {
    $url = $disk->url($path);
}
$removed = $disk->delete($path);
```

For local storage, deleting a missing file returns `false`. S3 returns `true` after the delete request succeeds. Driver/SDK failures may throw; a boolean is not a universal substitute for handling errors.

`put()` is a low-level operation: it does not run the uploader's type, extension or size validation. Use `Uploader` for untrusted uploads. For trusted application-generated files:

```php
$stored = $storage->disk('private')->put('reports/monthly.csv', $generatedFile, [
    'visibility' => 'private',
    'content_type' => 'text/csv',
]);
```

`$generatedFile` is an existing local source file. Paths are relative to the disk, without `..` or an absolute prefix. Local storage creates directories and checks resolved paths against its root; its `move` option is used by assembled chunk uploads.

## Public and private local files

The example public disk stores files in `public/uploads` and exposes `/uploads` URLs. The private disk stores them in `storage/uploads` and has no public URL.

`visibility('private')` does not change a local disk's root or web-server access rules. Using it on the public disk still writes under `public/uploads`, and that disk can still supply its configured URL. Choose the private disk for private files, keep its root outside the document root, and authorize every download.

Save `disk` and relative `path` in your database. Rebuild paths/URLs through the storage manager when needed. Avoid persisting deployment-specific absolute paths or returning them in public JSON.

### Authorized downloads

The following API action assumes an application-owned `attachments` table with `id`, `user_id`, `disk` and `path` columns. Register it with `auth` middleware. It looks up an attachment belonging to the authenticated user before accessing storage:

```php
use FloCMS\Core\App;
use FloCMS\Core\Http\Request;
use FloCMS\Core\Http\Response;
use FloCMS\Api\Security\Identity;
use FloCMS\Uploader\Storage\StorageManager;

public function show(Request $request, int $id): Response
{
    $identity = $request->attribute('identity');
    if (!$identity instanceof Identity) {
        $this->fail('Authentication required.', 401);
    }

    $db = App::db() ?? $this->fail('The database is not configured.', 503);
    $attachment = $db->table('attachments')
        ->where('id', '=', $id)
        ->where('user_id', '=', $identity->id)
        ->first() ?? $this->fail('Document not found.', 404);

    if ($attachment['disk'] !== 'private') {
        $this->fail('Document not found.', 404);
    }

    $disk = (new StorageManager(require ROOT . '/config/upload.php'))->disk('private');
    $path = $disk->path((string) $attachment['path']);
    if ($path === null || !is_file($path) || !is_readable($path)) {
        $this->fail('Document not found.', 404);
    }

    $body = file_get_contents($path);
    if ($body === false) {
        $this->fail('Unable to read the document.', 500);
    }

    return Response::make($body)
        ->header('Content-Type', 'application/octet-stream')
        ->header('Content-Disposition', 'attachment; filename="document"')
        ->header('Cache-Control', 'private, no-store');
}
```

Place the action in a controller extending `FloCMS\Api\Controller`; adapt the ownership rule to your application. The URL supplies an attachment ID, not a filesystem path. This example buffers the file in memory. For large files, implement streaming or authorized web-server delivery in your application; `Response` stores a string body.

## Images and related files

Image versions and video posters are separate stored files. Deleting the main path does not delete them automatically:

```php
foreach ($result->versions() as $version) {
    if (isset($version['path'])) {
        $disk->delete($version['path']);
    }
}
if ($result->path() !== null && $disk->exists($result->path())) {
    $disk->delete($result->path());
}
```

Use the disk matching each result, including a video's `poster()` result. Record the paths you need when uploading so a later cleanup can remove all related files and update database records consistently.

## S3 configuration

Install `aws/aws-sdk-php`, then configure these disk keys:

| Key | Meaning |
|---|---|
| `driver` | `s3` |
| `key`, `secret` | Required AWS credentials |
| `region`, `bucket` | Required region and bucket |
| `version` | SDK service version, `latest` by default |
| `prefix` | Optional key prefix inside the bucket |
| `url` | Optional public/CDN base URL |
| `visibility` | Upload ACL: `public-read` for public, `private` otherwise |

Stored paths omit the configured prefix; the driver adds it to object operations and URLs. An S3 URL is not a signed URL and does not grant access to a private object. For private downloads, authorize the request and use your application's AWS SDK client to fetch the object or create a short-lived signed request.

The bundled driver supplies an object ACL on upload. If your bucket rejects ACL parameters, adapt storage through the SDK in your application. The driver does not expose custom endpoint/path-style options or signed-URL methods.

## Using storage outside the skeleton

Pass absolute roots explicitly; you do not need the example configuration's `ROOT` constant:

```php
use FloCMS\Uploader\Uploader;
use FloCMS\Uploader\Storage\StorageManager;

$config = [
    'default_disk' => 'private',
    'disks' => [
        'private' => ['driver' => 'local', 'root' => '/srv/my-site/uploads', 'visibility' => 'private'],
    ],
];

$storage = new StorageManager($config);
$uploader = Uploader::disk('private', $config);
```

For another backend, implement `StorageInterface` and use it in an application service. `StorageManager` has no custom-driver registration API; adding an arbitrary driver name to its configuration is not sufficient.
