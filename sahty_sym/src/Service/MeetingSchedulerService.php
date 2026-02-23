<?php

namespace App\Service;

use App\Entity\RendezVous;

final class MeetingSchedulerService
{
    public function __construct(
        private readonly MeetingLinkGenerator $meetingLinkGenerator,
        private readonly GoogleMeetApiService $googleMeetApiService,
        private readonly string $provider = 'jitsi'
    ) {
    }

    /**
     * @return array{provider:string,url:string}
     */
    public function createForRendezVous(RendezVous $rendezVous): array
    {
        $provider = strtolower(trim($this->provider));

        if (in_array($provider, ['google_meet', 'google'], true)) {
            return $this->googleMeetApiService->createMeetingLink($rendezVous);
        }

        return $this->meetingLinkGenerator->generate($this->buildSeed($rendezVous));
    }

    private function buildSeed(RendezVous $rendezVous): string
    {
        return sprintf(
            '%d-%s-%s-%s',
            (int) $rendezVous->getId(),
            (string) $rendezVous->getDateRdv()?->format('Ymd'),
            (string) $rendezVous->getHeureRdv()?->format('Hi'),
            (string) $rendezVous->getPatient()?->getId()
        );
    }
}

