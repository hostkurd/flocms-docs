<?php
declare(strict_types=1);

/**
 * Extract the complete files from the example guides and exercise them through
 * the real core dispatcher and renderer. No duplicated example implementations.
 *
 * Usage: php .github/scripts/check-examples.php /path/to/flocms-core
 */

$docs = dirname(__DIR__, 2);
$core = realpath(($argv[1] ?? '') . '/src');
if ($core === false || !is_file($core . '/TemplateEngine.php')) {
    fwrite(STDERR, "Supply the path to a Core 2.9.0 checkout.\n");
    exit(1);
}

$fixture = sys_get_temp_dir() . '/flo-docs-examples-' . bin2hex(random_bytes(8));
mkdir($fixture, 0700, true);
$checks = 0;

function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function writeFile(string $path, string $content): void
{
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0700, true);
    }
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException('Cannot write fixture: ' . $path);
    }
}

function command(array $arguments): string
{
    $process = proc_open($arguments, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start PHP.');
    }
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0 || $err !== '') {
        throw new RuntimeException("Example process failed:\n" . $out . $err);
    }
    return (string) $out;
}

function extractGuide(string $guide): void
{
    global $docs, $fixture;
    $markdown = (string) file_get_contents($docs . '/' . $guide . '.md');
    preg_match_all('/\*\*File: \x60([^\x60]+)\x60\*\*\s*\n\s*\x60{3}(php|html|css)\n(.*?)\n\x60{3}/s', $markdown, $matches, PREG_SET_ORDER);
    check($matches !== [], $guide . ': no complete files found');
    foreach ($matches as $match) {
        $relative = $match[1];
        check(!str_contains($relative, '..') && !str_starts_with($relative, '/'), 'Invalid example file path');
        $path = $fixture . '/' . $relative;
        writeFile($path, $match[3] . "\n");
        if ($match[2] === 'php') {
            command([PHP_BINARY, '-l', $path]);
        }
    }
}

function request(array $scenario): array
{
    global $fixture, $core;
    $result = command([PHP_BINARY, $fixture . '/request.php', $core, json_encode($scenario, JSON_THROW_ON_ERROR)]);
    return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
}

function removeFixture(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (array_diff(scandir($path), ['.', '..']) as $name) {
            removeFixture($path . '/' . $name);
        }
        rmdir($path);
    } else {
        unlink($path);
    }
}

try {
    writeFile($fixture . '/request.php', <<<'PHP'
<?php
declare(strict_types=1);

$core = $argv[1];
$scenario = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
$prefix = $scenario['prefix'] ?? '';
define('ROOT', __DIR__);
define('DS', DIRECTORY_SEPARATOR);
define('VIEWS_PATH', ROOT . '/views');
define('TEMPLATES_PATH', ROOT . '/templates');
define('SITE_URI', 'https://example.test' . $prefix);
define('ACTIVE_TEMPLATE', 'demo');
define('ACTIVE_LANG', 'en');
define('ACTIVE_LANG_PATH', '');

spl_autoload_register(static function (string $class) use ($core): void {
    foreach (['FloCMS\\Core\\' => $core, 'FloCMS\\Controllers\\' => ROOT . '/controllers'] as $namespace => $directory) {
        if (str_starts_with($class, $namespace)) {
            $file = $directory . '/' . str_replace('\\', '/', substr($class, strlen($namespace))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});
require $core . '/Helpers.php';

error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$_ENV['DEFAULT_LANG'] = 'en';
$_GET = $scenario['query'] ?? [];
$_POST = $scenario['post'] ?? [];
$_FILES = [];
$_COOKIE = [];
$_SESSION = ['_csrf_token' => 'example-token'] + ($scenario['session'] ?? []);
$_SERVER = [
    'REQUEST_METHOD' => $scenario['method'] ?? 'GET',
    'REQUEST_URI' => $prefix . ($scenario['path'] ?? '/blog'),
    'SCRIPT_NAME' => $prefix . '/index.php',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($scenario['csrf'] ?? true)) {
    $_POST['_token'] = 'example-token';
}

\FloCMS\Core\Config::set('routes', ['default' => '', 'admin' => 'admin_']);
\FloCMS\Core\Config::set('default_route', 'default');
\FloCMS\Core\Config::set('default_controller', 'pages');
\FloCMS\Core\Config::set('default_action', 'index');
\FloCMS\Core\Config::set('languages', ['en']);
\FloCMS\Core\Config::set('default_language', 'en');
\FloCMS\Core\Config::set('view.cache', $scenario['cache'] ?? true);
if (isset($scenario['permissions'])) {
    \FloCMS\Core\Config::set('permissions', $scenario['permissions']);
    \FloCMS\Core\Config::set('roles', $scenario['roles']);
}
if (isset($scenario['database'])) {
    \FloCMS\Core\App::$db = new \FloCMS\Core\Database(new PDO('sqlite:' . $scenario['database'], null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]));
}
http_response_code(200);

// Router::redirect() ends the request with exit: report on shutdown
$finished = false;
register_shutdown_function(static function () use (&$finished): void {
    $html = (string) ob_get_clean();
    echo json_encode([
        'status' => http_response_code(),
        'html' => $html,
        'redirected' => !$finished,
        'flash' => $_SESSION['__flash'] ?? null,
    ], JSON_THROW_ON_ERROR);
});

ob_start();
try {
    if (isset($scenario['partial'])) {
        \FloCMS\Core\Lang::load('en');
        echo render_partial('post-card', ['post' => (object) $scenario['partial']]);
    } else {
        \FloCMS\Core\App::run($_SERVER['REQUEST_URI']);
    }
} catch (\FloCMS\Core\HttpException $e) {
    http_response_code($e->status);
}
$finished = true;
PHP
    );
    // The scenario driver supplies the constants normally defined by this file.
    writeFile($fixture . '/public/conf_global.php', "<?php\n");
    extractGuide('example-blog');
    // The guide shows only the keys to add to the project's existing language file
    $blogGuide = (string) file_get_contents($docs . '/example-blog.md');
    check((bool) preg_match('/\*\*Add to \x60lang\/en\.php\x60:\*\*\s*\n\s*\x60{3}php\n(.*?)\n\x60{3}/s', $blogGuide, $langKeys), 'example-blog: language keys block not found');
    writeFile($fixture . '/lang/en.php', "<?php\nreturn [\n    'lng.dir' => 'ltr',\n" . $langKeys[1] . "\n];\n");
    command([PHP_BINARY, '-l', $fixture . '/lang/en.php']);

    $blog = request([]);
    check($blog['status'] === 200, 'Blog should return 200');
    check(substr_count($blog['html'], '<article class="post-card">') === 2, 'Blog should render two cards');
    check(str_contains($blog['html'], 'Latest posts'), 'Blog heading translation missing');
    check(str_contains($blog['html'], 'By Sara'), 'Author translation missing');
    check(str_contains($blog['html'], 'Views &amp; templates'), 'Post title must be escaped');
    check(str_contains($blog['html'], '<title>FloCMS Examples</title>'), 'Layout title must not depend on controller data');
    check(str_contains($blog['html'], 'https://example.test/themes/demo/css/site.css'), 'Theme asset URL is wrong');
    check(is_file($fixture . '/public/themes/demo/css/site.css'), 'Referenced stylesheet must exist');
    check(count(glob($fixture . '/views/cache/*.php') ?: []) >= 3, 'Views, layout and partial should be compiled');

    $empty = request(['query' => ['empty' => '1']]);
    check(str_contains($empty['html'], 'No posts yet.'), 'Empty state missing');
    check(!str_contains($empty['html'], '<article'), 'Empty state must not contain post cards');
    $uncached = request(['cache' => false]);
    check($uncached === $blog, 'Uncached rendering should give the same output');
    $escaped = request(['partial' => [
        'title' => '<script>alert(1)</script>',
        'excerpt' => '<b>Text</b>',
        'author' => '<strong>Sara</strong>',
    ]]);
    check(!str_contains($escaped['html'], '<script>'), 'Post title allowed executable markup');
    check(str_contains($escaped['html'], '&lt;script&gt;'), 'Post title escaping missing');
    check(str_contains($escaped['html'], 'By &lt;strong&gt;Sara&lt;/strong&gt;'), 'Translated author must be escaped');

    extractGuide('example-contact-form');
    $get = request(['path' => '/contact']);
    check($get['status'] === 200, 'Contact GET should return 200');
    check(str_contains($get['html'], 'name="_token" value="example-token"'), 'CSRF field missing');
    check(!str_contains($get['html'], 'passed validation'), 'GET must not show submission success');
    check(!str_contains($get['html'], '<ul class="field-errors">'), 'GET must not show field errors');
    $invalid = request(['path' => '/contact', 'method' => 'POST', 'post' => [
        'name' => '<b>Sara</b>', 'email' => 'invalid', 'message' => '<script>alert(1)</script>',
    ]]);
    check($invalid['status'] === 422, 'Invalid email should return 422');
    check(str_contains($invalid['html'], 'Enter a valid email address.'), 'Email format error missing');
    check(str_contains($invalid['html'], 'value="&lt;b&gt;Sara&lt;/b&gt;"'), 'Old input must stay escaped');
    check(str_contains($invalid['html'], '&lt;script&gt;alert(1)&lt;/script&gt;'), 'Textarea must escape old input');
    check(!str_contains($invalid['html'], '<script>'), 'Contact form allowed executable markup');
    $arrays = request(['path' => '/contact', 'method' => 'POST', 'post' => [
        'name' => ['Sara'], 'email' => ['sara@example.test'], 'message' => ['Hello there!'],
    ]]);
    check($arrays['status'] === 422, 'Array-shaped input must fail safely');
    check(str_contains($arrays['html'], 'name is Required.'), 'Array-shaped name must produce a field error');
    $blank = request(['path' => '/contact', 'method' => 'POST', 'post' => [
        'name' => '   ', 'email' => '', 'message' => '',
    ]]);
    check($blank['status'] === 422, 'Whitespace-only name must fail validation');
    $valid = request(['path' => '/contact', 'method' => 'POST', 'post' => [
        'name' => 'Sara', 'email' => 'sara@example.test', 'message' => 'Please contact me about FloCMS.',
    ]]);
    check($valid['status'] === 200, 'Valid form should return 200');
    check(str_contains($valid['html'], 'No email was sent or message saved.'), 'Demo confirmation must describe its actual behavior');
    check(str_contains($valid['html'], 'value=""'), 'Successful form must reset fields');
    check(!str_contains($valid['html'], 'sara@example.test'), 'Successful form should clear the submitted email');
    $csrf = request(['path' => '/contact', 'method' => 'POST', 'csrf' => false]);
    check($csrf['status'] === 419, 'Missing CSRF token should be rejected before the action');
    $helper = request(['path' => '/contact/forminput']);
    check($helper['status'] === 404, 'Protected form helper must not be callable from a URL');

    extractGuide('example-theme');
    foreach (['/blog', '/contact'] as $path) {
        $page = request(['path' => $path, 'prefix' => '/demo']);
        check($page['status'] === 200, 'Themed page should render: ' . $path);
        check(str_contains($page['html'], '<header class="site-header">'), 'Shared header missing');
        check(str_contains($page['html'], '<footer class="site-footer">'), 'Shared footer missing');
        check(str_contains($page['html'], 'https://example.test/demo/blog'), 'Blog link must retain the URL prefix');
        check(str_contains($page['html'], 'https://example.test/demo/contact'), 'Contact link must retain the URL prefix');
        check(str_contains($page['html'], '&copy; ' . date('Y')), 'Footer year missing');
    }
    $footer = $fixture . '/templates/demo/partials/site-footer.html';
    writeFile($fixture . '/templates/default/partials/site-footer.html', (string) file_get_contents($footer));
    rename($footer, $footer . '.bak');
    $fallback = request([]);
    check(str_contains($fallback['html'], '<footer class="site-footer">'), 'Default-theme partial fallback failed');
    rename($footer . '.bak', $footer);

    // Build your own admin: a users page under /admin with the skeleton's role rules
    writeFile($fixture . '/templates/default/layouts/admin.html', "<main><?= \$data['content'] ?></main>\n");
    extractGuide('example-admin');
    $database = $fixture . '/admin.sqlite';
    $pdo = new PDO('sqlite:' . $database, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, full_name VARCHAR(190) NOT NULL DEFAULT "",
        email VARCHAR(190) NOT NULL, role TINYINT NOT NULL DEFAULT 0, status TINYINT NOT NULL DEFAULT 1)');
    $insert = $pdo->prepare('INSERT INTO users (full_name, email, role) VALUES (?, ?, ?)');
    foreach ([['Sara', 'sara@example.test', 1], ['Ali', 'ali@example.test', 2], ['Root', 'root@example.test', 3], ['<script>x</script>', 'x@example.test', 0]] as $row) {
        $insert->execute($row);
    }
    $admin = function (array $scenario, int $role = 2, int $userId = 2) use ($database): array {
        return request($scenario + [
            'database' => $database,
            'session' => ['admin_access' => true, 'isloggedin' => true, 'role' => $role, 'user_id' => $userId],
            // The skeleton's defaults (config/config.php)
            'permissions' => [0 => [], 1 => ['content.*'], 2 => ['content.*', 'users.manage', 'users.assign_role', 'settings.*'], 3 => ['*']],
            'roles' => [0 => 'User', 1 => 'Editor', 2 => 'Admin', 3 => 'Super Admin'],
        ]);
    };
    $user = static fn (int $id): array => $pdo->query('SELECT full_name, role FROM users WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);

    $list = $admin(['path' => '/admin/team']);
    check($list['status'] === 200, 'Team list should return 200 for an Admin');
    check(str_contains($list['html'], '<main>'), 'Admin pages use the admin layout');
    check(str_contains($list['html'], '/admin/team/edit/1"'), 'Admin can edit the Editor');
    check(str_contains($list['html'], '/admin/team/edit/4"'), 'Admin can edit a User');
    check(!str_contains($list['html'], '/admin/team/edit/2"'), 'Nobody edits themselves');
    check(!str_contains($list['html'], '/admin/team/edit/3"'), 'Admin cannot edit a Super Admin');
    check(str_contains($list['html'], '&lt;script&gt;x&lt;/script&gt;') && !str_contains($list['html'], '<script>x'), 'User names must be escaped');
    check($admin(['path' => '/admin/team'], 1, 1)['status'] === 403, 'Editors lack users.manage');
    check($admin(['path' => '/admin/team/edit/3'])['status'] === 403, 'Editing a higher role must answer 403');
    check($admin(['path' => '/admin/team/edit/2'])['status'] === 403, 'Editing yourself must answer 403');
    check($admin(['path' => '/admin/team/edit/99'])['status'] === 404, 'Unknown user must answer 404');

    $form = $admin(['path' => '/admin/team/edit/1']);
    check($form['status'] === 200, 'Edit form should return 200');
    check(str_contains($form['html'], 'name="_token" value="example-token"'), 'Edit form CSRF field missing');
    check(str_contains($form['html'], '<option value="2">Admin</option>'), 'Roles up to your own are offered');
    check(!str_contains($form['html'], 'Super Admin'), 'Roles above your own are not offered');
    check(str_contains($form['html'], '<option value="1" selected>Editor</option>'), 'Current role is selected');

    $forged = $admin(['path' => '/admin/team/edit/1', 'method' => 'POST', 'post' => ['full_name' => 'Sara', 'role' => '3']]);
    check($forged['status'] === 422, 'A forged higher role must be refused');
    check(str_contains($forged['html'], 'You cannot give a role higher than your own.'), 'Role error message missing');
    check($user(1)['role'] === 1, 'A refused role must not be saved');
    $invalid = $admin(['path' => '/admin/team/edit/1', 'method' => 'POST', 'post' => ['full_name' => '', 'role' => '1']]);
    check($invalid['status'] === 422 && str_contains($invalid['html'], 'full_name is Required.'), 'Empty name must be refused');
    check($admin(['path' => '/admin/team/edit/1', 'method' => 'POST', 'csrf' => false, 'post' => ['full_name' => 'X', 'role' => '0']])['status'] === 419, 'Edit without CSRF token must be refused');

    $saved = $admin(['path' => '/admin/team/edit/1', 'method' => 'POST', 'post' => ['full_name' => 'Sara K', 'role' => '0']]);
    check($saved['redirected'], 'A successful save redirects back to the list');
    check(($saved['flash']['message'] ?? '') === 'Saved Sara K.', 'Save message missing');
    check($user(1) === ['full_name' => 'Sara K', 'role' => 0], 'Name and role must be saved');

    $super = $admin(['path' => '/admin/team'], 3, 3);
    check(str_contains($super['html'], '/admin/team/edit/2"'), 'A Super Admin can edit an Admin');

    echo $checks . " example checks passed against the documented files.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} finally {
    removeFixture($fixture);
}

