# Generators and stubs

Generators write the files every new feature starts with: a controller, a model, a view, a migration. They follow FloCMS conventions, so the files work as soon as they're created.

## A page in one command

```bash
php flo make:route Blog
```

creates three files:

```text
controllers/BlogController.php   index() action, model() accessor
models/BlogModel.php             table "blogs", all() and find()
views/blog/index.html            the view of index()
```

Open `/blog` and the page is there. `php flo make:route Blog show` creates a `show()` action and its view instead, and `--admin` creates an `admin_` action under `/admin`.

## Trying before writing

Every generator takes:

- `--dry-run`: show which files would be created or changed, and write nothing. Add `-v` to see their contents.
- `--force`: overwrite files that already exist. Without it, generators never replace your work.

```bash
php flo make:model Category --migration --dry-run -v
```

## What each generator writes

| Command | Writes |
|---|---|
| `make:controller Blog` | `controllers/BlogController.php` with `index()` |
| `make:controller Blog --admin` | The same with `admin_index()` and `$actionPermissions` (`blog.manage`) |
| `make:controller Blog --resource` | `index()`, `view()`, `admin_index()`, `admin_add()`, `admin_edit()` and `admin_delete()` |
| `make:controller Blog --model` | Also creates `models/BlogModel.php` and the `model()` accessor |
| `make:controller Posts --api` | Runs `make:api-controller Posts` instead |
| `make:model Category` | `models/CategoryModel.php` for the table `categories`; adds `model()` to an existing `CategoryController` |
| `make:model Category --table=cats --migration` | Another table name, and a migration for it |
| `make:view blog show` | `views/blog/show.html` and a `show()` action in `BlogController` |
| `make:view blog edit --admin` | `views/blog/admin_edit.html` and `admin_edit()` |
| `make:migration create_posts_table` | A migration with a `CREATE TABLE posts` template |
| `make:seeder Posts` | `database/seeders/PostsSeeder.php` |
| `make:api-controller Posts [--resource]` | `api/Controllers/PostsController.php` |
| `make:resource Post` | `api/Resources/PostResource.php` |
| `make:middleware Name` | `api/Middleware/Name.php` |
| `make:request StorePost` | `api/Requests/StorePostRequest.php` |
| `make:command SendReports` | `commands/SendReportsCommand.php` |
| `make:test Orders [--feature]` | `tests/Unit/OrdersTest.php` or `tests/Feature/OrdersTest.php` |
| `make:module news` | `modules/news/module.php` and a service provider |

The suffix is optional: `make:seeder PostsSeeder` and `make:seeder Posts` write the same file.

## Names

Names may contain letters, digits, `_` and `-`, and must start with a letter. Generators never write outside the project.

Generated code follows the same style everywhere: actions are lowercase with return types, models are reached through a lazy `model()` accessor so pages work before a database exists, and indentation is 4 spaces.

> [!WARNING]
> **Use one-word names for page controllers for now.** For a name like `BlogPost`, the generators write the view to `views/blog-post/`, but flocms-core 2.2.1 doesn't accept a hyphen in the URL: `/blog-post` answers 404, and `/blog_post` looks for its views in `views/blog_post/`. If you need a multi-word page controller, rename the view folder to `views/blog_post/` and link to `/blog_post`. API controllers, models and other generators aren't affected.

## Deleting generated code

```bash
php flo delete:route Blog --dry-run
php flo delete:route Blog
```

`delete:controller`, `delete:model`, `delete:view` and `delete:route` show every file they will delete or edit, and ask first. They also remove the action or the `model()` accessor from the controller. `--force` skips the question. The `remove:*` names work too.

## Customising the stubs

Generators build files from templates called stubs. To change what they write, for example to add your license header or a base controller, publish the stubs and edit them:

```bash
php flo stub:publish                     # every stub
php flo stub:publish controller model    # only these
```

The copies go to `stubs/<name>.stub` in your project, and the generators use them from then on. `--force` replaces copies you already published.

| Stub | Used by |
|---|---|
| `controller`, `controller.admin`, `controller.resource`, `controller.model`, `action` | `make:controller`, `make:view` |
| `model` | `make:model` |
| `view` | `make:view`, `make:route` |
| `migration`, `migration.create`, `migration.update` | `make:migration` |
| `seeder` | `make:seeder` |
| `api-controller`, `api-controller.resource`, `resource`, `middleware`, `request` | API generators |
| `command` | `make:command` |
| `test`, `test.feature` | `make:test` |
| `module`, `module.provider` | `make:module` |

Placeholders look like `{{ class }}`, `{{ table }}`, `{{ route }}`, `{{ title }}` and `{{ permission }}`. Keep the ones a stub already has; generators replace them when they write the file.
