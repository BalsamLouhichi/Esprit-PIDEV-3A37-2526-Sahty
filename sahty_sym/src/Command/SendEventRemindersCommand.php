<?php

namespace App\Command;

use App\Entity\InscriptionEvenement;
use App\Service\TwilioMessagingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:events:send-reminders',
    description: 'Sends SMS/WhatsApp reminders for upcoming event registrations.'
)]
class SendEventRemindersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TwilioMessagingService $twilioMessagingService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('hours', null, InputOption::VALUE_OPTIONAL, 'Reminder horizon in hours.', 24)
            ->addOption('channel', null, InputOption::VALUE_OPTIONAL, 'sms or whatsapp', 'sms');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $hours = max(1, (int) $input->getOption('hours'));
        $channel = strtolower((string) $input->getOption('channel'));
        if (!in_array($channel, ['sms', 'whatsapp'], true)) {
            $io->error('Option --channel invalide. Valeurs autorisees: sms, whatsapp.');
            return Command::INVALID;
        }

        $start = new \DateTimeImmutable();
        $end = $start->modify('+' . $hours . ' hours');

        $qb = $this->em->getRepository(InscriptionEvenement::class)->createQueryBuilder('i');
        $qb
            ->join('i.evenement', 'e')
            ->join('i.utilisateur', 'u')
            ->andWhere('i.statut = :inscriptionStatus')
            ->andWhere('e.statut IN (:eventStatuses)')
            ->andWhere('e.dateDebut BETWEEN :start AND :end')
            ->setParameter('inscriptionStatus', 'confirme')
            ->setParameter('eventStatuses', ['planifie', 'confirme', 'approuve', 'en_cours'])
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('e.dateDebut', 'ASC');

        /** @var InscriptionEvenement[] $inscriptions */
        $inscriptions = $qb->getQuery()->getResult();

        $sent = 0;
        $failed = 0;
        foreach ($inscriptions as $inscription) {
            $ok = $this->twilioMessagingService->sendReminderForEvent(
                $inscription->getUtilisateur(),
                $inscription->getEvenement(),
                $channel
            );

            if ($ok) {
                $sent++;
                continue;
            }

            $failed++;
        }

        $io->success(sprintf(
            'Rappels traites: %d inscription(s), %d envoye(s), %d echec(s).',
            count($inscriptions),
            $sent,
            $failed
        ));

        return Command::SUCCESS;
    }
}

