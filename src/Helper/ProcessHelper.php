<?php declare(strict_types=1);

namespace Shopware\Deployment\Helper;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\PhpSubprocess;
use Symfony\Component\Process\Process;

/**
 * @codeCoverageIgnore
 */
class ProcessHelper
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    /**
     * @param list<string> $args
     */
    public function run(array $args): void
    {
        $completeCmd = ['php', ...$args];

        $process = new PhpSubprocess($args, $this->projectDir);

        $startTime = $this->printPreStart($completeCmd);

        if (function_exists('stream_isatty') && stream_isatty(\STDOUT)) {
            $process->setTty(true);
        }

        $process->run(function (string $type, string $buffer): void {
            if ($type === Process::ERR) {
                fwrite(\STDERR, $buffer);
            } else {
                fwrite(\STDOUT, $buffer);
            }
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Execution of ' . implode(' ', $args) . ' failed');
        }

        $this->printPostStart($completeCmd, $startTime);
    }

    /**
     * @param list<string> $args
     */
    public function console(array $args): void
    {
        $this->run(['bin/console', '-n', ...$args]);
    }

    public function runAndTail(string $code): void
    {
        $start = $this->printPreStart([$code]);

        $process = new Process(['sh', '-c', $code], $this->projectDir);

        if (function_exists('stream_isatty') && stream_isatty(\STDOUT)) {
            $process->setTty(true);
        }

        $process->run(function (string $type, string $buffer): void {
            if ($type === Process::ERR) {
                fwrite(\STDERR, $buffer);
            } else {
                fwrite(\STDOUT, $buffer);
            }
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Execution of ' . $code . ' failed');
        }

        $this->printPostStart([$code], $start);
    }

    public function getPluginList(): string
    {
        return (new PhpSubprocess(['bin/console', 'plugin:list', '--json'], $this->projectDir))->mustRun()->getOutput();
    }

    /**
     * @param array<string> $cmd
     */
    private function printPreStart(array $cmd): float
    {
        $cmdString = implode(' ', $cmd);
        $startTime = microtime(true);

        fwrite(\STDOUT, PHP_EOL);
        fwrite(\STDOUT, "=================================================\n");
        fwrite(\STDOUT, sprintf("Start: %s\n", $cmdString));
        fwrite(\STDOUT, "=================================================\n");
        fwrite(\STDOUT, PHP_EOL);

        return $startTime;
    }

    /**
     * @param array<string> $cmd
     */
    private function printPostStart(array $cmd, float $startTime): void
    {
        fwrite(\STDOUT, "=================================================\n");
        fwrite(\STDOUT, sprintf("End: %s\n", implode(' ', $cmd)));
        fwrite(\STDOUT, sprintf(
            "> Time: %sms\n",
            number_format((microtime(true) - $startTime) * 1000, 2, '.', ''),
        ));

        fwrite(\STDOUT, "=================================================\n");
        fwrite(\STDOUT, PHP_EOL);
    }



}
