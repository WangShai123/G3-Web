<?php
namespace JEALER\G3\Core\Router;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ControllerClassFinder {
    /**
     * @return array<int,array{class:string,file:string,source:string}>
     */
    public function find(RouteSource $source): array
    {
        if (!$source->exists()) {
            return [];
        }

        $classes = [];
        foreach ($this->phpFiles($source->baseDir) as $file) {
            $class = $this->classFromFile($file->getPathname());
            if (!$class || !str_starts_with($class, trim($source->baseNamespace, '\\') . '\\')) {
                continue;
            }

            $classes[] = [
                'class'  => $class,
                'file'   => $file->getPathname(),
                'source' => $source->name,
            ];
        }

        return $classes;
    }

    /**
     * @return SplFileInfo[]
     */
    public function phpFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files    = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        usort($files, fn(SplFileInfo $a, SplFileInfo $b) => strcmp($a->getPathname(), $b->getPathname()));
        return $files;
    }

    private function classFromFile(string $file): ?string
    {
        $tokens    = token_get_all((string) file_get_contents($file));
        $namespace = '';
        $count     = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->readName($tokens, $i + 1);
                continue;
            }

            if ($token[0] === T_CLASS && !$this->isAnonymousClass($tokens, $i)) {
                $class = $this->readNextString($tokens, $i + 1);
                return $class ? ltrim($namespace . '\\' . $class, '\\') : null;
            }
        }

        return null;
    }

    private function readName(array $tokens, int $start): string
    {
        $name  = '';
        $count = count($tokens);
        for ($i = $start; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token === ';' || $token === '{') {
                break;
            }
            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                $name .= $token[1];
            }
        }
        return $name;
    }

    private function readNextString(array $tokens, int $start): ?string
    {
        $count = count($tokens);
        for ($i = $start; $i < $count; $i++) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }
        }
        return null;
    }

    private function isAnonymousClass(array $tokens, int $index): bool
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return is_array($token) && $token[0] === T_NEW;
        }
        return false;
    }
}
