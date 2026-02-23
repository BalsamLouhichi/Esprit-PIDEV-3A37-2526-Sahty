<?php

namespace App\Service;

use App\Entity\RendezVous;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GoogleMeetApiService
{
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const CALENDAR_API_BASE = 'https://www.googleapis.com/calendar/v3';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $clientId = '',
        private readonly string $clientSecret = '',
        private readonly string $refreshToken = '',
        private readonly string $calendarId = 'primary',
        private readonly string $timezone = 'UTC'
    ) {
    }

    /**
     * @return array{provider:string,url:string}
     */
    public function createMeetingLink(RendezVous $rdv): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Google Meet is not configured. Check Google API env vars.');
        }

        $accessToken = $this->fetchAccessToken();
        [$start, $end] = $this->buildWindow($rdv);

        $calendarId = trim($this->calendarId) !== '' ? trim($this->calendarId) : 'primary';
        $requestId = sprintf('sahty-%d-%s', (int) $rdv->getId(), bin2hex(random_bytes(4)));

        $attendees = [];
        $patientEmail = $rdv->getPatient()?->getEmail();
        $medecinEmail = $rdv->getMedecin()?->getEmail();
        if ($patientEmail) {
            $attendees[] = ['email' => $patientEmail];
        }
        if ($medecinEmail && $medecinEmail !== $patientEmail) {
            $attendees[] = ['email' => $medecinEmail];
        }

        $payload = [
            'summary' => sprintf(
                'Consultation Sahty - Dr. %s',
                (string) ($rdv->getMedecin()?->getNomComplet() ?? 'Medecin')
            ),
            'description' => (string) ($rdv->getRaison() ?? 'Consultation en ligne'),
            'start' => [
                'dateTime' => $start->format(\DateTimeInterface::RFC3339),
                'timeZone' => $this->timezone,
            ],
            'end' => [
                'dateTime' => $end->format(\DateTimeInterface::RFC3339),
                'timeZone' => $this->timezone,
            ],
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => $requestId,
                    'conferenceSolutionKey' => [
                        'type' => 'hangoutsMeet',
                    ],
                ],
            ],
            'attendees' => $attendees,
        ];

        $response = $this->httpClient->request(
            'POST',
            sprintf('%s/calendars/%s/events', self::CALENDAR_API_BASE, rawurlencode($calendarId)),
            [
                'query' => ['conferenceDataVersion' => 1],
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]
        );

        $status = $response->getStatusCode();
        $data = $response->toArray(false);
        if ($status < 200 || $status >= 300) {
            $message = is_array($data) ? json_encode($data) : (string) $response->getContent(false);
            throw new \RuntimeException('Google Calendar event creation failed: ' . $message);
        }

        $meetingUrl = $data['hangoutLink'] ?? null;
        if (!$meetingUrl && isset($data['conferenceData']['entryPoints']) && is_array($data['conferenceData']['entryPoints'])) {
            foreach ($data['conferenceData']['entryPoints'] as $entryPoint) {
                if (($entryPoint['entryPointType'] ?? '') === 'video' && !empty($entryPoint['uri'])) {
                    $meetingUrl = $entryPoint['uri'];
                    break;
                }
            }
        }

        if (!$meetingUrl) {
            throw new \RuntimeException('Google Meet link was not returned by Google Calendar API.');
        }

        return [
            'provider' => 'google_meet',
            'url' => (string) $meetingUrl,
        ];
    }

    private function isConfigured(): bool
    {
        return trim($this->clientId) !== ''
            && trim($this->clientSecret) !== ''
            && trim($this->refreshToken) !== '';
    }

    private function fetchAccessToken(): string
    {
        $response = $this->httpClient->request('POST', self::TOKEN_ENDPOINT, [
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type' => 'refresh_token',
            ],
        ]);

        $status = $response->getStatusCode();
        $data = $response->toArray(false);
        if ($status < 200 || $status >= 300 || empty($data['access_token'])) {
            $message = is_array($data) ? json_encode($data) : (string) $response->getContent(false);
            throw new \RuntimeException('Failed to get Google access token: ' . $message);
        }

        return (string) $data['access_token'];
    }

    /**
     * @return array{0:\DateTimeImmutable,1:\DateTimeImmutable}
     */
    private function buildWindow(RendezVous $rdv): array
    {
        $date = $rdv->getDateRdv();
        $time = $rdv->getHeureRdv();
        if (!$date || !$time) {
            throw new \RuntimeException('RendezVous date/time is missing.');
        }

        $timezone = new \DateTimeZone($this->timezone);
        $start = new \DateTimeImmutable(
            sprintf('%s %s', $date->format('Y-m-d'), $time->format('H:i:s')),
            $timezone
        );

        return [$start, $start->modify('+30 minutes')];
    }
}

