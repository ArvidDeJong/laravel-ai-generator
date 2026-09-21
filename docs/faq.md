---
title: FAQ
nav_order: 7
description: "Short answers about darvis/laravel-ai-generator: what it returns, languages, images, other AI providers, costs and safety of the generated HTML."
faq: true
---

# Frequently asked questions

{% for item in site.data.faq %}
## {{ item.q }}

{{ item.a | markdownify }}
{% endfor %}
