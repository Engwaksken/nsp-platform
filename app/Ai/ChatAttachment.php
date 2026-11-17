<?php

namespace App\Ai;

use App\Registry\RegistryException;
use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * A file attached to a chat message. Only its text is read, in memory, from the upload's temporary file; the file
 * itself is never saved. The AI providers receive plain text only, so documents are reduced to their words.
 */
final class ChatAttachment
{
    /** Extensions people may attach, and the label shown in the chat. */
    public const TYPES = ['txt' => 'Text', 'md' => 'Markdown', 'csv' => 'CSV', 'json' => 'JSON', 'log' => 'Log', 'docx' => 'Word document'];

    public const MAX_KB = 2048;

    /** Characters of a file's text that are sent; the rest is left out and the model is told so. */
    public const MAX_CHARS = 12000;

    private function __construct(public readonly string $name, public readonly string $text, public readonly bool $truncated) {}

    public static function fromUpload(UploadedFile $file): self
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! isset(self::TYPES[$extension])) {
            throw new RegistryException('You can attach '.self::typeList().' files.');
        }
        $text = $extension === 'docx' ? self::docxText((string) $file->getRealPath()) : self::plainText((string) file_get_contents($file->getRealPath()));
        $text = trim((string) preg_replace(["/[ \t]+\n/", "/\n{3,}/"], ["\n", "\n\n"], $text));
        if ($text === '') {
            throw new RegistryException('No text was found in that file. Attach a file that contains text.');
        }
        $name = mb_substr(trim((string) preg_replace('/[^\pL\pN ._()\-]+/u', '_', $file->getClientOriginalName())), 0, 120) ?: 'attachment.'.$extension;

        return new self($name, mb_substr($text, 0, self::MAX_CHARS), mb_strlen($text) > self::MAX_CHARS);
    }

    public static function typeList(): string
    {
        $types = array_map(fn ($ext) => '.'.$ext, array_keys(self::TYPES));

        return implode(', ', array_slice($types, 0, -1)).' or '.end($types);
    }

    private static function plainText(string $bytes): string
    {
        if (str_contains(substr($bytes, 0, 8000), "\0")) {
            throw new RegistryException('That file does not look like a text file.');
        }
        $bytes = (string) preg_replace('/^\xEF\xBB\xBF/', '', $bytes);
        if (! mb_check_encoding($bytes, 'UTF-8')) {
            $bytes = mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
        }

        return (string) preg_replace('/[^\P{C}\n\t]/u', '', str_replace("\r\n", "\n", $bytes));
    }

    private static function docxText(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RegistryException('That Word document could not be opened. Save it again as .docx and retry.');
        }
        $entry = $zip->statName('word/document.xml');
        // Guard against zip bombs: the document part of a 2 MB file has no business being huge.
        if (! $entry || $entry['size'] > 20 * 1024 * 1024) {
            $zip->close();
            throw new RegistryException('That Word document could not be read.');
        }
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        $xml = str_replace(['</w:p>', '<w:br/>', '<w:cr/>', '<w:tab/>'], ["\n", "\n", "\n", "\t"], $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
