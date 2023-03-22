<?php

namespace CommonGateway\HuwelijksplannerBundle\Command;

use CommonGateway\HuwelijksplannerBundle\Service\HandleAssentService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to execute the HandleAssentService.
 */
class HandleAssentCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'huwelijksplanner:assent:execute';

    /**
     * @var HandleAssentService
     */
    private HandleAssentService $service;

    /**
     * @param HandleAssentService $service The HandleAssentService
     */
    public function __construct(HandleAssentService $service)
    {
        $this->service = $service;
        parent::__construct();
    }//end __construct()

    protected function configure(): void
    {
        $this
            ->setDescription('Requests or approves a assent')
            ->setHelp('Requests or approves a assent');
    }//end configure()

    /**
     * @param InputInterface  $input  The input
     * @param OutputInterface $output The ouput
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $this->service->setStyle($style);

        if (!$this->service->handleAssentHandler([], [])) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }//end execute()
}//end class
