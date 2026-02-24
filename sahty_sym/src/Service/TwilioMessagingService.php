<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\Utilisateur;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TwilioMessagingService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $accountSid = '',
        private readonly string $authToken = '',
        private readonly string $smsFrom = '',
        private readonly string $whatsappFrom = ''
    ) {
    }

    public function isConfiguredForSms(): bool
    {
        return $this->accountSid !== '' && $this->authToken !== '' && $this->smsFrom !== '';
    }

    public function isConfiguredForWhatsApp(): bool
    {
        return $this->accountSid !== '' && $this->authToken !== '' && $this->whatsappFrom !== '';
    }

    public function sendReminderForEvent(Utilisateur $user, Evenement $evenement, string $channel = 'sms'): bool
    {
        $phone = $this->normalizePhone($user->getTelephone());
        if ($phone === null) {
            return false;
        }

        $title = (string) $evenement->getTitre();
        $start = $evenement->getDateDebut()?->format('d/m/Y H:i') ?? 'date non definie';
        $mode = (string) $evenement->getMode();
        $lieu = trim((string) ($evenement->getLieu() ?? ''));
        $locationPart = $lieu !== '' ? ' - Lieu: ' . $lieu : '';

        $message = sprintf(
            'Rappel Sahty: "%s" commence le %s (%s)%s.',
            $title,
            $start,
            $mode,
            $locationPart
        );

        if ($channel === 'whatsapp' && $this->isConfiguredForWhatsApp()) {
            return $this->sendWhatsApp($phone, $message);
        }
        if ($channel === 'whatsapp') {
            return $this->simulateDispatch('whatsapp', $phone, $message);
        }

        if ($this->isConfiguredForSms()) {
            return $this->sendSms($phone, $message);
        }

        return $this->simulateDispatch('sms', $phone, $message);
    }

    public function sendSms(string $to, string $message): bool
    {
        if (!$this->isConfiguredForSms()) {
            return false;
        }

        return $this->sendRaw($this->smsFrom, $to, $message);
    }

    public function sendWhatsApp(string $to, string $message): bool
    {
        if (!$this->isConfiguredForWhatsApp()) {
            return false;
        }

        $from = str_starts_with($this->whatsappFrom, 'whatsapp:')
            ? $this->whatsappFrom
            : 'whatsapp:' . $this->whatsappFrom;

        $target = str_starts_with($to, 'whatsapp:') ? $to : 'whatsapp:' . $to;

        return $this->sendRaw($from, $target, $message);
    }

    private function sendRaw(string $from, string $to, string $message): bool
    {
        try {
            $url = sprintf(
                'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
                $this->accountSid
            );

            $response = $this->httpClient->request('POST', $url, [
                'auth_basic' => [$this->accountSid, $this->authToken],
                'body' => [
                    'From' => $from,
                    'To' => $to,
                    'Body' => $message,
                ],
                'timeout' => 10,
            ]);

            $code = $response->getStatusCode();
            return $code >= 200 && $code < 300;
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizePhone(?string $phone): ?string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }

        $normalized = preg_replace('/[^\d+]/', '', $raw);
        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        if ($normalized[0] !== '+') {
            $normalized = '+' . ltrim($normalized, '+');
        }

        if (strlen($normalized) < 8) {
            return null;
        }

        return $normalized;
    }

    private function simulateDispatch(string $channel, string $to, string $message): bool
    {
        error_log(sprintf(
            '[Sahty][SIMULATED_%s] to=%s message=%s',
            strtoupper($channel),
            $to,
            $message
        ));

        return true;
    }
}
