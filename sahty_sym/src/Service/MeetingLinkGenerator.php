<?php

namespace App\Service;

final class MeetingLinkGenerator
{
    public function __construct(
        private readonly string $provider = 'jitsi',
        private readonly string $jitsiDomain = 'meet.jit.si'
    ) {
    }

    /**
     * @return array{provider:string,url:string}
     */
    public function generate(string $seed): array
    {
        $provider = strtolower(trim($this->provider));

        if ($provider !== 'jitsi') {
            $provider = 'jitsi';
        }

        $room = $this->buildRoomName($seed);
        $domain = trim($this->jitsiDomain) !== '' ? trim($this->jitsiDomain) : 'meet.jit.si';
        $url = sprintf('https://%s/%s', $domain, $room);

        return [
            'provider' => $provider,
            'url' => $url,
        ];
    }

    private function buildRoomName(string $seed): string
    {
        $hash = strtoupper(substr(hash('sha256', $seed . microtime(true) . random_int(1000, 9999)), 0, 12));

        return 'SAHTY-' . $hash;
    }
}
