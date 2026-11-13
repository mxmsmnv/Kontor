<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\ImportExport\Format;

/** Translate human-readable contact headers before the usual validation and duplicate checks. */
final class ContactSpreadsheetReader implements RecordReaderInterface
{
    private const FIELDS = [
        'first_name' => ['first name', 'firstname', 'given name', 'имя'],
        'middle_name' => ['middle name', 'middlename', 'отчество'],
        'last_name' => ['last name', 'lastname', 'surname', 'family name', 'фамилия'],
        'display_name' => ['display name', 'full name', 'fullname', 'contact name', 'name', 'фио', 'ф и о', 'полное имя', 'контакт', 'имя контакта'],
        'email' => ['email', 'e mail', 'email address', 'e mail address', 'work email', 'электронная почта', 'почта'],
        'phone' => ['phone', 'phone number', 'telephone', 'work phone', 'телефон', 'номер телефона', 'рабочий телефон'],
        'mobile' => ['mobile', 'mobile phone', 'cell phone', 'мобильный', 'мобильный телефон'],
        'job_title' => ['job title', 'position', 'должность'],
        'source' => ['source', 'источник'],
        'status' => ['status'],
        'type' => ['type'],
    ];

    /** @var array<string,string> */
    private array $mapping = [];

    public function __construct(private readonly RecordReaderInterface $reader)
    {
    }

    public function mapping(): array
    {
        return $this->mapping;
    }

    public function read(string $path): iterable
    {
        $this->mapping = [];
        $initialized = false;
        foreach ($this->reader->read($path) as $record) {
            if (!$initialized) {
                $initialized = true;
                foreach (array_keys($record) as $header) {
                    $normalized = mb_strtolower((string) $header);
                    $normalized = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? '');
                    foreach (self::FIELDS as $field => $aliases) {
                        if (in_array($normalized, $aliases, true)) {
                            if (in_array($field, $this->mapping, true)) {
                                throw new \RuntimeException("Multiple columns match {$field}. Keep one column for that field.");
                            }
                            $this->mapping[$header] = $field;
                            break;
                        }
                    }
                }
                if (array_intersect(['display_name', 'first_name', 'last_name'], $this->mapping) === []) {
                    throw new \RuntimeException('No contact name column recognized. Use Full Name, First Name / Last Name, ФИО or Имя / Фамилия in the header row.');
                }
            }
            $mapped = [];
            foreach ($this->mapping as $header => $field) {
                $value = $record[$header] ?? null;
                if ($value !== null && (!is_string($value) || trim($value) !== '')) {
                    $mapped[$field] = $value;
                }
            }
            yield $mapped;
        }
    }
}
