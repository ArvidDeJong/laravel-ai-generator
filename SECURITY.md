# Security policy

This package sends your OpenAI API key and your content to an external API, so a way to leak the
key, to send it to another host than the configured one, or to get unexpected markup into the
generated HTML counts as a security issue.

## Supported versions

Only the latest minor release of 1.x receives security fixes. Upgrade before reporting.

## Reporting a vulnerability

Please do **not** open a public issue. Report it privately instead:

- via [GitHub private vulnerability reporting](https://github.com/ArvidDeJong/laravel-ai-generator/security/advisories/new), or
- by email to info@arvid.nl.

Include the package version, the Laravel version, the driver and model, and the steps that show the
problem. Leave your API key out.

You will get a reply within a week. Once a fix is released, the advisory is published and you are
credited, unless you prefer not to be.

## Out of scope

The `text` field is HTML written by a language model. Treat it as untrusted input and sanitise it
before you render it unescaped; that the model can be asked to produce any markup is how language
models work, not a vulnerability in this package.
