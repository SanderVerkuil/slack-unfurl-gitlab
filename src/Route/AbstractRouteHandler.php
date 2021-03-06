<?php

namespace GitlabSlackUnfurl\Route;

use DateTime;
use DateTimeZone;
use Generator;
use Gitlab;
use GitlabSlackUnfurl\Traits\SanitizeTextTrait;
use Iterator;
use Psr\Log\LoggerInterface;
use SlackUnfurl\SlackClient;
use function is_array;
use SlackUnfurl\Traits\LoggerTrait;

abstract class AbstractRouteHandler
{
    use LoggerTrait;
    use SanitizeTextTrait;

    /** @var Gitlab\Client */
    protected $apiClient;
    /** @var SlackClient */
    protected $slackClient;
    /** @var DateTimeZone */
    protected $utc;

    public function __construct(
        Gitlab\Client $apiClient,
        SlackClient $slackClient,
        LoggerInterface $logger
    ) {
        $this->apiClient = $apiClient;
        $this->slackClient = $slackClient;
        $this->logger = $logger;
        $this->utc = new DateTimeZone('UTC');
    }

    abstract protected function getDetails(array $parts): array;

    public function unfurl(string $url, array $parts): ?array
    {
        $object = $this->getDetails($parts);
        if (!$object) {
            return null;
        }
        // the original url
        $object['url'] = $url;

        return [
            'title' => $this->formatTitle($object),
            'text' => $this->getText($object),
            'color' => $this->getColor($object),
            'ts' => $this->formatCreatedDate($object),
            'footer' => "Created by {$this->formatAuthor($object['author'])}",
            'fields' => $this->getFields($object),
        ];
    }

    /**
     * @param array $object
     * @return string
     */
    abstract protected function getText(array $object): string;

    /**
     * @param array $object
     * @return string
     */
    protected function getColor(array $object): string
    {
        return '#E24329';
    }

    protected function getAssignees(array $assignees): Generator
    {
        foreach ($assignees as $assignee) {
            yield $this->formatAuthor($assignee);
        }
    }

    protected function formatTitle(array $object): string
    {
        return sprintf(
            '<%s|%s>: %s',
            $this->slackClient->urlencode($object['url']),
            $this->slackClient->escape($object['blurb']),
            $this->slackClient->escape($object['title'])
        );
    }

    protected function formatCreatedDate(array $object): int
    {
        return (new DateTime($object['created_at'], $this->utc))->getTimestamp();
    }

    protected function formatAuthor(?array $author): ?string
    {
        if (!$author) {
            return null;
        }

        return sprintf('<%s|%s>',
            $this->slackClient->urlencode($author['web_url']),
            $this->slackClient->escape($author['name'])
        );
    }

    protected function getFields(array $object): array
    {
        return iterator_to_array($this->compactFields($this->buildFields($object)));
    }

    protected function buildFields(array $object): Generator
    {
        yield [
            'title' => 'Assignee',
            'value' => $this->formatAuthor($object['assignee']),
        ];
        yield [
            'title' => 'Labels',
            'value' => $object['labels'] ?? null,
        ];
        yield [
            'title' => 'Milestone',
            'value' => $object['milestone']['title'] ?? null,
        ];
    }

    /**
     * Skip empty fields, join array and generators
     */
    protected function compactFields(iterable $fields): Generator
    {
        foreach ($fields as $field) {
            if ($field['value'] instanceof Iterator) {
                $field['value'] = iterator_to_array($field['value']);
            }
            if (is_array($field['value'])) {
                $field['value'] = implode(', ', $field['value']);
            }
            if (!isset($field['short'])) {
                $field['short'] = true;
            }

            if ($field['value']) {
                yield $field;
            }
        }
    }
}