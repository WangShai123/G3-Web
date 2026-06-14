<?php
namespace JEALER\G3\Commands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\DomCrawler\Crawler;
use JEALER\G3\Utilities\Image;
use JEALER\G3\Utilities\Validator;
use JEALER\G3\Utilities\Common;
use RuntimeException;

/**
 * 测试文章数据采集命令
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class TaCommand extends Command {
    protected function configure()
    {
        $this
            ->setName('G3:testPost')
            ->setAliases(['testPost'])
            ->setDescription('测试文章数据采集')
            ->addOption('post_type', null, InputOption::VALUE_OPTIONAL, '要添加的 post_type 名称', 'post')
            ->addOption('taxonomy', null, InputOption::VALUE_REQUIRED, '要添加的 taxonomy 名称')
            ->addOption('term', null, InputOption::VALUE_REQUIRED, '要添加的 term 别名')
            ->addOption('author_id', null, InputOption::VALUE_REQUIRED, '用户 id')
            ->addOption('count', null, InputOption::VALUE_OPTIONAL, '要添加的文章数量', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 获取问答助手
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        // 获取初始参数
        $postType  = $input->getOption('post_type');
        $taxonomy  = $input->getOption('taxonomy');
        $term      = $input->getOption('term');
        $authorId  = $input->getOption('author_id');
        $loopCount = $input->getOption('count');

        // 使用问答组件完善采集指标
        $output->writeln("<info>请完善采集数据的指标：</info>");

        // 文章类型
        if (empty($postType)) {
            $question = new Question("请输入要添加的 post_type 名称 [post]: ", 'post');
            $postType = $helper->ask($input, $output, $question);
        }

        // 分类法
        if (empty($taxonomy)) {
            $question = new Question("请输入要添加的 taxonomy 名称: ");
            $question->setValidator(function ($answer) {
                if (empty($answer)) {
                    throw new RuntimeException('taxonomy 不能为空');
                }
                return $answer;
            });
            $taxonomy = $helper->ask($input, $output, $question);
        }

        // 分类别名
        if (empty($term)) {
            $question = new Question("请输入要添加的 term 别名: ");
            $question->setValidator(function ($answer) {
                if (empty($answer)) {
                    throw new RuntimeException('term 不能为空');
                }
                return $answer;
            });
            $term = $helper->ask($input, $output, $question);
        }

        // 用户ID
        if (empty($authorId)) {
            $question = new Question("请输入用户 id: ");
            $question->setValidator(function ($answer) {
                if (empty($answer) || !is_numeric($answer)) {
                    throw new RuntimeException('用户 id 必须是有效的数字');
                }
                return $answer;
            });
            $authorId = $helper->ask($input, $output, $question);
        }

        // 文章数量
        // if (empty($loopCount) || $loopCount < 1) {
        $question = new Question("请输入要添加的文章数量 [1]: ", 1);
        $question->setValidator(function ($answer) {
            if (!is_numeric($answer) || $answer < 1) {
                throw new RuntimeException('文章数量必须是大于0的数字');
            }
            return $answer;
        });
        $loopCount = $helper->ask($input, $output, $question);
        // }

        // 确认参数
        $output->writeln("\n<comment>参数信息：</comment>");
        $output->writeln("post_type: {$postType}");
        $output->writeln("taxonomy: {$taxonomy}");
        $output->writeln("term: {$term}");
        $output->writeln("author_id: {$authorId}");
        $output->writeln("count: {$loopCount}");

        // 确认是否继续
        $confirmQuestion = new ConfirmationQuestion('请确认以上参数是否正确? (y/n) ', false);
        if (!$helper->ask($input, $output, $confirmQuestion)) {
            $output->writeln("<error>操作已取消</error>");
            return Command::FAILURE;
        }

        // 开始执行时间统计
        $start = microtime(true);

        $targetUrl = 'https://www.dushu.com/meiwen/random/';

        // 导入 wordpress 核心文件
        $wpFile = __DIR__ . '/../../../../../wp-load.php';
        require_once $wpFile;

        // 循环请求 API 获取数据
        for ($i = 1; $i <= $loopCount; $i++) {
            $output->writeln("正在获取第 {$i} 条数据...");

            // 爬取数据
            $htmlContent = file_get_contents($targetUrl);
            $crawler     = new Crawler($htmlContent);

            // 解析数据
            $article = $crawler->filter('.article-detail')->first();
            if (!$article->count()) {
                $output->writeln("第 {$i} 条数据解析失败：无法找到文章详情");
                continue;
            }
            $title           = $article->filter('h1')->first()->text();
            $authorElements  = $article->filter('.article-info span');
            $author          = $authorElements->count() > 0 ? $authorElements->first()->text() : '未知作者';
            $author          = '<p>作者: ' . $author . '</p>';
            $contentElements = $article->filter('.text');
            $content         = $contentElements->count() > 0 ? $contentElements->first()->html() : '';
            $content         = $content . PHP_EOL . $author;
            $digest          = mb_substr($content, 0, 150, 'utf-8') . '...';

            // 创建文章
            $newPost = array(
                'post_title'   => $title,
                'post_content' => $content,
                'post_excerpt' => $digest,
                'post_date'    => \current_time('mysql'),
                'post_type'    => $postType,
                'post_author'  => $authorId,
                'post_status'  => 'publish',
            );

            // 插入文章
            $postId = \wp_insert_post($newPost);

            // 设置缩略图
            $thumbnail = Image::randomImage('640', '480');
            // 上传图片
            $upload_dir = \wp_upload_dir();
            $image_data = file_get_contents($thumbnail);
            $filename   = 'auto_' . time() . '_' . basename($thumbnail);

            if (\wp_mkdir_p($upload_dir['path'])) {
                $file = $upload_dir['path'] . '/' . $filename;
            } else {
                $file = $upload_dir['basedir'] . '/' . $filename;
            }
            file_put_contents($file, $image_data);
            $wp_filetype = \wp_check_filetype($filename, null);
            $attachment  = array(
                'post_mime_type' => $wp_filetype['type'],
                'post_title'     => \sanitize_file_name($filename),
                'post_content'   => '',
                'post_status'    => 'inherit',
                'post_author'    => $authorId,
            );
            $attach_id   = \wp_insert_attachment($attachment, $file, $postId);

            // 确保附件元数据被正确生成，包括sizes信息
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = \wp_generate_attachment_metadata($attach_id, $file);
            \wp_update_attachment_metadata($attach_id, $attach_data);

            // 输出结果
            if ($postId) {
                // 设置封面图
                \set_post_thumbnail($postId, $attach_id);

                // 设置分类数据
                $termObj = \get_term_by('slug', $term, $taxonomy);

                if ($termObj) {
                    \wp_set_object_terms($postId, $termObj->term_id, $taxonomy);
                    $output->writeln("第 {$i} 条数据发布成功，文章ID为 {$postId}");
                } else {
                    $output->writeln("第 {$i} 条数据发布失败：无法找到分类 {$term}");
                }
            } else {
                $output->writeln("第 {$i} 条数据发布失败");
            }
        }

        // 结束执行时间统计
        $end           = microtime(true);
        $executionTime = round($end - $start, 2);

        // 打印总耗时
        $output->writeln("任务总计耗时: {$executionTime} 秒");

        return Command::SUCCESS;
    }
}