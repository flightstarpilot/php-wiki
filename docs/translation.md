# Translating the interface

The wiki ships with eight interface languages and auto-detects which one to
use from the browser's `Accept-Language` header. Users can override the
choice from the language menu in the header.

Adding a language is a single file. No registration step is required.

## Steps

1. Copy `lang/en-US.php` to `lang/<code>.php`, where `<code>` is a BCP 47
   language tag such as `pt-BR`, `it-IT`, or `nl-NL`.
2. Translate the values. Do not translate the keys.
3. Add the code to the supported list in `src/Services/I18nService.php`,
   method `supported()`.
4. Test by visiting `/lang/<code>` on a running instance.

## File format

Language files return a flat associative array:

    <?php
    return [
        'nav.home'    => 'Home',
        'nav.new'     => 'New page',
        'page.edit'   => 'Edit',
        // ...
    ];

Keys are stable identifiers. Values are the strings shown to the user.

## Rules

- **Do not translate keys.** Only values.
- **Preserve placeholders.** `{title}`, `{n}`, `{lang}`, `{fallback}`, and
  other `{...}` tokens are replaced at render time. They must appear in the
  translation, but you may reorder them.
- **Keep HTML out.** Values are plain text. If you need emphasis, request a
  markup change in the source template instead.
- **Match the tone.** The English source uses sentence case for headings and
  labels, and full sentences for help text. Follow the same pattern where it
  makes sense in your language.
- **Use UTF-8 without a BOM.** The file must be valid UTF-8.
- **Do not use emoji.** This is a project-wide rule.

## Partial translations

You do not need to translate every key. Missing keys fall back to `en-US` at
runtime, and the fallback is silent. Ship what you have; more can be added
later.

To see which keys are missing, compare your file with `lang/en-US.php`:

    php -r '
        $base = require "lang/en-US.php";
        $mine = require "lang/pt-BR.php";
        $missing = array_diff(array_keys($base), array_keys($mine));
        echo count($missing), " missing keys\n";
        foreach ($missing as $k) echo "  $k\n";
    '

## Testing

1. Place the file in `lang/`.
2. Add the code to `I18nService::supported()`.
3. Start the wiki and visit `/lang/<code>`.
4. Walk through the site: page view, edit form, history, an issue, a
   proposal, the admin panel. Look for truncated strings, awkward wrapping,
   or placeholders that did not substitute.

## Right-to-left languages

RTL languages such as Arabic and Hebrew are not yet supported. The templates
use Tailwind CSS logical properties in most places but the layout has not
been audited for RTL. Contributions toward RTL support are welcome; see
`CONTRIBUTING.md`.

## Submitting

Open a pull request with the new file and the one-line change to
`I18nService::supported()`. Translation-only pull requests are reviewed
quickly and never blocked on code style.
