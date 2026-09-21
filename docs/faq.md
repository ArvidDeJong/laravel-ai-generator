---
title: "FAQ"
nav_order: 9
description: "Short answers about darvis/laravel-ai-generator: what it is, what it needs and costs, the default image, other AI providers, failures, testing and HTML safety."
faq: true
---

# Frequently asked questions

{% for item in site.data.faq %}
## {{ item.q }}

{{ item.a | markdownify }}
{% endfor %}
