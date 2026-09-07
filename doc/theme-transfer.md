# Moving a theme between installations

A theme lives in the database. That is what makes it editable without a deploy,
and it is also what makes it awkward to move: the design your client configured
in production exists nowhere in your repository, and copying a database to get
it back is a heavy answer to a small question.

The bundle writes a theme to a JSON file and reads it back, from the admin or
from the console.

## What it is for

- **Pulling production into a local install.** Someone configures the theme in
  production, you develop against the real design without dumping a database.
- **Versioning the theme.** Commit `theme.json` next to the code and the design
  gets a history, a diff and a rollback.
- **Reusing a design.** A palette that worked for one project becomes the
  starting point for the next.
- **Seeding an environment.** Staging and CI get the real theme from a file
  rather than from a fixture that stopped being true months ago.

## From the admin

Both buttons need the **Themes** permission, under Settings.

| Where | Button | What it does |
|-------|--------|--------------|
| Theme list | **Import** | Creates a new theme from a file. Needs the *add* permission. |
| Theme > Details | **Export** | Downloads the theme being edited. Needs *view*. |
| Theme > Details | **Import** | Overwrites this theme, or creates a new one. Needs *edit*, and *add* for the second. |

The export button is disabled while the form has unsaved changes: the file is
written from the database, so exporting at that moment would quietly hand out a
version that is not what is on screen. Save first.

After an import, the CSS of the theme is recompiled - but only if a webspace
actually uses it, which is the same rule a normal save follows.

## From the console

```bash
# Write a theme to a file, by machine name or by id
php bin/console iw-sulu:theme:export corporate
php bin/console iw-sulu:theme:export corporate --output=theme.json

# Straight to stdout, so it can be piped.
# Note the "=": `-o -` is read by Symfony Console as a missing value.
php bin/console iw-sulu:theme:export corporate --output=-

# Read a file back as a NEW theme
php bin/console iw-sulu:theme:import theme.json
php bin/console iw-sulu:theme:import theme.json --name=staging

# Read a file back OVER an existing theme
php bin/console iw-sulu:theme:import theme.json --replace=corporate

# Straight from a pipe
ssh prod 'php bin/console iw-sulu:theme:export corporate --output=-' \
    | php bin/console iw-sulu:theme:import - --replace=corporate
```

## Through the API

The admin buttons and the commands go through the same two endpoints, which a
script can call directly. Both are behind the admin firewall, so authenticate
first and keep the session cookie.

```bash
# Log in once and keep the cookie
curl -s -c /tmp/sulu.txt -X POST https://example.org/admin/login \
    -H 'Content-Type: application/json' \
    -d '{"username":"admin","password":"secret"}'

# Export
curl -s -b /tmp/sulu.txt https://example.org/admin/api/iw-theme-configs/1/export > theme.json

# Import as a new theme, as a multipart upload
curl -s -b /tmp/sulu.txt -X POST https://example.org/admin/api/iw-theme-configs/import \
    -F mode=new -F file=@theme.json

# Import over theme 1, as JSON
curl -s -b /tmp/sulu.txt -X POST https://example.org/admin/api/iw-theme-configs/import \
    -H 'Content-Type: application/json' \
    -d "$(jq -n --arg c "$(cat theme.json)" '{mode:"replace", id:1, content:$c}')"
```

`POST /admin/api/iw-theme-configs/import` accepts:

| Field | Values | Meaning |
|-------|--------|---------|
| `mode` | `new` (default), `replace` | Create a theme, or overwrite the one `id` names |
| `id` | integer | The theme to overwrite, required by `replace` |
| `name` | string | Machine name for the created theme, `new` only |
| `file` | multipart file | The document |
| `content` | string | The document, when posting JSON instead of multipart |

The request body may also *be* the exported document, with no wrapper at all,
which is what lets an export be piped straight back in.

A refused import answers `422` with a translated sentence in `detail`, which
Sulu shows in its native snackbar.

## Images do not travel

This is the one thing to know before relying on the feature.

The logos, the fullscreen menu image, the map marker, the back-to-top icon, the
social sharing image and a variant's separator image are all **media
references**: the theme stores the id of a row in the media library. That id
means nothing in another installation, where it points at an unrelated image or
at none.

So the export leaves every media reference out, and the import drops any it
finds in a hand-edited file.

What that means in practice:

- **Importing over an existing theme keeps the logos already set on it.** A key
  absent from the file leaves the stored value alone, which is exactly what you
  want when pulling a design into an install that already has its images.
- **A theme created by an import has no images.** Set them once, then import
  over that theme as often as you like - they will stay.

Embedding the images in the file is a natural next step and deliberately not in
this first version: it turns a readable, diffable document into a few megabytes
of base64.

## The file

```json
{
    "_format": 1,
    "_bundleVersion": "3.0.0",
    "_exportedAt": "2026-09-07T14:22:31+02:00",
    "name": "corporate",
    "label": "Corporate",
    "palette": [
        {"role": "primary", "slug": "primary", "value": "#3366ff"}
    ],
    "buttons": [],
    "blockVariants": [],
    "menuConfig_type": "navbar",
    "footerConfig_type": "columns"
}
```

The payload is the theme exactly as the admin form reads it, flat keys and all.
Export and import are the two ends of the same mapper the form itself uses, so
a token added to a form travels without anything being registered here as well.

`_format` is the version of the **file format**, not of the bundle. It only
changes when a file written by an older version can no longer be read as it is.
A bundle refuses a file whose format is newer than what it knows, rather than
importing half of it - if that happens, update the bundle.

`name` and `label` are ignored when importing over an existing theme: pulling a
design in is not renaming the theme it lands on, and webspaces point at that
machine name. When creating a theme, a name already taken is suffixed
(`corporate`, then `corporate-2`), since the column is unique.

## What is validated

An import is checked exactly like a form save, because it goes through the same
mapper: color, variant and button slugs must be unique and well formed, and a
typography weight must be one the font actually ships. A file that fails any of
those is refused whole, with the same message the form would have shown.
