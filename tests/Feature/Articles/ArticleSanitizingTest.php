<?php

namespace Tests\Feature\Articles;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class ArticleSanitizingTest extends TestCase
{
    use InteractsWithArticles, InteractsWithAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public static function payloads(): array
    {
        return [
            'script tag' => ['<p>Hello<script>alert(1)</script></p>', '<p>Hello</p>'],
            'style tag' => ['<style>body{display:none}</style><p>Text</p>', '<p>Text</p>'],
            'onerror handler' => ['<img src="https://cdn.example.com/a.png" onerror="alert(1)" alt="A">', '<img src="https://cdn.example.com/a.png" alt="A" />'],
            'event handlers and styles on text' => ['<p onclick="steal()" style="color:red" class="x">Text</p>', '<p>Text</p>'],
            'javascript link' => ['<a href="javascript:alert(1)">Click</a>', '<a rel="noopener noreferrer nofollow">Click</a>'],
            'obfuscated javascript link' => ['<a href="JaVaScRiPt:alert(1)">Click</a>', '<a rel="noopener noreferrer nofollow">Click</a>'],
            'data image' => ['<p>A</p><img src="data:image/png;base64,iVBORw0KGgo=">', '<p>A</p>'],
            'http image' => ['<p>A</p><img src="http://cdn.example.com/a.png">', '<p>A</p>'],
            'non youtube iframe' => ['<p>A</p><iframe src="https://evil.example.com/embed/x"></iframe>', '<p>A</p>'],
            'youtube watch page in iframe' => ['<p>A</p><iframe src="https://www.youtube.com/watch?v=abc"></iframe>', '<p>A</p>'],
            'iframe srcdoc' => [
                '<iframe src="https://www.youtube.com/embed/abc" srcdoc="<script>alert(1)</script>"></iframe>',
                '<iframe src="https://www.youtube.com/embed/abc"></iframe>',
            ],
            'svg payload' => ['<p>A</p><svg onload="alert(1)"><script>alert(1)</script></svg>', '<p>A</p>'],
            'unknown wrappers keep their text' => ['<div><span>Kept</span></div>', 'Kept'],
            'allowed markup survives' => [
                '<h2>T</h2><h3>S</h3><h4>U</h4><p>A<br />B</p><ul><li><strong>x</strong></li></ul><ol><li><em>y</em></li></ol><blockquote>q</blockquote>',
                '<h2>T</h2><h3>S</h3><h4>U</h4><p>A<br />B</p><ul><li><strong>x</strong></li></ul><ol><li><em>y</em></li></ol><blockquote>q</blockquote>',
            ],
            'safe links get rel' => [
                '<a href="https://apfspn.org" target="_blank">Site</a><a href="mailto:info@apfspn.org">Mail</a>',
                '<a href="https://apfspn.org" rel="noopener noreferrer nofollow">Site</a><a href="mailto:info&#64;apfspn.org" rel="noopener noreferrer nofollow">Mail</a>',
            ],
            'youtube embeds survive' => [
                '<iframe src="https://www.youtube-nocookie.com/embed/abc" width="560" height="315" allowfullscreen></iframe>',
                '<iframe src="https://www.youtube-nocookie.com/embed/abc" width="560" height="315" allowfullscreen></iframe>',
            ],
        ];
    }

    #[DataProvider('payloads')]
    public function test_body_is_sanitised_on_save(string $body, string $expected): void
    {
        $this->api('POST', '/api/v1/admin/articles', $this->articlePayload(['body' => $body]), $this->tokenFor($this->adminWithRole('editor')))
            ->assertCreated()
            ->assertJsonPath('data.body', $expected);
    }

    public function test_body_is_sanitised_on_update_and_in_the_revision(): void
    {
        $token = $this->tokenFor($this->adminWithRole('editor'));
        $uuid = $this->api('POST', '/api/v1/admin/articles', $this->articlePayload(), $token)->json('data.uuid');

        $this->api('PUT', '/api/v1/admin/articles/'.$uuid, ['body' => '<p>Safe</p><script>alert(1)</script>'], $token)
            ->assertOk()
            ->assertJsonPath('data.body', '<p>Safe</p>');

        $this->assertDatabaseMissing('article_revisions', ['body' => '<p>Safe</p><script>alert(1)</script>']);
        $this->assertDatabaseHas('article_revisions', ['article_uuid' => $uuid, 'body' => '<p>Safe</p>']);
    }
}
