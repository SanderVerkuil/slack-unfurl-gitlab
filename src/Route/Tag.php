<?php
declare(strict_types=1);

namespace GitlabSlackUnfurl\Route;


use Generator;

class Tag extends AbstractRouteHandler
{
    protected function getDetails(array $parts): array
    {
        $tag = $this->apiClient->tags->show($parts['project_path'], $parts['tag']);
        $this->debug('tag', ['tag' => $tag]);

        $tag['blurb'] = $tag['commit']['short_id'];
        $tag['title'] = $tag['message'] ?? '';
        $tag['created_at'] = $tag['commit']['created_at'];
        $tag['author'] = [
            'name' => $tag['commit']['committer_name'],
            'email' => $tag['commit']['committer_email'],
            'web_url' => '',
        ];

        return $tag;
    }

    protected function buildFields(array $object): Generator
    {
        yield [
            'title' => 'Author',
            'value' => $object['commit']['author_name'],
            'short' => true,
        ];
    }

    protected function formatAuthor(?array $author): ?string
    {
        return $author['name'];
    }

    /**
     * @param array $object
     * @return string
     */
    protected function getText(array $object): string
    {
        $string = trim($object['release']['description'] ?? $object['commit']['message'], "\t\r ");

        return $this->formatUrls($string);
    }

    private function formatUrls(string $text): string
    {
        $regexp = "/\[(?<text>[^\[\]]*)\]\((?<url>.*?)\)/";

        preg_match($regexp, $text, $matches);

        return preg_replace($regexp, '<$1|$2>', $text);
    }
}