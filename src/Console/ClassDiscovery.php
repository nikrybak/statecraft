<?php

declare(strict_types=1);

namespace Statecraft\Console;

use FilesystemIterator;
use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Statecraft\Attribute\AttributeLoader;
use UnexpectedValueException;

/**
 * Finds classes and enums carrying #[StateMachine] below a directory.
 *
 * Files are tokenised rather than included blindly, so only files that declare a class are
 * loaded, and the autoloader gets the first chance to load them.
 */
final class ClassDiscovery
{
    /**
     * @return list<class-string>
     *
     * @throws UnexpectedValueException when the path does not exist
     */
    public function machines(string $path, ?string $namespacePrefix = null): array
    {
        $prefix = $namespacePrefix === null ? null : trim($namespacePrefix, '\\') . '\\';
        $found = [];

        foreach ($this->files($path) as $file) {
            foreach ($this->declaredClasses($file) as $class) {
                if ($prefix !== null && !str_starts_with($class . '\\', $prefix)) {
                    continue;
                }
                if (!class_exists($class) && !enum_exists($class)) {
                    require_once $file;
                }
                if ((class_exists($class) || enum_exists($class)) && AttributeLoader::isMachine($class)) {
                    $found[] = $class;
                }
            }
        }

        sort($found);

        return $found;
    }

    /**
     * @return list<string>
     */
    private function files(string $path): array
    {
        if (is_file($path)) {
            return [$path];
        }
        if (!is_dir($path)) {
            throw new UnexpectedValueException(\sprintf('Path "%s" is neither a file nor a directory.', $path));
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function declaredClasses(string $file): array
    {
        $code = file_get_contents($file);
        if ($code === false) {
            return [];
        }

        $tokens = array_values(array_filter(
            PhpToken::tokenize($code),
            static fn(PhpToken $t): bool => !$t->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
        ));

        $namespace = '';
        $classes = [];
        foreach ($tokens as $i => $token) {
            if ($token->is(T_NAMESPACE) && isset($tokens[$i + 1]) && $tokens[$i + 1]->is([T_NAME_QUALIFIED, T_STRING])) {
                $namespace = $tokens[$i + 1]->text . '\\';
                continue;
            }
            // Skips "Foo::class", "new class" and "enum_exists(...)"-style false positives.
            $previous = $tokens[$i - 1] ?? null;
            if ($token->is([T_CLASS, T_ENUM]) && !$previous?->is([T_DOUBLE_COLON, T_NEW]) && isset($tokens[$i + 1]) && $tokens[$i + 1]->is(T_STRING)) {
                $classes[] = $namespace . $tokens[$i + 1]->text;
            }
        }

        return $classes;
    }
}
