<?php

use App\Support\TelegramHtml;

it('converts rich editor html into telegram html', function () {
    $html = '<p>Hello <strong>{{first_name}}</strong></p><p>Visit <a href="https://example.com">our site</a></p><ul><li>One</li><li><em>Two</em></li></ul>';

    expect(TelegramHtml::fromRichHtml($html))->toBe(
        "Hello <b>{{first_name}}</b>\nVisit <a href=\"https://example.com\">our site</a>\n• One\n• <i>Two</i>"
    );
});

it('escapes plain text for telegram html mode', function () {
    expect(TelegramHtml::fromRichHtml('Hello <world> & friends'))
        ->toBe('Hello &lt;world&gt; &amp; friends');
});

it('treats empty rich editor paragraphs as blank', function () {
    expect(TelegramHtml::isBlank('<p></p>'))->toBeTrue()
        ->and(TelegramHtml::isBlank('<p>Hi</p>'))->toBeFalse();
});

it('preserves blank lines between paragraphs', function () {
    expect(TelegramHtml::fromRichHtml('<p>Line one</p><p></p><p>Line two</p>'))
        ->toBe("Line one\n\nLine two");

    expect(TelegramHtml::fromRichHtml('<p>Line one</p><p><br></p><p>Line two</p>'))
        ->toBe("Line one\n\nLine two");
});
