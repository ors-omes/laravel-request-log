---
layout: home
title: Laravel Request Log
titleTemplate: Every mutation and every error, recorded safely

hero:
  name: Laravel Request Log
  text: Every mutation and every error, recorded safely.
  tagline: A structured, redacted, queryable HTTP request log for Laravel 12 and 13. It stores payloads, responses, exceptions, query statistics and who did what, in indexed columns. Rows are written after the response is sent.
  image:
    src: /logo.svg
    alt: Laravel Request Log
  actions:
    - theme: brand
      text: Get started
      link: /guide/introduction
    - theme: alt
      text: Live demo
      link: /demo/en/index.html
      target: _self
    - theme: alt
      text: GitHub
      link: https://github.com/ors-omes/laravel-request-log

features:
  - icon: 📄
    title: Payloads you can actually read
    details: Request bodies up to 512 KB and responses up to 256 KB are stored whole. Anything larger is shortened while staying valid JSON ("…[+742 items]"), and the original size is kept.
    link: /guide/stored-data
  - icon: 🔒
    title: Redacted by default
    details: Passwords, tokens, secrets, PINs, card numbers, cookies and auth headers are redacted at any depth, in bodies, query strings, route parameters, responses and headers.
    link: /guide/security
  - icon: 🔖
    title: Reference codes everywhere
    details: Each request gets a 10-character code. It goes into the Laravel Context, the X-Request-Reference header and your error responses.
    link: /guide/reference-codes
  - icon: 🐞
    title: Exceptions you can act on
    details: The class, the message (with SQL bindings removed), the project location, a source snippet, a trimmed trace and a grouping hash that lists every occurrence.
    link: /guide/stored-data#exceptions
  - icon: ⚡
    title: Zero impact on the request
    details: The decision is cheap and made inside the request. Capturing and storing happen after the response is sent, and failures are swallowed.
    link: /guide/introduction#how-it-works
  - icon: 🔭
    title: Telescope-aware
    details: Telescope entries are tagged with the request's reference (ref:7KQ2M9XHTA), and the viewer opens the whole Telescope batch of a request.
    link: /guide/telescope
---

<div class="home-shot">

![The request log viewer](/screenshots/list.png){.screenshot}

</div>
