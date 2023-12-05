<?php
declare(strict_types=1);

namespace Xhtkyy\Assistant\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;

/**
 * @author   crayxn <https://github.com/crayxn>
 * @contact  crayxn@qq.com
 */
class GrpcNewGeneratorCommand extends \Hyperf\Command\Command
{
    protected bool $coroutine = false;
    protected ?string $name = 'gen:grpc-new';

    public function configure()
    {
        parent::configure();
        $this->setDescription('Generate Grpc Code');
        $this->addArgument('protobuf', InputArgument::IS_ARRAY, 'Protobuf files');
        $this->addOption('php_out', 'o', InputOption::VALUE_OPTIONAL, 'The php output dir.');
//        $this->addOption('grpc_out', 'grpc_out', InputOption::VALUE_OPTIONAL, 'The grpc output dir.');
        $this->addOption('paths', 'i', InputOption::VALUE_OPTIONAL, 'The proto paths.');
    }

    public function handle()
    {
        $protoArr = $this->input->getArgument('protobuf');
        $phpOut = $this->input->getOption('php_out') ?: '';
//        $grpcOut = $this->input->getOption('grpc_out') ?: $phpOut;
        $grpcOut = $phpOut; //todo 暂时不支持指定
        $paths = $this->input->getOption('paths') ?: $this->getRootPath(current($protoArr));

        if (empty($phpOut)) {
            $phpOut = getcwd();
            $sourceDir = $phpOut . '/Grpc';
            $targetDir = $phpOut . '/grpc';
        }

        $grpcOut = $phpOut;

        $process = new Process([
            'protoc',
            '--php_out=' . $phpOut,
            '--grpc_out=' . $grpcOut,
            '--plugin=protoc-gen-grpc=vendor/bin/grpc-code-generator-new',
            ...array_map(fn($item) => "-I={$item}", explode(',', $paths)),
            ...$protoArr
        ]);

        $process->run(function ($type, $buffer) {
            if (!$this->output->isVerbose() || !$buffer) {
                return;
            }

            $this->output->writeln($buffer);
        });

        $return = $process->getExitCode();
        $result = $process->getOutput();

        if ($return === 0) {
            //move
            if (isset($sourceDir) && isset($targetDir)) {
                $this->move($sourceDir, $targetDir, true);
            }
            $this->output->writeln('');
            $this->output->writeln($result);
            $this->output->writeln('');
            $this->output->writeln('<info>Successfully generate.</info>');
            return $return;
        }

        $this->output->writeln('<error>protoc exited with an error (' . $return . ') when executed with: </error>');
        $this->output->writeln('');
        $this->output->writeln('  ' . $process->getCommandLine());
        $this->output->writeln('');
        $this->output->writeln($result);
        $this->output->writeln('');
        $this->output->writeln($process->getErrorOutput());
        $this->output->writeln('');

        return $return;
    }

    private function move($sourceDir, $targetDir, $isDir = false)
    {
        if (!file_exists($targetDir) && $isDir) {
            mkdir($targetDir);
        }

        foreach (scandir($sourceDir) as $file) {
            // 忽略目录中的 '.' 和 '..' 文件
            if ($file != '.' && $file != '..') {
                // 构建源文件和目标文件的完整路径
                $sourceFile = $sourceDir . '/' . $file;
                $targetFile = $targetDir . '/' . $file;
                if (is_dir($sourceFile)) {
                    $this->move($sourceFile, $targetFile, true);
                } else {
                    // 移动文件
                    if (file_exists($targetFile)) {
                        unlink($targetFile);
                    }
                    if (!rename($sourceFile, $targetFile)) {
                        $this->output->writeln("Failed to move file: $file");
                    }
                }
                if (file_exists($sourceFile)) {
                    unlink($sourceFile);
                }
            }

        }

        rmdir($sourceDir);
    }

    private function getRootPath(string $path): string
    {
        $result = [];
        foreach (explode('/', $path) as $item) {
            $result[] = $item;
            if ($item == '.' || $item == '..') {
                continue;
            }
            break;
        }
        return implode("/", $result);
    }
}