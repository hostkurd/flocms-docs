# Uploads

`hostkurd/flocms-uploader` stores uploaded files safely: it checks each file's real type, gives it a random name, and keeps it inside the folder you choose. It also resizes images, accepts videos with a poster, and can receive large files in pieces. New FloCMS projects already require it.

## Setting it up

Copy the package's example configuration into your project:

```bash
cp vendor/hostkurd/flocms-uploader/config/upload.php config/upload.php
```

Then load it once, at the end of `config/config.php`:

```php
use FloCMS\Uploader\Uploader;

if (is_file(ROOT . '/config/upload.php')) {
    Uploader::configure(require ROOT . '/config/upload.php');
}
```

`config/config.php` is loaded on every page request, API request and `php flo` command that boots the application, so the uploader is configured everywhere.

## Disks

A disk is a place to store files. The example configuration has three:

| Disk | Stores in | For |
|---|---|---|
| `public` | `public/uploads`, served at `/uploads` | Images, avatars, downloads anyone may see |
| `private` | `storage/uploads`, not reachable from the web | Invoices, reports, personal documents |
| `s3` | An Amazon S3 bucket | Large or shared storage |

`default_disk` picks the disk when you don't name one. For S3, also run `composer require aws/aws-sdk-php` and set `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET` and, optionally, `AWS_URL` as environment variables.

For reading/deleting stored files, authorized private downloads and S3 limitations, see [storage disks](/docs/{{version}}/storage). Setting `visibility('private')` on a public local disk does not move the file outside its public root; choose the private disk for private files.

## Uploading a file

```php
use FloCMS\Uploader\Uploader;
use FloCMS\Uploader\Exceptions\UploadException;

public function admin_attach(): void
{
    $file = $this->request->file('attachment');

    if ($file === null) {
        return;
    }

    try {
        $result = Uploader::disk('public')
            ->directory('documents')
            ->useDatePath()
            ->allowExtensions(['pdf', 'docx', 'jpg', 'jpeg', 'png'])
            ->maxBytes(10 * 1024 * 1024)
            ->upload($file);
    } catch (UploadException $e) {
        $this->data['error'] = $e->getMessage();
        return;
    }

    $this->model()->saveAttachment($result->path(), $result->url());
}
```

The form needs `enctype="multipart/form-data"` and `@csrf`. With `useDatePath()`, files go into year and month folders, such as `documents/2026/10/`.

`UploadResult` tells you what was stored: `path()`, `url()`, `filename()`, `originalName()`, `extension()`, `mime()`, `size()`, `disk()`, and `toArray()` for all of it. Save the path in your database, not the absolute file system path.

### Result reference

| Methods | Returns |
|---|---|
| `disk()`, `directory()`, `path()` | Disk name and relative storage location |
| `url()`, `absolutePath()` | Configured/object URL and local absolute path; either can be `null` |
| `filename()`, `originalName()`, `extension()`, `mime()`, `size()` | Stored name, original name, validated type and byte size |
| `visibility()` | Stored visibility metadata |
| `width()`, `height()` | Image dimensions when available |
| `versions()`, `version($name)`, `versionUrl($name)` | Image version metadata, one version or its URL |
| `poster()` | A video's poster as `UploadResult`, or `null` |
| `toArray()` | All result metadata, including server-only paths |

Do not expose `toArray()` wholesale to public clients. The API's upload trait removes absolute server paths and disk names; use a deliberate response shape elsewhere. A URL is not an authorization grant for private files.

### Options

| Method | Does |
|---|---|
| `directory('avatars')` or `to('avatars')` | The folder inside the disk |
| `useDatePath()` | Adds `year/month` folders |
| `allowExtensions([...])`, `allowMimeTypes([...])` | What to accept. Extension and detected type are checked separately. |
| `maxBytes($bytes)` | The size limit |
| `preserveOriginalName()` | Keeps the visitor's file name instead of a random one |
| `filename('report')` | A fixed name; it can't change the checked extension |
| `visibility('private')` | Overrides the disk's visibility |
| `imageDimensions(['min_width' => 400, 'max_width' => 4000])` | Image size limits: `min_width`, `max_width`, `min_height`, `max_height` |

### Several files

```php
$results = Uploader::disk('public')->directory('gallery')->uploadMany($_FILES['images']);
```

For `<input type="file" name="images[]" multiple>`. It returns a list of `UploadResult`.

## Images

`Uploader::image()` accepts JPEG, PNG, GIF and WebP, checks that the file really is an image, and creates resized versions:

```php
$result = Uploader::image()
    ->onDisk('public')
    ->directory('posts')
    ->useDatePath()
    ->maxBytes(5 * 1024 * 1024)
    ->versions([
        'large'  => ['resize' => [1600, 1600]],
        'medium' => ['resize' => [800, 800]],
        'thumb'  => ['fit' => [300, 300], 'format' => 'webp', 'quality' => 82],
    ])
    ->upload($file);

$thumbUrl = $result->versionUrl('thumb');
```

- `resize` shrinks the image to fit inside the box and keeps its proportions; it never enlarges. `fit` fills the box exactly, cropping from the center.
- The original is kept in an `original` folder; `keepOriginal(false)` drops it.
- Files are stored like `posts/2026/10/original/abc123.jpg`, `posts/2026/10/thumb/abc123.webp`.
- `quality(85)`, `optimize()` and `driver('gd')` or `driver('imagick')` adjust processing. Imagick is used when installed, otherwise GD.

When no versions are supplied, the defaults are `large` resized within 1600 × 1600, `medium` within 800 × 800, and a 300 × 300 `thumb`. Each version can set `format`, `quality` and `optimize`, overriding the uploader-wide settings. Output formats are JPG/JPEG, PNG, GIF and WebP, subject to the installed driver's support. `quality()` accepts 0–100, and optimization is enabled by default.

When the original is kept, the main result path refers to it; with `keepOriginal(false)`, it refers to the first generated version. Version metadata includes its own path, URL, dimensions, MIME type and size. Use [storage operations](/docs/{{version}}/storage#images-and-related-files) to clean up related files.

> [!WARNING]
> Don't allow SVG uploads. SVG files can contain scripts, and the uploader doesn't clean them.

## Videos

```php
$result = Uploader::video()
    ->directory('videos')
    ->withPoster($this->request->file('poster'))   // optional: jpg, png or webp
    ->upload($this->request->file('video'));

$result->url();
$result->poster()?->url();   // stored as "<video name>-poster.<ext>"
```

- MP4 and WebM are accepted, and the type detected from the content must match the extension.
- MOV files (from iPhones) are off by default. Turn them on with `'video' => ['allow_mov' => true]` in the configuration or `->allowMov()`.
- Limits: `video.max_bytes` (500 MB by default) and `video.poster_max_bytes` (5 MB).
- The poster is checked before anything is stored. If storing it fails, the video is removed again.

The uploader stores the validated video and a poster you supply. It does not transcode video, create streaming variants or extract a poster frame automatically.

## Large files in pieces

Shared hosting often limits uploads to a few megabytes (`upload_max_filesize`). Chunked uploads send a large file in pieces below that limit, and can continue after a dropped connection:

```php
$chunked = Uploader::video()->directory('videos')->chunked();

$owner = (string) $userId;                                   // derived from the signed-in user
$session = $chunked->start($filename, $totalBytes, $owner);    // checks the name and size first
$status  = $chunked->append($session['upload_id'], $offset, $_FILES['chunk'], $owner);
$status  = $chunked->status($uploadId, $owner);                // to resume: continue at received_bytes
$result  = $chunked->complete($uploadId, $owner);              // an UploadResult
```

- Any uploader can be chunked. The finished file goes through that uploader's normal checks and storage.
- `$offset` must equal the bytes already received. Otherwise a `ChunkOffsetException` tells the client where to continue.
- A chunk can be a `$_FILES` entry or a stream such as `fopen('php://input', 'rb')`.
- Pass the signed-in user's ID as the owner, so only that user can continue or complete the upload.
- Pieces are kept in `chunks.directory`, `storage/uploads/.chunks` in the example configuration. Keep it outside `public/`.
- Keep `chunks.max_chunk_bytes` below PHP's `upload_max_filesize` and `post_max_size`.
- Uploads without activity for `chunks.expire` seconds (24 hours) are deleted, and the [scheduler](/docs/{{version}}/scheduler) cleans the folder hourly.

The package includes a complete example: `vendor/hostkurd/flocms-uploader/examples/chunked-upload.php` for the server and `chunked-upload.js` for the browser, with retries and resuming.

### Chunk configuration and cleanup

| `chunks` key | Default | Meaning |
|---|---|---|
| `directory` | `storage/uploads/.chunks` in the example | Private temporary data and session metadata |
| `chunk_size` | 5 × 1024 × 1024 bytes | Suggested size sent to the client |
| `max_chunk_bytes` | 10 × 1024 × 1024 bytes | Maximum accepted piece |
| `max_bytes` | 2 × 1024 × 1024 × 1024 bytes | Total limit when the target uploader has no limit of its own |
| `expire` | 86400 seconds | Idle time used by cleanup |

A video's own 500 MB limit still applies; the 2 GB fallback does not override it. Fluent `chunkSize($bytes)`, `maxChunkBytes($bytes)` and `expireAfter($seconds)` override the chunk uploader's settings.

Start/status/append return `upload_id`, `filename`, `total_bytes`, `received_bytes`, `complete`, `chunk_size` and `max_chunk_bytes`. `complete: true` means all bytes arrived; still call `complete()` to validate and store the assembled file. Resume from the server's `received_bytes` rather than the client's last attempted offset.

```php
$chunked->abort($uploadId, $owner);          // cancel and remove temporary files
$removed = $chunked->purgeExpired();         // number of stale uploads removed
```

Cleanup measures the last activity from the temporary files' modification times. It runs when starting an upload and when called by the scheduler; expiry is not checked as a timestamp on every status/append call. Keep the cleanup task enabled. For an incorrect offset, `ChunkOffsetException::expectedOffset()` tells you where to resume.

The owner is an optional string. Derive it server-side from the authenticated identity and pass the same value to every operation; don't trust an owner supplied in the request body. Without an owner, possession of the upload ID is the access mechanism.

### Through the API

API controllers get ready-made endpoints with the `HandlesUploads` trait:

```php
use FloCMS\Api\Controller;
use FloCMS\Api\Uploads\HandlesUploads;
use FloCMS\Core\Http\Request;
use FloCMS\Core\Http\Response;
use FloCMS\Uploader\Uploader;

final class MediaController extends Controller
{
    use HandlesUploads;

    public function store(Request $request): Response
    {
        return $this->storeUpload($request, Uploader::image()->to('posts'), 'image');   // 201
    }
}
```

These protected helpers implement the protocol; register your controller's public actions as routes and pass the same configured `ChunkedUploader` and authenticated owner to each:

| Helper | Request/response |
|---|---|
| `storeUpload($request, $uploader, $field = 'file')` | Multipart field; 201 with safe upload metadata |
| `startChunkedUpload($request, $chunks, $owner = null)` | `filename` and positive `total_bytes`; 201 with session metadata |
| `appendChunk($request, $chunks, $uploadId, $owner = null)` | `Upload-Offset` header (or `offset` field), multipart `chunk` or raw body; 200 with status |
| `chunkedUploadStatus($chunks, $uploadId, $owner = null)` | 200 with the server's current status |
| `completeChunkedUpload($chunks, $uploadId, $owner = null, $directory = null)` | 201 with the finished upload |
| `abortChunkedUpload($chunks, $uploadId, $owner = null)` | 204 with no body |

Validation failures become 422; an incorrect offset is 409 with `Upload-Offset` and `errors.expected_offset`; an unknown upload or mismatched owner is 404. Unexpected storage/processing failures follow normal exception handling. The trait does not add routes, authentication or rate limits automatically.

For raw chunks, permit an appropriate non-JSON content type. `RequireJsonMiddleware` rejects them; allowing multipart there still does not allow raw bodies. For a cross-origin client, include `Upload-Offset` in the configured allowed and exposed CORS headers. See [CORS](/docs/{{version}}/api-cors).

### Request-size limits

The global API body limit is 2 MB by default, smaller than the uploader's 5 MB suggested chunk size. Either configure smaller chunks or raise the API limit as shown in [middleware](/docs/{{version}}/api-middleware#upload-body-limits).

For multipart uploads, coordinate `upload_max_filesize`, `post_max_size`, web-server/proxy limits, the API body limit and `chunks.max_chunk_bytes`. Leave room for multipart fields and boundaries. Raw chunks also need sufficient server/API body limits. A total chunked-file limit is separate from a per-request limit.

## Security

The uploader:

- checks PHP's upload error before touching the file
- detects the real type with `finfo`, and checks it and the extension separately
- measures the size from the stored temporary file, not the size the browser claims
- uses random file names by default
- checks images with `getimagesize()` before processing them
- refuses `..`, absolute paths, unsafe folder names and symlinks that lead outside the disk
- blocks script extensions such as `php`, `phtml`, `phar`, `cgi`, `sh` and `htaccess`, even when you allow everything else

Change `blocked_extensions` only for private storage, and only when you're sure.

## Errors

Every problem throws an `UploadException` or one of its subclasses:

| Exception | When |
|---|---|
| `ValidationException` | The file is too large, of a type you don't allow, or not a real image |
| `ChunkOffsetException` | A piece arrived at the wrong position |
| `StorageException` | The file couldn't be stored |
| `ImageProcessingException` | An image couldn't be resized, or no image library is installed |

They're in the `FloCMS\Uploader\Exceptions` namespace. Validation messages are safe to show to the visitor.
