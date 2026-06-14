<?php
namespace JEALER\G3\Commands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\QuestionHelper;
use JEALER\G3\Service;
use JEALER\G3\Utilities\Validator;
use JEALER\G3\Utilities\Common;
use JEALER\G3\Services\ThemeGeneratorService;

class CreateCommand extends Command {
    protected static $defaultName = 'G3:create';
    private string   $lang        = 'en';
    private array    $messages    = [
        'en' => [
            'description'         => 'Create a new theme project',
            'welcome'             => 'Welcome. You are creating a new theme project with G3 Web...',
            'input_name'          => 'Please enter project name: ',
            'input_folder'        => 'Please enter project folder name: ',
            'input_url'           => 'Please enter project URL: ',
            'input_description'   => 'Please enter project description: ',
            'input_author'        => 'Please enter author name: ',
            'input_author_uri'    => 'Please enter author URL: ',
            'input_theme_version' => 'Please enter theme version: ',
            'error_folder_exists' => 'Theme folder already exists: ',
            'msg_create_success'  => 'Project created successfully: ',
            'msg_redirect_tip'    => 'To switch to the new project directory, please run the following command in your terminal:',
        ],
        'zh' => [
            'description'         => '创建一个新的主题项目',
            'welcome'             => '欢迎。您正在使用 G3 Web 创建新的主题项目...',
            'input_name'          => '请输入项目名称: ',
            'input_folder'        => '请输入项目文件夹名称(英文): ',
            'input_url'           => '请输入项目网址: ',
            'input_description'   => '请输入项目描述: ',
            'input_author'        => '请输入作者名称: ',
            'input_author_uri'    => '请输入作者网址: ',
            'input_theme_version' => '请输入主题版本号: ',
            'error_folder_exists' => '主题文件夹已存在: ',
            'msg_create_success'  => '项目创建成功: ',
            'msg_redirect_tip'    => '如需切换到新项目目录，请在终端执行以下命令:',
        ]
    ];
    protected function configure()
    {
        $this
            ->setName(self::$defaultName)
            ->setAliases(['create'])
            ->setDescription(Common::t('description', $this->lang, $this->messages))
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, Common::t('input_name', $this->lang, $this->messages))
            ->addOption('folder', null, InputOption::VALUE_OPTIONAL, Common::t('input_folder', $this->lang, $this->messages))
            ->addOption('author', null, InputOption::VALUE_OPTIONAL, Common::t('input_author', $this->lang, $this->messages))
            ->addOption('author_uri', null, InputOption::VALUE_OPTIONAL, Common::t('input_author_uri', $this->lang, $this->messages))
            ->addOption('url', null, InputOption::VALUE_OPTIONAL, Common::t('input_url', $this->lang, $this->messages))
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, Common::t('input_description', $this->lang, $this->messages))
            ->addOption('theme_version', null, InputOption::VALUE_OPTIONAL, Common::t('input_theme_version', $this->lang, $this->messages));
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->lang = Validator::detectLang();
        $this->start($output);
        /**
         * @var QuestionHelper $helper
         */
        $helper = $this->getHelper('question');

        // 1. Get parameters or interactive input
        $name = $input->getOption('name');
        if (!$name) {
            $question = new Question(Common::t('input_name', $this->lang, $this->messages), 'new-g3-theme-' . time());
            $name     = $helper->ask($input, $output, $question);
        }

        $folder = $input->getOption('folder');
        if (!$folder) {
            $question = new Question(Common::t('input_folder', $this->lang, $this->messages), 'new-g3-' . time());
            $folder   = $helper->ask($input, $output, $question);
        }

        $url = $input->getOption('url');
        if (!$url) {
            $question = new Question(Common::t('input_url', $this->lang, $this->messages));
            $url      = $helper->ask($input, $output, $question);
        }

        $description = $input->getOption('description');
        if (!$description) {
            $question    = new Question(Common::t('input_description', $this->lang, $this->messages));
            $description = $helper->ask($input, $output, $question);
        }

        $author = $input->getOption('author');
        if (!$author) {
            $question = new Question(Common::t('input_author', $this->lang, $this->messages));
            $author   = $helper->ask($input, $output, $question);
        }

        $author_uri = $input->getOption('author_uri');
        if (!$author_uri) {
            $question   = new Question(Common::t('input_author_uri', $this->lang, $this->messages));
            $author_uri = $helper->ask($input, $output, $question);
        }

        $theme_version = $input->getOption('theme_version');
        if (!$theme_version) {
            $question      = new Question(Common::t('input_theme_version', $this->lang, $this->messages), '0.0.1');
            $theme_version = $helper->ask($input, $output, $question);
        }


        // 2. check if theme directory exists
        $themeBasePath = dirname(__DIR__, 4) . '/themes';
        $themePath     = $themeBasePath . '/' . $folder;
        if (file_exists($themePath)) {
            $output->writeln("<error>" . Common::t('error_folder_exists', $this->lang, $this->messages) . $themePath . "</error>");
            return Command::FAILURE;
        }

        // 3. create theme project
        $params  = [
            'name'        => $name,
            'folder'      => $folder,
            'url'         => $url,
            'description' => $description,
            'author'      => $author,
            'authorUrl'   => $author_uri,
            'version'     => $theme_version,
        ];
        $service = Container::run()->get(ThemeGeneratorService::class);
        $service->create($params);

        // continue
        $output->writeln('');
        $output->writeln("<info>" . Common::t('msg_create_success', $this->lang, $this->messages) . $themePath . "</info>");

        $this->redirectTip($themePath, $output);

        return Command::SUCCESS;
    }
    private function start(OutputInterface $output): void
    {
        // Clear terminal
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            // Windows
            system('cls');
        } else {
            // Linux/Unix/Mac
            echo chr(27) . chr(91) . 'H' . chr(27) . chr(91) . 'J';
        }

        // Display welcome message
        $output->writeln('');
        $output->writeln('<info>
       ___________    __    __________     ___________
      / / ____/   |  / /   / ____/ __ \   / ____/__  /
 __  / / __/ / /| | / /   / __/ / /_/ /  / / __  /_ < 
/ /_/ / /___/ ___ |/ /___/ /___/ _, _/  / /_/ /___/ / 
\____/_____/_/  |_/_____/_____/_/ |_|   \____//____/  
    </info>');

        $welcome = Common::t('welcome', $this->lang, $this->messages);
        $str     = '';
        foreach (mb_str_split($welcome) as $char) {
            $str .= $char;
            echo "\033[32m" . $char . "\033[0m";
            $this->fsleep(0.05);
        }
        echo PHP_EOL;
        $output->writeln('');
    }
    private function fsleep(float|int $seconds)
    {
        $microseconds = (int) ($seconds * 1000000);
        usleep($microseconds);
    }
    private function redirectTip(string $themePath, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>' . Common::t('msg_redirect_tip', $this->lang, $this->messages) . '</info>');
        $output->writeln('<info>cd ' . $themePath . '</info>');
        $output->writeln('');
    }
}