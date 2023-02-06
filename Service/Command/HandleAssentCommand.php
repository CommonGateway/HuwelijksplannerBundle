<?php

namespace CommonGateway\HuwelijksplannerBundle\Command;

use CommonGateway\HuwelijksplannerBundle\Service\HandleAssentService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to execute the HandleAssentService.
 */
class HandleAssentCommand extends Command
{
    protected static $defaultName = 'huwelijksplanner:assent:execute';
    private HandleAssentService $handleAssentService;

    public function __construct(HandleAssentService $handleAssentService)
    {
        $this->handleAssentService = $handleAssentService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Requests or approves a assent')
            ->setHelp('Requests or approves a assent');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->handleAssentService->setStyle($io);

        if (!$this->handleAssentService->handleAssentHandler([], [])) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
