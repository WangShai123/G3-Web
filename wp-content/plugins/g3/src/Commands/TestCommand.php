<?php
namespace JEALER\G3\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use JEALER\G3\Utilities\Validator;
use JEALER\G3\Utilities\Common;

class TestCommand extends Command {
    protected static $defaultName = 'G3:test';
    private string $lang = 'en';
    private array $messages = [
        'en' => [
            'description' => 'Test command',
            'help'        => 'This is a demonstration of how to use the G3 test command',
            'msg'         => 'Hello, G3!',
        ],
        'zh' => [
            'description' => '测试命令',
            'help'        => '这是一个演示如何使用 G3 的测试命令',
            'msg'         => '你好，G3！',
        ]
    ];


    protected function configure()
    {
        $this->lang = Validator::detectLang();
        $this
            ->setName(self::$defaultName)
            ->setAliases(['test'])
            ->setDescription(Common::t('description', $this->lang, $this->messages))
            ->setHelp(Common::t('help', $this->lang, $this->messages));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(Common::t('msg', $this->lang, $this->messages));
        return Command::SUCCESS;
    }
}