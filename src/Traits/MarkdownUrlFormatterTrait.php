<?php
declare(strict_types=1);

namespace GitlabSlackUnfurl\Traits;


trait MarkdownUrlFormatterTrait
{
    protected function formatUrls(string $text): string
    {
        $regexp = "/\[(?<text>[^\[\]]*)\]\((?<url>.*?)\)/";

        preg_match($regexp, $text, $matches);

        return preg_replace($regexp, '<$2|$1>', $text);
    }
}