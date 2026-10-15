<?php

use App\Cms\Support\HtmlSanitizer;

it('keeps the design: classes, ids, data-*, styles, layout tags, icon tags and inline SVG', function () {
    $html = '<section class="row g-4" id="intro" data-aos="fade-up" style="color: red">'
        .'<i class="bi bi-star-fill"></i><span class="badge"></span>'
        .'<svg viewBox="0 0 24 24" width="24"><linearGradient id="g"><stop offset="0" stop-color="#fff" /></linearGradient>'
        .'<path fill-rule="evenodd" d="M0 0h24v24H0z" /><use href="#icon" /></svg>'
        .'<button type="button" class="btn btn-brand">Go</button>'
        .'<a href="/services" class="card-link"><div class="card">Block link</div></a>'
        .'<style>.card{border-radius:20px}</style>'
        .'<iframe src="https://www.google.com/maps/embed?pb=1" width="600"></iframe>'
        .'<img src="data:image/png;base64,AAAA" alt="">'
        .'</section>';

    // Unchanged (an empty attribute is written the HTML5 way: alt="" → alt).
    expect(HtmlSanitizer::clean($html))->toBe(str_replace('alt=""', 'alt', $html));
});

it('removes scripts, event handlers and active elements', function () {
    $clean = HtmlSanitizer::clean(
        '<p onclick="steal()" class="lead">Hi<script>alert(1)</script></p>'
        .'<img src="x.png" onerror="steal()"><!-- note -->'
        .'<object data="x.swf"></object><embed src="x.swf"><base href="https://evil.test/"><meta http-equiv="refresh" content="0">'
        .'<link rel="stylesheet" href="https://evil.test/x.css"><noscript>x</noscript>'
        .'<svg><foreignObject><p>x</p></foreignObject><set attributeName="href" to="javascript:alert(1)" />'
        .'<animate attributeName="xlink:href" values="javascript:alert(1)" /><animate attributeName="opacity" values="0;1" /></svg>'
        .'<iframe srcdoc="<script>alert(1)</script>" src="https://www.youtube.com/embed/x"></iframe>'
    );

    expect($clean)->toBe(
        '<p class="lead">Hi</p><img src="x.png">'
        .'<svg><animate attributeName="opacity" values="0;1" /></svg>'
        .'<iframe src="https://www.youtube.com/embed/x"></iframe>'
    );
});

it('removes unsafe URLs and CSS but keeps safe ones', function () {
    $clean = HtmlSanitizer::clean(
        '<a href="javascript:alert(1)">a</a><a href="java&#09;script:alert(1)">b</a><a href=" JAVASCRIPT:x">c</a>'
        .'<a href="data:text/html,<script>x</script>">d</a><img src="data:image/svg+xml,<svg onload=x>">'
        .'<a href="mailto:a@b.c">e</a><a href="tel:+996">f</a><a href="#top">g</a><a href="https://odin.test/x?y=1">h</a>'
        .'<form action="javascript:x"><button formaction="javascript:y">i</button></form>'
        .'<div style="background:url(javascript:x)">j</div><div style="width: expression(alert(1))">k</div>'
        .'<style>body{background:url("javascript:x")}</style>'
        .'<iframe src="http://insecure.test/"></iframe><iframe src="javascript:alert(1)"></iframe>'
        .'<iframe src="https://evil.test/page"></iframe>'
    );

    expect($clean)->toBe(
        '<a>a</a><a>b</a><a>c</a><a>d</a><img>'
        .'<a href="mailto:a@b.c">e</a><a href="tel:+996">f</a><a href="#top">g</a><a href="https://odin.test/x?y=1">h</a>'
        .'<form><button>i</button></form><div>j</div><div>k</div>'
    );
});

it('cleans every translation of an array and leaves empty values alone', function () {
    expect(HtmlSanitizer::clean(['en' => '<b onclick="x">Hi</b>', 'ru' => '']))->toBe(['en' => '<b>Hi</b>', 'ru' => ''])
        ->and(HtmlSanitizer::clean(null))->toBeNull()
        ->and(HtmlSanitizer::clean('Plain text & more'))->toBe('Plain text &amp; more');
});

it('keeps iframes only from the configured embeds', function () {
    $iframes = '<iframe src="https://www.youtube.com/embed/x"></iframe><iframe src="https://forms.test/f"></iframe>';

    expect(HtmlSanitizer::clean($iframes))->toBe('<iframe src="https://www.youtube.com/embed/x"></iframe>');

    config(['cms.safe_iframe' => '%^https://%']);
    expect(HtmlSanitizer::clean($iframes))->toBe($iframes);
});
