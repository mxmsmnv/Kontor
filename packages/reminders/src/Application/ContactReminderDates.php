<?php

declare(strict_types=1);

namespace Kontor\Reminders\Application;

use DateTimeImmutable;
use InvalidArgumentException;
use Kontor\Core\Infrastructure\Persistence\ExtensionRepository;
use PDO;

final class ContactReminderDates
{
    public function __construct(private ExtensionRepository $extensions, private PDO $database) {}

    public function get(int $organizationId, string $uid): array
    {
        return $this->extensions->get($organizationId, 'KontorReminders', 'contact', $uid, 'dates') ?? [];
    }

    public function save(int $organizationId, string $uid, string $birthday, string $lastContact): void
    {
        $this->date($birthday);
        $last = $this->date($lastContact);
        if ($last !== null && $last > new DateTimeImmutable('today')) {
            throw new InvalidArgumentException('Last contact cannot be in the future.');
        }
        if ($birthday === '' && $lastContact === '') {
            $this->extensions->delete($organizationId, 'KontorReminders', 'contact', $uid, 'dates');
            return;
        }
        $this->extensions->set($organizationId, 'KontorReminders', 'contact', $uid, 'dates', [
            'birthday' => $birthday, 'last_contact' => $lastContact,
        ]);
    }

    public function list(int $organizationId, DateTimeImmutable $today): array
    {
        $statement = $this->database->prepare(
            "SELECT c.uid, c.display_name, e.value_json
             FROM kontor_extensions e JOIN kontor_contacts c
               ON c.uid = e.entity_uid AND c.organization_id = e.organization_id
             WHERE e.organization_id = ? AND e.owner_component = 'KontorReminders'
               AND e.entity_type = 'contact' AND e.extension_key = 'dates'
               AND c.archived_at IS NULL AND c.deleted_at IS NULL AND c.status = 'active'"
        );
        $statement->execute([$organizationId]);
        $rows = [];
        while ($contact = $statement->fetch(PDO::FETCH_ASSOC)) {
            $data = json_decode($contact['value_json'], true, flags: JSON_THROW_ON_ERROR);
            $dates = [];
            if ($birthday = $this->date($data['birthday'] ?? '')) {
                $next = $this->shift($birthday, (int) $today->format('Y'), (int) $birthday->format('n'));
                $dates['birthday'] = $next < $today
                    ? $this->shift($birthday, (int) $today->format('Y') + 1, (int) $birthday->format('n')) : $next;
            }
            if ($last = $this->date($data['last_contact'] ?? '')) {
                $nextMonth = $last->modify('first day of next month');
                $dates['followup_month'] = $this->shift($last, (int) $nextMonth->format('Y'), (int) $nextMonth->format('n'));
                $dates['followup_year'] = $this->shift($last, (int) $last->format('Y') + 1, (int) $last->format('n'));
            }
            foreach ($dates as $kind => $due) {
                $rows[] = [
                    'taskUid' => null, 'contactUid' => $contact['uid'], 'title' => $contact['display_name'],
                    'kind' => $kind, 'remindAt' => $due, 'channel' => 'app', 'sentAt' => null,
                ];
            }
        }
        return $rows;
    }

    private function date(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Enter a valid date.');
        }
        return $date;
    }

    private function shift(DateTimeImmutable $source, int $year, int $month): DateTimeImmutable
    {
        $first = $source->setDate($year, $month, 1);
        return $first->setDate($year, $month, min((int) $source->format('j'), (int) $first->format('t')));
    }
}
