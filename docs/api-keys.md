---
title: "Get your API keys"
nav_order: 3
description: "Step by step for beginners: where to create an API key for Claude, ChatGPT, Gemini and Grok, what it costs, how to set a spending limit and how to keep the key secret."
---

# Get your API keys

The package does not have an AI model of its own. It sends your request to an AI provider:
Anthropic (Claude), OpenAI (ChatGPT), Google (Gemini) or xAI (Grok). To do that it needs an
**API key** of that provider.

You need a key for **at least one** provider. You can add more: for example Claude for the text and
OpenAI for the images. See [Multiple providers](providers.md).

## What is an API key?

An API key is a long, secret string of letters and numbers. It works like a password for your
application: every request the package sends carries the key, and the provider uses it to know who
you are and whose account to bill.

Anyone who has your key can use your account and spend your money. Treat it like a password. The
section [Keep your keys secret](#keep-your-keys-secret) explains how.

## A chat subscription is not an API key

This confuses many people, so read it before you start:

- A **ChatGPT Plus** subscription does not include use of the OpenAI API. The API is billed
  separately, on platform.openai.com.
- A **Claude Pro** or **Max** subscription does not include use of the Claude API. The API is billed
  separately, with credits on platform.claude.com.
- A **Google AI Pro** or **Ultra** plan does not pay for calls to the Gemini API with a key. Those are
  billed through Google Cloud, or fall under the free tier.
- **Grok** in the X app or a **SuperGrok** subscription is separate from the xAI API. The API uses its
  own prepaid credits on console.x.ai.

So even when you pay for a chat app already, you create a separate developer account and a key.

## What "billed" means

Every time the package calls a provider, that call costs a small amount of money. The provider
counts the text that goes in and out (in "tokens", pieces of words) and charges your account. An
image is a separate call and usually costs more than a text.

In these docs, a **billed call** means a request that costs money. The check with
`php artisan ai-generator:status` only asks for the list of models and costs nothing. Generating a
text or an image does cost money.

Set a **spending limit** at every provider you use. Then a bug in your code, for example a loop that
generates the same article a thousand times, cannot empty your account. Each section below says
where the limit is.

## Claude (Anthropic) {#claude}

1. Go to [platform.claude.com](https://platform.claude.com) and sign up, or log in.
   The old address, console.anthropic.com, sends you there too.
2. Buy credits: go to **Settings**, then **Billing**, and choose **Buy credits**. The API only works
   when your account has credits.
3. Open **Settings**, then **API keys**, or go straight to
   [platform.claude.com/settings/keys](https://platform.claude.com/settings/keys).
4. Click **Create key**. Give it a name you will recognise later, for example `my-shop-production`.
5. Copy the key right away and paste it into your `.env` file (see [below](#put-them-in-env)). You may
   not be able to see it again. If you lose it, create a new one.

Is the **Create key** button greyed out? Then your role in the organisation may not create keys. Ask
the administrator of the organisation.

- **Environment variable:** `ANTHROPIC_API_KEY`
- **Spending limit:** every usage tier has a monthly limit, shown under **Settings**, **Billing**.
  You can also turn auto-reload of credits off, so you never pay more than you loaded.
- **Revoke a key:** on the same **API keys** page.

## ChatGPT (OpenAI) {#openai}

1. Go to [platform.openai.com](https://platform.openai.com) and sign up, or log in. This is the
   developer platform, not chatgpt.com.
2. Add a payment method and credits under **Billing**.
3. Go to [platform.openai.com/api-keys](https://platform.openai.com/api-keys). Keys belong to a
   **project**; the default project is fine to start with.
4. Click **Create new secret key**. Give it a name. You can choose "All" or "Restricted"
   permissions; "All" is the simple choice for this package. OpenAI recommends an expiry date.
5. Copy the key right away and paste it into your `.env` file. You may not be able to see it again.

- **Environment variable:** `OPENAI_API_KEY`
- **Spending limit:** on the **Limits** page of your organisation or project you can set alerts and
  a monthly budget.
- **Revoke a key:** on the API keys page. OpenAI says a revoked key stops working within seconds.

## Gemini (Google) {#gemini}

1. Go to [aistudio.google.com/apikey](https://aistudio.google.com/apikey) and log in with a Google
   account.
2. Click **Create API key**. Every key belongs to a Google Cloud project; a new account gets a default
   project, so you don't have to set anything up in Google Cloud first.
3. Copy the key and paste it into your `.env` file.

Google changed its keys in 2026. New keys made in AI Studio are a new kind of key, and Google has
announced that the Gemini API stops accepting the older "standard" keys. Tutorials from before 2026
may show a key that looks different from yours; that is expected. When an old key is refused,
create a new one in AI Studio.

- **Environment variable:** `GEMINI_API_KEY`. The package reads only this one. Google's own tools
  also read `GOOGLE_API_KEY`; the package does not.
- **Free tier:** a new account starts on a free tier with limited models and rate limits. For more,
  click **Set up billing** in AI Studio and link a Cloud Billing account.
- **Spending limit:** set a spend cap per project on the **Spend** page in AI Studio, and a budget
  alert in the Google Cloud console.
- **Revoke a key:** create a new key first and put it in `.env`, then delete the old key in AI
  Studio or on the **Credentials** page of the Google Cloud console.

## Grok (xAI) {#grok}

1. Go to [console.x.ai](https://console.x.ai) and sign up, or log in.
2. Load credits under **Billing**. The API only works when your team has credits.
3. Open **API Keys** in the sidebar and create a key. Keys belong to a **team**; the default team is
   fine.
4. Copy the key right away and paste it into your `.env` file. You may not be able to see it again.

- **Environment variable:** `XAI_API_KEY`
- **Spending limit:** under **Billing**, API spend management. Auto top-up has a monthly cap. Without
  auto top-up, requests simply stop when your credits run out.
- **Revoke a key:** on the **API Keys** page, open the menu with three dots next to the key and
  disable or delete it.

## Put them in .env {#put-them-in-env}

`.env` is a text file in the root folder of your Laravel application. It holds the settings that
differ per machine and the secrets, such as database passwords and API keys. Laravel reads it when
the application starts.

### The easy way: the wizard

```bash
php artisan ai-generator:install
```

The wizard asks which providers you have a key for, lets you paste each key, checks the key with the
provider (this costs nothing), lets you pick the models, and writes everything to `.env`. See
[Installation](installation.md#the-setup-wizard).

### By hand

Open `.env` and add a line per provider you use. Leave out the providers you don't use.

```env
ANTHROPIC_API_KEY=paste-your-claude-key-here
OPENAI_API_KEY=paste-your-openai-key-here
GEMINI_API_KEY=paste-your-gemini-key-here
XAI_API_KEY=paste-your-grok-key-here
```

- No spaces around the `=`.
- No quotes needed: keys contain no spaces.
- Save the file, then check the keys: `php artisan ai-generator:status`.

## Keep your keys secret {#keep-your-keys-secret}

- **Never commit `.env`.** A new Laravel application lists `.env` in `.gitignore`, so git skips it.
  Check that the line is still there before your first commit.
- **Put empty values in `.env.example`.** That file is committed, so other developers see which
  variables exist: `ANTHROPIC_API_KEY=` without the key.
- **Never put a key in your code**, in a Blade view, in JavaScript or in a config file with the value
  written out. Code ends up in git, and JavaScript ends up in every visitor's browser. Always use
  `env()` in a config file, and read the config in your code.
- **Use a different key per environment.** One key for your laptop, one for the production server.
  When one leaks, you revoke only that one.
- **Set a spending limit** at every provider, as described above.
- **On a server that caches its config** (`php artisan config:cache`), run that command again after
  you change `.env`. Until then the application keeps the old values.

### When a key has leaked

You pushed `.env` to GitHub, pasted a key in a chat or a screenshot, or you are not sure:

1. Revoke the key at the provider right away, on the page where you created it.
2. Create a new key and put it in `.env` (and on your server).
3. Removing the key from git history is not enough: bots scan public repositories within minutes.
   Always revoke.
4. Check the usage page of the provider for calls you did not make.
