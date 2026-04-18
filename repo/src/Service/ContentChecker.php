<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ValidationException;

final class ContentChecker
{
    /**
     * @param string[] $prohibitedWords
     * @param string[] $allowedMimeTypes
     */
    public function __construct(
        private array $prohibitedWords = [],
        private array $allowedMimeTypes = ['text/plain', 'image/png', 'image/jpeg', 'application/pdf'],
        private int $maxBytes = 5_000_000,
    ) {
    }

    public function checkText(string $text): void
    {
        if (trim($text) === '') {
            throw new ValidationException('content is empty');
        }
        $lower = strtolower($text);
        foreach ($this->prohibitedWords as $word) {
            if (str_contains($lower, strtolower($word))) {
                throw new ValidationException('content contains prohibited term');
            }
        }
    }

    public function checkFile(string $mime, int $sizeBytes): void
    {
        if (!in_array($mime, $this->allowedMimeTypes, true)) {
            throw new ValidationException('file type not allowed');
        }
        if ($sizeBytes <= 0 || $sizeBytes > $this->maxBytes) {
            throw new ValidationException('file size invalid');
        }
    }

    public function checksum(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * Reject files whose magic bytes don't match the declared MIME. This is
     * the second line of defence against a client lying about Content-Type.
     */
    public function checkMagicBytes(string $mime, string $content): void
    {
        $prefix = substr($content, 0, 8);
        switch ($mime) {
            case 'image/png':
                if (!str_starts_with($content, "\x89PNG\r\n\x1a\n")) {
                    throw new ValidationException('file content does not match image/png');
                }
                return;
            case 'image/jpeg':
                if (!str_starts_with($content, "\xff\xd8\xff")) {
                    throw new ValidationException('file content does not match image/jpeg');
                }
                return;
            case 'application/pdf':
                if (!str_starts_with($content, '%PDF-')) {
                    throw new ValidationException('file content does not match application/pdf');
                }
                return;
            case 'text/plain':
                // UTF-8/ASCII sanity check: reject NUL byte in first KB.
                if (str_contains(substr($content, 0, 1024), "\0")) {
                    throw new ValidationException('file content does not match text/plain');
                }
                return;
        }
        // Unknown MIME: checkFile has already rejected it; we never reach here.
        unset($prefix);
    }
}
