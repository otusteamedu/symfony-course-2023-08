<?php

namespace App\Command;

use App\DTO\ManageUserDTO;
use App\Entity\User;
use App\Service\SubscriptionService;
use App\Manager\UserManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::FOLLOWERS_ADD_COMMAND_NAME,
    description: 'Adds followers to author',
    hidden: true,
)]
final class AddFollowersCommand extends Command
{
    use LockableTrait;

    public const FOLLOWERS_ADD_COMMAND_NAME = 'followers:add';
    private const DEFAULT_FOLLOWERS = 10;
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
            ->addArgument('count', InputArgument::OPTIONAL, 'How many followers should be added')
            ->addOption('login', 'l', InputOption::VALUE_REQUIRED, 'Follower login prefix');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln('<info>Command is already running.</info>');

            return self::SUCCESS;
        }

        $authorId = (int)$input->getArgument('authorId');
        $user = $this->userManager->findUser($authorId);
        if ($user === null) {
            $output->write("<error>User with ID $authorId doesn't exist</error>\n");

            return self::FAILURE;
        }

        $count = (int)($input->getArgument('count') ?? self::DEFAULT_FOLLOWERS);
        if ($count < 0) {
            $output->write("<error>Count should be positive integer</error>\n");

            return self::FAILURE;
        }

        $loginPrefix = $input->getOption('login') ?? self::DEFAULT_LOGIN_PREFIX;
        $progressBar = new ProgressBar($output, $count);
        $progressBar->start();
        $createdFollowers = 0;
        for ($i = 0; $i < $count; $i++) {
            $login = $loginPrefix.$authorId."_#$i";
            $phone = '+'.str_pad((string)abs(crc32($login)), 10, '0');
            $email = "$login@gmail.com";
            $preferred = random_int(0, 1) === 1 ? User::EMAIL_NOTIFICATION : User::SMS_NOTIFICATION;
            $followerId = $this->userManager->saveUserFromDTO(
                new User(),
                new ManageUserDTO(
                    $login,
                    $login,
                    $i,
                    true,
                    [],
                    [],
                    $phone,
                    $email,
                    $preferred
                )
            );

            if ($followerId !== null) {
                $this->subscriptionService->subscribe($user->getId(), $followerId);
                $createdFollowers++;
                usleep(100000);
                $progressBar->advance();
            }
        }
        $output->write("<info>$createdFollowers followers were created</info>\n");
        $progressBar->finish();

        return self::SUCCESS;
    }
}
