---
title: "FAQ"
nav_order: 12
description: "Short answers about darvis/laravel-ai-generator: providers, API keys and subscriptions, costs, the default image, MCP, failures, testing and HTML safety."
faq: true
---

# Frequently asked questions

{% for item in site.data.faq %}
## {{ item.q }}

{{ item.a | markdownify }}
{% endfor %}
