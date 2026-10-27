<?php

declare(strict_types=1);

namespace Statecraft\Console;

use Statecraft\Attribute\AttributeLoader;
use Statecraft\Definition\Definition;
use Statecraft\Dumper\Dumper;
use Statecraft\Dumper\GraphvizDumper;
use Statecraft\Dumper\MermaidDumper;
use Statecraft\Exception\StatecraftException;
use UnexpectedValueException;

/**
 * Dependency-free command line front end used by bin/statecraft.
 *
 * Exit codes: 0 success, 1 invalid machine or none found, 2 usage error.
 */
final class Application
{
    public const int SUCCESS = 0;
    public const int FAILURE = 1;
    public const int USAGE = 2;

    private const string USAGE_TEXT = <<<'TXT'
        Usage:
          statecraft validate <path> [--namespace=Prefix]
          statecraft render <path> [--namespace=Prefix] [--format=mermaid|dot] [--machine=name] [--output=dir]

        Commands:
          validate   Load every #[StateMachine] class below <path> and report definition errors.
          render     Print (or write to --output) a diagram of every machine found.

        Options:
          --namespace  Only consider classes whose name starts with this prefix.
          --format     Diagram format, mermaid (default) or dot.
          --machine    Only render the machine with this name.
          --output     Directory to write <machine>.<ext> files to instead of printing.
          --bootstrap  PHP file to include first, e.g. your project's vendor/autoload.php.

        TXT;

    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct(
        private $stdout,
        private $stderr,
        private readonly ClassDiscovery $discovery = new ClassDiscovery(),
        private readonly AttributeLoader $loader = new AttributeLoader(),
    ) {}

    /**
     * @param list<string> $argv arguments without the script name
     */
    public function run(array $argv): int
    {
        [$positional, $options] = $this->parse($argv);
        $command = $positional[0] ?? 'help';

        if (isset($options['bootstrap'])) {
            if (!is_file($options['bootstrap'])) {
                return $this->usageError(\sprintf('Bootstrap file "%s" does not exist.', $options['bootstrap']));
            }
            require_once $options['bootstrap'];
        }

        if ($command === 'help' || isset($options['help'])) {
            $this->write($this->stdout, self::USAGE_TEXT);

            return self::SUCCESS;
        }
        if (!\in_array($command, ['validate', 'render'], true)) {
            return $this->usageError(\sprintf('Unknown command "%s".', $command));
        }
        if (!isset($positional[1])) {
            return $this->usageError(\sprintf('The %s command needs a <path>.', $command));
        }

        try {
            $classes = $this->discovery->machines($positional[1], $options['namespace'] ?? null);
        } catch (UnexpectedValueException $e) {
            return $this->usageError($e->getMessage());
        }
        if ($classes === []) {
            $this->write($this->stderr, \sprintf("No #[StateMachine] classes found in %s.\n", $positional[1]));

            return self::FAILURE;
        }

        return $command === 'validate' ? $this->validate($classes) : $this->render($classes, $options);
    }

    /**
     * @param list<class-string> $classes
     */
    private function validate(array $classes): int
    {
        $failed = 0;
        foreach ($classes as $class) {
            try {
                $definition = $this->loader->load($class);
                $this->write($this->stdout, \sprintf(
                    "OK    %s (%s): %d states, %d transitions, %s\n",
                    $definition->name,
                    $class,
                    \count($definition->places()),
                    \count($definition->transitions()),
                    str_replace('_', ' ', $definition->type->value),
                ));
            } catch (StatecraftException $e) {
                ++$failed;
                $this->write($this->stdout, \sprintf("FAIL  %s\n%s\n", $class, $this->indent($e->getMessage())));
            }
        }

        $this->write($this->stdout, \sprintf("\n%d machine(s) checked, %d failed.\n", \count($classes), $failed));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param list<class-string>    $classes
     * @param array<string, string> $options
     */
    private function render(array $classes, array $options): int
    {
        $dumper = match ($options['format'] ?? 'mermaid') {
            'mermaid', 'mmd' => new MermaidDumper(),
            'dot', 'graphviz' => new GraphvizDumper(),
            default => null,
        };
        if ($dumper === null) {
            return $this->usageError(\sprintf('Unknown format "%s"; use mermaid or dot.', $options['format'] ?? ''));
        }

        $outputDir = $options['output'] ?? null;
        if ($outputDir !== null && !is_dir($outputDir) && !mkdir($outputDir, 0o777, true) && !is_dir($outputDir)) {
            return $this->usageError(\sprintf('Cannot create output directory "%s".', $outputDir));
        }

        $rendered = 0;
        $status = self::SUCCESS;
        foreach ($classes as $class) {
            try {
                $definition = $this->loader->load($class);
            } catch (StatecraftException $e) {
                $this->write($this->stderr, \sprintf("Skipping %s:\n%s\n", $class, $this->indent($e->getMessage())));
                $status = self::FAILURE;
                continue;
            }
            if (isset($options['machine']) && $options['machine'] !== $definition->name) {
                continue;
            }

            $this->emit($dumper, $definition, $outputDir);
            ++$rendered;
        }

        if ($rendered === 0 && isset($options['machine'])) {
            $this->write($this->stderr, \sprintf("No machine named \"%s\".\n", $options['machine']));

            return self::FAILURE;
        }

        return $status;
    }

    private function emit(Dumper $dumper, Definition $definition, ?string $outputDir): void
    {
        $diagram = $dumper->dump($definition);
        if ($outputDir === null) {
            $this->write($this->stdout, $diagram . "\n");

            return;
        }

        $file = rtrim($outputDir, '/\\') . \DIRECTORY_SEPARATOR . $definition->name . '.' . $dumper->extension();
        file_put_contents($file, $diagram);
        $this->write($this->stdout, \sprintf("Wrote %s\n", $file));
    }

    /**
     * @param list<string> $argv
     *
     * @return array{list<string>, array<string, string>}
     */
    private function parse(array $argv): array
    {
        $positional = [];
        $options = [];
        foreach ($argv as $argument) {
            if (str_starts_with($argument, '--')) {
                [$name, $value] = array_pad(explode('=', substr($argument, 2), 2), 2, '');
                $options[$name] = $value;
                continue;
            }
            $positional[] = $argument;
        }

        return [$positional, $options];
    }

    private function usageError(string $message): int
    {
        $this->write($this->stderr, $message . "\n\n" . self::USAGE_TEXT);

        return self::USAGE;
    }

    private function indent(string $text): string
    {
        return '      ' . str_replace("\n", "\n      ", $text);
    }

    /**
     * @param resource $stream
     */
    private function write($stream, string $text): void
    {
        fwrite($stream, $text);
    }
}
