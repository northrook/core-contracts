<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Parameter\Secret;

/**
 * Shared secret resolution + debug mask for {@see Serializer} and {@see Snapshot}.
 *
 * @phpstan-type SecretRedaction array{secret: Secret, tags: list<non-empty-string>}
 */
final class Redaction
{
    private function __construct() {}

    /**
     * Secret tier + tag seeds guarding `$name` on `$subject`, or `null` when plain.
     *
     * Instance `$secret` on the `value` property wins ({@see Parameter} convention).
     * Otherwise {@see PropertyAttributes::redaction()} (`#[Secret]` / `#[\SensitiveParameter]`).
     *
     * @return null|SecretRedaction
     */
    public static function for(
        object              $subject,
        string              $name,
        \ReflectionProperty $property,
    ): null|array {
        $instanceTags = self::tagsOf($subject);

        if ($name === 'value') {
            $policy = self::secretOf($subject);

            if ($policy !== null) {
                return [
                    'secret' => $policy,
                    'tags'   => $instanceTags,
                ];
            }
        }

        $redaction = PropertyAttributes::redaction(
            new \ReflectionClass($subject),
            $property,
        );

        if ($redaction === null) {
            return null;
        }

        if ($instanceTags === []) {
            return $redaction;
        }

        $tags = $redaction['tags'];

        foreach ($instanceTags as $tag) {
            if (! \in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        return [
            'secret' => $redaction['secret'],
            'tags'   => $tags,
        ];
    }

    /**
     * Debug-out / non-authoritative mask.
     *
     * @param list<non-empty-string> $tags
     */
    public static function mask(
        mixed         $value,
        Secret        $secret,
        array         $tags = [],
        null|object   $subject = null,
        null|Redactor $redactor = null,
    ): mixed {
        /** @var array<non-empty-string, non-empty-string> $context */
        $context = [];

        if ($subject !== null && \property_exists($subject, 'tags') && \is_array($subject->tags)) {
            foreach ($subject->tags as $key => $tag) {
                if (! \is_string($tag) || $tag === '') {
                    continue;
                }

                $context[\is_string($key) && $key !== '' ? \strtolower($key) : \strtolower($tag)] = $tag;
            }
        }

        foreach ($tags as $tag) {
            if ($tag === '') {
                continue;
            }

            $context[\strtolower($tag)] ??= $tag;
        }

        return ( $redactor ?? new Redactor )($value, $secret, $context);
    }

    /**
     * @return list<non-empty-string>
     */
    private static function tagsOf(
        object $subject,
    ): array {
        if (! \property_exists($subject, 'tags') || ! \is_array($subject->tags)) {
            return [];
        }

        $tags = [];

        foreach ($subject->tags as $tag) {
            if (\is_string($tag) && $tag !== '') {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    private static function secretOf(
        object $subject,
    ): null|Secret {
        if (! \property_exists($subject, 'secret')) {
            return null;
        }

        $property = new \ReflectionProperty($subject, 'secret');

        if (! $property->isInitialized($subject)) {
            return null;
        }

        $policy = $property->getValue($subject);

        return $policy instanceof Secret ? $policy : null;
    }
}
