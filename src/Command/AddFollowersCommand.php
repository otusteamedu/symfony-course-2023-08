<?php

namespace App\Command;

use App\Service\SubscriptionService;
use App\Manager\UserManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: self::FOLLOWERS_ADD_COMMAND_NAME,
    description: 'Adds followers to author',
    hidden: true,
)]
final class AddFollowersCommand extends Command
{
    use LockableTrait;

    public const FOLLOWERS_ADD_COMMAND_NAME = 'followers:add';
    private const DEFAULT_FOLLOWERS = 100;
    private const DEFAULT_LOGIN_PREFIX = 'cli_follower';

    public function __construct(
        private readonly UserManager $userManager,
        private readonly SubscriptionService $subscriptionService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('authorId', InputArgument::REQUIRED, 'ID of author')
            ->addOption('login', 'l', InputOption::VALUE_REQUIRED, 'Follower login prefix');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $authorId = (int)$input->getArgument('authorId');
        $user = $this->userManager->findUser($authorId);
        if ($user === null) {
            $output->write("<error>User with ID $authorId doesn't exist</error>\n");

            return self::FAILURE;
        }
        $helper = $this->getHelper('question');
        $question = new Question('How many followers you want to add?', self::DEFAULT_FOLLOWERS);
        $count = (int)$helper->ask($input, $output, $question);

        if ($count < 0) {
            $output->write("<error>Count should be positive integer</error>\n");

            return self::FAILURE;
        }

        $login = $input->getOption('login') ?? self::DEFAULT_LOGIN_PREFIX;
        $result = $this->subscriptionService->addFollowers($user, $login.$authorId, $count);
        $output->write("<info>$result followers were created</info>\n");

        return self::SUCCESS;
    }
}
