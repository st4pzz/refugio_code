<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class ICalendarService
{
    private const MAX_FEED_BYTES = 5_242_880;

    public function __construct(private PDO $db, private string $defaultTimezone = 'America/Sao_Paulo')
    {
    }

    public function parse(string $content, ?string $fallbackTimezone = null): array
    {
        if (strlen($content) > self::MAX_FEED_BYTES) {
            throw new RuntimeException('O calendário excede o limite de 5 MB.');
        }
        $content = preg_replace("/\r?\n[ \t]/", '', $content) ?? $content;
        if (!str_contains($content, 'BEGIN:VCALENDAR')) {
            throw new RuntimeException('Conteúdo iCalendar inválido.');
        }
        preg_match_all('/BEGIN:VEVENT\R(.*?)\REND:VEVENT/s', $content, $matches);
        $events = [];
        foreach ($matches[1] ?? [] as $block) {
            $properties = [];
            foreach (preg_split('/\r\n|\r|\n/', $block) ?: [] as $line) {
                if (!str_contains($line, ':')) continue;
                [$nameAndParams, $value] = explode(':', $line, 2);
                $parts = explode(';', $nameAndParams);
                $name = strtoupper(array_shift($parts) ?: '');
                $params = [];
                foreach ($parts as $part) {
                    if (str_contains($part, '=')) {
                        [$key, $paramValue] = explode('=', $part, 2);
                        $params[strtoupper($key)] = trim($paramValue, '"');
                    }
                }
                $properties[$name] = ['value' => $value, 'params' => $params];
            }
            $uid = trim((string) ($properties['UID']['value'] ?? ''));
            if ($uid === '' || empty($properties['DTSTART']['value'])) continue;
            [$start, $allDay] = $this->parseDate($properties['DTSTART'], $fallbackTimezone);
            if (!empty($properties['DTEND']['value'])) {
                [$end] = $this->parseDate($properties['DTEND'], $fallbackTimezone);
            } elseif (!empty($properties['DURATION']['value']) && preg_match('/^P(\d+)D$/', $properties['DURATION']['value'], $duration)) {
                $end = $start->modify('+' . (int) $duration[1] . ' days');
            } else {
                $end = $start->modify($allDay ? '+1 day' : '+1 hour');
            }
            if ($end <= $start) continue;
            $status = strtoupper(trim((string) ($properties['STATUS']['value'] ?? 'CONFIRMED')));
            if (!in_array($status, ['CONFIRMED','TENTATIVE','CANCELLED'], true)) $status = 'CONFIRMED';
            $raw = [
                'uid' => $uid, 'summary' => $this->decodeText((string) ($properties['SUMMARY']['value'] ?? 'Reserva externa')),
                'starts_at' => $start->format('Y-m-d H:i:s'), 'ends_at' => $end->format('Y-m-d H:i:s'),
                'all_day' => $allDay, 'status' => $status, 'sequence' => (int) ($properties['SEQUENCE']['value'] ?? 0),
            ];
            $raw['checksum'] = hash('sha256', json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $events[] = $raw;
        }
        return $events;
    }

    public function syncSource(int $sourceId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM calendar_sources WHERE id=? AND ativo=1');
        $stmt->execute([$sourceId]);
        $source = $stmt->fetch() ?: throw new RuntimeException('Fonte de calendário ativa não encontrada.');
        $this->assertSafeUrl((string) $source['feed_url']);
        $started = microtime(true);
        $this->db->prepare("INSERT INTO calendar_sync_logs (source_id,status,started_at) VALUES (?,'RUNNING',NOW())")->execute([$sourceId]);
        $logId = (int) $this->db->lastInsertId();
        try {
            [$body, $status, $headers] = $this->fetch((string) $source['feed_url'], $source['etag'] ?: null, $source['last_modified'] ?: null);
            if ($status === 304) {
                $this->finishLog($logId, 'NOT_MODIFIED', $status, [], $started);
                $this->scheduleNext($sourceId, (int) $source['sync_interval_minutes'], null, $headers);
                return ['status' => 'NOT_MODIFIED', 'seen' => 0, 'created' => 0, 'updated' => 0, 'cancelled' => 0];
            }
            if ($status < 200 || $status >= 300) throw new RuntimeException("A fonte iCal respondeu HTTP {$status}.");
            $events = $this->parse($body, (string) ($source['timezone'] ?: $this->defaultTimezone));
            $result = $this->importEvents($sourceId, $events);
            $this->finishLog($logId, 'SUCCESS', $status, $result, $started);
            $this->scheduleNext($sourceId, (int) $source['sync_interval_minutes'], null, $headers);
            return ['status' => 'SUCCESS'] + $result;
        } catch (Throwable $error) {
            $this->finishLog($logId, 'FAILED', null, ['error' => $error->getMessage()], $started);
            $this->scheduleNext($sourceId, (int) $source['sync_interval_minutes'], $error->getMessage());
            throw $error;
        }
    }

    public function importContent(int $sourceId, string $content, ?string $timezone = null): array
    {
        return $this->importEvents($sourceId, $this->parse($content, $timezone));
    }

    private function importEvents(int $sourceId, array $events): array
    {
        $seen = [];
        $created = 0; $updated = 0; $cancelled = 0;
        $this->db->beginTransaction();
        try {
            $find = $this->db->prepare('SELECT id,raw_checksum,status FROM calendar_external_events WHERE source_id=? AND external_uid=? FOR UPDATE');
            $insert = $this->db->prepare('INSERT INTO calendar_external_events (source_id,external_uid,summary,starts_at,ends_at,all_day,status,sequence_no,raw_checksum,raw_json) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $update = $this->db->prepare('UPDATE calendar_external_events SET summary=?,starts_at=?,ends_at=?,all_day=?,status=?,sequence_no=?,raw_checksum=?,raw_json=?,last_seen_at=NOW(),deleted_at=NULL WHERE id=?');
            $touch = $this->db->prepare('UPDATE calendar_external_events SET last_seen_at=NOW(),deleted_at=NULL WHERE id=?');
            foreach ($events as $event) {
                $seen[] = $event['uid'];
                $find->execute([$sourceId, $event['uid']]);
                $existing = $find->fetch();
                $json = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                if (!$existing) {
                    $insert->execute([$sourceId,$event['uid'],$event['summary'],$event['starts_at'],$event['ends_at'],$event['all_day'] ? 1 : 0,$event['status'],$event['sequence'],$event['checksum'],$json]);
                    $created++;
                } elseif (!hash_equals((string) $existing['raw_checksum'], (string) $event['checksum'])) {
                    $update->execute([$event['summary'],$event['starts_at'],$event['ends_at'],$event['all_day'] ? 1 : 0,$event['status'],$event['sequence'],$event['checksum'],$json,$existing['id']]);
                    $updated++;
                } else {
                    $touch->execute([$existing['id']]);
                }
                if ($event['status'] === 'CANCELLED') $cancelled++;
            }
            if ($seen === []) {
                $this->db->prepare('UPDATE calendar_external_events SET deleted_at=NOW() WHERE source_id=? AND deleted_at IS NULL')->execute([$sourceId]);
            } else {
                $marks = implode(',', array_fill(0, count($seen), '?'));
                $this->db->prepare("UPDATE calendar_external_events SET deleted_at=NOW() WHERE source_id=? AND external_uid NOT IN ({$marks}) AND deleted_at IS NULL")->execute([$sourceId, ...$seen]);
            }
            $this->db->commit();
            return ['seen' => count($events), 'created' => $created, 'updated' => $updated, 'cancelled' => $cancelled];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    private function parseDate(array $property, ?string $fallbackTimezone): array
    {
        $value = trim((string) $property['value']);
        $params = $property['params'] ?? [];
        $allDay = ($params['VALUE'] ?? null) === 'DATE' || preg_match('/^\d{8}$/', $value);
        $timezone = new DateTimeZone((string) ($params['TZID'] ?? $fallbackTimezone ?? $this->defaultTimezone));
        if ($allDay) {
            $date = DateTimeImmutable::createFromFormat('!Ymd', substr($value, 0, 8), $timezone);
        } elseif (str_ends_with($value, 'Z')) {
            $date = DateTimeImmutable::createFromFormat('!Ymd\THis\Z', $value, new DateTimeZone('UTC'));
        } else {
            $format = strlen($value) === 13 ? '!Ymd\THi' : '!Ymd\THis';
            $date = DateTimeImmutable::createFromFormat($format, $value, $timezone);
        }
        if (!$date) throw new RuntimeException('Data inválida em evento iCalendar.');
        return [$date->setTimezone(new DateTimeZone($this->defaultTimezone)), (bool) $allDay];
    }

    private function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['https','http'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('URL de calendário inválida.');
        }
        $host = (string) $parts['host'];
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) throw new RuntimeException('Não foi possível resolver a fonte iCal.');
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('A fonte iCal aponta para uma rede privada ou reservada.');
            }
        }
    }

    private function fetch(string $url, ?string $etag, ?string $lastModified): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('A extensão cURL é necessária para sincronizar iCal.');
        $responseHeaders = [];
        $requestHeaders = ['Accept: text/calendar, text/plain;q=0.8'];
        if ($etag) $requestHeaders[] = 'If-None-Match: ' . $etag;
        if ($lastModified) $requestHeaders[] = 'If-Modified-Since: ' . $lastModified;
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_USERAGENT => 'RefugioCalendar/1.0', CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int {
                $length = strlen($header);
                if (str_contains($header, ':')) { [$key,$value] = explode(':', $header, 2); $responseHeaders[strtolower(trim($key))] = trim($value); }
                return $length;
            },
        ]);
        $body = curl_exec($handle);
        if ($body === false) { $message = curl_error($handle); curl_close($handle); throw new RuntimeException('Falha ao buscar iCal: ' . $message); }
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if (strlen((string) $body) > self::MAX_FEED_BYTES) throw new RuntimeException('O calendário excede o limite de 5 MB.');
        return [(string) $body, $status, $responseHeaders];
    }

    private function finishLog(int $id, string $status, ?int $httpStatus, array $result, float $started): void
    {
        $stmt = $this->db->prepare('UPDATE calendar_sync_logs SET status=?,http_status=?,events_seen=?,events_created=?,events_updated=?,events_cancelled=?,duration_ms=?,error_message=?,finished_at=NOW() WHERE id=?');
        $stmt->execute([$status,$httpStatus,$result['seen'] ?? 0,$result['created'] ?? 0,$result['updated'] ?? 0,$result['cancelled'] ?? 0,(int) round((microtime(true)-$started)*1000),isset($result['error']) ? mb_substr((string) $result['error'],0,4000) : null,$id]);
    }

    private function scheduleNext(int $sourceId, int $minutes, ?string $error, array $headers = []): void
    {
        $stmt = $this->db->prepare('UPDATE calendar_sources SET ultimo_sync_em=NOW(),proximo_sync_em=DATE_ADD(NOW(),INTERVAL ? MINUTE),ultimo_status=?,ultimo_erro=?,etag=COALESCE(?,etag),last_modified=COALESCE(?,last_modified) WHERE id=?');
        $stmt->execute([$minutes,$error === null ? 'SUCCESS' : 'FAILED',$error ? mb_substr($error,0,4000) : null,$headers['etag'] ?? null,$headers['last-modified'] ?? null,$sourceId]);
    }

    private function decodeText(string $value): string
    {
        return mb_substr(str_replace(['\\n','\\,','\\;','\\\\'], ["\n",',',';','\\'], $value), 0, 255);
    }
}
