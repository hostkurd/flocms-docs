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

## Large files in pieces

Shared hosting often limits uploads to a few megabytes (`upload_max_filesize`). Chunked uploads send a large file in pieces below that limit, and can continue after a dropped connection:

```php
$chunked = Uploader::video()->directory('videos')->chunked();

$session = $chunked->start($filename, $totalBytes, $userId);    // checks the name and size first
$status  = $chunked->append($session['upload_id'], $offset, $_FILES['chunk'], $userId);
$status  = $chunked->status($uploadId, $userId);                // to resume: continue at received_bytes
$result  = $chunked->complete($uploadId, $userId);              // an UploadResult
```

- Any uploader can be chunked. The finished file goes through that uploader's normal checks and storage.
- `$offset` must equal the bytes already received. Otherwise a `ChunkOffsetException` tells the client where to continue.
- A chunk can be a `$_FILES` entry or a stream such as `fopen('php://input', 'rb')`.
- Pass the signed-in user's ID as the owner, so only that user can continue or complete the upload.
- Pieces are kept in `chunks.directory`, `storage/uploads/.chunks` in the example configuration. Keep it outside `public/`.
- Keep `chunks.max_chunk_bytes` below PHP's `upload_max_filesize` and `post_max_size`.
- Uploads without activity for `chunks.expire` seconds (24 hours) are deleted, and the [scheduler](/docs/{{version}}/scheduler) cleans the folder hourly.

The package includes a complete example: `vendor/hostkurd/flocms-uploader/examples/chunked-upload.php` for the server and `chunked-upload.js` for the browser, with retries and resuming.

### Through the API

API controllers get ready-made endpoints with the `HandlesUploads` trait:

```php
use FloCMS\Api\Controller;
use FloCMS\Api\Uploads\HandlesUploads;
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

`startChunkedUpload()`, `appendChunk()`, `chunkedUploadStatus()`, `completeChunkedUpload()` and `abortChunkedUpload()` cover the chunked protocol. Upload errors become 422 responses, a piece at the wrong offset is a 409 with the expected `Upload-Offset`, and server paths are never returned.

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
